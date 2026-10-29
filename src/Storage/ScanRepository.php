<?php

declare(strict_types=1);

namespace A11yFleet\Storage;

use A11yFleet\Scanner\ScanResult;
use A11yFleet\Scanner\Severity;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

/**
 * Reads/writes scan rows. Each scan for an object replaces the previous one,
 * so a page always has exactly one current scan.
 */
final class ScanRepository {

	/**
	 * Persists a scan and its issues; returns the new scan ID.
	 *
	 * @param ScanResult      $result Scan result to persist.
	 * @param IssueRepository $issues Issue repository for associated issues.
	 * @return int New scan row ID.
	 */
	public function save( ScanResult $result, IssueRepository $issues ): int {
		global $wpdb;

		// Preserve any issues the user previously ignored for this object so a
		// dismissal survives re-scanning.
		$ignored = $issues->ignoredFingerprintsForObject( $result->objectId, $result->objectType );

		$this->deleteForObject( $result->objectId, $result->objectType, $issues );

		// Aggregates reflect only the issues that will remain open.
		$penalty = 0;
		$errors  = 0;
		$open    = 0;
		foreach ( $result->issues as $issue ) {
			if ( isset( $ignored[ IssueRepository::fingerprint( $issue->ruleId, $issue->context ) ] ) ) {
				continue;
			}
			++$open;
			$penalty += $issue->severity->weight();
			if ( Severity::Error === $issue->severity ) {
				++$errors;
			}
		}

		$wpdb->insert(
			Schema::scansTable(),
			array(
				'object_id'   => $result->objectId,
				'object_type' => $result->objectType,
				'url'         => $result->url,
				'score'       => (int) max( 0, 100 - $penalty ),
				'issue_count' => $open,
				'error_count' => $errors,
				'scanned_at'  => gmdate( 'Y-m-d H:i:s', $result->scannedAt ),
			),
			array( '%d', '%s', '%s', '%d', '%d', '%d', '%s' )
		);

		$scanId = (int) $wpdb->insert_id;
		$issues->saveMany( $scanId, $result->issues, $ignored );

		return $scanId;
	}

	/**
	 * Deletes all scans (and their issues) for a given object.
	 *
	 * @param int             $objectId   Object ID (e.g. post ID).
	 * @param string          $objectType Object type (e.g. 'post').
	 * @param IssueRepository $issues     Issue repository for cascaded deletion.
	 */
	public function deleteForObject( int $objectId, string $objectType, IssueRepository $issues ): void {
		global $wpdb;

		$table = esc_sql( Schema::scansTable() );
		$ids   = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE object_id = %d AND object_type = %s",
				$objectId,
				$objectType
			)
		);

		foreach ( $ids as $id ) {
			$issues->deleteForScan( (int) $id );
		}

		$wpdb->delete(
			$table,
			array(
				'object_id'   => $objectId,
				'object_type' => $objectType,
			),
			array( '%d', '%s' )
		);
	}

	/**
	 * Returns a page of scans, worst score first (then newest).
	 *
	 * @param int                  $limit   Maximum number of rows to return.
	 * @param int                  $offset  Number of rows to skip (for pagination).
	 * @param array<string, mixed> $filters Optional column filters.
	 * @return list<array<string, mixed>>
	 */
	public function recent( int $limit = 20, int $offset = 0, array $filters = array() ): array {
		global $wpdb;

		$table                = esc_sql( Schema::scansTable() );
		list( $where, $args ) = $this->buildWhere( $filters );
		$args[]               = $limit;
		$args[]               = $offset;

		$rows = $wpdb->get_results(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $args array covers all placeholders including those in $where.
				"SELECT * FROM {$table} {$where} ORDER BY score ASC, scanned_at DESC LIMIT %d OFFSET %d",
				$args
			),
			ARRAY_A
		);

		return $rows ?? array();
	}

	/**
	 * Number of stored scans matching the filters (for pagination).
	 *
	 * @param array<string, mixed> $filters Optional filters.
	 * @return int
	 */
	public function count( array $filters = array() ): int {
		global $wpdb;

		$table                = esc_sql( Schema::scansTable() );
		list( $where, $args ) = $this->buildWhere( $filters );

		if ( empty( $args ) ) {
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} {$where}" );
		}

		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} {$where}", $args ) ); // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- placeholders are inside $where.
	}

	/**
	 * Returns the most recent stored scan for an object, or null.
	 *
	 * @param int    $objectId   Object ID.
	 * @param string $objectType Object type.
	 * @return array<string, mixed>|null
	 */
	public function latestForObject( int $objectId, string $objectType ): ?array {
		global $wpdb;

		$table = esc_sql( Schema::scansTable() );
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE object_id = %d AND object_type = %s ORDER BY scanned_at DESC LIMIT 1",
				$objectId,
				$objectType
			),
			ARRAY_A
		);

		return $row ? $row : null;
	}

	/**
	 * Distinct object types present in stored scans (for filter options).
	 *
	 * @return list<string>
	 */
	public function distinctObjectTypes(): array {
		global $wpdb;

		$table = esc_sql( Schema::scansTable() );
		$rows  = $wpdb->get_col( "SELECT DISTINCT object_type FROM {$table} ORDER BY object_type" );

		return array_map( 'strval', $rows ?? array() );
	}

	/**
	 * Builds a WHERE clause + bound args from a filters array.
	 *
	 * @param array<string, mixed> $filters Filters (object_type, errors_only).
	 * @return array{0: string, 1: list<mixed>}
	 */
	private function buildWhere( array $filters ): array {
		$conditions = array();
		$args       = array();

		if ( ! empty( $filters['object_type'] ) ) {
			$conditions[] = 'object_type = %s';
			$args[]       = (string) $filters['object_type'];
		}
		if ( ! empty( $filters['has_issues'] ) ) {
			$conditions[] = 'issue_count > 0';
		}

		$where = $conditions ? ( 'WHERE ' . implode( ' AND ', $conditions ) ) : '';

		return array( $where, $args );
	}

	/**
	 * Finds a single scan row by its ID.
	 *
	 * @param int $scanId Scan row ID.
	 * @return array<string, mixed>|null Row data, or null if not found.
	 */
	public function find( int $scanId ): ?array {
		global $wpdb;

		$table = esc_sql( Schema::scansTable() );
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $scanId ),
			ARRAY_A
		);

		return $row ? $row : null;
	}

	/**
	 * Returns site-wide totals for the dashboard summary.
	 *
	 * @return array{scans:int, issues:int, errors:int, avg_score:int}
	 */
	public function summary(): array {
		global $wpdb;

		$table = esc_sql( Schema::scansTable() );
		$row   = $wpdb->get_row(
			"SELECT COUNT(*) AS scans, COALESCE(SUM(issue_count),0) AS issues,
                    COALESCE(SUM(error_count),0) AS errors, COALESCE(ROUND(AVG(score)),0) AS avg_score
             FROM {$table}",
			ARRAY_A
		);

		return array(
			'scans'     => (int) ( $row['scans'] ?? 0 ),
			'issues'    => (int) ( $row['issues'] ?? 0 ),
			'errors'    => (int) ( $row['errors'] ?? 0 ),
			'avg_score' => (int) ( $row['avg_score'] ?? 0 ),
		);
	}

	/**
	 * Updates a scan's derived aggregates (e.g. after issues are ignored).
	 *
	 * @param int $scanId     Scan ID.
	 * @param int $score      Recomputed score (0–100).
	 * @param int $issueCount Open issue count.
	 * @param int $errorCount Open error count.
	 */
	public function updateAggregates( int $scanId, int $score, int $issueCount, int $errorCount ): void {
		global $wpdb;

		$wpdb->update(
			Schema::scansTable(),
			array(
				'score'       => $score,
				'issue_count' => $issueCount,
				'error_count' => $errorCount,
			),
			array( 'id' => $scanId ),
			array( '%d', '%d', '%d' ),
			array( '%d' )
		);
	}
}
