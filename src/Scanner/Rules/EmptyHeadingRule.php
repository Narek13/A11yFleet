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
 * WCAG 1.3.1 Info and Relationships: headings must not be empty.
 *
 * An empty heading breaks the document outline that assistive technology uses
 * to navigate. A heading containing only an image with alt text is acceptable.
 */
final class EmptyHeadingRule implements Rule {

	/**
	 * Returns the stable rule identifier.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'empty-heading';
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
	 * Evaluates heading elements and flags any with no text content.
	 *
	 * @param DOMDocument          $dom     Parsed document.
	 * @param DOMXPath             $xpath   XPath helper for the document.
	 * @param array<string, mixed> $context Optional scan context.
	 * @return list<Issue>
	 */
	public function evaluate( DOMDocument $dom, DOMXPath $xpath, array $context = array() ): array {
		$issues = array();

		foreach ( $xpath->query( '//h1|//h2|//h3|//h4|//h5|//h6' ) as $heading ) {
			if ( ! $heading instanceof DOMElement ) {
				continue;
			}
			if ( '' !== trim( $heading->textContent ) || $this->hasImageAlt( $heading ) ) {
				continue;
			}
			$issues[] = new Issue(
				ruleId: $this->id(),
				wcag: $this->wcag(),
				severity: Severity::Error,
				message: sprintf(
					/* translators: %s: the heading tag name, e.g. h2. */
					__( 'Heading <%s> is empty.', 'a11yfleet' ),
					strtolower( $heading->nodeName )
				),
				context: '<' . strtolower( $heading->nodeName ) . '></' . strtolower( $heading->nodeName ) . '>',
				howToFix: __( 'Add text to the heading, or remove the empty heading element.', 'a11yfleet' ),
			);
		}

		return $issues;
	}

	/**
	 * Determines whether a heading contains an image with non-empty alt text.
	 *
	 * @param DOMElement $heading Heading element to inspect.
	 * @return bool
	 */
	private function hasImageAlt( DOMElement $heading ): bool {
		foreach ( $heading->getElementsByTagName( 'img' ) as $img ) {
			if ( '' !== trim( $img->getAttribute( 'alt' ) ) ) {
				return true;
			}
		}

		return false;
	}
}
