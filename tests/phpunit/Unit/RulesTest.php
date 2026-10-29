<?php

declare(strict_types=1);

namespace A11yFleet\Tests\Unit;

use A11yFleet\Scanner\Engine;
use A11yFleet\Scanner\Rule;
use A11yFleet\Scanner\Rules\ButtonNameRule;
use A11yFleet\Scanner\Rules\DocumentLanguageRule;
use A11yFleet\Scanner\Rules\DuplicateIdRule;
use A11yFleet\Scanner\Rules\EmptyHeadingRule;
use A11yFleet\Scanner\Rules\FormControlLabelRule;
use A11yFleet\Scanner\Rules\IframeTitleRule;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the individual WCAG rules, each run through a single-rule
 * engine. WordPress i18n is stubbed via Brain Monkey.
 */
final class RulesTest extends TestCase {

	/**
	 * Sets up Brain Monkey and stubs i18n functions for each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
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
	 * Runs a single rule against the given HTML and returns the issues.
	 *
	 * @param Rule                 $rule    The rule under test.
	 * @param string               $html    HTML fragment to scan.
	 * @param array<string, mixed> $context Optional scan context.
	 * @return array<int, \A11yFleet\Scanner\Issue>
	 */
	private function scan( Rule $rule, string $html, array $context = array() ): array {
		return ( new Engine( array( $rule ) ) )->run( $html, $context );
	}

	/**
	 * A form control with no label of any kind is flagged.
	 */
	public function testUnlabelledControlIsFlagged(): void {
		$issues = $this->scan( new FormControlLabelRule(), '<input type="text" name="email">' );

		self::assertCount( 1, $issues );
		self::assertSame( 'form-control-label', $issues[0]->ruleId );
	}

	/**
	 * A control associated with a <label for> passes.
	 */
	public function testLabelledControlPasses(): void {
		$issues = $this->scan(
			new FormControlLabelRule(),
			'<label for="email">Email</label><input id="email" type="text">'
		);

		self::assertSame( array(), $issues );
	}

	/**
	 * A hidden input is exempt and not flagged.
	 */
	public function testHiddenInputIsExempt(): void {
		$issues = $this->scan( new FormControlLabelRule(), '<input type="hidden" name="nonce">' );

		self::assertSame( array(), $issues );
	}

	/**
	 * A button with no accessible name is flagged.
	 */
	public function testEmptyButtonIsFlagged(): void {
		$issues = $this->scan( new ButtonNameRule(), '<button></button>' );

		self::assertCount( 1, $issues );
		self::assertSame( 'button-name', $issues[0]->ruleId );
	}

	/**
	 * A button with visible text passes.
	 */
	public function testButtonWithTextPasses(): void {
		$issues = $this->scan( new ButtonNameRule(), '<button>Save changes</button>' );

		self::assertSame( array(), $issues );
	}

	/**
	 * An image input without alt text is flagged.
	 */
	public function testImageInputWithoutAltIsFlagged(): void {
		$issues = $this->scan( new ButtonNameRule(), '<input type="image" src="go.png">' );

		self::assertCount( 1, $issues );
	}

	/**
	 * Repeated id values are flagged once per duplicated id.
	 */
	public function testDuplicateIdsAreFlagged(): void {
		$issues = $this->scan( new DuplicateIdRule(), '<p id="x"></p><span id="x"></span>' );

		self::assertCount( 1, $issues );
		self::assertSame( 'duplicate-id', $issues[0]->ruleId );
	}

	/**
	 * Unique id values pass.
	 */
	public function testUniqueIdsPass(): void {
		$issues = $this->scan( new DuplicateIdRule(), '<p id="a"></p><span id="b"></span>' );

		self::assertSame( array(), $issues );
	}

	/**
	 * An empty heading is flagged; a heading with text passes.
	 */
	public function testEmptyHeadingIsFlagged(): void {
		self::assertCount( 1, $this->scan( new EmptyHeadingRule(), '<h2></h2>' ) );
		self::assertSame( array(), $this->scan( new EmptyHeadingRule(), '<h2>Section title</h2>' ) );
	}

	/**
	 * An iframe without a title is flagged; one with a title passes.
	 */
	public function testIframeTitleIsRequired(): void {
		self::assertCount( 1, $this->scan( new IframeTitleRule(), '<iframe src="/m"></iframe>' ) );
		self::assertSame( array(), $this->scan( new IframeTitleRule(), '<iframe src="/m" title="Map"></iframe>' ) );
	}

	/**
	 * A full page without <html lang> is flagged at page scope.
	 */
	public function testDocumentLanguageFlaggedOnPageScope(): void {
		$issues = $this->scan(
			new DocumentLanguageRule(),
			'<html><head><title>x</title></head><body></body></html>',
			array( 'scope' => 'page' )
		);

		self::assertCount( 1, $issues );
		self::assertSame( 'document-language', $issues[0]->ruleId );
	}

	/**
	 * A page that declares a language passes.
	 */
	public function testDocumentLanguageWithLangPasses(): void {
		$issues = $this->scan(
			new DocumentLanguageRule(),
			'<html lang="en"><body></body></html>',
			array( 'scope' => 'page' )
		);

		self::assertSame( array(), $issues );
	}

	/**
	 * The language rule never fires on content scans (no page scope), even though
	 * libxml synthesises an <html> wrapper around fragments.
	 */
	public function testDocumentLanguageIgnoredOnContentScope(): void {
		$issues = $this->scan( new DocumentLanguageRule(), '<p>Hello world</p>' );

		self::assertSame( array(), $issues );
	}
}
