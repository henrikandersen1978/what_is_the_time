<?php
/**
 * Queue table handler.
 *
 * @package    WorldTimeAI
 * @subpackage WorldTimeAI/includes/core
 */

/**
 * Queue database operations.
 *
 * @since 1.0.0
 */
class WTA_Queue {

	/**
	 * Get table name with prefix.
	 *
	 * @since 1.0.0
	 * @return string Full table name.
	 */
	private static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . WTA_QUEUE_TABLE;
	}

	/**
	 * Insert a new queue item.
	 *
	 * @since 1.0.0
	 * @param string $type       Queue item type.
	 * @param mixed  $source_id  Source ID.
	 * @param array  $payload    Data payload.
	 * @param string $status     Initial status (default: 'pending').
	 * @return int|false Queue item ID or false on failure.
	 */
	public static function insert( $type, $source_id, $payload, $status = 'pending' ) {
		global $wpdb;

		$result = $wpdb->insert(
			self::get_table_name(),
			array(
				'type'       => $type,
				'source_id'  => $source_id,
				'payload'    => WTA_Utils::sanitize_json( $payload ),
				'status'     => $status,
				'created_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( $result ) {
			return $wpdb->insert_id;
		}

		return false;
	}

	/**
	 * Get queue items by criteria.
	 *
	 * @since 1.0.0
	 * @param array $args Query arguments.
	 * @return array Array of queue items.
	 */
	public static function get_items( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'type'     => '',
			'status'   => '',
			'limit'    => 50,
			'offset'   => 0,
			'order_by' => 'id',
			'order'    => 'ASC',
		);

		$args = wp_parse_args( $args, $defaults );

		$table = self::get_table_name();
		$where = array( '1=1' );
		$where_values = array();

		if ( ! empty( $args['type'] ) ) {
			$where[] = 'type = %s';
			$where_values[] = $args['type'];
		}

		if ( ! empty( $args['status'] ) ) {
			$where[] = 'status = %s';
			$where_values[] = $args['status'];
		}

		$where_clause = implode( ' AND ', $where );
		$order_by = sanitize_sql_orderby( $args['order_by'] . ' ' . $args['order'] );
		$limit = absint( $args['limit'] );
		$offset = absint( $args['offset'] );

		$query = "SELECT * FROM $table WHERE $where_clause ORDER BY $order_by LIMIT $limit OFFSET $offset";

		if ( ! empty( $where_values ) ) {
			$query = $wpdb->prepare( $query, $where_values );
		}

		$results = $wpdb->get_results( $query, ARRAY_A );

		// Parse JSON payloads
		foreach ( $results as &$item ) {
			if ( ! empty( $item['payload'] ) ) {
				$item['payload'] = WTA_Utils::parse_json( $item['payload'] );
			}
		}

		return $results;
	}

	/**
	 * Get a single queue item by ID.
	 *
	 * @since 1.0.0
	 * @param int $id Queue item ID.
	 * @return array|null Queue item or null if not found.
	 */
	public static function get_item( $id ) {
		global $wpdb;

		$table = self::get_table_name();
		$query = $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id );
		$item = $wpdb->get_row( $query, ARRAY_A );

		if ( $item && ! empty( $item['payload'] ) ) {
			$item['payload'] = WTA_Utils::parse_json( $item['payload'] );
		}

		return $item;
	}

	/**
	 * Update queue item status.
	 *
	 * @since 1.0.0
	 * @param int    $id         Queue item ID.
	 * @param string $status     New status.
	 * @param string $last_error Last error message (optional).
	 * @return bool True on success.
	 */
	public static function update_status( $id, $status, $last_error = '' ) {
		global $wpdb;

		$data = array(
			'status'     => $status,
			'updated_at' => current_time( 'mysql' ),
		);

		$format = array( '%s', '%s' );

		if ( ! empty( $last_error ) ) {
			$data['last_error'] = $last_error;
			$format[] = '%s';
		}

		$result = $wpdb->update(
			self::get_table_name(),
			$data,
			array( 'id' => $id ),
			$format,
			array( '%d' )
		);

		return $result !== false;
	}

	/**
	 * Delete queue items by criteria.
	 *
	 * @since 1.0.0
	 * @param array $args Query arguments.
	 * @return int Number of deleted items.
	 */
	public static function delete_items( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'type'   => '',
			'status' => '',
		);

		$args = wp_parse_args( $args, $defaults );

		$table = self::get_table_name();
		$where = array();
		$where_values = array();

		if ( ! empty( $args['type'] ) ) {
			$where[] = 'type = %s';
			$where_values[] = $args['type'];
		}

		if ( ! empty( $args['status'] ) ) {
			$where[] = 'status = %s';
			$where_values[] = $args['status'];
		}

		if ( empty( $where ) ) {
			// Don't allow deleting all items without criteria
			return 0;
		}

		$where_clause = implode( ' AND ', $where );
		$query = "DELETE FROM $table WHERE $where_clause";

		if ( ! empty( $where_values ) ) {
			$query = $wpdb->prepare( $query, $where_values );
		}

		return $wpdb->query( $query );
	}

	/**
	 * Clear all queue items.
	 *
	 * @since 1.0.0
	 * @return bool True on success.
	 */
	public static function clear_all() {
		global $wpdb;
		$table = self::get_table_name();
		return $wpdb->query( "TRUNCATE TABLE $table" ) !== false;
	}

	/**
	 * Get queue statistics.
	 *
	 * @since 1.0.0
	 * @return array Statistics by type and status.
	 */
	public static function get_stats() {
		global $wpdb;
		$table = self::get_table_name();

		$query = "SELECT type, status, COUNT(*) as count FROM $table GROUP BY type, status";
		$results = $wpdb->get_results( $query, ARRAY_A );

		$stats = array(
			'total'     => 0,
			'by_type'   => array(),
			'by_status' => array(
				'pending'    => 0,
				'processing' => 0,
				'done'       => 0,
				'error'      => 0,
			),
		);

		foreach ( $results as $row ) {
			$type = $row['type'];
			$status = $row['status'];
			$count = intval( $row['count'] );

			$stats['total'] += $count;

			if ( ! isset( $stats['by_type'][ $type ] ) ) {
				$stats['by_type'][ $type ] = array(
					'total'      => 0,
					'pending'    => 0,
					'processing' => 0,
					'done'       => 0,
					'error'      => 0,
				);
			}

			$stats['by_type'][ $type ][ $status ] = $count;
			$stats['by_type'][ $type ]['total'] += $count;

			if ( isset( $stats['by_status'][ $status ] ) ) {
				$stats['by_status'][ $status ] += $count;
			}
		}

		return $stats;
	}

	/**
	 * Get count of items by status.
	 *
	 * @since 1.0.0
	 * @param string $status Status to count.
	 * @param string $type   Type filter (optional).
	 * @return int Count of items.
	 */
	public static function count( $status = '', $type = '' ) {
		global $wpdb;

		$table = self::get_table_name();
		$where = array();
		$where_values = array();

		if ( ! empty( $status ) ) {
			$where[] = 'status = %s';
			$where_values[] = $status;
		}

		if ( ! empty( $type ) ) {
			$where[] = 'type = %s';
			$where_values[] = $type;
		}

		if ( empty( $where ) ) {
			return $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
		}

		$where_clause = implode( ' AND ', $where );
		$query = "SELECT COUNT(*) FROM $table WHERE $where_clause";

		if ( ! empty( $where_values ) ) {
			$query = $wpdb->prepare( $query, $where_values );
		}

		return intval( $wpdb->get_var( $query ) );
	}

	/**
	 * Mark items as pending that were stuck in processing.
	 *
	 * @since 1.0.0
	 * @param int $timeout Timeout in seconds (default: 300).
	 * @return int Number of reset items.
	 */
	public static function reset_stuck_items( $timeout = 300 ) {
		global $wpdb;

		$table = self::get_table_name();
		$time_threshold = date( 'Y-m-d H:i:s', time() - $timeout );

		$query = $wpdb->prepare(
			"UPDATE $table SET status = 'pending', updated_at = %s 
			WHERE status = 'processing' AND updated_at < %s",
			current_time( 'mysql' ),
			$time_threshold
		);

		return $wpdb->query( $query );
	}
}





