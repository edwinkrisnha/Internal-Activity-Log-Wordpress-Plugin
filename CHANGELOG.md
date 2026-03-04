# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [1.0.3] — 2026-03-04

### Added
- Dashboard stat cards are now split into two tiers:
  - **Date-independent** (always show current totals, displayed above the date filter): Total Events (all time), Most Active User (all time), Active Users Today.
  - **Date-dependent** (reflect the selected range, displayed below the filter): Total Events (selected range), Most Active User (selected range).
- New stat card: **Most Active User (all time)** — shows the all-time top user with their total event count.
- New stat card: **Total Events (selected range)** — event count scoped to the current date filter.
- `IAL_Query::most_active_user_all_time()` — returns the single highest-event-count user across all recorded data.
- `IAL_Query::total_events_in_range(string $date_from, string $date_to)` — returns total event count for a specific date window.
- CSS grid variants: `.ial-stats-grid--top` (3-column, collapses to 2 → 1 at narrow viewports) and `.ial-stats-grid--range` (2-column).

## [1.0.2] — 2026-03-04

### Added
- Dashboard date range picker with six quick-access presets (Last 7 / 14 / 30 / 90 days, This month, This year) and a custom from/to date input form.
- Active preset is highlighted on the button bar after selection.
- Subtitle now shows the active date range instead of a hardcoded "Last 30 days" string.
- `IAL_Admin::resolve_date_range()` — central helper that reads, validates, and caps the GET date params; defaults to last 30 days when absent.

### Changed
- All dashboard chart queries (`top_users`, `daily_activity`, `events_by_action`, `most_active_user`) now accept explicit `$date_from` / `$date_to` strings instead of a `$days` integer, making the range fully user-controlled.
- `daily_activity` zero-fill now iterates the actual selected date range rather than a fixed rolling window.

## [1.0.1] — 2026-03-04

### Fixed
- Dashboard charts expanded infinitely on load. Root cause: Chart.js `responsive` mode reads the canvas parent's dimensions; the parent (`.ial-chart-card`) used `min-height` + `flex: 1` on the canvas, so each resize made the container taller, triggering another resize loop. Fixed by wrapping each canvas in a `position: relative` div (`.ial-chart-container`) with a fixed pixel height, which gives Chart.js a stable, immutable boundary.

## [1.0.0] — 2026-03-04

### Added
- Initial release.
- Custom `wp_activity_log` database table with indexes on `user_id`, `action`, and `created_at`.
- **IAL_Logger** — captures 22 distinct WordPress events across users, posts, media, comments, plugins, and settings.
- **IAL_Query** — read layer with helpers for dashboard stats, chart aggregations, and paginated/filtered log queries.
- **IAL_Admin** — admin menu ("Activity Log"), asset enqueueing, and capability-gated page renderers.
- **IAL_Log_Table** (`WP_List_Table`) — paginated activity log with filters by user ID, action type, and date range.
- Dashboard page with three Chart.js 4 charts:
  - Horizontal bar — top 10 most active users (last 30 days)
  - Line — daily event volume (last 30 days)
  - Doughnut — events by action type
- Stat cards: total events all-time, active users today, most active user (last 30 days).
- Action badges with colour-coded severity (success / info / warning / danger / neutral).
- IP address capture with Cloudflare / proxy header support.
- Safe uninstall: drops `wp_activity_log` table and removes `ial_db_version` option.
