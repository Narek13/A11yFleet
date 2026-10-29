<?php

declare(strict_types=1);

namespace A11yFleet\Admin;

use A11yFleet\Scanner\Scheduler;
use A11yFleet\Storage\IssueRepository;
use A11yFleet\Storage\ScanRepository;

/**
 * Registers the admin menu and renders the dashboard. The dashboard is a
 * server-rendered table today; a React SPA (talking to the REST API) can take
 * over the same screen later without changing the menu wiring.
 */
final class AdminMenu {

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
	 * Hooks the menu registration into WordPress.
	 */
	public function register(): void {
		add_action(
			'admin_menu',
			function (): void {
				add_menu_page(
					__( 'A11yFleet — Accessibility', 'a11yfleet' ),
					__( 'A11yFleet', 'a11yfleet' ),
					'manage_options',
					'a11yfleet',
					array( $this, 'renderDashboard' ),
					'dashicons-universal-access-alt',
					81
				);
			}
		);
	}

	/**
	 * Renders the plugin dashboard page.
	 */
	public function renderDashboard(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Read-only display filters (no state change → nonce not required).
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$ptype = isset( $_GET['ptype'] ) ? sanitize_key( wp_unslash( $_GET['ptype'] ) ) : '';
		$paged = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		// Set by the "Scan all" redirect; null when the page is opened directly.
		$queued = isset( $_GET['scanned'] ) ? absint( $_GET['scanned'] ) : null;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		// Only list pages that actually have issues — clean pages are noise.
		$filters = array( 'has_issues' => true );
		if ( '' !== $ptype ) {
			$filters['object_type'] = $ptype;
		}

		$per_page = 20;
		$total    = $this->scans->count( $filters );
		$pages    = (int) max( 1, (int) ceil( $total / $per_page ) );
		$paged    = min( $paged, $pages );
		$offset   = ( $paged - 1 ) * $per_page;

		$summary      = $this->scans->summary();
		$recent       = $this->scans->recent( $per_page, $offset, $filters );
		$object_types = $this->scans->distinctObjectTypes();
		$scan_status  = Scheduler::status();

		$scanAllUrl = wp_nonce_url(
			admin_url( 'admin-post.php?action=a11yfleet_scan_all' ),
			'a11yfleet_scan_all'
		);

		require A11YFLEET_DIR . 'templates/dashboard.php';
	}

	/**
	 * Helper used by the template to render the issues for one scan.
	 *
	 * @param int $scanId Scan ID to fetch issues for.
	 * @return list<array<string, mixed>>
	 */
	public function issuesFor( int $scanId ): array {
		return $this->issues->forScan( $scanId );
	}
}
