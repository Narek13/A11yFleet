<?php

declare(strict_types=1);

namespace A11yFleet\Tests\Unit;

use A11yFleet\Scanner\Engine;
use A11yFleet\Scanner\Rules\HeadingOrderRule;
use A11yFleet\Scanner\Rules\ImageAltRule;
use A11yFleet\Scanner\Rules\LinkTextRule;
use A11yFleet\Scanner\Severity;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the scan engine. WordPress's i18n functions are stubbed via
 * Brain Monkey, so the pure-PHP engine runs without a WP install.
 */
final class EngineTest extends TestCase {

	/**
	 * Sets up Brain Monkey and stubs i18n functions for each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		// __() / esc_* simply return their first argument in unit context.
		Functions\when( '__' )->returnArg();
	}

	/**
	 * Tears down Brain Monkey after each test.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Builds an engine with all three default rules registered.
	 *
	 * @return Engine
	 */
	private function engine(): Engine {
		return new Engine( array( new ImageAltRule(), new LinkTextRule(), new HeadingOrderRule() ) );
	}

	/**
	 * GIVEN valid, accessible HTML
	 * WHEN the engine scans it
	 * THEN no issues are reported.
	 */
	public function testCleanContentHasNoIssues(): void {
		$issues = $this->engine()->run(
			'<h2>Title</h2><p>A <a href="/report">read the 2026 report</a> link.</p>'
			. '<img src="a.jpg" alt="A chart">'
		);

		self::assertSame( array(), $issues );
	}

	/**
	 * GIVEN an img element with no alt attribute
	 * WHEN the engine scans it
	 * THEN one Error-severity image-alt issue is returned.
	 */
	public function testDetectsMissingAltText(): void {
		$issues = $this->engine()->run( '<img src="cat.jpg">' );

		self::assertCount( 1, $issues );
		self::assertSame( 'image-alt', $issues[0]->ruleId );
		self::assertSame( '1.1.1', $issues[0]->wcag );
		self::assertSame( Severity::Error, $issues[0]->severity );
	}

	/**
	 * GIVEN an img element with an explicit empty alt attribute
	 * WHEN the engine scans it
	 * THEN no issues are returned (empty alt is valid for decorative images).
	 */
	public function testDecorativeEmptyAltIsAllowed(): void {
		$issues = $this->engine()->run( '<img src="divider.png" alt="">' );

		self::assertSame( array(), $issues );
	}

	/**
	 * GIVEN a generic-text link and an empty link
	 * WHEN the engine scans them
	 * THEN two link-text issues are returned.
	 */
	public function testDetectsGenericAndEmptyLinks(): void {
		$issues = $this->engine()->run(
			'<a href="/a">click here</a><a href="/b"></a>'
		);

		self::assertCount( 2, $issues );
	}

	/**
	 * GIVEN headings that skip a level (h2 → h4)
	 * WHEN the engine scans them
	 * THEN one heading-order issue is returned.
	 */
	public function testDetectsSkippedHeadingLevel(): void {
		$issues = $this->engine()->run( '<h2>Section</h2><h4>Sub</h4>' );

		self::assertCount( 1, $issues );
		self::assertSame( 'heading-order', $issues[0]->ruleId );
	}

	/**
	 * GIVEN whitespace-only HTML
	 * WHEN the engine scans it
	 * THEN no issues are returned and no errors occur.
	 */
	public function testEmptyContentIsSafe(): void {
		self::assertSame( array(), $this->engine()->run( '   ' ) );
	}

	/**
	 * GIVEN a full HTML document (not just a fragment)
	 * WHEN the engine scans it
	 * THEN rules still run against the page body.
	 */
	public function testFullHtmlDocumentIsParsed(): void {
		$issues = $this->engine()->run(
			'<!DOCTYPE html><html lang="en"><head><title>T</title></head>'
			. '<body><img src="x.jpg"></body></html>'
		);

		self::assertCount( 1, $issues );
		self::assertSame( 'image-alt', $issues[0]->ruleId );
	}
}
