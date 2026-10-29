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
 * WCAG 3.1.1 Language of Page: the <html> element must declare a lang.
 *
 * This is a page-level check: it only runs when scanning a full rendered page
 * (context scope = "page"), never on content fragments — where libxml would
 * synthesise an <html> wrapper and produce a false positive.
 */
final class DocumentLanguageRule implements Rule {

	/**
	 * Returns the stable rule identifier.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'document-language';
	}

	/**
	 * Returns the WCAG success criterion this rule maps to.
	 *
	 * @return string
	 */
	public function wcag(): string {
		return '3.1.1';
	}

	/**
	 * Evaluates the document language, only for full-page scans.
	 *
	 * @param DOMDocument          $dom     Parsed document.
	 * @param DOMXPath             $xpath   XPath helper for the document.
	 * @param array<string, mixed> $context Optional scan context.
	 * @return list<Issue>
	 */
	public function evaluate( DOMDocument $dom, DOMXPath $xpath, array $context = array() ): array {
		if ( 'page' !== ( $context['scope'] ?? '' ) ) {
			return array();
		}

		$issues = array();

		foreach ( $xpath->query( '//html' ) as $html ) {
			if ( ! $html instanceof DOMElement ) {
				continue;
			}
			if ( '' !== trim( $html->getAttribute( 'lang' ) ) ) {
				continue;
			}
			$issues[] = new Issue(
				ruleId: $this->id(),
				wcag: $this->wcag(),
				severity: Severity::Error,
				message: __( 'The page <html> element has no lang attribute.', 'a11yfleet' ),
				context: '<html>',
				howToFix: __( 'Set the document language, e.g. <html lang="en">. In WordPress, ensure the theme outputs language_attributes() on the <html> tag.', 'a11yfleet' ),
			);
		}

		return $issues;
	}
}
