<?php

declare(strict_types=1);

namespace A11yFleet\Scanner;

use DOMDocument;
use DOMXPath;

/**
 * Contract for an accessibility rule. The engine parses the HTML once and
 * hands each rule a shared DOMDocument + DOMXPath, so rules stay cheap.
 *
 * Rules must return only automatable, defensible findings — when in doubt,
 * emit a Severity::Notice asking for human review rather than a false Error.
 */
interface Rule {

	/**
	 * Returns the stable rule identifier, e.g. "image-alt".
	 *
	 * @return string
	 */
	public function id(): string;

	/**
	 * Returns the WCAG success criterion this rule maps to, e.g. "1.1.1".
	 *
	 * @return string
	 */
	public function wcag(): string;

	/**
	 * Evaluates the document and returns any issues found.
	 *
	 * @param DOMDocument          $dom     Parsed document.
	 * @param DOMXPath             $xpath   XPath helper for the document.
	 * @param array<string, mixed> $context Optional scan context (post ID, type, etc.).
	 * @return list<Issue>
	 */
	public function evaluate( DOMDocument $dom, DOMXPath $xpath, array $context = array() ): array;
}
