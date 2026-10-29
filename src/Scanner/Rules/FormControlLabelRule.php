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
 * WCAG 1.3.1 / 4.1.2: form controls must have an accessible name.
 *
 * Every text-entry control (input, select, textarea) needs a programmatic
 * label via a <label for>, a wrapping <label>, aria-label, aria-labelledby or
 * title. Buttons and hidden/submit/reset/image inputs are handled elsewhere.
 */
final class FormControlLabelRule implements Rule {

	private const EXEMPT_INPUT_TYPES = array( 'hidden', 'submit', 'button', 'reset', 'image' );

	/**
	 * Returns the stable rule identifier.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'form-control-label';
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
	 * Evaluates form controls and flags any without an accessible name.
	 *
	 * @param DOMDocument          $dom     Parsed document.
	 * @param DOMXPath             $xpath   XPath helper for the document.
	 * @param array<string, mixed> $context Optional scan context.
	 * @return list<Issue>
	 */
	public function evaluate( DOMDocument $dom, DOMXPath $xpath, array $context = array() ): array {
		$issues   = array();
		$labelled = $this->labelledIds( $xpath );

		foreach ( $xpath->query( '//input | //select | //textarea' ) as $control ) {
			if ( ! $control instanceof DOMElement ) {
				continue;
			}

			if ( 'input' === strtolower( $control->nodeName ) && $this->isExemptInput( $control ) ) {
				continue;
			}

			if ( $this->hasAccessibleName( $control, $xpath, $labelled ) ) {
				continue;
			}

			$issues[] = new Issue(
				ruleId: $this->id(),
				wcag: $this->wcag(),
				severity: Severity::Error,
				message: __( 'Form control has no associated label.', 'a11yfleet' ),
				context: $this->snippet( $control ),
				howToFix: __( 'Add a <label for="…"> tied to the field by id, wrap the field in a <label>, or add an aria-label / aria-labelledby.', 'a11yfleet' ),
			);
		}

		return $issues;
	}

	/**
	 * Collects the set of ids referenced by <label for="…"> elements.
	 *
	 * @param DOMXPath $xpath XPath helper for the document.
	 * @return array<string, true>
	 */
	private function labelledIds( DOMXPath $xpath ): array {
		$ids = array();

		foreach ( $xpath->query( '//label[@for]' ) as $label ) {
			if ( ! $label instanceof DOMElement ) {
				continue;
			}
			$for = trim( $label->getAttribute( 'for' ) );
			if ( '' !== $for ) {
				$ids[ $for ] = true;
			}
		}

		return $ids;
	}

	/**
	 * Determines whether an input type is exempt from labelling here.
	 *
	 * @param DOMElement $input Input element to inspect.
	 * @return bool
	 */
	private function isExemptInput( DOMElement $input ): bool {
		$type = strtolower( trim( $input->getAttribute( 'type' ) ) );

		return in_array( $type, self::EXEMPT_INPUT_TYPES, true );
	}

	/**
	 * Determines whether a control has any accessible name.
	 *
	 * @param DOMElement          $control  Form control element.
	 * @param DOMXPath            $xpath    XPath helper for the document.
	 * @param array<string, true> $labelled Set of ids referenced by labels.
	 * @return bool
	 */
	private function hasAccessibleName( DOMElement $control, DOMXPath $xpath, array $labelled ): bool {
		if ( '' !== trim( $control->getAttribute( 'aria-label' ) ) ) {
			return true;
		}
		if ( '' !== trim( $control->getAttribute( 'aria-labelledby' ) ) ) {
			return true;
		}
		if ( '' !== trim( $control->getAttribute( 'title' ) ) ) {
			return true;
		}

		$id = trim( $control->getAttribute( 'id' ) );
		if ( '' !== $id && isset( $labelled[ $id ] ) ) {
			return true;
		}

		$ancestorLabels = $xpath->query( 'ancestor::label', $control );

		return $ancestorLabels instanceof \DOMNodeList && $ancestorLabels->length > 0;
	}

	/**
	 * Returns a short HTML snippet representing the control for reports.
	 *
	 * @param DOMElement $control Form control element.
	 * @return string
	 */
	private function snippet( DOMElement $control ): string {
		$name = $control->getAttribute( 'name' );

		return '<' . strtolower( $control->nodeName ) . ( '' !== $name ? ' name="' . $name . '"' : '' ) . '>';
	}
}
