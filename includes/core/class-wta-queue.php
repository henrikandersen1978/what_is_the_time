<?php
/**
 * Queue database operations.
 *
 * Handles CRUD operations for the queue table.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/core
 */

class WTA_Queue {

	/**
	 * Add an item to the queue.
	 *
	 * @since    2.0.0
	 * @param    string $type       Queue item type (continent, country, city, cities_import, timezone, ai_content).
	 * @param    array  $payload    Data for processing.
	 * @param    string $source_id  Optional. Source ID for deduplication.
	 * @return   int|false          Queue item ID or false on failure.
	 */
	public static function add( $type, $payload, $source_id = null ) {
		global $wpdb;

		$table_name = $wpdb->prefix . WTA_QUEUE_TABLE;
		$now = current_time( 'mysql' );

		$result = $wpdb->insert(
			$table_name,
			array(
				'type'       => $type,
				'source_id'  => $source_id,
				'payload'    => wp_json_encode( $payload ),
				'status'     => 'pending',
				'attempts'   => 0,
				'created_at' => $now,
				'updated_at' => $now,
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( false === $result ) {
			WTA_Logger::error( 'Failed to add queue item', array(
				'type'      => $type,
				'source_id' => $source_id,
				'error'     => $wpdb->last_error,
			) );
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Get pending queue items with atomic claiming.
	 * 
	 * CRITICAL: v3.0.41 - Implements atomic claiming to prevent race conditions
	 * when multiple concurrent queue processors are running.
	 * 
	 * Each processor atomically claims a unique batch of items by:
	 * 1. Generating a unique claim_id
	 * 2. Updating status from 'pending' to 'claimed' with the claim_id
	 * 3. Selecting only items with that specific claim_id
	 * 
	 * This ensures no two processors ever get the same items, even when
	 * running concurrently.
	 *
	 * @since    2.0.0
	 * @since    3.0.41  Added atomic claiming for concurrent processing.
	 * @param    string $type   Queue item type.
	 * @param    int    $limit  Maximum number of items to retrieve.
	 * @return   array          Array of queue items.
	 */
	public static function get_pending( $type = null, $limit = 50 ) {
		global $wpdb;

		$table_name = $wpdb->prefix . WTA_QUEUE_TABLE;
		
		// Generate unique claim ID for this batch
		$claim_id = md5( microtime() . wp_rand() );
		$now = current_time( 'mysql' );
		
		// ATOMIC CLAIM: Update status and claim_id in one query
		// This prevents race conditions with concurrent processors
		
	// v3.0.42: SPECIAL HANDLING FOR AI_CONTENT
	// v3.0.58: Updated to use wta_has_timezone flag instead of checking timezone existence
	// Only claim cities that have timezone data (prevents FAQ generation failures)
	// Continents/countries are always claimed (they don't require timezone)
	if ( 'ai_content' === $type ) {
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE $table_name q
				SET q.status = 'claimed', 
					q.claim_id = %s,
					q.updated_at = %s
				WHERE q.status = 'pending' 
				AND q.type = %s
				AND (
					-- Continents and countries: Always claim (no timezone required)
					JSON_UNQUOTE(JSON_EXTRACT(q.payload, '$.type')) IN ('continent', 'country')
					OR
					-- Cities: Only claim if has_timezone flag = 1
					(
						JSON_UNQUOTE(JSON_EXTRACT(q.payload, '$.type')) = 'city'
						AND EXISTS (
							SELECT 1 FROM {$wpdb->postmeta} pm
							WHERE pm.post_id = JSON_UNQUOTE(JSON_EXTRACT(q.payload, '$.post_id'))
							AND pm.meta_key = 'wta_has_timezone'
							AND pm.meta_value = '1'
						)
					)
				)
				ORDER BY q.created_at ASC 
				LIMIT %d",
				$claim_id,
				$now,
				$type,
				$limit
			)
		);
		} elseif ( $type ) {
			// Standard claiming for other queue types
			$updated = $wpdb->query(
				$wpdb->prepare(
					"UPDATE $table_name 
					SET status = 'claimed', 
						claim_id = %s,
						updated_at = %s
					WHERE status = 'pending' 
					AND type = %s
					ORDER BY created_at ASC 
					LIMIT %d",
					$claim_id,
					$now,
					$type,
					$limit
				)
			);
		} else {
			// No type specified - claim all pending
			$updated = $wpdb->query(
				$wpdb->prepare(
					"UPDATE $table_name 
					SET status = 'claimed', 
						claim_id = %s,
						updated_at = %s
					WHERE status = 'pending'
					ORDER BY created_at ASC 
					LIMIT %d",
					$claim_id,
					$now,
					$limit
				)
			);
		}
		
		if ( $updated === 0 ) {
			return array(); // Nothing to claim
		}
		
		// Now SELECT only items we just claimed
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE claim_id = %s",
				$claim_id
			),
			ARRAY_A
		);

		// Decode payloads
		foreach ( $results as &$item ) {
			$item['payload'] = json_decode( $item['payload'], true );
		}

		return $results;
	}

	/**
	 * Mark an item as processing.
	 * 
	 * v3.0.41: Updated to handle transition from 'claimed' to 'processing'.
	 * Items are first claimed atomically, then marked as processing when
	 * actual processing begins.
	 *
	 * @since    2.0.0
	 * @since    3.0.41  Updated to handle 'claimed' status.
	 * @param    int $item_id Queue item ID.
	 * @return   bool         Success status.
	 */
	public static function mark_processing( $item_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . WTA_QUEUE_TABLE;

		$result = $wpdb->update(
			$table_name,
			array(
				'status'     => 'processing',
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $item_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Mark an item as done.
	 *
	 * @since    2.0.0
	 * @param    int $item_id Queue item ID.
	 * @return   bool         Success status.
	 */
	public static function mark_done( $item_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . WTA_QUEUE_TABLE;

		$result = $wpdb->update(
			$table_name,
			array(
				'status'     => 'done',
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $item_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Mark an item as failed.
	 *
	 * @since    2.0.0
	 * @param    int    $item_id Queue item ID.
	 * @param    string $error   Error message.
	 * @return   bool            Success status.
	 */
	public static function mark_failed( $item_id, $error = '' ) {
		global $wpdb;

		$table_name = $wpdb->prefix . WTA_QUEUE_TABLE;

		// Increment attempts
		$wpdb->query( $wpdb->prepare( "UPDATE $table_name SET attempts = attempts + 1 WHERE id = %d", $item_id ) );

		$result = $wpdb->update(
			$table_name,
			array(
				'status'     => 'error',
				'last_error' => $error,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $item_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get queue statistics.
	 *
	 * @since    2.0.0
	 * @return   array Statistics by type and status.
	 */
	public static function get_stats() {
		global $wpdb;

		$table_name = $wpdb->prefix . WTA_QUEUE_TABLE;

		$results = $wpdb->get_results(
			"SELECT type, status, COUNT(*) as count 
			FROM $table_name 
			GROUP BY type, status",
			ARRAY_A
		);

		$stats = array(
			'by_type'   => array(),
			'by_status' => array(
				'pending'    => 0,
				'processing' => 0,
				'done'       => 0,
				'error'      => 0,
			),
			'total'     => 0,
		);

		foreach ( $results as $row ) {
			$type = $row['type'];
			$status = $row['status'];
			$count = (int) $row['count'];

			if ( ! isset( $stats['by_type'][ $type ] ) ) {
				$stats['by_type'][ $type ] = array(
					'pending'    => 0,
					'processing' => 0,
					'done'       => 0,
					'error'      => 0,
					'total'      => 0,
				);
			}

			$stats['by_type'][ $type ][ $status ] = $count;
			$stats['by_type'][ $type ]['total'] += $count;
			$stats['by_status'][ $status ] += $count;
			$stats['total'] += $count;
		}

		return $stats;
	}

	/**
	 * Clear the queue.
	 *
	 * @since    2.0.0
	 * @param    string $status Optional. Only clear items with this status.
	 * @return   int            Number of rows deleted.
	 */
	public static function clear( $status = null ) {
		global $wpdb;

		$table_name = $wpdb->prefix . WTA_QUEUE_TABLE;

		if ( $status ) {
			return $wpdb->delete( $table_name, array( 'status' => $status ), array( '%s' ) );
		}

		return $wpdb->query( "TRUNCATE TABLE $table_name" );
	}

	/**
	 * Retry failed items.
	 * 
	 * v3.0.52: Updated to work with Action Scheduler (Pilanto-AI model)
	 * instead of custom queue table.
	 *
	 * @since    2.0.0
	 * @since    3.0.52  Updated for Action Scheduler.
	 * @param    int $max_attempts Maximum attempts before permanent failure.
	 * @return   int               Number of items reset to pending.
	 */
	public static function retry_failed( $max_attempts = 3 ) {
		global $wpdb;

		// v3.0.52: Work with Action Scheduler table
		$table_name = $wpdb->prefix . 'actionscheduler_actions';
		
		// Get WTA hooks (our actions only)
		$wta_hooks = array(
			'wta_create_continent',
			'wta_create_country',
			'wta_create_city',
			'wta_lookup_timezone',
			'wta_generate_ai_content',
		);
		
		$hooks_placeholders = implode( ',', array_fill( 0, count( $wta_hooks ), '%s' ) );

		// Reset failed actions to pending
		// Schedule them to run now
		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE $table_name 
				SET status = 'pending',
					scheduled_date_gmt = %s,
					scheduled_date_local = %s
				WHERE status = 'failed' 
				AND hook IN ($hooks_placeholders)",
				gmdate( 'Y-m-d H:i:s' ),
				current_time( 'mysql' ),
				...$wta_hooks
			)
		);
		
		if ( $result > 0 ) {
			WTA_Logger::info( "Retry failed: Reset $result failed Action Scheduler actions to pending" );
		}

		return $result;
	}

	/**
	 * Reset stuck items.
	 * 
	 * v3.0.52: Deprecated in Pilanto-AI model. Action Scheduler handles
	 * timeouts and stuck actions automatically via its runner system.
	 * 
	 * This method now returns 0 and logs a message. For stuck Action Scheduler
	 * actions, use the built-in Action Scheduler management tools instead.
	 *
	 * @since    2.0.0
	 * @since    3.0.41  Also resets 'claimed' items.
	 * @since    3.0.52  Deprecated - Action Scheduler handles this automatically.
	 * @return   int Number of items reset (always 0).
	 */
	public static function reset_stuck() {
		WTA_Logger::debug( 'reset_stuck() called but deprecated in v3.0.52 (Pilanto-AI model). Action Scheduler manages timeouts automatically.' );
		return 0;
	}

	/**
	 * Check if an item already exists by source_id.
	 *
	 * @since    2.0.0
	 * @param    string $type      Queue item type.
	 * @param    string $source_id Source ID.
	 * @return   bool              True if exists.
	 */
	public static function exists( $type, $source_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . WTA_QUEUE_TABLE;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $table_name WHERE type = %s AND source_id = %s",
				$type,
				$source_id
			)
		);

		return $count > 0;
	}
}


