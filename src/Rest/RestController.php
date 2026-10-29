<?php

declare(strict_types=1);

namespace A11yFleet\Rest;

use A11yFleet\Scanner\Scheduler;
use A11yFleet\Scanner\ScanService;
use A11yFleet\Storage\IssueRepository;
use A11yFleet\Storage\ScanRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST API under /wp-json/a11yfleet/v1. Powers the dashboard SPA today and
 * external/fleet integrations later.
 */
final class RestController {

	private const NAMESPACE = 'a11yfleet/v1';

	/**
	 * Constructor.
	 *
	 * @param ScanService     $scanService Scan orchestrator.
	 * @param ScanRepository  $scans       Scan storage.
	 * @param IssueRepository $issues      Issue storage.
	 * @param Scheduler       $scheduler   Background scan scheduler.
	 */
	public function __construct(
		private readonly ScanService $scanService,
		private readonly ScanRepository $scans,
		private readonly IssueRepository $issues,
		private readonly Scheduler $scheduler,
	) {
	}

	/**
	 * Registers all REST routes for the plugin namespace.
	 */
	public function registerRoutes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/scans',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'listScans' ),
				'permission_callback' => array( $this, 'canManage' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/scans/(?P<id>\d+)/issues',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'listIssues' ),
				'permission_callback' => array( $this, 'canManage' ),
				'args'                => array( 'id' => array( 'sanitize_callback' => 'absint' ) ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/scan/(?P<post_id>\d+)',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'scanPost' ),
				'permission_callback' => array( $this, 'canManage' ),
				'args'                => array( 'post_id' => array( 'sanitize_callback' => 'absint' ) ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/scan-html',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'scanHtml' ),
				'permission_callback' => array( $this, 'canManage' ),
				'args'                => array(
					'html'    => array(
						'type'     => 'string',
						'required' => true,
					),
					'post_id' => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/scan-page/(?P<post_id>\d+)',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'scanPostPage' ),
				'permission_callback' => array( $this, 'canManage' ),
				'args'                => array( 'post_id' => array( 'sanitize_callback' => 'absint' ) ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/scans/by-post/(?P<post_id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'scansByPost' ),
				'permission_callback' => array( $this, 'canManage' ),
				'args'                => array( 'post_id' => array( 'sanitize_callback' => 'absint' ) ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/scan-status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'scanStatus' ),
				'permission_callback' => array( $this, 'canManage' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/issues/(?P<id>\d+)/status',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'updateIssueStatus' ),
				'permission_callback' => array( $this, 'canManage' ),
				'args'                => array(
					'id'     => array( 'sanitize_callback' => 'absint' ),
					'status' => array(
						'type'     => 'string',
						'required' => true,
						'enum'     => IssueRepository::STATUSES,
					),
				),
			)
		);
	}

	/**
	 * Permission callback — requires manage_options capability.
	 *
	 * @return bool True if the current user can manage options.
	 */
	public function canManage(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Returns background scan progress, advancing a stalled queue first.
	 *
	 * @return WP_REST_Response
	 */
	public function scanStatus(): WP_REST_Response {
		$this->scheduler->tick();

		return new WP_REST_Response( Scheduler::status() );
	}

	/**
	 * Updates an issue's workflow status (open / ignored / resolved).
	 *
	 * @param WP_REST_Request $request REST request with 'id' route param and 'status' body param.
	 * @return WP_REST_Response
	 */
	public function updateIssueStatus( WP_REST_Request $request ): WP_REST_Response {
		$issueId = (int) $request['id'];
		$status  = (string) $request->get_param( 'status' );

		$updated = $this->issues->updateStatus( $issueId, $status );

		if ( ! $updated ) {
			return new WP_REST_Response( array( 'error' => 'update_failed' ), 400 );
		}

		// Recompute the owning scan's score so ignored issues stop counting.
		$scanId = $this->issues->scanIdFor( $issueId );
		$scan   = null !== $scanId ? $this->scanService->recountScan( $scanId ) : null;

		return new WP_REST_Response(
			array(
				'id'      => $issueId,
				'status'  => $status,
				'scan_id' => $scanId,
				'scan'    => $scan,
			)
		);
	}

	/**
	 * Returns the most recent scans.
	 *
	 * @return WP_REST_Response
	 */
	public function listScans(): WP_REST_Response {
		return new WP_REST_Response( $this->scans->recent( 100 ) );
	}

	/**
	 * Returns all issues for a specific scan.
	 *
	 * @param WP_REST_Request $request REST request with 'id' route param.
	 * @return WP_REST_Response
	 */
	public function listIssues( WP_REST_Request $request ): WP_REST_Response {
		$scanId = (int) $request['id'];

		return new WP_REST_Response( $this->issues->forScan( $scanId ) );
	}

	/**
	 * Triggers a scan for a specific post and returns the result.
	 *
	 * @param WP_REST_Request $request REST request with 'post_id' route param.
	 * @return WP_REST_Response
	 */
	public function scanPost( WP_REST_Request $request ): WP_REST_Response {
		$postId = (int) $request['post_id'];
		$result = $this->scanService->scanPost( $postId );

		if ( null === $result ) {
			return new WP_REST_Response( array( 'error' => 'not_found' ), 404 );
		}

		return new WP_REST_Response(
			array(
				'post_id'     => $result->objectId,
				'score'       => $result->score(),
				'issue_count' => $result->issueCount(),
				'issues'      => array_map( static fn ( $i ) => $i->toArray(), $result->issues ),
			)
		);
	}

	/**
	 * Scans raw (unsaved) editor content posted in the request body.
	 *
	 * @param WP_REST_Request $request REST request with an 'html' body param.
	 * @return WP_REST_Response
	 */
	public function scanHtml( WP_REST_Request $request ): WP_REST_Response {
		$html   = (string) $request->get_param( 'html' );
		$postId = (int) $request->get_param( 'post_id' );
		$result = $this->scanService->previewContent( $html );

		// Reflect any issues the user already ignored for this post (read-only;
		// these preview issues are not persisted, so they have no id).
		$postType = get_post_type( $postId );
		$ignored  = $postId > 0
			? $this->issues->ignoredFingerprintsForObject( $postId, false !== $postType ? (string) $postType : 'post' )
			: array();

		$issues = array();
		foreach ( $result->issues as $issue ) {
			$data           = $issue->toArray();
			$data['id']     = null;
			$data['status'] = isset( $ignored[ IssueRepository::fingerprint( $issue->ruleId, $issue->context ) ] )
				? 'ignored'
				: 'open';
			$issues[]       = $data;
		}

		return new WP_REST_Response(
			array(
				'score'       => $result->score(),
				'issue_count' => $result->issueCount(),
				'issues'      => $issues,
			)
		);
	}

	/**
	 * Triggers a full rendered-page scan, persists it, and returns the stored
	 * issues (with ids + statuses) so the editor stays in sync with the dashboard.
	 *
	 * @param WP_REST_Request $request REST request with 'post_id' route param.
	 * @return WP_REST_Response
	 */
	public function scanPostPage( WP_REST_Request $request ): WP_REST_Response {
		$postId = (int) $request['post_id'];
		$result = $this->scanService->scanPostPage( $postId );

		if ( null === $result ) {
			return new WP_REST_Response( array( 'error' => 'scan_failed' ), 502 );
		}

		return new WP_REST_Response( $this->storedForPost( $postId ) );
	}

	/**
	 * Returns the latest stored scan + issues for a post (for the editor panel).
	 *
	 * @param WP_REST_Request $request REST request with 'post_id' route param.
	 * @return WP_REST_Response
	 */
	public function scansByPost( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( $this->storedForPost( (int) $request['post_id'] ) );
	}

	/**
	 * Assembles the latest stored scan + its issues for a post.
	 *
	 * @param int $postId Post ID.
	 * @return array{scan_id:int, score:int, issue_count:int, issues:list<array<string,mixed>>}
	 */
	private function storedForPost( int $postId ): array {
		$post = get_post( $postId );
		$type = $post ? $post->post_type : 'post';
		$scan = $this->scans->latestForObject( $postId, $type );

		if ( null === $scan ) {
			return array(
				'scan_id'     => 0,
				'score'       => 100,
				'issue_count' => 0,
				'issues'      => array(),
			);
		}

		return array(
			'scan_id'     => (int) $scan['id'],
			'score'       => (int) $scan['score'],
			'issue_count' => (int) $scan['issue_count'],
			'issues'      => $this->issues->forScan( (int) $scan['id'] ),
		);
	}
}
