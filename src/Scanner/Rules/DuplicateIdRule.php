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
 * WCAG 4.1.1 Parsing: id attributes must be unique within a document.
 *
 * Duplicate ids break label/control associations, in-page anchors and ARIA
 * relationships (aria-labelledby / aria-describedby).
 */
final class DuplicateIdRule implements Rule {

	/**
	 * Returns the stable rule identifier.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'duplicate-id';
	}

	/**
	 * Returns the WCAG success criterion this rule maps to.
	 *
	 * @return string
	 */
	public function wcag(): string {
		return '4.1.1';
	}

	/**
	 * Evaluates the document and flags each id value used more than once.
	 *
	 * @param DOMDocument          $dom     Parsed document.
	 * @param DOMXPath             $xpath   XPath helper for the document.
	 * @param array<string, mixed> $context Optional scan context.
	 * @return list<Issue>
	 */
	public function evaluate( DOMDocument $dom, DOMXPath $xpath, array $context = array() ): array {
		$issues = array();
		$counts = array();

		foreach ( $xpath->query( '//*[@id]' ) as $element ) {
			if ( ! $element instanceof DOMElement ) {
				continue;
			}
			$id = trim( $element->getAttribute( 'id' ) );
			if ( '' === $id ) {
				continue;
			}
			$counts[ $id ] = ( $counts[ $id ] ?? 0 ) + 1;
		}

		foreach ( $counts as $id => $count ) {
			if ( $count < 2 ) {
				continue;
			}
			$issues[] = new Issue(
				ruleId: $this->id(),
				wcag: $this->wcag(),
				severity: Severity::Warning,
				message: sprintf(
					/* translators: 1: the id value, 2: number of times it is used. */
					__( 'The id "%1$s" is used %2$d times; ids must be unique.', 'a11yfleet' ),
					$id,
					$count
				),
				context: '#' . $id,
				howToFix: __( 'Give each element a unique id, or use classes for shared styling/behaviour.', 'a11yfleet' ),
			);
		}

		return $issues;
	}
}
