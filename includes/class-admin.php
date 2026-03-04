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
			$days = 30;
			wp_localize_script(
				'ial-dashboard-js',
				'ialData',
				[
					'topUsers'      => IAL_Query::top_users( 10, $days ),
					'dailyActivity' => IAL_Query::daily_activity( $days ),
					'byAction'      => IAL_Query::events_by_action( $days ),
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

		$days  = 30;
		$stats = [
			'total_events'     => IAL_Query::total_events(),
			'active_today'     => IAL_Query::active_users_today(),
			'most_active_user' => IAL_Query::most_active_user( $days ),
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
