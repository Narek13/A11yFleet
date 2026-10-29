<?php

declare(strict_types=1);

namespace A11yFleet\Scanner;

use A11yFleet\Settings\Settings;

/**
 * Background, batched scanning.
 *
 * A full-site scan enqueues every scannable post ID into an option, then drains
 * the queue in small batches — processing one batch immediately and rescheduling
 * the rest via one-off WP-Cron events. This avoids request timeouts on large
 * sites. A recurring cron event (per Settings) triggers full scans on a schedule.
 */
final class Scheduler {

	public const CRON_HOOK   = 'a11yfleet_scheduled_scan';
	public const BATCH_HOOK  = 'a11yfleet_process_batch';
	public const QUEUE       = 'a11yfleet_scan_queue';
	public const TOTAL       = 'a11yfleet_scan_total';
	public const LOCK        = 'a11yfleet_batch_lock';
	private const BATCH_SIZE = 20;
	private const LOCK_TTL   = 120;
	private const STALL_SECS = 30;

	/**
	 * Constructor.
	 *
	 * @param ScanService $scanService Scan orchestrator.
	 * @param Settings    $settings    Settings service.
	 */
	public function __construct(
		private readonly ScanService $scanService,
		private readonly Settings $settings,
	) {
	}

	/**
	 * Wires cron + batch hooks.
	 */
	public function register(): void {
		add_action(
			self::CRON_HOOK,
			function (): void {
				$this->startFullScan();
			}
		);
		add_action( self::BATCH_HOOK, array( $this, 'processBatch' ) );
		add_action( 'admin_post_a11yfleet_scan_all', array( $this, 'onScanAllRequest' ) );

		// Keep the recurring schedule in sync with settings.
		add_action( 'init', array( $this, 'syncSchedule' ) );
		add_action( 'update_option_' . Settings::OPTION, array( $this, 'syncSchedule' ) );
	}

	/**
	 * Ensures the recurring cron event matches the configured interval.
	 */
	public function syncSchedule(): void {
		$interval  = $this->settings->cron();
		$timestamp = wp_next_scheduled( self::CRON_HOOK );

		if ( 'off' === $interval ) {
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, self::CRON_HOOK );
			}
			return;
		}

		if ( wp_get_schedule( self::CRON_HOOK ) !== $interval ) {
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, self::CRON_HOOK );
			}
			wp_schedule_event( time() + MINUTE_IN_SECONDS, $interval, self::CRON_HOOK );
		}
	}

	/**
	 * Builds the scan queue from all scannable published posts and starts it.
	 *
	 * @return int Number of items queued.
	 */
	public function startFullScan(): int {
		$ids = get_posts(
			array(
				'post_type'   => $this->settings->postTypes(),
				'post_status' => 'publish',
				'fields'      => 'ids',
				// phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- IDs only; drained in batches below.
				'numberposts' => -1,
			)
		);

		$ids = array_map( 'intval', (array) $ids );
		update_option( self::QUEUE, $ids, false );
		update_option( self::TOTAL, count( $ids ), false );

		$this->processBatch();

		return count( $ids );
	}

	/**
	 * Current scan progress.
	 *
	 * @return array{total: int, remaining: int, done: int, in_progress: bool}
	 */
	public static function status(): array {
		$total     = (int) get_option( self::TOTAL, 0 );
		$remaining = count( (array) get_option( self::QUEUE, array() ) );

		return array(
			'total'       => $total,
			'remaining'   => $remaining,
			'done'        => max( 0, $total - $remaining ),
			'in_progress' => $remaining > 0,
		);
	}

	/**
	 * Processes one batch from the queue, then reschedules if any remain.
	 *
	 * A short-lived lock keeps WP-Cron and status polling from scanning the
	 * same batch twice when they overlap.
	 */
	public function processBatch(): void {
		$queue = (array) get_option( self::QUEUE, array() );
		if ( empty( $queue ) || get_transient( self::LOCK ) ) {
			return;
		}

		set_transient( self::LOCK, 1, self::LOCK_TTL );

		try {
			$batch = array_splice( $queue, 0, self::BATCH_SIZE );
			foreach ( $batch as $postId ) {
				$this->scanService->scanPost( (int) $postId );
			}

			update_option( self::QUEUE, $queue, false );

			if ( ! empty( $queue ) && ! wp_next_scheduled( self::BATCH_HOOK ) ) {
				wp_schedule_single_event( time() + 30, self::BATCH_HOOK );
			}
		} finally {
			delete_transient( self::LOCK );
		}
	}

	/**
	 * Drains the queue directly when WP-Cron has stalled.
	 *
	 * Called from the status endpoint the dashboard polls, so an open dashboard
	 * finishes the scan even when cron never fires (loopback blocked,
	 * DISABLE_WP_CRON, no front-end traffic).
	 */
	public function tick(): void {
		if ( ! self::status()['in_progress'] ) {
			return;
		}

		$next = wp_next_scheduled( self::BATCH_HOOK );
		if ( false !== $next && $next > time() - self::STALL_SECS ) {
			return;
		}

		if ( false !== $next ) {
			wp_unschedule_event( $next, self::BATCH_HOOK );
		}

		$this->processBatch();
	}

	/**
	 * Admin-post handler for the "Scan all content" button.
	 */
	public function onScanAllRequest(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'a11yfleet' ) );
		}
		check_admin_referer( 'a11yfleet_scan_all' );

		$queued = $this->startFullScan();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'a11yfleet',
					'scanned' => $queued,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
