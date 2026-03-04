# Internal Activity Log

A WordPress plugin that tracks user activity and displays it as interactive charts and a filterable log table in the admin dashboard.

## Features

- **Activity tracking** — automatically captures the following events:
  - User login, logout, failed login, registration, profile update, deletion, password reset
  - Post/page created, published, updated, trashed, untrashed, deleted (all post types)
  - Media uploaded and deleted
  - Comments posted, trashed, spammed, unspammed
  - Plugins activated and deactivated
  - Settings/options updated (admin-side only, deduplicated)
- **Dashboard with Chart.js charts**
  - Horizontal bar chart — top 10 most active users (last 30 days)
  - Line chart — daily event volume (last 30 days, zero-filled)
  - Doughnut chart — event breakdown by action type
  - Stat cards — total events, active users today, most active user
- **Full log table** — paginated `WP_List_Table` with filters by user ID, action type, and date range
- **Data stored in a dedicated custom DB table** (`wp_activity_log`) with indexes for fast querying

## Requirements

- WordPress 6.0+
- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.3+

## Installation

1. Upload the `internal-activity-log` folder to `/wp-content/plugins/`.
2. Activate the plugin via **Plugins → Installed Plugins**.
3. On activation the `wp_activity_log` table is created automatically.
4. Navigate to **Activity Log** in the WP admin sidebar.

## Uninstall

Deactivating the plugin keeps your data intact. To permanently delete all log data, uninstall the plugin via WP admin — this drops the `wp_activity_log` table and removes the `ial_db_version` option.

## File structure

```
internal-activity-log/
├── internal-activity-log.php      # Plugin bootstrap & hook registration
├── includes/
│   ├── class-installer.php        # DB table creation / teardown
│   ├── class-logger.php           # WP action hooks → log writer
│   ├── class-query.php            # Read / aggregate from DB
│   ├── class-admin.php            # Admin menu, asset enqueueing, page renders
│   └── class-log-table.php        # WP_List_Table subclass
├── admin/
│   ├── views/
│   │   ├── page-dashboard.php     # Dashboard template
│   │   └── page-log.php           # Log list template
│   ├── js/
│   │   └── dashboard.js           # Chart.js initialization
│   └── css/
│       └── admin.css              # Scoped admin styles
├── CHANGELOG.md
└── README.md
```

## Architecture decisions

| Decision | Choice | Reason |
|---|---|---|
| Data storage | Custom `wp_activity_log` table | Fast indexed queries; clean aggregation for charts |
| Code structure | OOP single-responsibility classes | Testable, DRY, easy to extend |
| Charting | Chart.js 4 via CDN | Lightweight, no build step, no external data exposure |

## Security

- All DB reads use `$wpdb->prepare()`.
- All output is escaped with `esc_html`, `esc_url`, `esc_attr`.
- Admin pages check `current_user_can('manage_options')` before rendering.
- IP addresses are validated with `filter_var(FILTER_VALIDATE_IP)` before storing.
