<?php
/**
 * Schema and role installation.
 *
 * @package LIDevTheme
 * @since   1.1.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates tables and roles. Re-running is safe.
 *
 * @since 1.1.0
 */
final class Lidev_Install {

	/**
	 * Run when the stored schema version is behind the code.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public static function maybe_run(): void {
		$stored = (string) get_option( 'lidev_schema_version', '' );

		if ( $stored === LIDEV_SCHEMA_VERSION ) {
			return;
		}

		self::run();
	}

	/**
	 * Create tables, sync roles, and store the schema version.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public static function run(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset  = $wpdb->get_charset_collate();
		$phases   = lidev_table( 'phases' );
		$signoffs = lidev_table( 'signoffs' );
		$activity = lidev_table( 'activity' );

		$sql = "CREATE TABLE {$phases} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			project_id bigint(20) unsigned NOT NULL,
			type varchar(20) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'not_sent',
			external_link text NULL,
			team_message longtext NULL,
			sent_at datetime NULL,
			sent_by bigint(20) unsigned NULL,
			last_reminder_at datetime NULL,
			reminder_count int(10) unsigned NOT NULL DEFAULT 0,
			signed_at datetime NULL,
			signoff_id bigint(20) unsigned NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY project_type (project_id,type),
			KEY status (status)
		) {$charset};
		CREATE TABLE {$signoffs} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			project_id bigint(20) unsigned NOT NULL,
			phase_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			signer_name varchar(191) NOT NULL DEFAULT '',
			signature_type varchar(20) NOT NULL DEFAULT '',
			signature_data longtext NULL,
			record_hash char(64) NOT NULL DEFAULT '',
			prev_hash char(64) NOT NULL DEFAULT '',
			ip_address varchar(45) NOT NULL DEFAULT '',
			user_agent varchar(255) NOT NULL DEFAULT '',
			signed_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY phase_id_unique (phase_id),
			KEY project_id (project_id)
		) {$charset};
		CREATE TABLE {$activity} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			actor_user_id bigint(20) unsigned NULL,
			actor_label varchar(191) NOT NULL DEFAULT '',
			action varchar(64) NOT NULL DEFAULT '',
			project_id bigint(20) unsigned NOT NULL DEFAULT 0,
			phase_id bigint(20) unsigned NULL,
			details longtext NULL,
			ip_address varchar(45) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY project_id (project_id),
			KEY created_at (created_at)
		) {$charset};";

		dbDelta( $sql );
		Lidev_Roles::sync();
		update_option( 'lidev_schema_version', LIDEV_SCHEMA_VERSION, true );
		flush_rewrite_rules( false );
	}
}
