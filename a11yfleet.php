<?php
/**
 * Plugin Name:       A11yFleet
 * Description:       Accessibility compliance scanning, reporting and guided remediation for WordPress — built for EU (EAA / EN 301 549 / WCAG 2.1 AA) compliance.
 * Version:           1.0.1
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Narek Zakarian
 * Author URI:        https://profile.wordpress.org/nareknovembit
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       a11yfleet
 * Domain Path:       /languages
 *
 * @package A11yFleet
 */

declare(strict_types=1);

// No direct access.
defined( 'ABSPATH' ) || exit;

if ( defined( 'A11YFLEET_VERSION' ) ) {
	return;
}

define( 'A11YFLEET_VERSION', '1.0.1' );
define( 'A11YFLEET_FILE', __FILE__ );
define( 'A11YFLEET_DIR', plugin_dir_path( __FILE__ ) );
define( 'A11YFLEET_URL', plugin_dir_url( __FILE__ ) );

/*
 * Autoloading.
 *
 * Prefer Composer's autoloader if the plugin has been built (vendor/ present).
 * Otherwise fall back to a tiny PSR-4 autoloader so the plugin runs as soon as
 * it's dropped into wp-content/plugins, no build step required.
 */
if ( is_readable( A11YFLEET_DIR . 'vendor/autoload.php' ) ) {
	require A11YFLEET_DIR . 'vendor/autoload.php';
} else {
	spl_autoload_register(
		static function ( string $class_name ): void {
			$prefix = 'A11yFleet\\';
			if ( ! str_starts_with( $class_name, $prefix ) ) {
				return;
			}
			$relative = substr( $class_name, strlen( $prefix ) );
			$path     = A11YFLEET_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';
			if ( is_readable( $path ) ) {
				require $path;
			}
		}
	);
}

use A11yFleet\Activation\Activator;
use A11yFleet\Activation\Deactivator;
use A11yFleet\Plugin;

register_activation_hook( __FILE__, array( Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Deactivator::class, 'deactivate' ) );

// Boot once WordPress and all plugins are loaded.
add_action(
	'plugins_loaded',
	static function (): void {
		Plugin::instance()->boot();
	}
);
