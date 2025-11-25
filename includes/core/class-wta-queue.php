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
	 * Get pending queue items.
	 *
	 * @since    2.0.0
	 * @param    string $type   Queue item type.
	 * @param    int    $limit  Maximum number of items to retrieve.
	 * @return   array          Array of queue items.
	 */
	public static function get_pending( $type = null, $limit = 50 ) {
		global $wpdb;

		$table_name = $wpdb->prefix . WTA_QUEUE_TABLE;

		$sql = "SELECT * FROM $table_name WHERE status = 'pending'";

		if ( $type ) {
			$sql .= $wpdb->prepare( ' AND type = %s', $type );
		}

		$sql .= $wpdb->prepare( ' ORDER BY created_at ASC LIMIT %d', $limit );

		$results = $wpdb->get_results( $sql, ARRAY_A );

		// Decode payloads
		foreach ( $results as &$item ) {
			$item['payload'] = json_decode( $item['payload'], true );
		}

		return $results;
	}

	/**
	 * Mark an item as processing.
	 *
	 * @since    2.0.0
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
	 * @since    2.0.0
	 * @param    int $max_attempts Maximum attempts before permanent failure.
	 * @return   int               Number of items reset to pending.
	 */
	public static function retry_failed( $max_attempts = 3 ) {
		global $wpdb;

		$table_name = $wpdb->prefix . WTA_QUEUE_TABLE;

		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE $table_name 
				SET status = 'pending', updated_at = %s 
				WHERE status = 'error' AND attempts < %d",
				current_time( 'mysql' ),
				$max_attempts
			)
		);

		return $result;
	}

	/**
	 * Reset stuck items.
	 *
	 * Items stuck in 'processing' status for more than 5 minutes.
	 *
	 * @since    2.0.0
	 * @return   int Number of items reset.
	 */
	public static function reset_stuck() {
		global $wpdb;

		$table_name = $wpdb->prefix . WTA_QUEUE_TABLE;
		$threshold = date( 'Y-m-d H:i:s', strtotime( '-5 minutes' ) );

		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE $table_name 
				SET status = 'pending', updated_at = %s 
				WHERE status = 'processing' AND updated_at < %s",
				current_time( 'mysql' ),
				$threshold
			)
		);

		if ( $result > 0 ) {
			WTA_Logger::warning( "Reset $result stuck queue items" );
		}

		return $result;
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

