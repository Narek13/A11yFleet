<?php

declare(strict_types=1);

namespace A11yFleet\Scanner;

use DOMDocument;
use DOMXPath;

/**
 * Runs the registered rules over a piece of HTML and collects their issues.
 * Parses the document once and shares it with every rule.
 */
final class Engine {

	/**
	 * Registered rules.
	 *
	 * @var list<Rule>
	 */
	private array $rules;

	/**
	 * Constructor.
	 *
	 * @param list<Rule> $rules Rules to run on each scan.
	 */
	public function __construct( array $rules ) {
		$this->rules = $rules;
	}

	/**
	 * Parses the given HTML and runs all registered rules against it.
	 *
	 * @param string               $html    HTML to scan.
	 * @param array<string, mixed> $context Optional context passed to each rule.
	 * @return list<Issue>
	 */
	public function run( string $html, array $context = array() ): array {
		if ( '' === trim( $html ) ) {
			return array();
		}

		$dom   = $this->loadDom( $html );
		$xpath = new DOMXPath( $dom );

		$issues = array();
		foreach ( $this->rules as $rule ) {
			foreach ( $rule->evaluate( $dom, $xpath, $context ) as $issue ) {
				$issues[] = $issue;
			}
		}

		return $issues;
	}

	/**
	 * Parses an HTML string into a DOMDocument, suppressing libxml warnings.
	 *
	 * @param string $html Raw HTML fragment.
	 * @return DOMDocument
	 */
	private function loadDom( string $html ): DOMDocument {
		$dom = new DOMDocument();

		// Force UTF-8 interpretation via an XML prolog. For a full document
		// (contains <html>) we load it as-is so <html lang> is preserved; a
		// fragment is wrapped in a known root.
		$is_document = 1 === preg_match( '/<html[\s>]/i', $html );
		$markup      = $is_document
			? '<?xml encoding="utf-8" ?>' . $html
			: '<?xml encoding="utf-8" ?><div id="a11yfleet-root">' . $html . '</div>';

		// Suppress libxml's noisy warnings about HTML5 tags it doesn't recognise.
		$previous = libxml_use_internal_errors( true );
		$dom->loadHTML( $markup, LIBXML_NOERROR | LIBXML_NOWARNING );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		return $dom;
	}
}
