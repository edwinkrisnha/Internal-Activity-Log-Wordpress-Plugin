/* global Chart, ialData */
( function () {
	'use strict';

	// Palette — 12 distinct colours that look good on white admin backgrounds
	const PALETTE = [
		'#4e8ef7', '#f76e4e', '#4ef7a8', '#f7c74e', '#a84ef7',
		'#4ef7f4', '#f74e8e', '#8ef74e', '#f74ee8', '#4e6af7',
		'#f7a84e', '#4ef75e',
	];

	// ── Helpers ──────────────────────────────────────────────────────────

	function getCtx( id ) {
		var el = document.getElementById( id );
		return el ? el.getContext( '2d' ) : null;
	}

	/**
	 * Format a date string (YYYY-MM-DD) as a short locale string.
	 * Falls back to the raw string if the browser can't parse it.
	 */
	function shortDate( dateStr ) {
		try {
			var d = new Date( dateStr + 'T00:00:00' );
			return d.toLocaleDateString( undefined, { month: 'short', day: 'numeric' } );
		} catch ( e ) {
			return dateStr;
		}
	}

	function humanAction( slug ) {
		return slug.replace( /_/g, ' ' ).replace( /\b\w/g, function ( c ) { return c.toUpperCase(); } );
	}

	// ── Charts ───────────────────────────────────────────────────────────

	function buildTopUsersChart() {
		var ctx = getCtx( 'ial-chart-top-users' );
		if ( ! ctx || ! ialData.topUsers || ! ialData.topUsers.length ) {
			return;
		}

		new Chart( ctx, {
			type: 'bar',
			data: {
				labels: ialData.topUsers.map( function ( u ) { return u.username; } ),
				datasets: [ {
					label: 'Events',
					data: ialData.topUsers.map( function ( u ) { return parseInt( u.event_count, 10 ); } ),
					backgroundColor: PALETTE[ 0 ],
					borderColor: PALETTE[ 0 ],
					borderRadius: 4,
				} ],
			},
			options: {
				indexAxis: 'y',
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: { display: false },
					tooltip: {
						callbacks: {
							label: function ( ctx ) {
								return ' ' + ctx.parsed.x + ' events';
							},
						},
					},
				},
				scales: {
					x: {
						beginAtZero: true,
						ticks: { precision: 0 },
						grid: { color: 'rgba(0,0,0,0.05)' },
					},
					y: {
						grid: { display: false },
					},
				},
			},
		} );
	}

	function buildDailyChart() {
		var ctx = getCtx( 'ial-chart-daily' );
		if ( ! ctx || ! ialData.dailyActivity || ! ialData.dailyActivity.length ) {
			return;
		}

		new Chart( ctx, {
			type: 'line',
			data: {
				labels: ialData.dailyActivity.map( function ( d ) { return shortDate( d.day ); } ),
				datasets: [ {
					label: 'Events',
					data: ialData.dailyActivity.map( function ( d ) { return parseInt( d.event_count, 10 ); } ),
					borderColor: PALETTE[ 0 ],
					backgroundColor: 'rgba(78,142,247,0.12)',
					pointBackgroundColor: PALETTE[ 0 ],
					pointRadius: 3,
					pointHoverRadius: 5,
					fill: true,
					tension: 0.35,
				} ],
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: { display: false },
					tooltip: {
						callbacks: {
							label: function ( ctx ) {
								return ' ' + ctx.parsed.y + ' events';
							},
						},
					},
				},
				scales: {
					x: {
						grid: { color: 'rgba(0,0,0,0.05)' },
						ticks: { maxTicksLimit: 10 },
					},
					y: {
						beginAtZero: true,
						ticks: { precision: 0 },
						grid: { color: 'rgba(0,0,0,0.05)' },
					},
				},
			},
		} );
	}

	function buildActionsChart() {
		var ctx = getCtx( 'ial-chart-actions' );
		if ( ! ctx || ! ialData.byAction || ! ialData.byAction.length ) {
			return;
		}

		new Chart( ctx, {
			type: 'doughnut',
			data: {
				labels: ialData.byAction.map( function ( a ) { return humanAction( a.action ); } ),
				datasets: [ {
					data: ialData.byAction.map( function ( a ) { return parseInt( a.event_count, 10 ); } ),
					backgroundColor: ialData.byAction.map( function ( _, i ) {
						return PALETTE[ i % PALETTE.length ];
					} ),
					borderWidth: 2,
					borderColor: '#fff',
					hoverOffset: 6,
				} ],
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: {
						position: 'right',
						labels: { boxWidth: 12, padding: 14, font: { size: 12 } },
					},
					tooltip: {
						callbacks: {
							label: function ( ctx ) {
								var total = ctx.dataset.data.reduce( function ( a, b ) { return a + b; }, 0 );
								var pct   = total > 0 ? ( ( ctx.parsed / total ) * 100 ).toFixed( 1 ) : 0;
								return ' ' + ctx.parsed + ' (' + pct + '%)';
							},
						},
					},
				},
			},
		} );
	}

	// ── Init ─────────────────────────────────────────────────────────────

	document.addEventListener( 'DOMContentLoaded', function () {
		buildTopUsersChart();
		buildDailyChart();
		buildActionsChart();
	} );
} )();
