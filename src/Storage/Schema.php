<?php

declare(strict_types=1);

namespace A11yFleet\Storage;

/**
 * Custom database tables.
 *
 * Scan data is high-volume and queried by severity/score/date, so it lives in
 * dedicated, indexed tables — not postmeta. Designed to roll up to an account
 * later (the agency "fleet" tier) without a schema rewrite.
 */
final class Schema {

	public const DB_VERSION = '1';

	/**
	 * Returns the fully qualified scans table name.
	 *
	 * @return string
	 */
	public static function scansTable(): string {
		global $wpdb;

		return $wpdb->prefix . 'a11yfleet_scans';
	}

	/**
	 * Returns the fully qualified issues table name.
	 *
	 * @return string
	 */
	public static function issuesTable(): string {
		global $wpdb;

		return $wpdb->prefix . 'a11yfleet_issues';
	}

	/**
	 * Creates or upgrades the plugin's custom tables via dbDelta.
	 */
	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$scans   = self::scansTable();
		$issues  = self::issuesTable();

		$sql = array();

		$sql[] = "CREATE TABLE {$scans} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            object_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            object_type VARCHAR(20) NOT NULL DEFAULT 'post',
            url VARCHAR(255) NOT NULL DEFAULT '',
            score TINYINT UNSIGNED NOT NULL DEFAULT 0,
            issue_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            error_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            scanned_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY object (object_id, object_type),
            KEY scanned_at (scanned_at),
            KEY score (score)
        ) {$charset};";

		$sql[] = "CREATE TABLE {$issues} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            scan_id BIGINT UNSIGNED NOT NULL,
            rule_id VARCHAR(64) NOT NULL,
            wcag VARCHAR(16) NOT NULL DEFAULT '',
            severity VARCHAR(10) NOT NULL DEFAULT 'error',
            message TEXT NOT NULL,
            context TEXT NULL,
            status VARCHAR(12) NOT NULL DEFAULT 'open',
            PRIMARY KEY (id),
            KEY scan_id (scan_id),
            KEY severity (severity),
            KEY status (status)
        ) {$charset};";

		// dbDelta handles create-or-upgrade.
		dbDelta( $sql );
	}
}
