<?php

declare(strict_types=1);

namespace A11yFleet\Scanner;

use A11yFleet\Settings\Settings;
use A11yFleet\Storage\IssueRepository;
use A11yFleet\Storage\ScanRepository;
use WP_Post;

/**
 * Orchestrates scanning: render a post's content the way the front-end would,
 * run the engine, persist the result. Also wires the WordPress hooks.
 */
final class ScanService {

	/**
	 * Constructor.
	 *
	 * @param Engine          $engine   Scan engine.
	 * @param ScanRepository  $scans    Scan storage.
	 * @param IssueRepository $issues   Issue storage.
	 * @param PageFetcher     $pages    Rendered-page fetcher.
	 * @param Settings        $settings Plugin settings.
	 */
	public function __construct(
		private readonly Engine $engine,
		private readonly ScanRepository $scans,
		private readonly IssueRepository $issues,
		private readonly PageFetcher $pages,
		private readonly Settings $settings,
	) {
	}

	/**
	 * Scans a single post and stores the result.
	 *
	 * @param int $postId Post ID to scan.
	 * @return ScanResult|null Scan result, or null if the post does not exist.
	 */
	public function scanPost( int $postId ): ?ScanResult {
		$post = get_post( $postId );
		if ( ! $post instanceof WP_Post ) {
			return null;
		}

		// Render content through the same filters the theme would apply, so we
		// scan what visitors actually receive (shortcodes, blocks, embeds).
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WP filter, not a plugin-defined hook.
		$html  = apply_filters( 'the_content', $post->post_content );
		$found = $this->engine->run(
			(string) $html,
			array(
				'scope'       => 'content',
				'object_id'   => $postId,
				'object_type' => $post->post_type,
			)
		);

		$result = new ScanResult(
			objectId: $postId,
			objectType: $post->post_type,
			url: (string) get_permalink( $post ),
			issues: $found,
			scannedAt: time(),
		);

		$this->scans->save( $result, $this->issues );

		return $result;
	}

	/**
	 * Recomputes and stores a scan's score/counts from its still-open issues.
	 *
	 * Called after an issue's status changes (e.g. ignored) so dismissed issues
	 * stop counting against the score.
	 *
	 * @param int $scanId Scan ID to recount.
	 * @return array{score: int, issue_count: int, error_count: int}
	 */
	public function recountScan( int $scanId ): array {
		$severities = $this->issues->openSeverities( $scanId );

		$penalty = 0;
		$errors  = 0;
		foreach ( $severities as $severity ) {
			$penalty += Severity::from( $severity )->weight();
			if ( 'error' === $severity ) {
				++$errors;
			}
		}

		$score = (int) max( 0, 100 - $penalty );
		$count = count( $severities );

		$this->scans->updateAggregates( $scanId, $score, $count, $errors );

		return array(
			'score'       => $score,
			'issue_count' => $count,
			'error_count' => $errors,
		);
	}

	/**
	 * Scans a raw content/block string without persisting it.
	 *
	 * Used to preview the current (unsaved) editor content, so authors can scan
	 * before saving. The block markup is rendered through the_content first.
	 *
	 * @param string $html Raw post content / block markup.
	 * @return ScanResult Transient result (objectId 0, not stored).
	 */
	public function previewContent( string $html ): ScanResult {
		// Render block markup + shortcodes the way the front end would.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WP filter, not a plugin-defined hook.
		$rendered = apply_filters( 'the_content', $html );
		$found    = $this->engine->run(
			(string) $rendered,
			array( 'scope' => 'content' )
		);

		return new ScanResult(
			objectId: 0,
			objectType: 'preview',
			url: '',
			issues: $found,
			scannedAt: time(),
		);
	}

	/**
	 * Scans the fully-rendered front-end page for a post (theme markup included).
	 *
	 * Fetches the published URL over HTTP and runs the engine on the whole
	 * document, enabling page-level checks (e.g. document language). Returns
	 * null if the post is missing or the page could not be fetched.
	 *
	 * @param int $postId Post ID to scan.
	 * @return ScanResult|null Scan result, or null if the post is missing or the page could not be fetched.
	 */
	public function scanPostPage( int $postId ): ?ScanResult {
		$post = get_post( $postId );
		if ( ! $post instanceof WP_Post ) {
			return null;
		}

		$url  = (string) get_permalink( $post );
		$html = $this->pages->fetch( $url );
		if ( null === $html ) {
			return null;
		}

		$found = $this->engine->run(
			$html,
			array(
				'scope'       => 'page',
				'object_id'   => $postId,
				'object_type' => $post->post_type,
			)
		);

		$result = new ScanResult(
			objectId: $postId,
			objectType: $post->post_type,
			url: $url,
			issues: $found,
			scannedAt: time(),
		);

		$this->scans->save( $result, $this->issues );

		return $result;
	}

	/**
	 * Save_post hook handler — auto-scans published posts, skipping autosaves, revisions, and trashing.
	 *
	 * @param int     $postId Post ID.
	 * @param WP_Post $post   Post object.
	 */
	public function onSavePost( int $postId, WP_Post $post ): void {
		if ( ! $this->settings->scanOnSave() ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $postId ) || wp_is_post_autosave( $postId ) ) {
			return;
		}
		if ( 'publish' !== $post->post_status ) {
			return;
		}
		if ( ! in_array( $post->post_type, $this->settings->postTypes(), true ) ) {
			return;
		}

		$this->scanPost( $postId );
	}
}
