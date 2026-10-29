<?php

declare(strict_types=1);

namespace A11yFleet\Report;

use A11yFleet\Storage\IssueRepository;
use A11yFleet\Storage\ScanRepository;

/**
 * Aggregates stored scan data into a site compliance report (EN 301 549 /
 * WCAG 2.1 AA framing) plus the inputs for an accessibility statement.
 */
final class ReportBuilder {

	/**
	 * Constructor.
	 *
	 * @param ScanRepository  $scans  Scan storage.
	 * @param IssueRepository $issues Issue storage.
	 */
	public function __construct(
		private readonly ScanRepository $scans,
		private readonly IssueRepository $issues,
	) {
	}

	/**
	 * Builds the full report data structure.
	 *
	 * @return array<string, mixed>
	 */
	public function build(): array {
		$summary    = $this->scans->summary();
		$severities = $this->issues->openCountsBySeverity();
		$open       = $severities['error'] + $severities['warning'] + $severities['notice'];

		$criteria = array();
		foreach ( $this->issues->openSummaryByWcag() as $row ) {
			$severity = $row['error_count'] > 0
				? 'error'
				: ( $row['warning_count'] > 0 ? 'warning' : 'notice' );

			$criteria[] = array(
				'wcag'        => $row['wcag'],
				'title'       => WcagCriteria::title( $row['wcag'] ),
				'severity'    => $severity,
				'issue_count' => $row['issue_count'],
				'page_count'  => $row['page_count'],
			);
		}

		return array(
			'generated_at' => current_time( 'mysql' ),
			'site_name'    => get_bloginfo( 'name' ),
			'site_url'     => home_url(),
			'standard'     => __( 'EN 301 549 (WCAG 2.1, level AA)', 'a11yfleet' ),
			'conformance'  => $open > 0 ? 'partial' : 'clean',
			'totals'       => array(
				'pages_scanned'     => $this->scans->count(),
				'pages_with_issues' => $this->scans->count( array( 'has_issues' => true ) ),
				'avg_score'         => (int) $summary['avg_score'],
				'open_issues'       => $open,
				'errors'            => $severities['error'],
				'warnings'          => $severities['warning'],
				'notices'           => $severities['notice'],
			),
			'criteria'     => $criteria,
			/**
			 * Filters the feedback contact shown in the accessibility statement.
			 *
			 * @param string $contact Contact (email or URL).
			 */
			'contact'      => (string) apply_filters( 'a11yfleet_report_contact', get_option( 'admin_email' ) ),
		);
	}
}
