<?php
/**
 * Dashboard view — date range picker, stat cards, Chart.js charts.
 *
 * Variables available (set by IAL_Admin::render_dashboard()):
 *   $range['date_from']        string    YYYY-MM-DD
 *   $range['date_to']          string    YYYY-MM-DD
 *   $range['presets']          array     quick-access preset definitions
 *   $stats['total_events']     int
 *   $stats['active_today']     int
 *   $stats['most_active_user'] array|null
 */
defined( 'ABSPATH' ) || exit;

$base_url = admin_url( 'admin.php?page=ial-dashboard' );
?>
<div class="wrap ial-wrap">

	<h1><?php esc_html_e( 'Activity Log — Dashboard', 'internal-activity-log' ); ?></h1>

	<!-- ── Date-independent stat cards (always show current totals) ────── -->
	<div class="ial-stats-grid ial-stats-grid--top">

		<div class="ial-stat-card">
			<div class="ial-stat-label"><?php esc_html_e( 'Total Events (all time)', 'internal-activity-log' ); ?></div>
			<div class="ial-stat-value"><?php echo esc_html( number_format_i18n( $stats['total_events'] ) ); ?></div>
		</div>

		<div class="ial-stat-card">
			<div class="ial-stat-label"><?php esc_html_e( 'Most Active User (all time)', 'internal-activity-log' ); ?></div>
			<?php if ( $stats['most_active_user_all_time'] ) : ?>
				<div class="ial-stat-value ial-stat-value--sm">
					<?php echo esc_html( $stats['most_active_user_all_time']['username'] ); ?>
				</div>
				<div class="ial-stat-sub">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: number of events */
							_n( '%s event', '%s events', (int) $stats['most_active_user_all_time']['event_count'], 'internal-activity-log' ),
							number_format_i18n( (int) $stats['most_active_user_all_time']['event_count'] )
						)
					);
					?>
				</div>
			<?php else : ?>
				<div class="ial-stat-value">—</div>
			<?php endif; ?>
		</div>

		<div class="ial-stat-card">
			<div class="ial-stat-label"><?php esc_html_e( 'Active Users Today', 'internal-activity-log' ); ?></div>
			<div class="ial-stat-value"><?php echo esc_html( number_format_i18n( $stats['active_today'] ) ); ?></div>
		</div>

	</div><!-- .ial-stats-grid--top -->

	<!-- ── Date range bar ──────────────────────────────────────────────── -->
	<div class="ial-date-bar">

		<!-- Quick-access preset links (always relative to today, server-side) -->
		<div class="ial-date-presets">
			<?php foreach ( $range['presets'] as $preset ) :
				$is_active = ( $range['date_from'] === $preset['from'] && $range['date_to'] === $preset['to'] );
				$url       = add_query_arg( [ 'date_from' => $preset['from'], 'date_to' => $preset['to'] ], $base_url );
			?>
				<a href="<?php echo esc_url( $url ); ?>"
				   class="ial-preset-btn<?php echo $is_active ? ' ial-preset-btn--active' : ''; ?>">
					<?php echo esc_html( $preset['label'] ); ?>
				</a>
			<?php endforeach; ?>
		</div>

		<!-- Custom date range form -->
		<form method="get" class="ial-custom-range" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
			<input type="hidden" name="page" value="ial-dashboard">
			<label for="ial-date-from" class="screen-reader-text"><?php esc_html_e( 'From', 'internal-activity-log' ); ?></label>
			<input
				type="date"
				id="ial-date-from"
				name="date_from"
				value="<?php echo esc_attr( $range['date_from'] ); ?>"
				max="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>"
			>
			<span class="ial-range-sep" aria-hidden="true">–</span>
			<label for="ial-date-to" class="screen-reader-text"><?php esc_html_e( 'To', 'internal-activity-log' ); ?></label>
			<input
				type="date"
				id="ial-date-to"
				name="date_to"
				value="<?php echo esc_attr( $range['date_to'] ); ?>"
				max="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>"
			>
			<?php submit_button( __( 'Apply', 'internal-activity-log' ), 'secondary small', 'apply_range', false ); ?>
		</form>

	</div><!-- .ial-date-bar -->

	<p class="ial-subtitle">
		<?php
		printf(
			/* translators: 1: start date, 2: end date */
			'Showing <strong>%1$s</strong> – <strong>%2$s</strong> &middot; all times UTC',
			esc_html( $range['date_from'] ),
			esc_html( $range['date_to'] )
		);
		?>
	</p>

	<!-- ── Date-dependent stat cards ───────────────────────────────────── -->
	<div class="ial-stats-grid ial-stats-grid--range">

		<div class="ial-stat-card">
			<div class="ial-stat-label"><?php esc_html_e( 'Total Events (selected range)', 'internal-activity-log' ); ?></div>
			<div class="ial-stat-value"><?php echo esc_html( number_format_i18n( $stats['total_events_in_range'] ) ); ?></div>
		</div>

		<div class="ial-stat-card">
			<div class="ial-stat-label"><?php esc_html_e( 'Most Active User (selected range)', 'internal-activity-log' ); ?></div>
			<?php if ( $stats['most_active_user'] ) : ?>
				<div class="ial-stat-value ial-stat-value--sm">
					<?php echo esc_html( $stats['most_active_user']['username'] ); ?>
				</div>
				<div class="ial-stat-sub">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: number of events */
							_n( '%s event', '%s events', (int) $stats['most_active_user']['event_count'], 'internal-activity-log' ),
							number_format_i18n( (int) $stats['most_active_user']['event_count'] )
						)
					);
					?>
				</div>
			<?php else : ?>
				<div class="ial-stat-value">—</div>
			<?php endif; ?>
		</div>

	</div><!-- .ial-stats-grid--range -->

	<!-- ── Charts ──────────────────────────────────────────────────────── -->
	<div class="ial-charts-grid">

		<!-- Bar: Top 10 users -->
		<div class="ial-chart-card">
			<h3><?php esc_html_e( 'Most Active Users', 'internal-activity-log' ); ?></h3>
			<div class="ial-chart-container">
				<canvas id="ial-chart-top-users" aria-label="<?php esc_attr_e( 'Most active users bar chart', 'internal-activity-log' ); ?>" role="img"></canvas>
			</div>
		</div>

		<!-- Doughnut: Events by action type -->
		<div class="ial-chart-card">
			<h3><?php esc_html_e( 'Events by Action Type', 'internal-activity-log' ); ?></h3>
			<div class="ial-chart-container">
				<canvas id="ial-chart-actions" aria-label="<?php esc_attr_e( 'Events by action type doughnut chart', 'internal-activity-log' ); ?>" role="img"></canvas>
			</div>
		</div>

		<!-- Line: Daily activity -->
		<div class="ial-chart-card ial-chart-card--wide">
			<h3><?php esc_html_e( 'Daily Activity', 'internal-activity-log' ); ?></h3>
			<div class="ial-chart-container ial-chart-container--short">
				<canvas id="ial-chart-daily" aria-label="<?php esc_attr_e( 'Daily activity line chart', 'internal-activity-log' ); ?>" role="img"></canvas>
			</div>
		</div>

	</div><!-- .ial-charts-grid -->

	<!-- ── Quick link ──────────────────────────────────────────────────── -->
	<p>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=ial-log' ) ); ?>" class="button">
			<?php esc_html_e( 'View Full Activity Log →', 'internal-activity-log' ); ?>
		</a>
	</p>

</div><!-- .wrap -->
