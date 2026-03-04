<?php
defined( 'ABSPATH' ) || exit;

class IAL_Installer {

	public static function install(): void {
		global $wpdb;

		$table          = $wpdb->prefix . IAL_TABLE_NAME;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id          bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id     bigint(20) unsigned NOT NULL DEFAULT 0,
			username    varchar(60)         NOT NULL DEFAULT '',
			action      varchar(100)        NOT NULL DEFAULT '',
			object_type varchar(100)        NOT NULL DEFAULT '',
			object_id   bigint(20) unsigned NOT NULL DEFAULT 0,
			object_name varchar(255)        NOT NULL DEFAULT '',
			ip_address  varchar(45)         NOT NULL DEFAULT '',
			created_at  datetime            NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY user_id    (user_id),
			KEY action     (action),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'ial_db_version', IAL_VERSION );
	}

	public static function deactivate(): void {
		// No-op — keep data intact on deactivation.
	}

	public static function uninstall(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . IAL_TABLE_NAME ); // phpcs:ignore
		delete_option( 'ial_db_version' );
	}
}
