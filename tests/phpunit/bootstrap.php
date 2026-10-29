<?php
/**
 * PHPUnit bootstrap. Unit tests use Brain Monkey to stub WordPress functions,
 * so no full WP install is required.
 *
 * @package A11yFleet
 */

declare(strict_types=1);

$a11yfleet_autoload = dirname( __DIR__, 2 ) . '/vendor/autoload.php';

if ( ! is_readable( $a11yfleet_autoload ) ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI bootstrap, no WP_Filesystem available.
	fwrite( STDERR, "Dependencies not installed. Run `composer install` first.\n" );
	exit( 1 );
}

require $a11yfleet_autoload;
