<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * WP_List_Table subclass for paginated, filterable activity log display.
 */
class IAL_Log_Table extends WP_List_Table {

	/** Validated filter values for the current request. */
	private array $filters;

	public function __construct() {
		parent::__construct( [
			'singular' => 'log_entry',
			'plural'   => 'log_entries',
			'ajax'     => false,
		] );

		// Validate and capture filter values from the request
		$this->filters = array_filter( [
			'username'  => sanitize_text_field( wp_unslash( $_GET['username'] ?? '' ) ), // phpcs:ignore
			'action'    => sanitize_key( $_GET['action_filter'] ?? '' ),                  // phpcs:ignore
			'date_from' => sanitize_text_field( $_GET['date_from'] ?? '' ),               // phpcs:ignore
			'date_to'   => sanitize_text_field( $_GET['date_to'] ?? '' ),                 // phpcs:ignore
		] );
	}

	// -------------------------------------------------------------------------
	// Column definitions
	// -------------------------------------------------------------------------

	public function get_columns(): array {
		return [
			'created_at'  => __( 'Date / Time',  'internal-activity-log' ),
			'username'    => __( 'User',          'internal-activity-log' ),
			'action'      => __( 'Action',        'internal-activity-log' ),
			'object_type' => __( 'Object Type',   'internal-activity-log' ),
			'object_name' => __( 'Object',        'internal-activity-log' ),
			'ip_address'  => __( 'IP Address',    'internal-activity-log' ),
		];
	}

	public function get_sortable_columns(): array {
		return [
			'created_at' => [ 'created_at', true ],
			'username'   => [ 'username',   false ],
			'action'     => [ 'action',     false ],
		];
	}

	// -------------------------------------------------------------------------
	// Data loading
	// -------------------------------------------------------------------------

	public function prepare_items(): void {
		$per_page     = $this->get_items_per_page( 'ial_logs_per_page', 20 );
		$current_page = $this->get_pagenum();

		$result      = IAL_Query::get_logs( $this->filters, $current_page, $per_page );
		$this->items = $result['logs'];

		$this->set_pagination_args( [
			'total_items' => $result['total'],
			'per_page'    => $per_page,
			'total_pages' => (int) ceil( $result['total'] / $per_page ),
		] );

		$this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];
	}

	// -------------------------------------------------------------------------
	// Column renderers
	// -------------------------------------------------------------------------

	/** Fallback for columns without a dedicated renderer. */
	public function column_default( $item, $column_name ): string {
		return esc_html( $item[ $column_name ] ?? '' );
	}

	public function column_created_at( array $item ): string {
		// created_at is stored in UTC; convert to local WP timezone for display
		$local = get_date_from_gmt( $item['created_at'], 'Y-m-d H:i:s' );
		return '<time datetime="' . esc_attr( $item['created_at'] ) . '">' . esc_html( $local ) . '</time>';
	}

	public function column_username( array $item ): string {
		if ( empty( $item['user_id'] ) ) {
			// Guest or anonymous action (e.g. failed login attempt)
			return '<em>' . esc_html( $item['username'] ) . '</em>';
		}

		$url = add_query_arg(
			[ 'page' => 'ial-log', 'username' => $item['username'] ],
			admin_url( 'admin.php' )
		);

		return '<a href="' . esc_url( $url ) . '">' . esc_html( $item['username'] ) . '</a>';
	}

	public function column_action( array $item ): string {
		$labels = IAL_Admin::action_labels();
		$action = $item['action'];
		$label  = $labels[ $action ] ?? ucwords( str_replace( '_', ' ', $action ) );
		$class  = IAL_Admin::action_class( $action );

		return '<span class="ial-badge ial-badge--' . esc_attr( $class ) . '">' . esc_html( $label ) . '</span>';
	}

	public function column_object_type( array $item ): string {
		return '<code>' . esc_html( $item['object_type'] ) . '</code>';
	}

	public function no_items(): void {
		echo esc_html__( 'No activity logged yet.', 'internal-activity-log' );
	}
}
