<?php

declare(strict_types=1);

namespace A11yFleet\Report;

/**
 * Human-readable titles for the WCAG success criteria the scanner reports,
 * used in the compliance report and accessibility statement.
 */
final class WcagCriteria {

	/**
	 * Map of criterion number => title (WCAG 2.1).
	 *
	 * @var array<string, string>
	 */
	private const TITLES = array(
		'1.1.1' => 'Non-text Content',
		'1.3.1' => 'Info and Relationships',
		'2.4.4' => 'Link Purpose (In Context)',
		'2.4.6' => 'Headings and Labels',
		'3.1.1' => 'Language of Page',
		'4.1.1' => 'Parsing',
		'4.1.2' => 'Name, Role, Value',
	);

	/**
	 * Returns the title for a criterion, or a generic fallback.
	 *
	 * @param string $wcag Criterion number, e.g. "1.1.1".
	 * @return string
	 */
	public static function title( string $wcag ): string {
		return self::TITLES[ $wcag ] ?? sprintf(
			/* translators: %s: WCAG success criterion number. */
			__( 'WCAG %s', 'a11yfleet' ),
			$wcag
		);
	}
}
