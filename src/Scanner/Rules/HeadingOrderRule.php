<?php

declare(strict_types=1);

namespace A11yFleet\Scanner\Rules;

use A11yFleet\Scanner\Issue;
use A11yFleet\Scanner\Rule;
use A11yFleet\Scanner\Severity;
use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * WCAG 1.3.1 Info and Relationships: heading levels should not skip (e.g. an
 * <h2> followed directly by an <h4>), which breaks the document outline for
 * assistive technology.
 */
final class HeadingOrderRule implements Rule {

	/**
	 * Returns the stable rule identifier.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'heading-order';
	}

	/**
	 * Returns the WCAG success criterion this rule maps to.
	 *
	 * @return string
	 */
	public function wcag(): string {
		return '1.3.1';
	}

	/**
	 * Evaluates heading order and returns issues for any skipped levels.
	 *
	 * @param DOMDocument          $dom     Parsed document.
	 * @param DOMXPath             $xpath   XPath helper for the document.
	 * @param array<string, mixed> $context Optional scan context.
	 * @return list<Issue>
	 */
	public function evaluate( DOMDocument $dom, DOMXPath $xpath, array $context = array() ): array {
		$issues        = array();
		$previousLevel = 0;

		foreach ( $xpath->query( '//h1|//h2|//h3|//h4|//h5|//h6' ) as $heading ) {
			$level = (int) substr( $heading->nodeName, 1 );

			if ( $previousLevel > 0 && $level > $previousLevel + 1 ) {
				$issues[] = new Issue(
					ruleId: $this->id(),
					wcag: $this->wcag(),
					severity: Severity::Warning,
					message: sprintf(
						/* translators: 1: previous heading level, 2: current heading level. */
						__( 'Heading level jumps from h%1$d to h%2$d, skipping a level.', 'a11yfleet' ),
						$previousLevel,
						$level
					),
					context: '<' . $heading->nodeName . '>' . trim( $heading->textContent ) . '</' . $heading->nodeName . '>',
					howToFix: __( 'Use heading levels in order (do not skip levels) so the document outline stays logical.', 'a11yfleet' ),
				);
			}

			$previousLevel = $level;
		}

		return $issues;
	}
}
