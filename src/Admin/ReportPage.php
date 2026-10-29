<?php

declare(strict_types=1);

namespace A11yFleet\Admin;

use A11yFleet\Report\ReportBuilder;

/**
 * Registers the compliance report screen and its print styles/script.
 */
final class ReportPage {

	/**
	 * Constructor.
	 *
	 * @param ReportBuilder $builder Report aggregator.
	 */
	public function __construct(
		private readonly ReportBuilder $builder,
	) {
	}

	/**
	 * Hooks the report submenu + its assets.
	 */
	public function register(): void {
		add_action(
			'admin_menu',
			function (): void {
				add_submenu_page(
					'a11yfleet',
					__( 'A11yFleet Compliance Report', 'a11yfleet' ),
					__( 'Report', 'a11yfleet' ),
					'manage_options',
					'a11yfleet-report',
					array( $this, 'render' )
				);
			}
		);

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueues the report's styles (screen + print) and print trigger, only on
	 * the report screen.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function enqueue( string $hook ): void {
		if ( 'a11yfleet_page_a11yfleet-report' !== $hook ) {
			return;
		}

		$css = '.a11yfleet-report table{border-collapse:collapse;width:100%;max-width:820px;margin:12px 0;background:#fff}'
			. '.a11yfleet-report th,.a11yfleet-report td{border:1px solid #c3c4c7;padding:6px 10px;text-align:left;vertical-align:top}'
			. '.a11yfleet-report__stats th{background:#f6f7f7;width:22%}'
			. '.a11yfleet-report__statement,.a11yfleet-report__disclaimer{max-width:820px}'
			. '.a11yfleet-report__disclaimer{color:#646970}'
			. '.a11yfleet-table-wrap{overflow-x:auto;max-width:820px}'
			. '.a11yfleet-table-wrap table{margin:0}'
			. '@media screen and (max-width:782px){.a11yfleet-report th,.a11yfleet-report td{padding:6px}.a11yfleet-print-actions .button{min-height:44px;line-height:42px}}'
			. '@media print{#adminmenumain,#wpadminbar,#wpfooter,.a11yfleet-print-actions,.notice,.update-nag{display:none!important}'
			. '#wpcontent,#wpbody-content{margin:0!important;padding:0!important}}';

		$js = "document.addEventListener('click',function(e){var b=e.target.closest('#a11yfleet-print');if(b){e.preventDefault();window.print();}});";

		wp_register_style( 'a11yfleet-report', false, array(), A11YFLEET_VERSION );
		wp_enqueue_style( 'a11yfleet-report' );
		wp_add_inline_style( 'a11yfleet-report', $css );

		wp_register_script( 'a11yfleet-report', false, array(), A11YFLEET_VERSION, true );
		wp_enqueue_script( 'a11yfleet-report' );
		wp_add_inline_script( 'a11yfleet-report', $js );
	}

	/**
	 * Renders the report admin screen.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$report = $this->builder->build();

		require A11YFLEET_DIR . 'templates/report-page.php';
	}
}
