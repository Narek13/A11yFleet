<?php

declare(strict_types=1);

namespace A11yFleet\Tests\Unit;

use A11yFleet\Scanner\Engine;
use A11yFleet\Scanner\PageFetcher;
use A11yFleet\Scanner\ScanService;
use A11yFleet\Scanner\Scheduler;
use A11yFleet\Settings\Settings;
use A11yFleet\Storage\IssueRepository;
use A11yFleet\Storage\ScanRepository;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the background scan scheduler's stall recovery and batch lock.
 *
 * Queued IDs resolve to no post, so scanning an item is a no-op and the tests
 * focus on queue, lock and cron behaviour.
 */
final class SchedulerTest extends TestCase {

	/**
	 * Sets up Brain Monkey and stubs the lookups every scenario relies on.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'get_post' )->justReturn( null );
	}

	/**
	 * Tears down Brain Monkey after each test.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Builds a scheduler around a real scan service.
	 */
	private function createScheduler(): Scheduler {
		$settings = new Settings();
		$service  = new ScanService(
			new Engine( array() ),
			new ScanRepository(),
			new IssueRepository(),
			new PageFetcher(),
			$settings
		);

		return new Scheduler( $service, $settings );
	}

	/**
	 * Stubs the queue option and lock transient.
	 *
	 * @param list<int> $queue  Queued post IDs.
	 * @param bool      $locked Whether the batch lock is held.
	 */
	private function stubQueue( array $queue, bool $locked = false ): void {
		Functions\when( 'get_option' )->alias(
			static fn( string $name, $fallback = false ) => ( Scheduler::QUEUE === $name ) ? $queue : $fallback
		);
		Functions\when( 'get_transient' )->justReturn( $locked ? 1 : false );
	}

	/**
	 * GIVEN no scan is in progress
	 * WHEN the dashboard ticks
	 * THEN nothing is scheduled, queued or locked.
	 */
	public function testTickDoesNothingWhenNoScanIsInProgress(): void {
		$this->stubQueue( array() );
		Functions\expect( 'wp_next_scheduled' )->never();
		Functions\expect( 'update_option' )->never();
		Functions\expect( 'set_transient' )->never();

		$this->createScheduler()->tick();

		$this->addToAssertionCount( 1 );
	}

	/**
	 * GIVEN a scan in progress with the next batch scheduled in the future
	 * WHEN the dashboard ticks
	 * THEN cron is left to run it and the queue is untouched.
	 */
	public function testTickLeavesQueueAloneWhenBatchIsScheduledInFuture(): void {
		$this->stubQueue( array( 1, 2, 3 ) );
		Functions\when( 'wp_next_scheduled' )->justReturn( time() + 60 );
		Functions\expect( 'wp_unschedule_event' )->never();
		Functions\expect( 'update_option' )->never();

		$this->createScheduler()->tick();

		$this->addToAssertionCount( 1 );
	}

	/**
	 * GIVEN a scan in progress whose batch event is overdue by more than 30 seconds
	 * WHEN the dashboard ticks
	 * THEN the stalled event is unscheduled, one batch is processed under the lock
	 * AND the remaining IDs are saved back to the queue.
	 */
	public function testTickProcessesBatchWhenScheduledEventIsStalled(): void {
		$overdue = time() - 120;
		$queue   = range( 1, 25 );
		$this->stubQueue( $queue );
		Functions\when( 'wp_next_scheduled' )->justReturn( $overdue );
		Functions\when( 'wp_schedule_single_event' )->justReturn( true );
		Functions\expect( 'wp_unschedule_event' )->once()->with( $overdue, Scheduler::BATCH_HOOK );
		Functions\expect( 'set_transient' )->once()->with( Scheduler::LOCK, 1, \Mockery::type( 'int' ) );
		Functions\expect( 'update_option' )->once()->with( Scheduler::QUEUE, range( 21, 25 ), false );
		Functions\expect( 'delete_transient' )->once()->with( Scheduler::LOCK );

		$this->createScheduler()->tick();

		$this->addToAssertionCount( 1 );
	}

	/**
	 * GIVEN a scan in progress with more items than one batch and no batch event scheduled
	 * WHEN the dashboard ticks
	 * THEN a batch is processed and the next one is scheduled.
	 */
	public function testTickSchedulesNextBatchWhenNoEventExistsAndItemsRemain(): void {
		$this->stubQueue( range( 1, 25 ) );
		Functions\when( 'wp_next_scheduled' )->justReturn( false );
		Functions\when( 'update_option' )->justReturn( true );
		Functions\when( 'set_transient' )->justReturn( true );
		Functions\when( 'delete_transient' )->justReturn( true );
		Functions\expect( 'wp_unschedule_event' )->never();
		Functions\expect( 'wp_schedule_single_event' )->once()->with( \Mockery::type( 'int' ), Scheduler::BATCH_HOOK );

		$this->createScheduler()->tick();

		$this->addToAssertionCount( 1 );
	}

	/**
	 * GIVEN a scan whose final items fit in one batch and no batch event scheduled
	 * WHEN the dashboard ticks
	 * THEN the queue is emptied and no further batch is scheduled.
	 */
	public function testTickDoesNotScheduleAnotherBatchWhenQueueIsDrained(): void {
		$this->stubQueue( array( 1, 2, 3 ) );
		Functions\when( 'wp_next_scheduled' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );
		Functions\when( 'delete_transient' )->justReturn( true );
		Functions\expect( 'update_option' )->once()->with( Scheduler::QUEUE, array(), false );
		Functions\expect( 'wp_schedule_single_event' )->never();

		$this->createScheduler()->tick();

		$this->addToAssertionCount( 1 );
	}

	/**
	 * GIVEN another request already holds the batch lock
	 * WHEN a batch is processed
	 * THEN nothing is scanned, saved or locked again.
	 */
	public function testProcessBatchIsNoOpWhenLockIsHeld(): void {
		$this->stubQueue( array( 1, 2, 3 ), true );
		Functions\expect( 'set_transient' )->never();
		Functions\expect( 'update_option' )->never();
		Functions\expect( 'delete_transient' )->never();
		Functions\expect( 'wp_schedule_single_event' )->never();

		$this->createScheduler()->processBatch();

		$this->addToAssertionCount( 1 );
	}
}
