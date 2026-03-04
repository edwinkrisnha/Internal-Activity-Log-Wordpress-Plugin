<?php
defined( 'ABSPATH' ) || exit;

/**
 * Hooks into WordPress events and writes entries to the activity log table.
 *
 * Responsibilities:
 *  - Register all WP action hooks.
 *  - Sanitize and normalize event data.
 *  - Delegate persistence to self::log().
 */
class IAL_Logger {

	/** Options that update too frequently or are WP-internal — never log these. */
	private const IGNORED_OPTION_PREFIXES = [
		'_transient_',
		'_site_transient_',
		'_user_',
		'ial_',         // our own options
		'cron',
		'rewrite_rules',
	];

	/** Post types that produce noise and should never be logged. */
	private const IGNORED_POST_TYPES = [
		'revision',
		'nav_menu_item',
		'customize_changeset',
		'oembed_cache',
		'user_request',
		'wp_block',
	];

	// -------------------------------------------------------------------------
	// Init
	// -------------------------------------------------------------------------

	public static function init(): void {
		// User events
		add_action( 'wp_login',        [ self::class, 'on_user_login' ],       10, 2 );
		add_action( 'wp_logout',       [ self::class, 'on_user_logout' ],      10, 1 );
		add_action( 'user_register',   [ self::class, 'on_user_registered' ],  10, 1 );
		add_action( 'profile_update',  [ self::class, 'on_profile_updated' ],  10, 2 );
		add_action( 'delete_user',     [ self::class, 'on_user_deleted' ],     10, 1 );
		add_action( 'password_reset',  [ self::class, 'on_password_reset' ],   10, 1 );
		add_action( 'wp_login_failed', [ self::class, 'on_login_failed' ],     10, 2 );

		// Post events
		add_action( 'transition_post_status', [ self::class, 'on_post_status_change' ], 10, 3 );
		add_action( 'wp_trash_post',          [ self::class, 'on_post_trashed' ],       10, 1 );
		add_action( 'untrash_post',           [ self::class, 'on_post_untrashed' ],     10, 1 );
		add_action( 'before_delete_post',     [ self::class, 'on_post_deleted' ],       10, 2 );

		// Media
		add_action( 'add_attachment',    [ self::class, 'on_attachment_added' ],   10, 1 );
		add_action( 'delete_attachment', [ self::class, 'on_attachment_deleted' ], 10, 1 );

		// Comments
		add_action( 'comment_post',  [ self::class, 'on_comment_posted' ],    10, 3 );
		add_action( 'trash_comment', [ self::class, 'on_comment_trashed' ],   10, 2 );
		add_action( 'spam_comment',  [ self::class, 'on_comment_spammed' ],   10, 2 );
		add_action( 'unspam_comment',[ self::class, 'on_comment_unspammed' ], 10, 2 );

		// Plugins
		add_action( 'activated_plugin',   [ self::class, 'on_plugin_activated' ],   10, 1 );
		add_action( 'deactivated_plugin', [ self::class, 'on_plugin_deactivated' ], 10, 1 );

		// Settings (only changes made inside WP admin, deduplicated per request)
		add_action( 'updated_option', [ self::class, 'on_option_updated' ], 10, 3 );
	}

	// -------------------------------------------------------------------------
	// User events
	// -------------------------------------------------------------------------

	public static function on_user_login( string $username, WP_User $user ): void {
		self::log( [
			'user_id'     => $user->ID,
			'username'    => $user->user_login,
			'action'      => 'user_login',
			'object_type' => 'user',
			'object_id'   => $user->ID,
			'object_name' => $user->user_login,
		] );
	}

	public static function on_user_logout( int $user_id ): void {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		self::log( [
			'user_id'     => $user_id,
			'username'    => $user->user_login,
			'action'      => 'user_logout',
			'object_type' => 'user',
			'object_id'   => $user_id,
			'object_name' => $user->user_login,
		] );
	}

	public static function on_user_registered( int $user_id ): void {
		$new_user = get_userdata( $user_id );
		if ( ! $new_user ) {
			return;
		}

		// The creator is the currently logged-in user; fall back to the new user for self-registration.
		$actor       = wp_get_current_user();
		$actor_id    = $actor && $actor->ID ? $actor->ID : $user_id;
		$actor_login = $actor && $actor->ID ? $actor->user_login : $new_user->user_login;

		self::log( [
			'user_id'     => $actor_id,
			'username'    => $actor_login,
			'action'      => 'user_registered',
			'object_type' => 'user',
			'object_id'   => $user_id,
			'object_name' => $new_user->user_login,
		] );
	}

	public static function on_profile_updated( int $user_id, WP_User $old_data ): void {
		$actor = wp_get_current_user();
		if ( ! $actor || ! $actor->ID ) {
			return;
		}

		$target = get_userdata( $user_id );

		self::log( [
			'user_id'     => $actor->ID,
			'username'    => $actor->user_login,
			'action'      => 'profile_updated',
			'object_type' => 'user',
			'object_id'   => $user_id,
			'object_name' => $target ? $target->user_login : "User #{$user_id}",
		] );
	}

	public static function on_user_deleted( int $user_id ): void {
		$actor = wp_get_current_user();
		if ( ! $actor || ! $actor->ID ) {
			return;
		}

		$target = get_userdata( $user_id );

		self::log( [
			'user_id'     => $actor->ID,
			'username'    => $actor->user_login,
			'action'      => 'user_deleted',
			'object_type' => 'user',
			'object_id'   => $user_id,
			'object_name' => $target ? $target->user_login : "User #{$user_id}",
		] );
	}

	public static function on_password_reset( WP_User $user ): void {
		self::log( [
			'user_id'     => $user->ID,
			'username'    => $user->user_login,
			'action'      => 'password_reset',
			'object_type' => 'user',
			'object_id'   => $user->ID,
			'object_name' => $user->user_login,
		] );
	}

	public static function on_login_failed( string $username, WP_Error $error ): void {
		self::log( [
			'user_id'     => 0,
			'username'    => sanitize_user( $username ),
			'action'      => 'login_failed',
			'object_type' => 'user',
			'object_id'   => 0,
			'object_name' => sanitize_user( $username ),
		] );
	}

	// -------------------------------------------------------------------------
	// Post events
	// -------------------------------------------------------------------------

	public static function on_post_status_change( string $new_status, string $old_status, WP_Post $post ): void {
		if ( in_array( $post->post_type, self::IGNORED_POST_TYPES, true ) ) {
			return;
		}

		// Ignore unchanged status (e.g. re-saving a draft)
		if ( $new_status === $old_status ) {
			return;
		}

		// Trash transition is handled by the dedicated wp_trash_post hook
		if ( 'trash' === $new_status ) {
			return;
		}

		$loggable_statuses = [ 'draft', 'pending', 'publish', 'private', 'future' ];
		if ( ! in_array( $new_status, $loggable_statuses, true ) ) {
			return;
		}

		if ( 'publish' === $new_status && 'publish' !== $old_status ) {
			$action = 'post_published';
		} elseif ( 'auto-draft' === $old_status ) {
			$action = 'post_created';
		} else {
			$action = 'post_updated';
		}

		$actor = wp_get_current_user();
		if ( ! $actor || ! $actor->ID ) {
			return;
		}

		self::log( [
			'user_id'     => $actor->ID,
			'username'    => $actor->user_login,
			'action'      => $action,
			'object_type' => $post->post_type,
			'object_id'   => $post->ID,
			'object_name' => $post->post_title ?: '(no title)',
		] );
	}

	public static function on_post_trashed( int $post_id ): void {
		$actor = wp_get_current_user();
		if ( ! $actor || ! $actor->ID ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post || in_array( $post->post_type, self::IGNORED_POST_TYPES, true ) ) {
			return;
		}

		self::log( [
			'user_id'     => $actor->ID,
			'username'    => $actor->user_login,
			'action'      => 'post_trashed',
			'object_type' => $post->post_type,
			'object_id'   => $post_id,
			'object_name' => $post->post_title ?: '(no title)',
		] );
	}

	public static function on_post_untrashed( int $post_id ): void {
		$actor = wp_get_current_user();
		if ( ! $actor || ! $actor->ID ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		self::log( [
			'user_id'     => $actor->ID,
			'username'    => $actor->user_login,
			'action'      => 'post_untrashed',
			'object_type' => $post->post_type,
			'object_id'   => $post_id,
			'object_name' => $post->post_title ?: '(no title)',
		] );
	}

	public static function on_post_deleted( int $post_id, WP_Post $post ): void {
		if ( in_array( $post->post_type, self::IGNORED_POST_TYPES, true ) ) {
			return;
		}

		$actor = wp_get_current_user();
		if ( ! $actor || ! $actor->ID ) {
			return;
		}

		self::log( [
			'user_id'     => $actor->ID,
			'username'    => $actor->user_login,
			'action'      => 'post_deleted',
			'object_type' => $post->post_type,
			'object_id'   => $post_id,
			'object_name' => $post->post_title ?: '(no title)',
		] );
	}

	// -------------------------------------------------------------------------
	// Media events
	// -------------------------------------------------------------------------

	public static function on_attachment_added( int $post_id ): void {
		$actor = wp_get_current_user();
		if ( ! $actor || ! $actor->ID ) {
			return;
		}

		$post = get_post( $post_id );

		self::log( [
			'user_id'     => $actor->ID,
			'username'    => $actor->user_login,
			'action'      => 'media_uploaded',
			'object_type' => 'attachment',
			'object_id'   => $post_id,
			'object_name' => $post ? ( $post->post_title ?: basename( get_attached_file( $post_id ) ) ) : "Attachment #{$post_id}",
		] );
	}

	public static function on_attachment_deleted( int $post_id ): void {
		$actor = wp_get_current_user();
		if ( ! $actor || ! $actor->ID ) {
			return;
		}

		$post = get_post( $post_id );

		self::log( [
			'user_id'     => $actor->ID,
			'username'    => $actor->user_login,
			'action'      => 'media_deleted',
			'object_type' => 'attachment',
			'object_id'   => $post_id,
			'object_name' => $post ? $post->post_title : "Attachment #{$post_id}",
		] );
	}

	// -------------------------------------------------------------------------
	// Comment events
	// -------------------------------------------------------------------------

	public static function on_comment_posted( int $comment_id, int $comment_approved, array $data ): void {
		$actor    = wp_get_current_user();
		$user_id  = $actor && $actor->ID ? $actor->ID : 0;
		$username = $actor && $actor->ID ? $actor->user_login : ( $data['comment_author'] ?? 'Guest' );

		self::log( [
			'user_id'     => $user_id,
			'username'    => $username,
			'action'      => 'comment_posted',
			'object_type' => 'comment',
			'object_id'   => $comment_id,
			'object_name' => substr( $data['comment_content'] ?? '', 0, 80 ),
		] );
	}

	public static function on_comment_trashed( string $comment_id, WP_Comment $comment ): void {
		$actor = wp_get_current_user();
		if ( ! $actor || ! $actor->ID ) {
			return;
		}

		self::log( [
			'user_id'     => $actor->ID,
			'username'    => $actor->user_login,
			'action'      => 'comment_trashed',
			'object_type' => 'comment',
			'object_id'   => (int) $comment_id,
			'object_name' => substr( $comment->comment_content, 0, 80 ),
		] );
	}

	public static function on_comment_spammed( string $comment_id, WP_Comment $comment ): void {
		$actor = wp_get_current_user();
		if ( ! $actor || ! $actor->ID ) {
			return;
		}

		self::log( [
			'user_id'     => $actor->ID,
			'username'    => $actor->user_login,
			'action'      => 'comment_spammed',
			'object_type' => 'comment',
			'object_id'   => (int) $comment_id,
			'object_name' => substr( $comment->comment_content, 0, 80 ),
		] );
	}

	public static function on_comment_unspammed( string $comment_id, WP_Comment $comment ): void {
		$actor = wp_get_current_user();
		if ( ! $actor || ! $actor->ID ) {
			return;
		}

		self::log( [
			'user_id'     => $actor->ID,
			'username'    => $actor->user_login,
			'action'      => 'comment_unspammed',
			'object_type' => 'comment',
			'object_id'   => (int) $comment_id,
			'object_name' => substr( $comment->comment_content, 0, 80 ),
		] );
	}

	// -------------------------------------------------------------------------
	// Plugin events
	// -------------------------------------------------------------------------

	public static function on_plugin_activated( string $plugin ): void {
		$actor = wp_get_current_user();
		if ( ! $actor || ! $actor->ID ) {
			return;
		}

		self::log( [
			'user_id'     => $actor->ID,
			'username'    => $actor->user_login,
			'action'      => 'plugin_activated',
			'object_type' => 'plugin',
			'object_id'   => 0,
			'object_name' => $plugin,
		] );
	}

	public static function on_plugin_deactivated( string $plugin ): void {
		$actor = wp_get_current_user();
		if ( ! $actor || ! $actor->ID ) {
			return;
		}

		self::log( [
			'user_id'     => $actor->ID,
			'username'    => $actor->user_login,
			'action'      => 'plugin_deactivated',
			'object_type' => 'plugin',
			'object_id'   => 0,
			'object_name' => $plugin,
		] );
	}

	// -------------------------------------------------------------------------
	// Settings events
	// -------------------------------------------------------------------------

	/**
	 * Tracks option changes made via the WP admin settings pages.
	 * Uses a static set to deduplicate multiple fires for the same option in one request.
	 */
	public static function on_option_updated( string $option, $old_value, $new_value ): void {
		// Only log admin-side changes
		if ( ! is_admin() ) {
			return;
		}

		// Skip internal/noisy options
		foreach ( self::IGNORED_OPTION_PREFIXES as $prefix ) {
			if ( str_starts_with( $option, $prefix ) ) {
				return;
			}
		}

		// Skip if nothing actually changed
		// phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
		if ( $old_value == $new_value ) {
			return;
		}

		$actor = wp_get_current_user();
		if ( ! $actor || ! $actor->ID ) {
			return;
		}

		// Deduplicate: only log each option once per PHP request
		static $logged = [];
		if ( isset( $logged[ $option ] ) ) {
			return;
		}
		$logged[ $option ] = true;

		self::log( [
			'user_id'     => $actor->ID,
			'username'    => $actor->user_login,
			'action'      => 'option_updated',
			'object_type' => 'option',
			'object_id'   => 0,
			'object_name' => $option,
		] );
	}

	// -------------------------------------------------------------------------
	// Core log writer
	// -------------------------------------------------------------------------

	/**
	 * Insert a single event row into the activity log table.
	 *
	 * @param array{
	 *   user_id?: int,
	 *   username?: string,
	 *   action: string,
	 *   object_type?: string,
	 *   object_id?: int,
	 *   object_name?: string,
	 *   ip_address?: string,
	 *   created_at?: string
	 * } $data
	 */
	public static function log( array $data ): bool {
		global $wpdb;

		$row = array_merge(
			[
				'user_id'     => 0,
				'username'    => '',
				'action'      => '',
				'object_type' => '',
				'object_id'   => 0,
				'object_name' => '',
				'ip_address'  => self::client_ip(),
				'created_at'  => gmdate( 'Y-m-d H:i:s' ), // always UTC
			],
			$data
		);

		// Sanitize
		$row['username']    = sanitize_user( (string) $row['username'] );
		$row['action']      = sanitize_key( (string) $row['action'] );
		$row['object_type'] = sanitize_key( (string) $row['object_type'] );
		$row['object_name'] = sanitize_text_field( (string) $row['object_name'] );
		$row['ip_address']  = sanitize_text_field( (string) $row['ip_address'] );

		$result = $wpdb->insert(
			$wpdb->prefix . IAL_TABLE_NAME,
			$row,
			[ '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s' ]
		);

		return false !== $result;
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private static function client_ip(): string {
		$candidates = [
			'HTTP_CF_CONNECTING_IP',  // Cloudflare
			'HTTP_X_FORWARDED_FOR',
			'HTTP_CLIENT_IP',
			'REMOTE_ADDR',
		];

		foreach ( $candidates as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = trim( explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) )[0] );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '';
	}
}
