<?php

declare(strict_types=1);

namespace A11yFleet\Admin;

/**
 * Enqueues admin CSS/JS, only on the plugin's own screen.
 *
 * Assets are built from src/Admin/resources/ by `npm run build` (wp-scripts +
 * webpack) into assets/. The generated index.asset.php carries the dependency
 * list and a content hash for cache-busting. Everything is guarded with
 * file_exists so an unbuilt checkout degrades gracefully instead of 404-ing.
 */
final class Assets {

	/**
	 * Hooks the enqueue callback into WordPress.
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueueEditor' ) );
	}

	/**
	 * Enqueues plugin assets when on the plugin's admin screen.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function enqueue( string $hook ): void {
		if ( 'toplevel_page_a11yfleet' !== $hook ) {
			return;
		}

		$jsFile  = A11YFLEET_DIR . 'assets/js/index.js';
		$cssFile = A11YFLEET_DIR . 'assets/css/index.css';

		if ( is_readable( $cssFile ) ) {
			wp_enqueue_style(
				'a11yfleet-admin',
				A11YFLEET_URL . 'assets/css/index.css',
				array(),
				(string) filemtime( $cssFile )
			);
		}

		if ( is_readable( $jsFile ) ) {
			$assetFile = A11YFLEET_DIR . 'assets/js/index.asset.php';
			$asset     = is_readable( $assetFile )
				? (array) require $assetFile
				: array(
					'dependencies' => array( 'wp-api-fetch' ),
					'version'      => A11YFLEET_VERSION,
				);

			wp_enqueue_script(
				'a11yfleet-admin',
				A11YFLEET_URL . 'assets/js/index.js',
				$asset['dependencies'] ?? array( 'wp-api-fetch' ),
				(string) ( $asset['version'] ?? A11YFLEET_VERSION ),
				true
			);

			// Expose REST root + nonce for the dashboard SPA / AJAX calls.
			wp_localize_script(
				'a11yfleet-admin',
				'A11yFleet',
				array(
					'restUrl' => esc_url_raw( rest_url( 'a11yfleet/v1' ) ),
					'nonce'   => wp_create_nonce( 'wp_rest' ),
				)
			);
		}
	}

	/**
	 * Enqueues the block-editor accessibility panel.
	 *
	 * Runs on enqueue_block_editor_assets. apiFetch is already configured with
	 * the REST nonce inside the editor, so no extra localisation is needed.
	 */
	public function enqueueEditor(): void {
		$jsFile = A11YFLEET_DIR . 'assets/js/editor.js';
		if ( ! is_readable( $jsFile ) ) {
			return;
		}

		$assetFile = A11YFLEET_DIR . 'assets/js/editor.asset.php';
		$asset     = is_readable( $assetFile )
			? (array) require $assetFile
			: array(
				'dependencies' => array(),
				'version'      => A11YFLEET_VERSION,
			);

		wp_enqueue_script(
			'a11yfleet-editor',
			A11YFLEET_URL . 'assets/js/editor.js',
			$asset['dependencies'] ?? array(),
			(string) ( $asset['version'] ?? A11YFLEET_VERSION ),
			true
		);
	}
}
