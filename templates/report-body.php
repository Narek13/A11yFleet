<?php
/**
 * Compliance report body (shared by the admin screen and the export document).
 *
 * @var array<string, mixed> $report
 *
 * @package A11yFleet
 */

declare(strict_types=1);

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- template variables scoped to include context.

defined('ABSPATH') || exit;

$totals      = $report['totals'];
$is_partial  = 'partial' === $report['conformance'];
$generated   = mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $report['generated_at']);
?>
<header class="a11yfleet-report__header">
    <h2><?php esc_html_e('Accessibility compliance report', 'a11yfleet'); ?></h2>
    <p>
        <strong><?php echo esc_html($report['site_name']); ?></strong> —
        <a href="<?php echo esc_url($report['site_url']); ?>"><?php echo esc_html($report['site_url']); ?></a><br>
        <?php
        printf(
            /* translators: 1: standard name, 2: date. */
            esc_html__('Measured against %1$s · generated %2$s', 'a11yfleet'),
            esc_html($report['standard']),
            esc_html($generated)
        );
        ?>
    </p>
</header>

<div class="a11yfleet-table-wrap">
<table class="a11yfleet-report__stats">
    <tbody>
        <tr>
            <th><?php esc_html_e('Average score', 'a11yfleet'); ?></th>
            <td><?php echo (int) $totals['avg_score']; ?>/100</td>
            <th><?php esc_html_e('Pages scanned', 'a11yfleet'); ?></th>
            <td><?php echo (int) $totals['pages_scanned']; ?></td>
        </tr>
        <tr>
            <th><?php esc_html_e('Pages with issues', 'a11yfleet'); ?></th>
            <td><?php echo (int) $totals['pages_with_issues']; ?></td>
            <th><?php esc_html_e('Open issues', 'a11yfleet'); ?></th>
            <td><?php echo (int) $totals['open_issues']; ?></td>
        </tr>
        <tr>
            <th><?php esc_html_e('Errors', 'a11yfleet'); ?></th>
            <td><?php echo (int) $totals['errors']; ?></td>
            <th><?php esc_html_e('Warnings / notices', 'a11yfleet'); ?></th>
            <td><?php echo (int) $totals['warnings']; ?> / <?php echo (int) $totals['notices']; ?></td>
        </tr>
    </tbody>
</table>
</div>

<h3><?php esc_html_e('Outstanding issues by WCAG success criterion', 'a11yfleet'); ?></h3>
<?php if (empty($report['criteria'])) : ?>
    <p><?php esc_html_e('No automatable issues are currently outstanding.', 'a11yfleet'); ?></p>
<?php else : ?>
    <div class="a11yfleet-table-wrap">
    <table class="a11yfleet-report__criteria">
        <thead>
            <tr>
                <th><?php esc_html_e('Criterion', 'a11yfleet'); ?></th>
                <th><?php esc_html_e('Title', 'a11yfleet'); ?></th>
                <th><?php esc_html_e('Severity', 'a11yfleet'); ?></th>
                <th><?php esc_html_e('Issues', 'a11yfleet'); ?></th>
                <th><?php esc_html_e('Pages', 'a11yfleet'); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($report['criteria'] as $criterion) : ?>
            <tr>
                <td><?php echo esc_html($criterion['wcag']); ?></td>
                <td><?php echo esc_html($criterion['title']); ?></td>
                <td><?php echo esc_html(ucfirst($criterion['severity'])); ?></td>
                <td><?php echo (int) $criterion['issue_count']; ?></td>
                <td><?php echo (int) $criterion['page_count']; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>

<h3><?php esc_html_e('Accessibility statement (draft)', 'a11yfleet'); ?></h3>
<div class="a11yfleet-report__statement">
    <p>
        <?php
        printf(
            /* translators: 1: site name, 2: standard. */
            esc_html__('%1$s is committed to making its website accessible, in line with the European Accessibility Act and %2$s.', 'a11yfleet'),
            esc_html($report['site_name']),
            esc_html($report['standard'])
        );
        ?>
    </p>
    <p>
        <strong><?php esc_html_e('Conformance status:', 'a11yfleet'); ?></strong>
        <?php if ($is_partial) : ?>
            <?php
            printf(
                /* translators: 1: standard, 2: open issue count, 3: page count, 4: date. */
                esc_html__('Based on automated testing, this website is partially conformant with %1$s. Automated checks identified %2$d issues across %3$d pages as of %4$s. “Partially conformant” means some content does not yet fully conform.', 'a11yfleet'),
                esc_html($report['standard']),
                (int) $totals['open_issues'],
                (int) $totals['pages_with_issues'],
                esc_html($generated)
            );
            ?>
        <?php else : ?>
            <?php
            printf(
                /* translators: 1: standard, 2: date. */
                esc_html__('Automated testing on %2$s found no outstanding automatable issues against %1$s. Note that automated testing does not cover all accessibility requirements.', 'a11yfleet'),
                esc_html($report['standard']),
                esc_html($generated)
            );
            ?>
        <?php endif; ?>
    </p>
    <p>
        <strong><?php esc_html_e('Feedback:', 'a11yfleet'); ?></strong>
        <?php
        printf(
            /* translators: %s: contact email or URL. */
            esc_html__('If you encounter accessibility barriers, please contact %s.', 'a11yfleet'),
            esc_html($report['contact'])
        );
        ?>
    </p>
    <p>
        <strong><?php esc_html_e('Preparation:', 'a11yfleet'); ?></strong>
        <?php
        printf(
            /* translators: %s: date. */
            esc_html__('This statement was generated from automated testing with A11yFleet on %s. Automated tools detect only a portion of accessibility issues; a complete evaluation requires manual testing.', 'a11yfleet'),
            esc_html($generated)
        );
        ?>
    </p>
</div>

<p class="a11yfleet-report__disclaimer">
    <em><?php esc_html_e('Draft based on automated results only. This is not a substitute for a manual audit or a legally reviewed accessibility statement — review before publishing.', 'a11yfleet'); ?></em>
</p>
