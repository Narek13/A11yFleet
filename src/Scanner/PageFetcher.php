<?php

declare(strict_types=1);

namespace A11yFleet\Scanner;

/**
 * Fetches the fully-rendered HTML of a URL via the WordPress HTTP API, so the
 * scanner can inspect the whole page (theme header, navigation, footer,
 * widgets) — not just post content.
 */
final class PageFetcher {

	/**
	 * Fetches the rendered HTML for a URL.
	 *
	 * @param string $url Absolute URL to fetch.
	 * @return string|null HTML body, or null on failure / non-200 response.
	 */
	public function fetch( string $url ): ?string {
		if ( '' === trim( $url ) ) {
			return null;
		}

		/**
		 * Filters the wp_remote_get arguments used for page scanning.
		 *
		 * @param array<string, mixed> $args Request arguments.
		 * @param string               $url  URL being fetched.
		 */
		$args = apply_filters(
			'a11yfleet_page_fetch_args',
			array(
				'timeout'     => 15,
				'redirection' => 3,
				// Local/staging sites often use self-signed certificates.
				'sslverify'   => false,
				'headers'     => array( 'Accept' => 'text/html' ),
				'user-agent'  => 'A11yFleet/' . A11YFLEET_VERSION,
			),
			$url
		);

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			return null;
		}
		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$body = wp_remote_retrieve_body( $response );

		return '' !== $body ? $body : null;
	}
}
