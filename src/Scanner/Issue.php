<?php

declare(strict_types=1);

namespace A11yFleet\Scanner;

/**
 * A single accessibility finding produced by a rule.
 */
final class Issue {

	/**
	 * Constructor.
	 *
	 * @param string   $ruleId    Stable rule identifier, e.g. "image-alt".
	 * @param string   $wcag      WCAG success criterion, e.g. "1.1.1".
	 * @param Severity $severity  Finding severity.
	 * @param string   $message   Human-readable, translated description.
	 * @param string   $context   Offending HTML snippet or selector.
	 * @param string   $howToFix  Remediation guidance.
	 */
	public function __construct(
		public readonly string $ruleId,
		public readonly string $wcag,
		public readonly Severity $severity,
		public readonly string $message,
		public readonly string $context = '',
		public readonly string $howToFix = '',
	) {
	}

	/**
	 * Serializes the issue to a plain array for REST / JSON output.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return array(
			'rule_id'    => $this->ruleId,
			'wcag'       => $this->wcag,
			'severity'   => $this->severity->value,
			'message'    => $this->message,
			'context'    => $this->context,
			'how_to_fix' => $this->howToFix,
		);
	}
}
