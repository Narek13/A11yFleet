/**
 * A11yFleet block-editor panel.
 *
 * Shows the post's stored accessibility scan (in sync with the dashboard:
 * statuses, ignore/restore), and lets the author re-scan the content (unsaved)
 * or the full rendered page. Built by wp-scripts into assets/js/editor.js.
 */
import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/edit-post';
import { useSelect } from '@wordpress/data';
import { useState, useEffect } from '@wordpress/element';
import { Button, Spinner, Notice } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';

const SEVERITY_ORDER = { error: 0, warning: 1, notice: 2 };
const PER_PAGE = 5;

const SEVERITY_COLORS = {
	error: { bg: '#fcebec', fg: '#b32d2e' },
	warning: { bg: '#fcf3e6', fg: '#996800' },
	notice: { bg: '#edf4fb', fg: '#2271b1' },
};

function badge( bg, fg ) {
	return {
		display: 'inline-block',
		background: bg,
		color: fg,
		borderRadius: '3px',
		padding: '0 5px',
		fontSize: '10px',
		fontWeight: 600,
		textTransform: 'uppercase',
	};
}

function severityBadgeStyle( severity ) {
	const c = SEVERITY_COLORS[ severity ] || SEVERITY_COLORS.notice;
	return badge( c.bg, c.fg );
}

function statusBadgeStyle( status ) {
	return 'ignored' === status
		? badge( '#f0f0f1', '#646970' )
		: badge( '#edf4fb', '#2271b1' );
}

function sortIssues( issues ) {
	return ( issues || [] )
		.slice()
		.sort(
			( a, b ) =>
				( SEVERITY_ORDER[ a.severity ] ?? 9 ) -
				( SEVERITY_ORDER[ b.severity ] ?? 9 )
		);
}

function countBy( issues, severity ) {
	return issues.filter( ( i ) => i.severity === severity ).length;
}

function A11yFleetPanel() {
	const postId = useSelect(
		( select ) => select( 'core/editor' ).getCurrentPostId(),
		[]
	);
	const postContent = useSelect(
		( select ) => select( 'core/editor' ).getEditedPostContent(),
		[]
	);

	const [ loading, setLoading ] = useState( false );
	const [ result, setResult ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ busyId, setBusyId ] = useState( 0 );
	const [ page, setPage ] = useState( 1 );

	const apply = ( res ) => {
		if ( res && res.issues ) {
			res.issues = sortIssues( res.issues );
		}
		setResult( res );
		setPage( 1 );
	};

	// Load the stored scan (with statuses) when the panel opens.
	useEffect( () => {
		if ( ! postId ) {
			return;
		}
		apiFetch( { path: `/a11yfleet/v1/scans/by-post/${ postId }` } )
			.then( ( res ) => {
				if ( res && res.scan_id ) {
					apply( res );
				}
			} )
			.catch( () => {} );
	}, [ postId ] );

	const run = async ( path, data ) => {
		setLoading( true );
		setError( null );
		try {
			const options = { path, method: 'POST' };
			if ( data ) {
				options.data = data;
			}
			apply( await apiFetch( options ) );
		} catch ( e ) {
			setError( e.message || __( 'Scan failed.', 'a11yfleet' ) );
		} finally {
			setLoading( false );
		}
	};

	const toggle = async ( issue ) => {
		if ( ! issue.id ) {
			return;
		}
		const target = 'ignored' === issue.status ? 'open' : 'ignored';
		setBusyId( issue.id );
		try {
			const res = await apiFetch( {
				path: `/a11yfleet/v1/issues/${ issue.id }/status`,
				method: 'POST',
				data: { status: target },
			} );
			setResult( ( prev ) => {
				if ( ! prev ) {
					return prev;
				}
				const issues = prev.issues.map( ( i ) =>
					i.id === issue.id ? { ...i, status: target } : i
				);
				const next = { ...prev, issues };
				if ( res && res.scan ) {
					next.score = res.scan.score;
					next.issue_count = res.scan.issue_count;
				}
				return next;
			} );
		} catch ( e ) {
			setError( e.message || __( 'Update failed.', 'a11yfleet' ) );
		} finally {
			setBusyId( 0 );
		}
	};

	const issues = result ? result.issues : [];
	const totalPages = Math.max( 1, Math.ceil( issues.length / PER_PAGE ) );
	const currentPage = Math.min( page, totalPages );
	const visibleIssues = issues.slice(
		( currentPage - 1 ) * PER_PAGE,
		( currentPage - 1 ) * PER_PAGE + PER_PAGE
	);

	return (
		<PluginDocumentSettingPanel
			name="a11yfleet-panel"
			title={ __( 'Accessibility', 'a11yfleet' ) }
			className="a11yfleet-panel"
		>
			<div className="a11yfleet-panel__actions">
				<Button
					variant="primary"
					disabled={ loading }
					onClick={ () =>
						run( '/a11yfleet/v1/scan-html', {
							html: postContent,
							post_id: postId,
						} )
					}
				>
					{ __( 'Scan content', 'a11yfleet' ) }
				</Button>{ ' ' }
				<Button
					variant="secondary"
					disabled={ loading }
					onClick={ () =>
						run( `/a11yfleet/v1/scan-page/${ postId }` )
					}
				>
					{ __( 'Scan full page', 'a11yfleet' ) }
				</Button>
			</div>

			{ loading && <Spinner /> }

			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			{ ! loading && ! result && (
				<p>
					{ __( 'Run a scan to check this content.', 'a11yfleet' ) }
				</p>
			) }

			{ ! loading && result && (
				<div className="a11yfleet-panel__result">
					<p style={ { margin: '0 0 4px' } }>
						<strong>
							{ sprintf(
								/* translators: %d: score out of 100. */
								__( 'Score %d/100', 'a11yfleet' ),
								result.score
							) }
						</strong>
					</p>

					{ result.issues.length === 0 ? (
						<Notice status="success" isDismissible={ false }>
							{ __(
								'No automatable issues found. Remember to also review manually.',
								'a11yfleet'
							) }
						</Notice>
					) : (
						<>
							<p
								style={ {
									margin: '0 0 8px',
									fontSize: '12px',
									color: '#50575e',
								} }
							>
								{ sprintf(
									/* translators: 1: errors, 2: warnings, 3: notices. */
									__(
										'%1$d errors · %2$d warnings · %3$d notices',
										'a11yfleet'
									),
									countBy( result.issues, 'error' ),
									countBy( result.issues, 'warning' ),
									countBy( result.issues, 'notice' )
								) }
							</p>

							<ul
								style={ {
									listStyle: 'none',
									margin: 0,
									padding: 0,
								} }
							>
								{ visibleIssues.map( ( issue, index ) => (
									<li
										key={ issue.id || index }
										style={ {
											borderTop: '1px solid #e0e0e0',
											padding: '6px 0',
											opacity:
												'ignored' === issue.status
													? 0.55
													: 1,
										} }
									>
										<details>
											<summary
												style={ {
													cursor: 'pointer',
													fontSize: '12px',
													lineHeight: 1.5,
												} }
											>
												<span
													style={ severityBadgeStyle(
														issue.severity
													) }
												>
													{ issue.severity }
												</span>{ ' ' }
												<span
													style={ statusBadgeStyle(
														issue.status
													) }
												>
													{ issue.status }
												</span>{ ' ' }
												{ issue.message }
											</summary>

											<div
												style={ {
													marginTop: '6px',
													fontSize: '11px',
												} }
											>
												{ issue.context && (
													<code
														style={ {
															display: 'block',
															overflowX: 'auto',
															whiteSpace: 'pre',
															background:
																'#f0f0f1',
															padding: '4px 6px',
															borderRadius: '3px',
															margin: '0 0 6px',
														} }
													>
														{ issue.context }
													</code>
												) }
												{ issue.how_to_fix && (
													<p
														style={ {
															margin: '0 0 4px',
															color: '#50575e',
														} }
													>
														{ issue.how_to_fix }
													</p>
												) }
												<span
													style={ {
														color: '#787c82',
													} }
												>
													{ `WCAG ${ issue.wcag }` }
												</span>
												{ issue.id && (
													<div
														style={ {
															marginTop: '4px',
														} }
													>
														<Button
															variant="link"
															isBusy={
																busyId ===
																issue.id
															}
															disabled={
																busyId ===
																issue.id
															}
															onClick={ () =>
																toggle( issue )
															}
														>
															{ 'ignored' ===
															issue.status
																? __(
																		'Restore',
																		'a11yfleet'
																  )
																: __(
																		'Ignore',
																		'a11yfleet'
																  ) }
														</Button>
													</div>
												) }
											</div>
										</details>
									</li>
								) ) }
							</ul>

							{ totalPages > 1 && (
								<div
									className="a11yfleet-panel__pager"
									style={ {
										display: 'flex',
										alignItems: 'center',
										gap: '8px',
										marginTop: '8px',
									} }
								>
									<Button
										variant="secondary"
										disabled={ currentPage <= 1 }
										onClick={ () =>
											setPage( currentPage - 1 )
										}
									>
										{ __( 'Prev', 'a11yfleet' ) }
									</Button>
									<span
										style={ {
											fontSize: '12px',
											color: '#50575e',
										} }
									>
										{ sprintf(
											/* translators: 1: current page, 2: total pages. */
											__(
												'Page %1$d of %2$d',
												'a11yfleet'
											),
											currentPage,
											totalPages
										) }
									</span>
									<Button
										variant="secondary"
										disabled={ currentPage >= totalPages }
										onClick={ () =>
											setPage( currentPage + 1 )
										}
									>
										{ __( 'Next', 'a11yfleet' ) }
									</Button>
								</div>
							) }
						</>
					) }
				</div>
			) }
		</PluginDocumentSettingPanel>
	);
}

registerPlugin( 'a11yfleet', { render: A11yFleetPanel } );
