<?php
/**
 * Full activity log view with filters and paginated WP_List_Table.
 *
 * Variables available (set by IAL_Admin::render_log()):
 *   $table  IAL_Log_Table
 */
defined( 'ABSPATH' ) || exit;

$actions     = IAL_Query::distinct_actions();
$action_labels = IAL_Admin::action_labels();

// Current filter values for repopulating the form
$current_username  = isset( $_GET['username'] )      ? sanitize_text_field( wp_unslash( $_GET['username'] ) ) : ''; // phpcs:ignore
$current_action    = isset( $_GET['action_filter'] ) ? sanitize_key( $_GET['action_filter'] )                  : ''; // phpcs:ignore
$current_date_from = isset( $_GET['date_from'] )     ? sanitize_text_field( $_GET['date_from'] )               : ''; // phpcs:ignore
$current_date_to   = isset( $_GET['date_to'] )       ? sanitize_text_field( $_GET['date_to'] )                 : ''; // phpcs:ignore
?>
<div class="wrap ial-wrap">

	<h1><?php esc_html_e( 'Activity Log', 'internal-activity-log' ); ?></h1>

	<!-- ── Filters ─────────────────────────────────────────────────────── -->
	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" id="ial-filter-form">
		<input type="hidden" name="page" value="ial-log">

		<div class="ial-filters">

			<div class="ial-filter-group">
				<label for="ial-filter-user"><?php esc_html_e( 'Username', 'internal-activity-log' ); ?></label>
				<input
					type="text"
					id="ial-filter-user"
					name="username"
					value="<?php echo esc_attr( $current_username ); ?>"
					placeholder="<?php esc_attr_e( 'Any', 'internal-activity-log' ); ?>"
					style="width:120px;"
				>
			</div>

			<div class="ial-filter-group">
				<label for="ial-filter-action"><?php esc_html_e( 'Action', 'internal-activity-log' ); ?></label>
				<select id="ial-filter-action" name="action_filter" onchange="this.form.submit()">
					<option value=""><?php esc_html_e( 'All actions', 'internal-activity-log' ); ?></option>
					<?php foreach ( $actions as $slug ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $current_action, $slug ); ?>>
							<?php echo esc_html( $action_labels[ $slug ] ?? ucwords( str_replace( '_', ' ', $slug ) ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="ial-filter-group">
				<label for="ial-filter-from"><?php esc_html_e( 'From', 'internal-activity-log' ); ?></label>
				<input
					type="date"
					id="ial-filter-from"
					name="date_from"
					value="<?php echo esc_attr( $current_date_from ); ?>"
					onchange="this.form.submit()"
				>
			</div>

			<div class="ial-filter-group">
				<label for="ial-filter-to"><?php esc_html_e( 'To', 'internal-activity-log' ); ?></label>
				<input
					type="date"
					id="ial-filter-to"
					name="date_to"
					value="<?php echo esc_attr( $current_date_to ); ?>"
					onchange="this.form.submit()"
				>
			</div>

			<div class="ial-filter-group ial-filter-group--actions">
				<?php if ( $current_username || $current_action || $current_date_from || $current_date_to ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=ial-log' ) ); ?>" class="button">
						<?php esc_html_e( 'Clear', 'internal-activity-log' ); ?>
					</a>
				<?php endif; ?>
			</div>

		</div><!-- .ial-filters -->
	</form>
	<script>
	document.getElementById( 'ial-filter-user' ).addEventListener( 'change', function () {
		this.form.submit();
	} );
	</script>

	<!-- ── Table ────────────────────────────────────────────────────────── -->
	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
		<input type="hidden" name="page" value="ial-log">
		<?php
		// Persist active filters across pagination
		foreach ( [ 'username', 'action_filter', 'date_from', 'date_to' ] as $key ) {
			if ( ! empty( $_GET[ $key ] ) ) { // phpcs:ignore
				printf(
					'<input type="hidden" name="%s" value="%s">',
					esc_attr( $key ),
					esc_attr( sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) ) // phpcs:ignore
				);
			}
		}

		$table->display();
		?>
	</form>

</div><!-- .wrap -->
