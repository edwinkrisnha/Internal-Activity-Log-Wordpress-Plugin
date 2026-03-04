<?php
/**
 * Dashboard view — stat cards + Chart.js charts.
 *
 * Variables available (set by IAL_Admin::render_dashboard()):
 *   $stats['total_events']      int
 *   $stats['active_today']      int
 *   $stats['most_active_user']  array|null  { user_id, username, event_count }
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="wrap ial-wrap">

	<h1><?php esc_html_e( 'Activity Log — Dashboard', 'internal-activity-log' ); ?></h1>
	<p class="ial-subtitle"><?php esc_html_e( 'Last 30 days · all times in site timezone', 'internal-activity-log' ); ?></p>

	<!-- ── Stat cards ──────────────────────────────────────────────────── -->
	<div class="ial-stats-grid">

		<div class="ial-stat-card">
			<div class="ial-stat-label"><?php esc_html_e( 'Total Events', 'internal-activity-log' ); ?></div>
			<div class="ial-stat-value"><?php echo esc_html( number_format_i18n( $stats['total_events'] ) ); ?></div>
		</div>

		<div class="ial-stat-card">
			<div class="ial-stat-label"><?php esc_html_e( 'Active Users Today', 'internal-activity-log' ); ?></div>
			<div class="ial-stat-value"><?php echo esc_html( number_format_i18n( $stats['active_today'] ) ); ?></div>
		</div>

		<div class="ial-stat-card">
			<div class="ial-stat-label"><?php esc_html_e( 'Most Active (30 d)', 'internal-activity-log' ); ?></div>
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

	</div><!-- .ial-stats-grid -->

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
			<h3><?php esc_html_e( 'Daily Activity (Last 30 Days)', 'internal-activity-log' ); ?></h3>
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
