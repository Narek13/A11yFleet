/**
 * A11yFleet admin dashboard script.
 *
 * Adds ignore/restore behaviour to the issues listed on the dashboard and
 * updates the per-page score and summary cards live. REST root + nonce are
 * injected via wp_localize_script as `window.A11yFleet`.
 */
import '../css/index.css';
import { __, sprintf } from '@wordpress/i18n';

( function () {
	'use strict';

	const config = window.A11yFleet;
	if ( ! config ) {
		return;
	}

	async function setStatus( issueId, status ) {
		const response = await fetch(
			`${ config.restUrl }/issues/${ issueId }/status`,
			{
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': config.nonce,
				},
				body: JSON.stringify( { status } ),
			}
		);

		if ( ! response.ok ) {
			throw new Error( 'Request failed (' + response.status + ')' );
		}

		return response.json();
	}

	function setText( el, value ) {
		if ( el ) {
			el.textContent = String( value );
		}
	}

	function updateScanRow( scanEl, scan ) {
		if ( ! scanEl || ! scan ) {
			return;
		}
		scanEl.dataset.score = scan.score;
		scanEl.dataset.issues = scan.issue_count;
		scanEl.dataset.errors = scan.error_count;

		setText( scanEl.querySelector( '[data-score-value]' ), scan.score );
		setText(
			scanEl.querySelector( '[data-issue-count]' ),
			scan.issue_count
		);
		setText(
			scanEl.querySelector( '[data-error-count]' ),
			scan.error_count
		);

		const wrap = scanEl.querySelector( '[data-error-wrap]' );
		if ( wrap ) {
			wrap.style.display = scan.error_count > 0 ? '' : 'none';
		}
	}

	document.addEventListener( 'click', async function ( event ) {
		const button = event.target.closest( '.a11yfleet-toggle' );
		if ( ! button ) {
			return;
		}

		event.preventDefault();

		const issueId = button.dataset.issueId;
		const target = button.dataset.actionTarget; // 'ignored' or 'open'
		button.disabled = true;

		try {
			const json = await setStatus( issueId, target );

			const row = button.closest( '[data-issue-row]' );
			if ( row ) {
				row.dataset.status = target;
				const label = row.querySelector( '[data-status-label]' );
				if ( label ) {
					label.textContent =
						'ignored' === target
							? label.dataset.labelIgnored
							: label.dataset.labelOpen;
					label.className =
						'a11yfleet-status a11yfleet-status--' + target;
				}
			}

			if ( 'ignored' === target ) {
				button.textContent = button.dataset.labelRestore;
				button.dataset.actionTarget = 'open';
			} else {
				button.textContent = button.dataset.labelIgnore;
				button.dataset.actionTarget = 'ignored';
			}

			if ( json && json.scan ) {
				updateScanRow( button.closest( '.a11yfleet-scan' ), json.scan );
			}
		} catch ( error ) {
			window.alert( 'A11yFleet: ' + error.message ); // eslint-disable-line no-alert
		} finally {
			button.disabled = false;
		}
	} );

	// Poll background-scan progress when a scan is running.
	const progress = document.querySelector( '[data-a11yfleet-progress]' );
	if ( progress && ! progress.hidden ) {
		const fill = progress.querySelector( '.a11yfleet-progress__fill' );
		const text = progress.querySelector( '.a11yfleet-progress__text' );

		const poll = async () => {
			try {
				const response = await fetch(
					`${ config.restUrl }/scan-status`,
					{ headers: { 'X-WP-Nonce': config.nonce } }
				);
				if ( ! response.ok ) {
					return;
				}
				const status = await response.json();
				const total = status.total || 0;
				const done = status.done || 0;
				const pct = total ? Math.round( ( 100 * done ) / total ) : 100;

				if ( fill ) {
					fill.style.width = pct + '%';
				}
				if ( text ) {
					text.textContent = sprintf(
						/* translators: 1: completed, 2: total. */
						__( 'Scanning %1$d of %2$d pages…', 'a11yfleet' ),
						done,
						total
					);
				}

				if ( ! status.in_progress ) {
					clearInterval( timer );
					if ( fill ) {
						fill.style.width = '100%';
					}
					if ( text ) {
						text.textContent = __(
							'Scan complete — refreshing…',
							'a11yfleet'
						);
					}
					setTimeout( () => window.location.reload(), 1200 );
				}
			} catch ( e ) {
				// Ignore transient polling errors.
			}
		};

		const timer = setInterval( poll, 3000 );
		poll();
	}
} )();
