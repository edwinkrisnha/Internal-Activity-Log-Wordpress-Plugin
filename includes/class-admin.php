<?php
defined( 'ABSPATH' ) || exit;

require_once IAL_PLUGIN_DIR . 'includes/class-query.php';
require_once IAL_PLUGIN_DIR . 'includes/class-log-table.php';

/**
 * Registers WP admin menus, enqueues assets, and renders dashboard/log pages.
 */
class IAL_Admin {

	// -------------------------------------------------------------------------
	// Init
	// -------------------------------------------------------------------------

	public static function init(): void {
		add_action( 'admin_menu',             [ self::class, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts',  [ self::class, 'enqueue_assets' ] );
	}

	// -------------------------------------------------------------------------
	// Menu
	// -------------------------------------------------------------------------

	public static function register_menu(): void {
		add_menu_page(
			__( 'Activity Log', 'internal-activity-log' ),
			__( 'Activity Log', 'internal-activity-log' ),
			'manage_options',
			'ial-dashboard',
			[ self::class, 'render_dashboard' ],
			'dashicons-chart-bar',
			30
		);

		add_submenu_page(
			'ial-dashboard',
			__( 'Dashboard', 'internal-activity-log' ),
			__( 'Dashboard', 'internal-activity-log' ),
			'manage_options',
			'ial-dashboard',
			[ self::class, 'render_dashboard' ]
		);

		add_submenu_page(
			'ial-dashboard',
			__( 'Log', 'internal-activity-log' ),
			__( 'Log', 'internal-activity-log' ),
			'manage_options',
			'ial-log',
			[ self::class, 'render_log' ]
		);
	}

	// -------------------------------------------------------------------------
	// Date range resolver
	// -------------------------------------------------------------------------

	/**
	 * Parse and validate date_from / date_to from the current GET request.
	 *
	 * Rules:
	 *  - Defaults to last 30 days when params are absent or invalid.
	 *  - Swaps from/to if they are reversed.
	 *  - Caps the range at 366 days to prevent runaway queries.
	 *
	 * @return array{ date_from: string, date_to: string, presets: array }
	 */
	public static function resolve_date_range(): array {
		$today = gmdate( 'Y-m-d' );

		$from = sanitize_text_field( wp_unslash( $_GET['date_from'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$to   = sanitize_text_field( wp_unslash( $_GET['date_to']   ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification

		$is_valid_date = static fn( string $d ): bool =>
			(bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) && false !== strtotime( $d );

		if ( ! $is_valid_date( $from ) ) {
			$from = gmdate( 'Y-m-d', strtotime( '-29 days' ) ); // last 30 days inclusive
		}
		if ( ! $is_valid_date( $to ) ) {
			$to = $today;
		}

		// Ensure chronological order
		if ( $from > $to ) {
			[ $from, $to ] = [ $to, $from ];
		}

		// Hard cap: no more than 366 days in one query
		if ( ( strtotime( $to ) - strtotime( $from ) ) > ( 366 * DAY_IN_SECONDS ) ) {
			$from = gmdate( 'Y-m-d', strtotime( $to ) - ( 366 * DAY_IN_SECONDS ) );
		}

		// Quick-access presets (PHP-generated so dates are always relative to today)
		$presets = [
			[ 'key' => '7d',         'label' => __( 'Last 7 days',  'internal-activity-log' ), 'from' => gmdate( 'Y-m-d', strtotime( '-6 days' ) ),  'to' => $today ],
			[ 'key' => '14d',        'label' => __( 'Last 14 days', 'internal-activity-log' ), 'from' => gmdate( 'Y-m-d', strtotime( '-13 days' ) ), 'to' => $today ],
			[ 'key' => '30d',        'label' => __( 'Last 30 days', 'internal-activity-log' ), 'from' => gmdate( 'Y-m-d', strtotime( '-29 days' ) ), 'to' => $today ],
			[ 'key' => '90d',        'label' => __( 'Last 90 days', 'internal-activity-log' ), 'from' => gmdate( 'Y-m-d', strtotime( '-89 days' ) ), 'to' => $today ],
			[ 'key' => 'this_month', 'label' => __( 'This month',   'internal-activity-log' ), 'from' => gmdate( 'Y-m-01' ),                         'to' => $today ],
			[ 'key' => 'this_year',  'label' => __( 'This year',    'internal-activity-log' ), 'from' => gmdate( 'Y-01-01' ),                        'to' => $today ],
		];

		return [
			'date_from' => $from,
			'date_to'   => $to,
			'presets'   => $presets,
		];
	}

	// -------------------------------------------------------------------------
	// Assets
	// -------------------------------------------------------------------------

	public static function enqueue_assets( string $hook ): void {
		$our_hooks = [ 'toplevel_page_ial-dashboard', 'activity-log_page_ial-log' ];
		if ( ! in_array( $hook, $our_hooks, true ) ) {
			return;
		}

		// Chart.js — pinned version via CDN
		wp_enqueue_script(
			'chartjs',
			'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
			[],
			'4.4.0',
			true
		);

		wp_enqueue_script(
			'ial-dashboard-js',
			IAL_PLUGIN_URL . 'admin/js/dashboard.js',
			[ 'chartjs' ],
			IAL_VERSION,
			true
		);

		wp_enqueue_style(
			'ial-admin-css',
			IAL_PLUGIN_URL . 'admin/css/admin.css',
			[],
			IAL_VERSION
		);

		// Pass chart data for the dashboard page only
		if ( 'toplevel_page_ial-dashboard' === $hook ) {
			$range = self::resolve_date_range();
			wp_localize_script(
				'ial-dashboard-js',
				'ialData',
				[
					'topUsers'      => IAL_Query::top_users( 10, $range['date_from'], $range['date_to'] ),
					'dailyActivity' => IAL_Query::daily_activity( $range['date_from'], $range['date_to'] ),
					'byAction'      => IAL_Query::events_by_action( $range['date_from'], $range['date_to'] ),
				]
			);
		}
	}

	// -------------------------------------------------------------------------
	// Page renderers
	// -------------------------------------------------------------------------

	public static function render_dashboard(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'internal-activity-log' ) );
		}

		$range = self::resolve_date_range();
		$stats = [
			'total_events'            => IAL_Query::total_events(),
			'active_today'            => IAL_Query::active_users_today(),
			'most_active_user_all_time' => IAL_Query::most_active_user_all_time(),
			'total_events_in_range'   => IAL_Query::total_events_in_range( $range['date_from'], $range['date_to'] ),
			'most_active_user'        => IAL_Query::most_active_user( $range['date_from'], $range['date_to'] ),
		];

		include IAL_PLUGIN_DIR . 'admin/views/page-dashboard.php';
	}

	public static function render_log(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'internal-activity-log' ) );
		}

		$table = new IAL_Log_Table();
		$table->prepare_items();

		include IAL_PLUGIN_DIR . 'admin/views/page-log.php';
	}

	// -------------------------------------------------------------------------
	// Shared presentation helpers (used by IAL_Log_Table too)
	// -------------------------------------------------------------------------

	/** Human-readable labels for every action slug. */
	public static function action_labels(): array {
		return [
			'user_login'         => __( 'Login',               'internal-activity-log' ),
			'user_logout'        => __( 'Logout',              'internal-activity-log' ),
			'user_registered'    => __( 'User Registered',     'internal-activity-log' ),
			'profile_updated'    => __( 'Profile Updated',     'internal-activity-log' ),
			'user_deleted'       => __( 'User Deleted',        'internal-activity-log' ),
			'password_reset'     => __( 'Password Reset',      'internal-activity-log' ),
			'login_failed'       => __( 'Login Failed',        'internal-activity-log' ),
			'post_published'     => __( 'Published',           'internal-activity-log' ),
			'post_created'       => __( 'Post Created',        'internal-activity-log' ),
			'post_updated'       => __( 'Post Updated',        'internal-activity-log' ),
			'post_trashed'       => __( 'Trashed',             'internal-activity-log' ),
			'post_untrashed'     => __( 'Untrashed',           'internal-activity-log' ),
			'post_deleted'       => __( 'Post Deleted',        'internal-activity-log' ),
			'media_uploaded'     => __( 'Media Uploaded',      'internal-activity-log' ),
			'media_deleted'      => __( 'Media Deleted',       'internal-activity-log' ),
			'comment_posted'     => __( 'Comment Posted',      'internal-activity-log' ),
			'comment_trashed'    => __( 'Comment Trashed',     'internal-activity-log' ),
			'comment_spammed'    => __( 'Comment Spammed',     'internal-activity-log' ),
			'comment_unspammed'  => __( 'Comment Unspammed',   'internal-activity-log' ),
			'plugin_activated'   => __( 'Plugin Activated',    'internal-activity-log' ),
			'plugin_deactivated' => __( 'Plugin Deactivated',  'internal-activity-log' ),
			'option_updated'     => __( 'Settings Updated',    'internal-activity-log' ),
		];
	}

	/** CSS modifier class for an action badge. */
	public static function action_class( string $action ): string {
		$map = [
			'user_login'         => 'success',
			'user_logout'        => 'neutral',
			'user_registered'    => 'success',
			'profile_updated'    => 'neutral',
			'user_deleted'       => 'danger',
			'password_reset'     => 'warning',
			'login_failed'       => 'danger',
			'post_published'     => 'info',
			'post_created'       => 'info',
			'post_updated'       => 'info',
			'post_trashed'       => 'warning',
			'post_untrashed'     => 'neutral',
			'post_deleted'       => 'danger',
			'media_uploaded'     => 'info',
			'media_deleted'      => 'danger',
			'comment_posted'     => 'info',
			'comment_trashed'    => 'warning',
			'comment_spammed'    => 'danger',
			'comment_unspammed'  => 'neutral',
			'plugin_activated'   => 'success',
			'plugin_deactivated' => 'warning',
			'option_updated'     => 'neutral',
		];

		return $map[ $action ] ?? 'neutral';
	}
}
