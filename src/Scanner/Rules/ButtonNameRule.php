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
 * WCAG 4.1.2 Name, Role, Value: buttons must expose an accessible name.
 *
 * Covers <button> elements (text, aria-label, title or an inner image alt) and
 * image inputs (<input type="image">), which need non-empty alt text because
 * they are functional controls.
 */
final class ButtonNameRule implements Rule {

	/**
	 * Returns the stable rule identifier.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'button-name';
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
	 * Evaluates buttons and image inputs for a missing accessible name.
	 *
	 * @param DOMDocument          $dom     Parsed document.
	 * @param DOMXPath             $xpath   XPath helper for the document.
	 * @param array<string, mixed> $context Optional scan context.
	 * @return list<Issue>
	 */
	public function evaluate( DOMDocument $dom, DOMXPath $xpath, array $context = array() ): array {
		$issues = array();

		foreach ( $xpath->query( '//button' ) as $button ) {
			if ( ! $button instanceof DOMElement ) {
				continue;
			}
			if ( 'true' === $button->getAttribute( 'aria-hidden' ) ) {
				continue;
			}
			if ( '' === $this->accessibleName( $button ) ) {
				$issues[] = new Issue(
					ruleId: $this->id(),
					wcag: $this->wcag(),
					severity: Severity::Error,
					message: __( 'Button has no discernible text.', 'a11yfleet' ),
					context: '<button>',
					howToFix: __( 'Add visible button text, or an aria-label, so its purpose is announced.', 'a11yfleet' ),
				);
			}
		}

		foreach ( $xpath->query( "//input[@type='image']" ) as $input ) {
			if ( ! $input instanceof DOMElement ) {
				continue;
			}
			if ( '' === trim( $input->getAttribute( 'alt' ) ) && '' === trim( $input->getAttribute( 'aria-label' ) ) ) {
				$issues[] = new Issue(
					ruleId: $this->id(),
					wcag: $this->wcag(),
					severity: Severity::Error,
					message: __( 'Image button is missing alt text.', 'a11yfleet' ),
					context: '<input type="image">',
					howToFix: __( 'Give the <input type="image"> a descriptive alt attribute.', 'a11yfleet' ),
				);
			}
		}

		return $issues;
	}

	/**
	 * Resolves the accessible name of a button element.
	 *
	 * @param DOMElement $button Button element to inspect.
	 * @return string Accessible name, or empty string if none found.
	 */
	private function accessibleName( DOMElement $button ): string {
		$aria = trim( $button->getAttribute( 'aria-label' ) );
		if ( '' !== $aria ) {
			return $aria;
		}

		$text = trim( $button->textContent );
		if ( '' !== $text ) {
			return $text;
		}

		$title = trim( $button->getAttribute( 'title' ) );
		if ( '' !== $title ) {
			return $title;
		}

		foreach ( $button->getElementsByTagName( 'img' ) as $img ) {
			$alt = trim( $img->getAttribute( 'alt' ) );
			if ( '' !== $alt ) {
				return $alt;
			}
		}

		return '';
	}
}
