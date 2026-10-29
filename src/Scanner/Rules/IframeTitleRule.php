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
 * WCAG 4.1.2 Name, Role, Value: each <iframe> needs a title.
 *
 * Screen readers announce the title so users know what the embedded frame
 * contains. Frames explicitly hidden with aria-hidden="true" are exempt.
 */
final class IframeTitleRule implements Rule {

	/**
	 * Returns the stable rule identifier.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'iframe-title';
	}

	/**
	 * Returns the WCAG success criterion this rule maps to.
	 *
	 * @return string
	 */
	public function wcag(): string {
		return '4.1.2';
	}

	/**
	 * Evaluates iframe elements and flags any without a non-empty title.
	 *
	 * @param DOMDocument          $dom     Parsed document.
	 * @param DOMXPath             $xpath   XPath helper for the document.
	 * @param array<string, mixed> $context Optional scan context.
	 * @return list<Issue>
	 */
	public function evaluate( DOMDocument $dom, DOMXPath $xpath, array $context = array() ): array {
		$issues = array();

		foreach ( $xpath->query( '//iframe' ) as $iframe ) {
			if ( ! $iframe instanceof DOMElement ) {
				continue;
			}
			if ( 'true' === $iframe->getAttribute( 'aria-hidden' ) ) {
				continue;
			}
			if ( '' !== trim( $iframe->getAttribute( 'title' ) ) ) {
				continue;
			}

			$src      = $iframe->getAttribute( 'src' );
			$issues[] = new Issue(
				ruleId: $this->id(),
				wcag: $this->wcag(),
				severity: Severity::Error,
				message: __( 'iframe is missing a title attribute.', 'a11yfleet' ),
				context: '' !== $src ? '<iframe src="' . $src . '">' : '<iframe>',
				howToFix: __( 'Add a title attribute describing the embedded content, e.g. title="Location map".', 'a11yfleet' ),
			);
		}

		return $issues;
	}
}
