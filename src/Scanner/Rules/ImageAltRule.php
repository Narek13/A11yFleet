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
 * WCAG 1.1.1 Non-text Content: every <img> needs an alt attribute.
 *
 * A missing alt is an error. An explicitly empty alt (alt="") is valid for
 * decorative images, so we do NOT flag it.
 */
final class ImageAltRule implements Rule {

	/**
	 * Returns the stable rule identifier.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'image-alt';
	}

	/**
	 * Returns the WCAG success criterion this rule maps to.
	 *
	 * @return string
	 */
	public function wcag(): string {
		return '1.1.1';
	}

	/**
	 * Evaluates img elements and returns issues for those missing an alt attribute.
	 *
	 * @param DOMDocument          $dom     Parsed document.
	 * @param DOMXPath             $xpath   XPath helper for the document.
	 * @param array<string, mixed> $context Optional scan context.
	 * @return list<Issue>
	 */
	public function evaluate( DOMDocument $dom, DOMXPath $xpath, array $context = array() ): array {
		$issues = array();

		foreach ( $xpath->query( '//img' ) as $img ) {
			if ( ! $img instanceof DOMElement ) {
				continue;
			}

			// Images marked aria-hidden or role=presentation are exempt.
			if ( 'true' === $img->getAttribute( 'aria-hidden' )
				|| 'presentation' === $img->getAttribute( 'role' )
			) {
				continue;
			}

			if ( ! $img->hasAttribute( 'alt' ) ) {
				$src      = $img->getAttribute( 'src' );
				$issues[] = new Issue(
					ruleId: $this->id(),
					wcag: $this->wcag(),
					severity: Severity::Error,
					message: __( 'Image is missing an alt attribute.', 'a11yfleet' ),
					context: '' !== $src ? '<img src="' . $src . '">' : '<img>',
					howToFix: __( 'Add a descriptive alt attribute. For purely decorative images, use an empty alt="".', 'a11yfleet' ),
				);
			}
		}

		return $issues;
	}
}
