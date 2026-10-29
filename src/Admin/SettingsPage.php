<?php

declare(strict_types=1);

namespace A11yFleet\Admin;

use A11yFleet\Settings\Settings;

/**
 * Registers and renders the A11yFleet settings screen (Settings API).
 */
final class SettingsPage {

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings service.
	 */
	public function __construct(
		private readonly Settings $settings,
	) {
	}

	/**
	 * Hooks the settings submenu + setting registration.
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this->settings, 'register' ) );

		add_action(
			'admin_menu',
			function (): void {
				add_submenu_page(
					'a11yfleet',
					__( 'A11yFleet Settings', 'a11yfleet' ),
					__( 'Settings', 'a11yfleet' ),
					'manage_options',
					'a11yfleet-settings',
					array( $this, 'render' )
				);
			}
		);
	}

	/**
	 * Renders the settings form.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$current    = $this->settings->get();
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		$option     = Settings::OPTION;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'A11yFleet Settings', 'a11yfleet' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'a11yfleet' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Scan these post types', 'a11yfleet' ); ?></th>
						<td>
							<?php foreach ( $post_types as $type ) : ?>
								<label style="display:block;margin-bottom:4px">
									<input
										type="checkbox"
										name="<?php echo esc_attr( $option ); ?>[post_types][]"
										value="<?php echo esc_attr( $type->name ); ?>"
										<?php checked( in_array( $type->name, (array) $current['post_types'], true ) ); ?>
									>
									<?php echo esc_html( $type->labels->singular_name ); ?>
									<code><?php echo esc_html( $type->name ); ?></code>
								</label>
							<?php endforeach; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Auto-scan on save', 'a11yfleet' ); ?></th>
						<td>
							<label>
								<input
									type="checkbox"
									name="<?php echo esc_attr( $option ); ?>[scan_on_save]"
									value="1"
									<?php checked( ! empty( $current['scan_on_save'] ) ); ?>
								>
								<?php esc_html_e( 'Scan content automatically when it is published or updated.', 'a11yfleet' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Scheduled full-site scan', 'a11yfleet' ); ?></th>
						<td>
							<select name="<?php echo esc_attr( $option ); ?>[cron]">
								<?php
								$labels = array(
									'off'        => __( 'Off', 'a11yfleet' ),
									'hourly'     => __( 'Hourly', 'a11yfleet' ),
									'twicedaily' => __( 'Twice daily', 'a11yfleet' ),
									'daily'      => __( 'Daily', 'a11yfleet' ),
									'weekly'     => __( 'Weekly', 'a11yfleet' ),
								);
								foreach ( $labels as $value => $label ) :
									?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current['cron'], $value ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description">
								<?php esc_html_e( 'Re-scans all selected content in the background, in batches.', 'a11yfleet' ); ?>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
