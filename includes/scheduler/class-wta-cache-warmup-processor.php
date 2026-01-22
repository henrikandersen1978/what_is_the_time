<?php
/**
 * Cache Warmup Processor
 *
 * Proactively warms up cache for largest city in each country
 * to eliminate slow first loads for users.
 *
 * v3.7.0: Redesigned to queue all 244 cities individually instead of batch processing.
 * Each city checks cache freshness and skips if already warm (0.01s).
 *
 * @package     World_Time_AI
 * @subpackage  Scheduler
 * @since       3.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WTA_Cache_Warmup_Processor {

    /**
     * Kickstart cache warmup for all countries
     * 
     * Queues individual warmup actions for the largest city in each country.
     * Each action checks cache freshness and skips if already warm.
     * 
     * v3.7.0: Queues all 244 cities with 2-second stagger (total ~8 minutes to queue all).
     * Action Scheduler then processes them, skipping cities with fresh cache (0.01s per skip).
     *
     * @return void
     */
    public function kickstart() {
        $start_time = microtime( true );
        
        WTA_Logger::info( 'Cache warmup kickstart: Starting queue process' );
        
        // Get ALL cities (one per country) - direct from wp_posts, no cache dependency
        $cities = $this->get_all_country_cities( 300 ); // High limit to get all
        
        if ( empty( $cities ) ) {
            WTA_Logger::info( 'Cache warmup kickstart: No cities found' );
            return;
        }
        
        $queued = 0;
        $skipped = 0;
        
        // Queue each city as individual action with staggered timing
        foreach ( $cities as $index => $city ) {
            // Skip if already queued (avoid duplicates)
            $existing = as_next_scheduled_action( 'wta_warmup_single_city', array( 
                'city_id' => $city->city_id 
            ) );
            
            if ( false !== $existing ) {
                $skipped++;
                continue;
            }
            
            // Queue with 2-second delay between each (244 cities × 2s = ~8 minutes to queue all)
            $delay = $index * 2;
            as_schedule_single_action( time() + $delay, 'wta_warmup_single_city', array( 
                'city_id' => $city->city_id,
                'city_name' => $city->city_name,
                'country_id' => $city->country_id
            ), 'wta_cache_warmup' );
            
            $queued++;
        }
        
        $duration = round( microtime( true ) - $start_time, 2 );
        
        WTA_Logger::info( 'Cache warmup kickstart: Queue completed', array(
            'queued' => $queued,
            'skipped' => $skipped,
            'total_cities' => count( $cities ),
            'duration' => $duration . 's'
        ) );
    }

    /**
     * Get largest city in each country (for warmup)
     * 
     * Returns ALL countries regardless of cache status.
     * Individual warmup actions will check cache freshness.
     * 
     * v3.7.0: No defensive cache table check - we always want to queue warmups.
     * Queries wp_posts directly for maximum reliability.
     * 
     * @param int $limit Maximum number of cities to return
     * @return array
     */
    private function get_all_country_cities( $limit ) {
        global $wpdb;
        
        // Get largest city in each country (regardless of cache status)
        $cities = $wpdb->get_results( $wpdb->prepare(
            "SELECT 
                country.ID as country_id,
                country.post_title as country_name,
                city.ID as city_id,
                city.post_title as city_name,
                pm.meta_value as population
            FROM {$wpdb->posts} country
            INNER JOIN {$wpdb->posts} continent ON continent.ID = country.post_parent
            INNER JOIN {$wpdb->posts} city ON city.post_parent = country.ID
            LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = city.ID AND pm.meta_key = 'population'
            WHERE country.post_type = 'wta_location'
                AND country.post_status = 'publish'
                AND country.post_parent > 0        -- Country has a parent (continent)
                AND continent.post_parent = 0      -- Parent is a continent (no parent)
                AND city.post_type = 'wta_location'
                AND city.post_status = 'publish'
            GROUP BY country.ID
            HAVING city_id = (
                SELECT ID FROM {$wpdb->posts} c2
                LEFT JOIN {$wpdb->postmeta} pm2 ON pm2.post_id = c2.ID AND pm2.meta_key = 'population'
                WHERE c2.post_parent = country.ID
                    AND c2.post_type = 'wta_location'
                    AND c2.post_status = 'publish'
                ORDER BY CAST(pm2.meta_value AS UNSIGNED) DESC
                LIMIT 1
            )
            ORDER BY country.ID
            LIMIT %d",
            $limit
        ) );
        
        return $cities;
    }

    /**
     * Warmup a single city (called by Action Scheduler)
     * 
     * Checks cache freshness first - skips if already warm.
     * 
     * @param int    $city_id City post ID
     * @param string $city_name City name
     * @param int    $country_id Country post ID
     * @return void
     */
    public function warmup_single_city( $city_id, $city_name, $country_id ) {
        $result = $this->warmup_city( $city_id, $city_name, $country_id );
        
        // warmup_city() already logs details
        return $result;
    }

    /**
     * Warmup cache for a city by making HTTP request to its page
     *
     * @param int    $city_id City post ID
     * @param string $city_name City name (for logging)
     * @param int    $country_id Country post ID
     * @return string 'warmed', 'skipped', or 'error'
     */
    private function warmup_city( $city_id, $city_name, $country_id ) {
        // Smart cache check - skip if country master cache is fresh
        $cache_key = 'wta_country_master_' . $country_id . '_v2';
        if ( false !== WTA_Cache::get( $cache_key ) ) {
            WTA_Logger::debug( 'Cache warmup: Skipped (cache fresh)', array(
                'city' => $city_name,
                'city_id' => $city_id
            ) );
            return 'skipped';
        }

        // Get city permalink
        $url = get_permalink( $city_id );
        if ( ! $url ) {
            WTA_Logger::error( 'Cache warmup: Failed to get permalink', array(
                'city_id' => $city_id,
                'city_name' => $city_name
            ) );
            return 'error';
        }

        // Start timing for HTTP request
        $start_time = microtime( true );

        // Set Independent Analytics ignore cookie to exclude warmup from stats
        // Based on: https://independentwp.com/knowledgebase/tracking/block-user-roles/
        // Cookie 'iawp_ignore_visitor' tells Independent Analytics to ignore this request
        $cookies = array(
            new WP_Http_Cookie( array(
                'name'  => 'iawp_ignore_visitor',
                'value' => '1'
            ) )
        );

        // Make HTTP request - this triggers ALL the same processes as a real user visit:
        // - WordPress loads the page
        // - All shortcodes execute
        // - Master cache is built
        // - HTML caches are built
        // - All database queries run
        $response = wp_remote_get( $url, array(
            'timeout'     => 30,
            'redirection' => 5,
            'user-agent'  => 'WTA-Cache-Warmup/3.7.0',
            'sslverify'   => false, // Allow local/dev environments
            'cookies'     => $cookies // Exclude from Independent Analytics
        ) );

        // Calculate duration
        $duration = round( microtime( true ) - $start_time, 2 );

        if ( is_wp_error( $response ) ) {
            WTA_Logger::error( 'Cache warmup: HTTP request failed', array(
                'city' => $city_name,
                'url' => $url,
                'duration' => $duration . 's',
                'error' => $response->get_error_message()
            ) );
            return 'error';
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        if ( $status_code !== 200 ) {
            WTA_Logger::error( 'Cache warmup: Non-200 response', array(
                'city' => $city_name,
                'url' => $url,
                'duration' => $duration . 's',
                'status_code' => $status_code
            ) );
            return 'error';
        }

        WTA_Logger::info( 'Cache warmup: Success', array(
            'city' => $city_name,
            'url' => $url,
            'duration' => $duration . 's'
        ) );

        return 'warmed';
    }

    /**
     * Get warmup statistics
     *
     * @return array
     */
    public function get_stats() {
        global $wpdb;

        $total_countries = $wpdb->get_var(
            "SELECT COUNT(*)
            FROM {$wpdb->posts} country
            INNER JOIN {$wpdb->posts} continent ON continent.ID = country.post_parent
            WHERE country.post_type = 'wta_location'
                AND country.post_status = 'publish'
                AND country.post_parent > 0
                AND continent.post_parent = 0"
        );

        $cached_countries = $wpdb->get_var(
            "SELECT COUNT(DISTINCT SUBSTRING(cache_key, 21, LENGTH(cache_key) - 24))
            FROM {$wpdb->prefix}wta_cache
            WHERE cache_key LIKE 'wta_country_master_%_v2'
                AND expires > UNIX_TIMESTAMP()"
        );

        $pending = $total_countries - $cached_countries;

        return array(
            'total_countries' => (int) $total_countries,
            'cached_countries' => (int) $cached_countries,
            'pending' => (int) $pending,
            'progress_percent' => $total_countries > 0 ? round( ( $cached_countries / $total_countries ) * 100, 1 ) : 0
        );
    }
}
