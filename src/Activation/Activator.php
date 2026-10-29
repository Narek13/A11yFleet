<?php

declare(strict_types=1);

namespace A11yFleet\Activation;

use A11yFleet\Settings\Settings;
use A11yFleet\Storage\Schema;

/**
 * Runs on plugin activation: create/upgrade the custom tables and stamp the
 * installed DB version so we can run migrations on later upgrades.
 */
final class Activator {

	/**
	 * Creates tables, seeds default settings, and records the install timestamp.
	 */
	public static function activate(): void {
		Schema::install();

		add_option( 'a11yfleet_db_version', Schema::DB_VERSION );
		add_option( 'a11yfleet_installed_at', time() );
		add_option( Settings::OPTION, Settings::defaults() );
	}
}
