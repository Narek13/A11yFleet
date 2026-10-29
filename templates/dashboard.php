<?php
/**
 * Admin dashboard template.
 *
 * @var array{scans:int, issues:int, errors:int, avg_score:int} $summary
 * @var list<array<string, mixed>> $recent
 * @var string $scanAllUrl
 * @var int    $total
 * @var int    $paged
 * @var int    $pages
 * @var string $ptype
 * @var list<string> $object_types
 * @var array{total:int, remaining:int, done:int, in_progress:bool} $scan_status
 * @var int|null $queued
 * @var \A11yFleet\Admin\AdminMenu $this
 *
 * @package A11yFleet
 */

declare(strict_types=1);

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- template variables scoped to include context.

defined('ABSPATH') || exit;
?>
<div class="wrap a11yfleet">
    <h1><?php esc_html_e('A11yFleet — Accessibility', 'a11yfleet'); ?></h1>

    <?php if (0 === $queued) : ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <?php esc_html_e('No published content was found for the post types selected in Settings, so nothing was scanned.', 'a11yfleet'); ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=a11yfleet-settings')); ?>"><?php esc_html_e('Review settings', 'a11yfleet'); ?></a>
            </p>
        </div>
    <?php elseif (null !== $queued) : ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php
                printf(
                    /* translators: %d: number of pages queued for scanning. */
                    esc_html(_n('Scan started: %d page queued.', 'Scan started: %d pages queued.', $queued, 'a11yfleet')),
                    (int) $queued
                );
                ?>
            </p>
        </div>
    <?php endif; ?>

    <?php if ($scan_status['in_progress']) : ?>
        <div class="notice notice-info">
            <p>
                <?php
                printf(
                    /* translators: 1: completed pages, 2: total pages. */
                    esc_html__('Background scan in progress — %1$d of %2$d pages done. Results update automatically.', 'a11yfleet'),
                    (int) $scan_status['done'],
                    (int) $scan_status['total']
                );
                ?>
            </p>
        </div>
    <?php endif; ?>

    <div class="a11yfleet-cards">
        <div class="a11yfleet-card">
            <span class="a11yfleet-card__num"><?php echo esc_html((string) $summary['avg_score']); ?></span>
            <span class="a11yfleet-card__label"><?php esc_html_e('Avg. score', 'a11yfleet'); ?></span>
        </div>
        <div class="a11yfleet-card">
            <span class="a11yfleet-card__num"><?php echo esc_html((string) $summary['errors']); ?></span>
            <span class="a11yfleet-card__label"><?php esc_html_e('Open errors', 'a11yfleet'); ?></span>
        </div>
        <div class="a11yfleet-card">
            <span class="a11yfleet-card__num"><?php echo esc_html((string) $summary['issues']); ?></span>
            <span class="a11yfleet-card__label"><?php esc_html_e('Open issues', 'a11yfleet'); ?></span>
        </div>
        <div class="a11yfleet-card">
            <span class="a11yfleet-card__num"><?php echo esc_html((string) $summary['scans']); ?></span>
            <span class="a11yfleet-card__label"><?php esc_html_e('Pages scanned', 'a11yfleet'); ?></span>
        </div>
    </div>

    <p>
        <?php if ($scan_status['in_progress']) : ?>
            <span class="button button-primary disabled" aria-disabled="true">
                <?php esc_html_e('Scan all published content', 'a11yfleet'); ?>
            </span>
            <span class="button disabled" aria-disabled="true">
                <?php esc_html_e('View compliance report', 'a11yfleet'); ?>
            </span>
            <span class="description">
                <?php esc_html_e('A scan is running — please wait for it to finish.', 'a11yfleet'); ?>
            </span>
        <?php else : ?>
            <a href="<?php echo esc_url($scanAllUrl); ?>" class="button button-primary">
                <?php esc_html_e('Scan all published content', 'a11yfleet'); ?>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=a11yfleet-report')); ?>" class="button">
                <?php esc_html_e('View compliance report', 'a11yfleet'); ?>
            </a>
            <span class="description">
                <?php esc_html_e('Automated checks catch part of WCAG — always pair with a manual review.', 'a11yfleet'); ?>
            </span>
        <?php endif; ?>
    </p>

    <div
        class="a11yfleet-progress"
        data-a11yfleet-progress
        data-total="<?php echo (int) $scan_status['total']; ?>"
        <?php echo $scan_status['in_progress'] ? '' : 'hidden'; ?>
    >
        <div class="a11yfleet-progress__bar">
            <span
                class="a11yfleet-progress__fill"
                style="width:<?php echo $scan_status['total'] > 0 ? (int) round(100 * $scan_status['done'] / max(1, $scan_status['total'])) : 0; ?>%"
            ></span>
        </div>
        <span class="a11yfleet-progress__text">
            <?php esc_html_e('Scanning in the background…', 'a11yfleet'); ?>
        </span>
    </div>

    <form method="get" class="a11yfleet-filters">
        <input type="hidden" name="page" value="a11yfleet">
        <label>
            <?php esc_html_e('Post type', 'a11yfleet'); ?>
            <select name="ptype">
                <option value=""><?php esc_html_e('All', 'a11yfleet'); ?></option>
                <?php foreach ($object_types as $type) : ?>
                    <option value="<?php echo esc_attr($type); ?>" <?php selected($ptype, $type); ?>><?php echo esc_html($type); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="button"><?php esc_html_e('Filter', 'a11yfleet'); ?></button>
    </form>

    <?php if (empty($recent)) : ?>
        <p>
            <?php
            if ('' !== $ptype) {
                esc_html_e('No pages match this filter.', 'a11yfleet');
            } elseif ($summary['scans'] > 0) {
                printf(
                    /* translators: %d: number of scanned pages. */
                    esc_html(_n('The %d scanned page passed all automated checks. Pair this with a manual review.', 'All %d scanned pages passed the automated checks. Pair this with a manual review.', (int) $summary['scans'], 'a11yfleet')),
                    (int) $summary['scans']
                );
            } else {
                esc_html_e('Nothing has been scanned yet — click “Scan all published content” to start.', 'a11yfleet');
            }
            ?>
        </p>
    <?php else : ?>
        <p class="description">
            <?php
            printf(
                /* translators: 1: current page, 2: total pages, 3: number of pages with issues. */
                esc_html__('Page %1$d of %2$d — %3$d pages with issues (worst score first).', 'a11yfleet'),
                (int) $paged,
                (int) $pages,
                (int) $total
            );
            ?>
        </p>

        <div class="a11yfleet-scans">
        <?php foreach ($recent as $scan) : ?>
            <details
                class="a11yfleet-scan"
                data-scan-id="<?php echo (int) $scan['id']; ?>"
                data-score="<?php echo (int) $scan['score']; ?>"
                data-issues="<?php echo (int) $scan['issue_count']; ?>"
                data-errors="<?php echo (int) $scan['error_count']; ?>"
            >
                <summary class="a11yfleet-scan__summary">
                    <span class="a11yfleet-scan__name">
                        <?php echo esc_html(get_the_title((int) $scan['object_id']) ?: ('#' . $scan['object_id'])); ?>
                    </span>
                    <span class="a11yfleet-scan__meta">
                        <?php esc_html_e('Score', 'a11yfleet'); ?>
                        <strong data-score-value><?php echo (int) $scan['score']; ?></strong>/100
                        · <span data-issue-count><?php echo (int) $scan['issue_count']; ?></span> <?php esc_html_e('open', 'a11yfleet'); ?>
                        <span data-error-wrap style="<?php echo (int) $scan['error_count'] > 0 ? '' : 'display:none'; ?>">
                            · <span data-error-count><?php echo (int) $scan['error_count']; ?></span> <?php esc_html_e('errors', 'a11yfleet'); ?>
                        </span>
                    </span>
                </summary>

                <p class="a11yfleet-scan__links">
                    <a href="<?php echo esc_url(get_edit_post_link((int) $scan['object_id']) ?: '#'); ?>"><?php esc_html_e('Edit page', 'a11yfleet'); ?></a>
                    <?php $permalink = get_permalink((int) $scan['object_id']); ?>
                    <?php if ($permalink) : ?>
                        · <a href="<?php echo esc_url($permalink); ?>" target="_blank" rel="noopener"><?php esc_html_e('View page', 'a11yfleet'); ?></a>
                    <?php endif; ?>
                    · <span class="description"><?php echo esc_html((string) $scan['scanned_at']); ?> UTC</span>
                </p>

                <div class="a11yfleet-table-wrap">
                <table class="widefat striped a11yfleet-issues-table">
                    <thead>
                        <tr>
                            <th class="a11yfleet-col-sev"><?php esc_html_e('Severity', 'a11yfleet'); ?></th>
                            <th><?php esc_html_e('Issue', 'a11yfleet'); ?></th>
                            <th class="a11yfleet-col-wcag"><?php esc_html_e('WCAG', 'a11yfleet'); ?></th>
                            <th class="a11yfleet-col-status"><?php esc_html_e('Status', 'a11yfleet'); ?></th>
                            <th class="a11yfleet-col-action"><?php esc_html_e('Action', 'a11yfleet'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php $issues = $this->issuesFor((int) $scan['id']); ?>
                    <?php foreach ($issues as $issue) : ?>
                        <?php $isIgnored = 'ignored' === $issue['status']; ?>
                        <tr data-issue-row data-status="<?php echo esc_attr($issue['status']); ?>">
                            <td>
                                <span class="a11yfleet-sev a11yfleet-sev--<?php echo esc_attr($issue['severity']); ?>"><?php echo esc_html($issue['severity']); ?></span>
                            </td>
                            <td>
                                <?php echo esc_html($issue['message']); ?>
                                <?php if (! empty($issue['context'])) : ?>
                                    <br><code class="a11yfleet-issue__context"><?php echo esc_html((string) $issue['context']); ?></code>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html((string) $issue['wcag']); ?></td>
                            <td>
                                <span
                                    class="a11yfleet-status a11yfleet-status--<?php echo esc_attr($issue['status']); ?>"
                                    data-status-label
                                    data-label-open="<?php echo esc_attr__('Open', 'a11yfleet'); ?>"
                                    data-label-ignored="<?php echo esc_attr__('Ignored', 'a11yfleet'); ?>"
                                ><?php echo $isIgnored ? esc_html__('Ignored', 'a11yfleet') : esc_html__('Open', 'a11yfleet'); ?></span>
                            </td>
                            <td>
                                <button
                                    type="button"
                                    class="button-link a11yfleet-toggle"
                                    data-issue-id="<?php echo (int) $issue['id']; ?>"
                                    data-action-target="<?php echo $isIgnored ? 'open' : 'ignored'; ?>"
                                    data-label-ignore="<?php echo esc_attr__('Ignore', 'a11yfleet'); ?>"
                                    data-label-restore="<?php echo esc_attr__('Restore', 'a11yfleet'); ?>"
                                ><?php echo $isIgnored ? esc_html__('Restore', 'a11yfleet') : esc_html__('Ignore', 'a11yfleet'); ?></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($issues)) : ?>
                        <tr><td colspan="5"><?php esc_html_e('No issues found on this page.', 'a11yfleet'); ?></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </details>
        <?php endforeach; ?>
        </div>

        <?php if ($pages > 1) : ?>
            <nav class="a11yfleet-pagination tablenav">
                <div class="tablenav-pages">
                    <?php
                    echo wp_kses_post(
                        (string) paginate_links(
                            array(
                                'base'    => add_query_arg('paged', '%#%'),
                                'format'  => '',
                                'current' => (int) $paged,
                                'total'   => (int) $pages,
                            )
                        )
                    );
                    ?>
                </div>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>
