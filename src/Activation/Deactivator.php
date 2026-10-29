<?php

declare(strict_types=1);

namespace A11yFleet\Activation;

/**
 * Runs on deactivation. We intentionally keep data on deactivate (users often
 * toggle plugins) — destructive cleanup lives in uninstall.php instead.
 */
final class Deactivator {

	/**
	 * Clears any scheduled hooks registered by the plugin.
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'a11yfleet_scheduled_scan' );
		wp_clear_scheduled_hook( 'a11yfleet_process_batch' );
	}
}
