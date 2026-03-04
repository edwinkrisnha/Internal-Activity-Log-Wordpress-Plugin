<?php
defined( 'ABSPATH' ) || exit;

/**
 * Read-only interface to the activity log table.
 * All aggregation happens in SQL so the PHP layer stays thin.
 */
class IAL_Query {

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private static function table(): string {
		global $wpdb;
		return $wpdb->prefix . IAL_TABLE_NAME;
	}

	/** Return a UTC datetime string N days in the past. */
	private static function since( int $days ): string {
		return gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
	}

	// -------------------------------------------------------------------------
	// Dashboard stats
	// -------------------------------------------------------------------------

	/** Total number of log entries ever recorded. */
	public static function total_events(): int {
		global $wpdb;
		$table = self::table();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/** Number of distinct registered users (user_id > 0) active today (UTC). */
	public static function active_users_today(): int {
		global $wpdb;
		$table = self::table();
		$today = gmdate( 'Y-m-d' );

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT user_id) FROM `{$table}` WHERE DATE(created_at) = %s AND user_id > 0", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$today
			)
		);
	}

	/** Single most-active user in the last $days days, or null if no data. */
	public static function most_active_user( int $days = 30 ): ?array {
		global $wpdb;
		$table = self::table();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT user_id, username, COUNT(*) AS event_count
				 FROM `{$table}`
				 WHERE created_at >= %s AND user_id > 0
				 GROUP BY user_id, username
				 ORDER BY event_count DESC
				 LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				self::since( $days )
			),
			ARRAY_A
		);

		return $row ?: null;
	}

	// -------------------------------------------------------------------------
	// Chart data
	// -------------------------------------------------------------------------

	/**
	 * Top N users by event count over the last $days days.
	 *
	 * @return array<array{user_id: string, username: string, event_count: string}>
	 */
	public static function top_users( int $limit = 10, int $days = 30 ): array {
		global $wpdb;
		$table = self::table();

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id, username, COUNT(*) AS event_count
				 FROM `{$table}`
				 WHERE created_at >= %s AND user_id > 0
				 GROUP BY user_id, username
				 ORDER BY event_count DESC
				 LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				self::since( $days ),
				$limit
			),
			ARRAY_A
		) ?: [];
	}

	/**
	 * Daily event counts for the last $days days, with zero-fill for missing days.
	 *
	 * @return array<array{day: string, event_count: int}>
	 */
	public static function daily_activity( int $days = 30 ): array {
		global $wpdb;
		$table = self::table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(created_at) AS day, COUNT(*) AS event_count
				 FROM `{$table}`
				 WHERE created_at >= %s
				 GROUP BY DATE(created_at)
				 ORDER BY day ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				self::since( $days )
			),
			ARRAY_A
		) ?: [];

		// Index by date for fast lookup
		$by_day = array_column( $rows, 'event_count', 'day' );

		// Build a complete date range, filling gaps with 0
		$result = [];
		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$date     = gmdate( 'Y-m-d', strtotime( "-{$i} days" ) );
			$result[] = [
				'day'         => $date,
				'event_count' => (int) ( $by_day[ $date ] ?? 0 ),
			];
		}

		return $result;
	}

	/**
	 * Event counts grouped by action type for the last $days days.
	 *
	 * @return array<array{action: string, event_count: string}>
	 */
	public static function events_by_action( int $days = 30 ): array {
		global $wpdb;
		$table = self::table();

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT action, COUNT(*) AS event_count
				 FROM `{$table}`
				 WHERE created_at >= %s
				 GROUP BY action
				 ORDER BY event_count DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				self::since( $days )
			),
			ARRAY_A
		) ?: [];
	}

	// -------------------------------------------------------------------------
	// Log table
	// -------------------------------------------------------------------------

	/**
	 * Paginated, filtered log entries.
	 *
	 * @param array{
	 *   user_id?: int,
	 *   action?: string,
	 *   date_from?: string,
	 *   date_to?: string
	 * } $filters
	 *
	 * @return array{logs: array, total: int}
	 */
	public static function get_logs( array $filters = [], int $page = 1, int $per_page = 20 ): array {
		global $wpdb;
		$table = self::table();

		$where  = [ '1=1' ];
		$values = [];

		if ( ! empty( $filters['user_id'] ) ) {
			$where[]  = 'user_id = %d';
			$values[] = (int) $filters['user_id'];
		}

		if ( ! empty( $filters['action'] ) ) {
			$where[]  = 'action = %s';
			$values[] = $filters['action'];
		}

		if ( ! empty( $filters['date_from'] ) ) {
			$where[]  = 'created_at >= %s';
			$values[] = $filters['date_from'] . ' 00:00:00';
		}

		if ( ! empty( $filters['date_to'] ) ) {
			$where[]  = 'created_at <= %s';
			$values[] = $filters['date_to'] . ' 23:59:59';
		}

		$where_sql = implode( ' AND ', $where );
		$offset    = ( $page - 1 ) * $per_page;

		if ( $values ) {
			$total = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM `{$table}` WHERE {$where_sql}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					...$values
				)
			);

			$logs = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM `{$table}` WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					...array_merge( $values, [ $per_page, $offset ] )
				),
				ARRAY_A
			) ?: [];
		} else {
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$logs  = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM `{$table}` ORDER BY created_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$per_page,
					$offset
				),
				ARRAY_A
			) ?: [];
		}

		return [ 'logs' => $logs, 'total' => $total ];
	}

	/** All distinct action slugs recorded so far (for filter dropdowns). */
	public static function distinct_actions(): array {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_col( "SELECT DISTINCT action FROM `{$table}` ORDER BY action ASC" ) ?: []; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
