<?php

declare(strict_types=1);

namespace A11yFleet\Scanner;

/**
 * Issue severity. "error" = a clear WCAG failure; "warning" = likely problem
 * needing human review; "notice" = advisory / manual-check reminder.
 */
enum Severity: string {

	case Error   = 'error';
	case Warning = 'warning';
	case Notice  = 'notice';

	/**
	 * Returns the penalty weight used when computing a page score (higher = worse).
	 *
	 * @return int
	 */
	public function weight(): int {
		return match ( $this ) {
			self::Error => 10,
			self::Warning => 4,
			self::Notice => 1,
		};
	}
}
