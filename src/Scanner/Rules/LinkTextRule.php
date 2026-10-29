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
 * WCAG 2.4.4 Link Purpose: links must have meaningful text.
 *
 * - A link with no accessible name at all is an error.
 * - A link whose text is generic ("click here", "read more") is a warning,
 *   because out of context it tells a screen-reader user nothing.
 */
final class LinkTextRule implements Rule {

	private const GENERIC = array( 'click here', 'read more', 'here', 'more', 'link', 'this', 'details' );

	/**
	 * Returns the stable rule identifier.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'link-text';
	}

	/**
	 * Returns the WCAG success criterion this rule maps to.
	 *
	 * @return string
	 */
	public function wcag(): string {
		return '2.4.4';
	}

	/**
	 * Evaluates anchor elements and returns issues for empty or generic link text.
	 *
	 * @param DOMDocument          $dom     Parsed document.
	 * @param DOMXPath             $xpath   XPath helper for the document.
	 * @param array<string, mixed> $context Optional scan context.
	 * @return list<Issue>
	 */
	public function evaluate( DOMDocument $dom, DOMXPath $xpath, array $context = array() ): array {
		$issues = array();

		foreach ( $xpath->query( '//a[@href]' ) as $link ) {
			if ( ! $link instanceof DOMElement ) {
				continue;
			}

			$name = $this->accessibleName( $link );

			if ( '' === $name ) {
				$issues[] = new Issue(
					ruleId: $this->id(),
					wcag: $this->wcag(),
					severity: Severity::Error,
					message: __( 'Link has no discernible text.', 'a11yfleet' ),
					context: $this->snippet( $link ),
					howToFix: __( 'Add visible link text, or an aria-label, so the link\'s purpose is clear.', 'a11yfleet' ),
				);
				continue;
			}

			if ( in_array( strtolower( $name ), self::GENERIC, true ) ) {
				$issues[] = new Issue(
					ruleId: $this->id(),
					wcag: $this->wcag(),
					severity: Severity::Warning,
					message: sprintf(
						/* translators: %s: the generic link text found. */
						__( 'Link text "%s" is not descriptive on its own.', 'a11yfleet' ),
						$name
					),
					context: $this->snippet( $link ),
					howToFix: __( 'Use link text that describes the destination, e.g. "Read the 2026 report" instead of "read more".', 'a11yfleet' ),
				);
			}
		}

		return $issues;
	}

	/**
	 * Resolves the accessible name for a link element.
	 *
	 * @param DOMElement $link Anchor element to inspect.
	 * @return string Accessible name, or empty string if none found.
	 */
	private function accessibleName( DOMElement $link ): string {
		$aria = trim( $link->getAttribute( 'aria-label' ) );
		if ( '' !== $aria ) {
			return $aria;
		}

		$text = trim( $link->textContent );
		if ( '' !== $text ) {
			return $text;
		}

		// Image-only link: fall back to the inner image's alt text.
		foreach ( $link->getElementsByTagName( 'img' ) as $img ) {
			$alt = trim( $img->getAttribute( 'alt' ) );
			if ( '' !== $alt ) {
				return $alt;
			}
		}

		return '';
	}

	/**
	 * Returns a short HTML snippet representing the link for display in reports.
	 *
	 * @param DOMElement $link Anchor element to represent.
	 * @return string
	 */
	private function snippet( DOMElement $link ): string {
		$href = $link->getAttribute( 'href' );

		return '<a href="' . $href . '">' . trim( $link->textContent ) . '</a>';
	}
}
