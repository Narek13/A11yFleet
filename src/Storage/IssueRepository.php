<?php

declare(strict_types=1);

namespace A11yFleet\Storage;

use A11yFleet\Scanner\Issue;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

/**
 * Reads/writes issue rows belonging to a scan.
 */
final class IssueRepository {

	/**
	 * Valid issue workflow statuses.
	 *
	 * @var list<string>
	 */
	public const STATUSES = array( 'open', 'ignored', 'resolved' );

	/**
	 * Stable identifier for an issue across re-scans (rule + offending markup).
	 *
	 * @param string $ruleId  Rule identifier.
	 * @param string $context Offending HTML snippet / selector.
	 * @return string
	 */
	public static function fingerprint( string $ruleId, string $context ): string {
		return $ruleId . '|' . $context;
	}

	/**
	 * Inserts all issues for a scan in a single loop. Issues whose fingerprint
	 * is in $ignoredFingerprints are inserted as 'ignored' so a user's dismissal
	 * survives re-scans.
	 *
	 * @param int                 $scanId              Owning scan ID.
	 * @param list<Issue>         $issues              Issues to insert.
	 * @param array<string, true> $ignoredFingerprints Fingerprints to keep ignored.
	 */
	public function saveMany( int $scanId, array $issues, array $ignoredFingerprints = array() ): void {
		global $wpdb;

		$table = esc_sql( Schema::issuesTable() );

		foreach ( $issues as $issue ) {
			$status = isset( $ignoredFingerprints[ self::fingerprint( $issue->ruleId, $issue->context ) ] )
				? 'ignored'
				: 'open';

			$wpdb->insert(
				$table,
				array(
					'scan_id'  => $scanId,
					'rule_id'  => $issue->ruleId,
					'wcag'     => $issue->wcag,
					'severity' => $issue->severity->value,
					'message'  => $issue->message,
					'context'  => $issue->context,
					'status'   => $status,
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
			);
		}
	}

	/**
	 * Returns the fingerprints of issues the user has ignored for an object,
	 * so they can be re-applied after a re-scan.
	 *
	 * @param int    $objectId   Object ID (e.g. post ID).
	 * @param string $objectType Object type (e.g. 'post').
	 * @return array<string, true>
	 */
	public function ignoredFingerprintsForObject( int $objectId, string $objectType ): array {
		global $wpdb;

		$issuesTable = esc_sql( Schema::issuesTable() );
		$scansTable  = esc_sql( Schema::scansTable() );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT i.rule_id AS rule_id, i.context AS context
				 FROM {$issuesTable} i
				 INNER JOIN {$scansTable} s ON i.scan_id = s.id
				 WHERE s.object_id = %d AND s.object_type = %s AND i.status = 'ignored'",
				$objectId,
				$objectType
			),
			ARRAY_A
		);

		$set = array();
		foreach ( $rows ?? array() as $row ) {
			$set[ self::fingerprint( (string) $row['rule_id'], (string) ( $row['context'] ?? '' ) ) ] = true;
		}

		return $set;
	}

	/**
	 * Deletes all issue rows for a given scan.
	 *
	 * @param int $scanId Scan ID whose issues should be removed.
	 */
	public function deleteForScan( int $scanId ): void {
		global $wpdb;

		$wpdb->delete( Schema::issuesTable(), array( 'scan_id' => $scanId ), array( '%d' ) );
	}

	/**
	 * Returns all issues for a scan, ordered by severity then insertion order.
	 *
	 * @param int $scanId Scan ID to fetch issues for.
	 * @return list<array<string, mixed>>
	 */
	public function forScan( int $scanId ): array {
		global $wpdb;

		$table = esc_sql( Schema::issuesTable() );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE scan_id = %d ORDER BY FIELD(severity,'error','warning','notice'), id",
				$scanId
			),
			ARRAY_A
		);

		return $rows ?? array();
	}

	/**
	 * Updates the workflow status of a single issue.
	 *
	 * @param int    $issueId Issue row ID.
	 * @param string $status  New status: open | ignored | resolved.
	 * @return bool True if a row was updated.
	 */
	public function updateStatus( int $issueId, string $status ): bool {
		global $wpdb;

		if ( ! in_array( $status, self::STATUSES, true ) ) {
			return false;
		}

		$updated = $wpdb->update(
			Schema::issuesTable(),
			array( 'status' => $status ),
			array( 'id' => $issueId ),
			array( '%s' ),
			array( '%d' )
		);

		return is_int( $updated ) && $updated > 0;
	}

	/**
	 * Open-issue counts grouped by severity (site-wide).
	 *
	 * @return array{error:int, warning:int, notice:int}
	 */
	public function openCountsBySeverity(): array {
		global $wpdb;

		$table = esc_sql( Schema::issuesTable() );
		$rows  = $wpdb->get_results(
			"SELECT severity, COUNT(*) AS total FROM {$table} WHERE status = 'open' GROUP BY severity",
			ARRAY_A
		);

		$counts = array(
			'error'   => 0,
			'warning' => 0,
			'notice'  => 0,
		);
		foreach ( $rows ?? array() as $row ) {
			$severity = (string) $row['severity'];
			if ( isset( $counts[ $severity ] ) ) {
				$counts[ $severity ] = (int) $row['total'];
			}
		}

		return $counts;
	}

	/**
	 * Open issues aggregated per WCAG success criterion (for the report).
	 *
	 * @return list<array{wcag:string, issue_count:int, page_count:int, error_count:int, warning_count:int}>
	 */
	public function openSummaryByWcag(): array {
		global $wpdb;

		$table = esc_sql( Schema::issuesTable() );
		$rows  = $wpdb->get_results(
			"SELECT wcag,
				COUNT(*) AS issue_count,
				COUNT(DISTINCT scan_id) AS page_count,
				SUM(CASE WHEN severity = 'error' THEN 1 ELSE 0 END) AS error_count,
				SUM(CASE WHEN severity = 'warning' THEN 1 ELSE 0 END) AS warning_count
			FROM {$table}
			WHERE status = 'open'
			GROUP BY wcag
			ORDER BY error_count DESC, issue_count DESC",
			ARRAY_A
		);

		return array_map(
			static fn ( $row ) => array(
				'wcag'          => (string) $row['wcag'],
				'issue_count'   => (int) $row['issue_count'],
				'page_count'    => (int) $row['page_count'],
				'error_count'   => (int) $row['error_count'],
				'warning_count' => (int) $row['warning_count'],
			),
			$rows ?? array()
		);
	}

	/**
	 * Returns the scan ID that owns an issue.
	 *
	 * @param int $issueId Issue row ID.
	 * @return int|null
	 */
	public function scanIdFor( int $issueId ): ?int {
		global $wpdb;

		$table  = esc_sql( Schema::issuesTable() );
		$scanId = $wpdb->get_var( $wpdb->prepare( "SELECT scan_id FROM {$table} WHERE id = %d", $issueId ) );

		return null !== $scanId ? (int) $scanId : null;
	}

	/**
	 * Returns the severities of the still-open issues for a scan.
	 *
	 * @param int $scanId Scan ID.
	 * @return list<string>
	 */
	public function openSeverities( int $scanId ): array {
		global $wpdb;

		$table = esc_sql( Schema::issuesTable() );
		$rows  = $wpdb->get_col(
			$wpdb->prepare( "SELECT severity FROM {$table} WHERE scan_id = %d AND status = 'open'", $scanId )
		);

		return array_map( 'strval', $rows ?? array() );
	}
}
