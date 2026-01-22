<?php
/**
 * Cache Warmup Processor
 *
 * Proactively warms up cache for largest city in each country
 * to eliminate slow first loads for users.
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
     * Batch size - number of cities to warm per batch
     * Conservative: 5 cities × 12s = 60s per batch (safe for Action Scheduler)
     */
    const BATCH_SIZE = 5;

    /**
     * Delay between warmup requests within a batch (seconds)
     */
    const DELAY_SECONDS = 1;

    /**
     * Process a batch of cities for cache warmup
     *
     * @return void
     */
    public function process_batch() {
        $start_time = microtime( true );

        WTA_Logger::info( 'Cache warmup batch started', array(
            'batch_size' => self::BATCH_SIZE
        ) );

        // Get cities needing warmup
        $cities = $this->get_cities_needing_warmup( self::BATCH_SIZE );

        if ( empty( $cities ) ) {
            WTA_Logger::info( 'Cache warmup: No cities pending', array(
                'batch_size' => self::BATCH_SIZE
            ) );
            return;
        }

        $warmed = 0;
        $skipped = 0;
        $errors = 0;

        foreach ( $cities as $city ) {
            try {
                $result = $this->warmup_city( $city->city_id, $city->city_name, $city->country_id );
                
                if ( $result === 'skipped' ) {
                    $skipped++;
                } elseif ( $result === 'warmed' ) {
                    $warmed++;
                } else {
                    $errors++;
                }

                // Delay between requests to avoid server overload
                if ( self::DELAY_SECONDS > 0 ) {
                    sleep( self::DELAY_SECONDS );
                }

            } catch ( Exception $e ) {
                $errors++;
                WTA_Logger::error( 'Cache warmup failed for city', array(
                    'city_id' => $city->city_id,
                    'city_name' => $city->city_name,
                    'error' => $e->getMessage()
                ) );
            }
        }

        $duration = round( microtime( true ) - $start_time, 2 );

        WTA_Logger::info( 'Cache warmup batch completed', array(
            'warmed' => $warmed,
            'skipped' => $skipped,
            'errors' => $errors,
            'duration' => $duration . 's'
        ) );

        // Schedule next batch if there are more cities to process
        if ( count( $cities ) === self::BATCH_SIZE ) {
            as_schedule_single_action( time() + 60, 'wta_cache_warmup_batch', array(), 'wta_cache_warmup' );
            
            WTA_Logger::debug( 'Cache warmup: Next batch scheduled in 60s' );
        } else {
            WTA_Logger::info( 'Cache warmup: All cities processed - cycle complete' );
        }
    }

    /**
     * Get cities that need cache warmup
     *
     * Returns largest city in each country that doesn't have a fresh country master cache.
     *
     * @param int $limit Number of cities to return
     * @return array
     */
    private function get_cities_needing_warmup( $limit ) {
        global $wpdb;

        // DEFENSIVE: Check if cache table exists
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}wta_cache'" );
        if ( ! $table_exists ) {
            WTA_Logger::error( 'Cache table missing - cannot process cache warmup' );
            return array();
        }

        // Find countries without fresh master cache, get their largest city
        $cities = $wpdb->get_results( $wpdb->prepare(
            "SELECT 
                country.ID as country_id,
                country.post_title as country_name,
                city.ID as city_id,
                city.post_title as city_name,
                pm.meta_value as population
            FROM {$wpdb->posts} country
            INNER JOIN {$wpdb->posts} continent ON continent.ID = country.post_parent
            LEFT JOIN {$wpdb->prefix}wta_cache c 
                ON c.cache_key = CONCAT('wta_country_master_', country.ID, '_v2')
                AND c.expires > UNIX_TIMESTAMP()
            INNER JOIN {$wpdb->posts} city ON city.post_parent = country.ID
            LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = city.ID AND pm.meta_key = 'population'
            WHERE country.post_type = 'wta_location'
                AND country.post_status = 'publish'
                AND country.post_parent > 0        -- Country has a parent (continent)
                AND continent.post_parent = 0      -- Parent is a continent (no parent)
                AND c.cache_key IS NULL            -- No fresh cache exists
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
            ORDER BY country.post_title
            LIMIT %d",
            $limit
        ) );

        return $cities;
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

        // Make HTTP request to warmup all caches
        $response = wp_remote_get( $url, array(
            'timeout'     => 30,
            'redirection' => 5,
            'user-agent'  => 'WTA-Cache-Warmup/3.6.2',
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
