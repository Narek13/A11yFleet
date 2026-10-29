<?php

declare(strict_types=1);

namespace A11yFleet\Settings;

/**
 * Plugin settings, stored in a single option array.
 *
 * Controls which post types are scanned, whether content is auto-scanned on
 * save, and how often a full site scan runs via WP-Cron.
 */
final class Settings {

	public const OPTION = 'a11yfleet_settings';

	/**
	 * Allowed cron intervals (WordPress core schedules + "off").
	 *
	 * @var list<string>
	 */
	public const CRON_OPTIONS = array( 'off', 'hourly', 'twicedaily', 'daily', 'weekly' );

	/**
	 * Default settings.
	 *
	 * @return array{post_types: list<string>, scan_on_save: bool, cron: string}
	 */
	public static function defaults(): array {
		return array(
			'post_types'   => array( 'post', 'page' ),
			'scan_on_save' => true,
			'cron'         => 'off',
		);
	}

	/**
	 * Returns the current settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function get(): array {
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return array_merge( self::defaults(), $saved );
	}

	/**
	 * Post types eligible for scanning (filterable).
	 *
	 * @return list<string>
	 */
	public function postTypes(): array {
		/**
		 * Filters the post types A11yFleet scans.
		 *
		 * @param list<string> $types Post type slugs.
		 */
		$types = apply_filters( 'a11yfleet_scannable_post_types', $this->get()['post_types'] );

		return array_values( array_filter( array_map( 'strval', (array) $types ) ) );
	}

	/**
	 * Whether to auto-scan content when it is saved.
	 *
	 * @return bool
	 */
	public function scanOnSave(): bool {
		return (bool) $this->get()['scan_on_save'];
	}

	/**
	 * The configured cron interval, or "off".
	 *
	 * @return string
	 */
	public function cron(): string {
		$cron = (string) $this->get()['cron'];

		return in_array( $cron, self::CRON_OPTIONS, true ) ? $cron : 'off';
	}

	/**
	 * Registers the setting with WordPress.
	 */
	public function register(): void {
		register_setting(
			'a11yfleet',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * Sanitizes submitted settings.
	 *
	 * @param mixed $input Raw submitted value.
	 * @return array{post_types: list<string>, scan_on_save: bool, cron: string}
	 */
	public function sanitize( $input ): array {
		$input = (array) $input;

		$valid_types = array_keys( get_post_types( array( 'public' => true ) ) );
		$types       = array();
		foreach ( (array) ( $input['post_types'] ?? array() ) as $type ) {
			$type = sanitize_key( (string) $type );
			if ( in_array( $type, $valid_types, true ) ) {
				$types[] = $type;
			}
		}
		if ( empty( $types ) ) {
			$types = array( 'post', 'page' );
		}

		$cron = (string) ( $input['cron'] ?? 'off' );
		if ( ! in_array( $cron, self::CRON_OPTIONS, true ) ) {
			$cron = 'off';
		}

		return array(
			'post_types'   => $types,
			'scan_on_save' => ! empty( $input['scan_on_save'] ),
			'cron'         => $cron,
		);
	}
}
