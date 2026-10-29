<?php

declare(strict_types=1);

namespace A11yFleet;

use A11yFleet\Admin\AdminMenu;
use A11yFleet\Admin\Assets;
use A11yFleet\Admin\ReportPage;
use A11yFleet\Admin\SettingsPage;
use A11yFleet\Report\ReportBuilder;
use A11yFleet\Rest\RestController;
use A11yFleet\Scanner\Engine;
use A11yFleet\Scanner\PageFetcher;
use A11yFleet\Scanner\Scheduler;
use A11yFleet\Scanner\Rules\ButtonNameRule;
use A11yFleet\Scanner\Rules\DocumentLanguageRule;
use A11yFleet\Scanner\Rules\DuplicateIdRule;
use A11yFleet\Scanner\Rules\EmptyHeadingRule;
use A11yFleet\Scanner\Rules\FormControlLabelRule;
use A11yFleet\Scanner\Rules\HeadingOrderRule;
use A11yFleet\Scanner\Rules\IframeTitleRule;
use A11yFleet\Scanner\Rules\ImageAltRule;
use A11yFleet\Scanner\Rules\LinkTextRule;
use A11yFleet\Scanner\ScanService;
use A11yFleet\Settings\Settings;
use A11yFleet\Storage\IssueRepository;
use A11yFleet\Storage\ScanRepository;

/**
 * Plugin container + bootstrap.
 *
 * Deliberately a tiny service locator rather than a heavy DI container — a WP
 * plugin should boot fast and stay simple. Services are lazily instantiated
 * and shared.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Shared service instances keyed by class name.
	 *
	 * @var array<string, object>
	 */
	private array $services = array();

	/**
	 * Whether the plugin has already booted.
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * Returns the singleton plugin instance.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		return self::$instance ??= new self();
	}

	/**
	 * Private constructor — use instance() instead.
	 */
	private function __construct() {
	}

	/**
	 * Boots the plugin: loads text domain, wires admin UI, REST routes, and the auto-scan hook.
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		// Admin UI.
		if ( is_admin() ) {
			$this->adminMenu()->register();
			$this->assets()->register();
			$this->settingsPage()->register();
			$this->reportPage()->register();
		}

		// REST API for the dashboard SPA (and external/fleet use later).
		add_action( 'rest_api_init', fn () => $this->rest()->registerRoutes() );

		// Auto-scan content when it's saved (respects the scan-on-save setting).
		add_action( 'save_post', array( $this->scanService(), 'onSavePost' ), 20, 2 );

		// Cron + batched scanning, and the manual "scan all" action.
		$this->scheduler()->register();
	}

	/**
	 * Returns the shared scan engine.
	 *
	 * @return Engine
	 */
	public function engine(): Engine {
		return $this->services[ Engine::class ] ??= new Engine(
			array(
				new ImageAltRule(),
				new LinkTextRule(),
				new HeadingOrderRule(),
				new FormControlLabelRule(),
				new ButtonNameRule(),
				new DuplicateIdRule(),
				new EmptyHeadingRule(),
				new IframeTitleRule(),
				new DocumentLanguageRule(),
			)
		);
	}

	/**
	 * Returns the shared scan repository.
	 *
	 * @return ScanRepository
	 */
	public function scanRepository(): ScanRepository {
		return $this->services[ ScanRepository::class ] ??= new ScanRepository();
	}

	/**
	 * Returns the shared issue repository.
	 *
	 * @return IssueRepository
	 */
	public function issueRepository(): IssueRepository {
		return $this->services[ IssueRepository::class ] ??= new IssueRepository();
	}

	/**
	 * Returns the shared scan service.
	 *
	 * @return ScanService
	 */
	public function scanService(): ScanService {
		return $this->services[ ScanService::class ] ??= new ScanService(
			$this->engine(),
			$this->scanRepository(),
			$this->issueRepository(),
			new PageFetcher(),
			$this->settings(),
		);
	}

	/**
	 * Returns the shared settings service.
	 *
	 * @return Settings
	 */
	public function settings(): Settings {
		return $this->services[ Settings::class ] ??= new Settings();
	}

	/**
	 * Returns the shared scheduler.
	 *
	 * @return Scheduler
	 */
	private function scheduler(): Scheduler {
		return $this->services[ Scheduler::class ] ??= new Scheduler(
			$this->scanService(),
			$this->settings(),
		);
	}

	/**
	 * Returns the shared settings page.
	 *
	 * @return SettingsPage
	 */
	private function settingsPage(): SettingsPage {
		return $this->services[ SettingsPage::class ] ??= new SettingsPage( $this->settings() );
	}

	/**
	 * Returns the shared report page.
	 *
	 * @return ReportPage
	 */
	private function reportPage(): ReportPage {
		return $this->services[ ReportPage::class ] ??= new ReportPage(
			new ReportBuilder( $this->scanRepository(), $this->issueRepository() )
		);
	}

	/**
	 * Returns the shared admin menu component.
	 *
	 * @return AdminMenu
	 */
	private function adminMenu(): AdminMenu {
		return $this->services[ AdminMenu::class ] ??= new AdminMenu(
			$this->scanRepository(),
			$this->issueRepository(),
		);
	}

	/**
	 * Returns the shared admin assets component.
	 *
	 * @return Assets
	 */
	private function assets(): Assets {
		return $this->services[ Assets::class ] ??= new Assets();
	}

	/**
	 * Returns the shared REST controller.
	 *
	 * @return RestController
	 */
	private function rest(): RestController {
		return $this->services[ RestController::class ] ??= new RestController(
			$this->scanService(),
			$this->scanRepository(),
			$this->issueRepository(),
			$this->scheduler(),
		);
	}
}
