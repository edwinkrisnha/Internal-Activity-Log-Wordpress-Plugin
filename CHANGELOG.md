# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

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
