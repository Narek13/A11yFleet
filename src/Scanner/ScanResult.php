<?php

declare(strict_types=1);

namespace A11yFleet\Scanner;

/**
 * The outcome of scanning one piece of content: the issues found plus a
 * derived 0–100 score (100 = no automatable issues detected).
 */
final class ScanResult {

	/**
	 * Constructor.
	 *
	 * @param int         $objectId   Post or object ID that was scanned.
	 * @param string      $objectType Post type or object type, e.g. 'post'.
	 * @param string      $url        Canonical URL of the scanned object.
	 * @param list<Issue> $issues    Issues found during the scan.
	 * @param int         $scannedAt  Unix timestamp of the scan.
	 */
	public function __construct(
		public readonly int $objectId,
		public readonly string $objectType,
		public readonly string $url,
		public readonly array $issues,
		public readonly int $scannedAt,
	) {
	}

	/**
	 * Calculates a 0–100 score for this scan (higher is better).
	 *
	 * @return int
	 */
	public function score(): int {
		$penalty = 0;
		foreach ( $this->issues as $issue ) {
			$penalty += $issue->severity->weight();
		}

		return (int) max( 0, 100 - $penalty );
	}

	/**
	 * Returns the total number of issues found.
	 *
	 * @return int
	 */
	public function issueCount(): int {
		return count( $this->issues );
	}

	/**
	 * Returns the number of issues at the given severity level.
	 *
	 * @param Severity $severity Severity to count.
	 * @return int
	 */
	public function countBySeverity( Severity $severity ): int {
		return count(
			array_filter(
				$this->issues,
				static fn ( Issue $i ): bool => $i->severity === $severity,
			)
		);
	}
}
