<?php
/**
 * Admin compliance report screen.
 *
 * @var array<string, mixed> $report
 *
 * @package A11yFleet
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
?>
<div class="wrap a11yfleet a11yfleet-report">
    <h1><?php esc_html_e('A11yFleet — Compliance Report', 'a11yfleet'); ?></h1>

    <p class="a11yfleet-print-actions">
        <button type="button" id="a11yfleet-print" class="button button-primary">
            <?php esc_html_e('Print / Save as PDF', 'a11yfleet'); ?>
        </button>
    </p>

    <?php require __DIR__ . '/report-body.php'; ?>
</div>
