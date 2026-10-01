<?php
/**
 * Settings class for Nik VoiceDesk AI.
 * Unified design matching the Ticket Console, Leaderboard Banner, Pricing Grid, Dark/Light mode, and Automated Licensing.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nikvotia_Settings {
	

	public function init() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'update_option_nikvotia_telemetry_optin', array( $this, 'on_telemetry_toggle' ), 10, 2 );
	}

	public function on_telemetry_toggle( $old_value, $new_value ) {
		if ( 'yes' === $new_value ) {
			Nikvotia_Telemetry::send_telemetry();
		}
	}

	/**
	 * Reliably detect if current admin view is part of VoiceDesk.
	 */
	public static function is_plugin_admin_page() {
		global $post_type;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page detection.
		$get_pt = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
		$current_pt = $post_type ?: $get_pt;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page detection.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'voicedesk_ticket' === $current_pt || 'nikvotia-settings' === $page || 'nikvotia-about' === $page  ) {
			return true;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page detection.
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		if ( $post_id && get_post_type( $post_id ) === 'voicedesk_ticket' ) {
			return true;
		}
		return false;
	}

	public function enqueue_admin_scripts( $hook ) {
		if ( ! self::is_plugin_admin_page() ) {
			return;
		}
		wp_enqueue_style( 'nikvotia-admin', NIKVOTIA_URL . 'assets/css/admin.css', array(), time() );
		wp_enqueue_script( 'nikvotia-admin', NIKVOTIA_URL . 'assets/js/admin.js', array(), time(), true );
	}

	public function add_settings_page() {
		add_submenu_page(
			'edit.php?post_type=voicedesk_ticket',
			__( 'VoiceDesk Settings', 'nik-voice-ticketing-ai' ),
			__( 'Settings', 'nik-voice-ticketing-ai' ),
			'manage_options',
			'nikvotia-settings',
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			'edit.php?post_type=voicedesk_ticket',
			__( 'About Nik Neural AI Inc.', 'nik-voice-ticketing-ai' ),
			__( 'About', 'nik-voice-ticketing-ai' ),
			'manage_options',
			'nikvotia-about',
			array( $this, 'render_about_page' )
		);
	}

	public function register_settings() {
		// General Settings
		register_setting( 'nikvotia_general', 'nikvotia_portal_page_id', array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 0,
		) );
		register_setting( 'nikvotia_general', 'nikvotia_visibility_post_types', array(
			'type'              => 'array',
			'sanitize_callback' => array( $this, 'sanitize_array_of_strings' ),
			'default'           => array(),
		) );
		register_setting( 'nikvotia_general', 'nikvotia_visibility_user_status', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'all',
		) );
		register_setting( 'nikvotia_general', 'nikvotia_visibility_specific_users', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		) );
		register_setting( 'nikvotia_general', 'nikvotia_departments', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'Sales, Technical Support, Billing, General Support',
		) );
		register_setting( 'nikvotia_general', 'nikvotia_telemetry_optin', array(
			'type'              => 'string',
			'sanitize_callback' => array( $this, 'sanitize_telemetry_optin' ),
			'default'           => 'no',
		) );

		// API Integrations
		register_setting( 'nikvotia_api', 'nikvotia_stt_model', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'openai_whisper',
		) );
		register_setting( 'nikvotia_api', 'nikvotia_llm_model', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'openai_gpt',
		) );
		register_setting( 'nikvotia_api', 'nikvotia_openai_key', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		) );
		register_setting( 'nikvotia_api', 'nikvotia_modulate_key', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		) );

		// Appearance Settings
		register_setting( 'nikvotia_appearance', 'nikvotia_btn_color', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_hex_color',
			'default'           => '#ffd700',
		) );
		register_setting( 'nikvotia_appearance', 'nikvotia_icon_color', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_hex_color',
			'default'           => '#333333',
		) );
		register_setting( 'nikvotia_appearance', 'nikvotia_tooltip_text', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'Speak your issue and click on problematic areas.',
		) );
		register_setting( 'nikvotia_appearance', 'nikvotia_custom_icon', array(
			'type'              => 'string',
			'sanitize_callback' => array( $this, 'sanitize_svg_code' ),
			'default'           => '',
		) );
		register_setting( 'nikvotia_appearance', 'nikvotia_show_attribution', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '0',
		) );


	}

	/**
	 * Sanitize telemetry opt-in and trigger immediate sync whenever saved as enabled.
	 *
	 * @param string $input Submitted value.
	 * @return string
	 */
	public function sanitize_telemetry_optin( $input ) {
		$sanitized = ( 'yes' === $input ) ? 'yes' : 'no';
		if ( 'yes' === $sanitized ) {
			Nikvotia_Telemetry::send_telemetry( true );
		}
		return $sanitized;
	}

	public function sanitize_array_of_strings( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}
		return array_map( 'sanitize_key', $input );
	}

	public function sanitize_svg_code( $input ) {
		$allowed = array(
			'svg'   => array(
				'viewbox'        => true,
				'viewBox'        => true,
				'width'          => true,
				'height'         => true,
				'stroke'         => true,
				'stroke-width'   => true,
				'fill'           => true,
				'stroke-linecap' => true,
				'stroke-linejoin'=> true,
				'class'          => true,
				'xmlns'          => true,
			),
			'path'  => array(
				'd'      => true,
				'fill'   => true,
				'stroke' => true,
			),
			'line'  => array(
				'x1'     => true,
				'y1'     => true,
				'x2'     => true,
				'y2'     => true,
				'stroke' => true,
			),
			'circle'=> array(
				'cx'     => true,
				'cy'     => true,
				'r'      => true,
				'fill'   => true,
				'stroke' => true,
			),
		);
		return wp_kses( $input, $allowed );
	}

	/**
	 * Check if Enterprise License is active.
	 */
	public static function is_enterprise() {
		return false;
	}

	/**
	 * Render responsive Leaderboard Ad Banner.
	 * Neutralized per WordPress.org Plugin Directory guidelines (no trialware ad banners).
	 */
	public static function render_leaderboard_banner() {
		return;
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only active tab parameter.
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'general';
		?>
		<div class="wrap nik-vd-settings-wrap">
			<div class="nik-vd-admin-console nik-vd-settings-console">
				<!-- Header Bar with Unified Styling & Dark/Light Toggle -->
				<div class="nik-vd-header-bar">
					<div class="nik-vd-header-left">
						<div class="nik-vd-ticket-id">
							<span class="dashicons dashicons-admin-generic" style="color: #38bdf8;"></span>
							<span class="nik-vd-id-label"><?php esc_html_e( 'VoiceDesk', 'nik-voice-ticketing-ai' ); ?></span>
							<span class="nik-vd-id-value"><?php esc_html_e( 'Settings', 'nik-voice-ticketing-ai' ); ?></span>
						</div>
						<div class="nik-vd-customer-pill">
							<span class="dashicons dashicons-tag"></span>
							<strong>v<?php echo esc_html( NIKVOTIA_VERSION ); ?></strong>
						</div>
						<span class="nik-status-pill nik-status-resolved"><?php esc_html_e( 'ACTIVE', 'nik-voice-ticketing-ai' ); ?></span>
					</div>

					<div class="nik-vd-header-right">
						<!-- Theme Switcher Button -->
						<button type="button" class="nik-vd-theme-btn" id="nik-vd-theme-toggle" aria-label="<?php esc_attr_e( 'Toggle Dark or Light Mode', 'nik-voice-ticketing-ai' ); ?>">
							<span class="nik-vd-theme-icon">🌙</span>
							<span class="nik-vd-theme-text"><?php esc_html_e( 'Dark Mode', 'nik-voice-ticketing-ai' ); ?></span>
						</button>

						<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=voicedesk_ticket' ) ); ?>" class="button button-secondary nik-vd-back-btn">
							← <?php esc_html_e( 'Tickets Queue', 'nik-voice-ticketing-ai' ); ?>
						</a>
					</div>
				</div>

				<!-- Unified Navigation Tabs -->
				<div class="nik-vd-settings-tabs-bar">
					<a href="?post_type=voicedesk_ticket&page=nikvotia-settings&tab=general" class="nik-vd-tab-link <?php echo $active_tab === 'general' ? 'is-active' : ''; ?>">
						<span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e( 'General & Visibility', 'nik-voice-ticketing-ai' ); ?>
					</a>
					<a href="?post_type=voicedesk_ticket&page=nikvotia-settings&tab=appearance" class="nik-vd-tab-link <?php echo $active_tab === 'appearance' ? 'is-active' : ''; ?>">
						<span class="dashicons dashicons-art"></span> <?php esc_html_e( 'Button & Appearance', 'nik-voice-ticketing-ai' ); ?>
					</a>
					<a href="?post_type=voicedesk_ticket&page=nikvotia-settings&tab=api" class="nik-vd-tab-link <?php echo $active_tab === 'api' ? 'is-active' : ''; ?>">
						<span class="dashicons dashicons-rest-api"></span> <?php esc_html_e( 'AI & API Providers', 'nik-voice-ticketing-ai' ); ?>
					</a>
					<a href="?post_type=voicedesk_ticket&page=nikvotia-settings&tab=pro" class="nik-vd-tab-link <?php echo ( $active_tab === 'pro' || $active_tab === 'license' ) ? 'is-active' : ''; ?>">
						<span class="dashicons dashicons-awards"></span> <?php esc_html_e( 'Pro & Cloud Add-ons', 'nik-voice-ticketing-ai' ); ?>
					</a>
				</div>

				<!-- Tab Content Card -->
				<div class="nik-vd-settings-body">
					<form method="post" action="options.php">
						<?php
						if ( 'general' === $active_tab ) {
							settings_fields( 'nikvotia_general' );
							$this->render_general_tab();
						} elseif ( 'appearance' === $active_tab ) {
							settings_fields( 'nikvotia_appearance' );
							$this->render_appearance_tab();
						} elseif ( 'api' === $active_tab ) {
							settings_fields( 'nikvotia_api' );
							$this->render_api_tab();
						} elseif ( 'pro' === $active_tab || 'license' === $active_tab ) {
							$this->render_pro_tab();
						}
						
						echo '<div class="nik-vd-form-footer">';
						submit_button( __( 'Save All Changes', 'nik-voice-ticketing-ai' ), 'primary button-large nik-vd-save-btn', 'submit', false );
						echo '</div>';
						?>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	private function render_general_tab() {
		$post_types_selected = get_option( 'nikvotia_visibility_post_types', array( ) );
		$user_status = get_option( 'nikvotia_visibility_user_status', 'all'  );
		$specific_users = get_option( 'nikvotia_visibility_specific_users', ''  );
		$departments = get_option( 'nikvotia_departments', 'Sales, Technical Support, Billing, General Support'  );
		$public_post_types = get_post_types( array( 'public' => true ), 'objects' );
		$portal_page_id = get_option( 'nikvotia_portal_page_id', 0  );
		?>
		<div class="nik-vd-settings-section">
			<h3><span class="dashicons dashicons-admin-home"></span> <?php esc_html_e( 'Customer Portal & Shortcode', 'nik-voice-ticketing-ai' ); ?></h3>
			<table class="form-table nik-vd-table">
				<tr>
					<th scope="row"><label for="nikvotia_portal_page_id"><?php esc_html_e( 'Customer Portal Page', 'nik-voice-ticketing-ai' ); ?></label></th>
					<td>
						<?php
						wp_dropdown_pages( array(
							'name'              => 'nikvotia_portal_page_id',
							'id'                => 'nikvotia_portal_page_id',
							'show_option_none'  => esc_html__( '— Select a Page —', 'nik-voice-ticketing-ai' ),
							'option_none_value' => '0',
							'selected'          => absint( $portal_page_id ),
							'class'             => 'nik-vd-input-select',
						) );
						?>
						<p class="description">
							<?php esc_html_e( 'Select the page where you added the shortcode [nikvotia_tickets]. Customers are directed here from confirmation emails and success notifications.', 'nik-voice-ticketing-ai' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label><?php esc_html_e( 'Portal Shortcode', 'nik-voice-ticketing-ai' ); ?></label></th>
					<td>
						<code class="nik-vd-code-badge">[nikvotia_tickets]</code>
						<p class="description"><?php esc_html_e( 'Place this shortcode inside any WordPress page to display the user support ticket dashboard.', 'nik-voice-ticketing-ai' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<div class="nik-vd-settings-section">
			<h3><span class="dashicons dashicons-visibility"></span> <?php esc_html_e( 'Button Visibility Rules', 'nik-voice-ticketing-ai' ); ?></h3>
			<table class="form-table nik-vd-table">
				<tr>
					<th scope="row"><label><?php esc_html_e( 'Display on Content Types', 'nik-voice-ticketing-ai' ); ?></label></th>
					<td>
						<select name="nikvotia_visibility_post_types[]" multiple="multiple" size="5" class="nik-vd-multi-select">
							<?php foreach ( $public_post_types as $pt ) : ?>
								<option value="<?php echo esc_attr( $pt->name ); ?>" <?php echo in_array( $pt->name, (array) $post_types_selected, true ) ? 'selected' : ''; ?>><?php echo esc_html( $pt->label ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Hold CTRL (CMD on Mac) to select multiple post types where the microphone button should appear.', 'nik-voice-ticketing-ai' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="nikvotia_visibility_user_status"><?php esc_html_e( 'Target Audience', 'nik-voice-ticketing-ai' ); ?></label></th>
					<td>
						<select id="nikvotia_visibility_user_status" name="nikvotia_visibility_user_status" class="nik-vd-input-select">
							<option value="all" <?php selected( $user_status, 'all' ); ?>><?php esc_html_e( 'All Users (Guests & Logged In)', 'nik-voice-ticketing-ai' ); ?></option>
							<option value="logged_in" <?php selected( $user_status, 'logged_in' ); ?>><?php esc_html_e( 'Logged In Members Only', 'nik-voice-ticketing-ai' ); ?></option>
							<option value="not_logged_in" <?php selected( $user_status, 'not_logged_in' ); ?>><?php esc_html_e( 'Guests (Not Logged In) Only', 'nik-voice-ticketing-ai' ); ?></option>
							<option value="specific" <?php selected( $user_status, 'specific' ); ?>><?php esc_html_e( 'Specific Users by ID', 'nik-voice-ticketing-ai' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="nikvotia_visibility_specific_users"><?php esc_html_e( 'Specific User IDs', 'nik-voice-ticketing-ai' ); ?></label></th>
					<td>
						<input type="text" id="nikvotia_visibility_specific_users" name="nikvotia_visibility_specific_users" value="<?php echo esc_attr( $specific_users ); ?>" class="regular-text nik-vd-input-text" placeholder="1, 5, 12" />
						<p class="description"><?php esc_html_e( 'Comma-separated user IDs (e.g. 1, 5, 12). Only works when "Specific Users by ID" is chosen.', 'nik-voice-ticketing-ai' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="nikvotia_departments"><?php esc_html_e( 'Department Categories', 'nik-voice-ticketing-ai' ); ?></label></th>
					<td>
						<input type="text" id="nikvotia_departments" name="nikvotia_departments" value="<?php echo esc_attr( $departments ); ?>" class="regular-text nik-vd-input-text" />
						<p class="description"><?php esc_html_e( 'Comma-separated list of departments for automated AI routing and triage.', 'nik-voice-ticketing-ai' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<div class="nik-vd-settings-section">
			<h3><span class="dashicons dashicons-chart-bar"></span> <?php esc_html_e( 'Diagnostics & Usage Telemetry', 'nik-voice-ticketing-ai' ); ?></h3>
			<table class="form-table nik-vd-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Anonymous Telemetry', 'nik-voice-ticketing-ai' ); ?></th>
					<td>
						<?php $telemetry_optin = get_option( 'nikvotia_telemetry_optin', 'no'  ); ?>
						<label class="nik-vd-checkbox-label" style="display: flex; align-items: center; gap: 8px;">
							<input type="hidden" name="nikvotia_telemetry_optin" value="no" />
							<input type="checkbox" name="nikvotia_telemetry_optin" value="yes" <?php checked( $telemetry_optin, 'yes' ); ?> />
							<strong><?php esc_html_e( 'Enable Anonymous Diagnostic & Telemetry Sync', 'nik-voice-ticketing-ai' ); ?></strong>
						</label>
						<p class="description">
							<?php esc_html_e( 'Share non-sensitive diagnostic environment data (WordPress version, PHP version, server OS, and plugin version) to https://nikneural.ca/tracking/ to assist future development and bug fixes. No customer voice recordings or sensitive personal details are ever collected. You can turn this on or off at any time.', 'nik-voice-ticketing-ai' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	private function render_appearance_tab() {
		$btn_color = get_option( 'nikvotia_btn_color', '#ffd700'  );
		$icon_color = get_option( 'nikvotia_icon_color', '#333333'  );
		$tooltip_text = get_option( 'nikvotia_tooltip_text', 'Speak your issue and click on problematic areas.'  );
		$custom_icon = get_option( 'nikvotia_custom_icon', ''  );
		?>
		<div class="nik-vd-settings-section">
			<h3><span class="dashicons dashicons-art"></span> <?php esc_html_e( 'Button & Visual Customization', 'nik-voice-ticketing-ai' ); ?></h3>
			<table class="form-table nik-vd-table">
				<tr>
					<th scope="row"><label for="nikvotia_btn_color"><?php esc_html_e( 'Button Background Color', 'nik-voice-ticketing-ai' ); ?></label></th>
					<td>
						<div style="display: flex; align-items: center; gap: 10px;">
							<input type="color" id="nikvotia_btn_color" name="nikvotia_btn_color" value="<?php echo esc_attr( $btn_color ); ?>" />
							<span class="nik-vd-color-code"><?php echo esc_html( $btn_color ); ?></span>
						</div>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="nikvotia_icon_color"><?php esc_html_e( 'Button Icon Color', 'nik-voice-ticketing-ai' ); ?></label></th>
					<td>
						<div style="display: flex; align-items: center; gap: 10px;">
							<input type="color" id="nikvotia_icon_color" name="nikvotia_icon_color" value="<?php echo esc_attr( $icon_color ); ?>" />
							<span class="nik-vd-color-code"><?php echo esc_html( $icon_color ); ?></span>
						</div>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="nikvotia_tooltip_text"><?php esc_html_e( 'Dock Instruction Text', 'nik-voice-ticketing-ai' ); ?></label></th>
					<td>
						<input type="text" id="nikvotia_tooltip_text" name="nikvotia_tooltip_text" value="<?php echo esc_attr( $tooltip_text ); ?>" class="regular-text nik-vd-input-text" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="nikvotia_custom_icon"><?php esc_html_e( 'Custom SVG Icon', 'nik-voice-ticketing-ai' ); ?></label></th>
					<td>
						<textarea id="nikvotia_custom_icon" name="nikvotia_custom_icon" rows="4" class="large-text code nik-vd-textarea"><?php echo esc_textarea( $custom_icon ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Paste raw SVG markup here to override the default microphone icon.', 'nik-voice-ticketing-ai' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Developer Attribution', 'nik-voice-ticketing-ai' ); ?></th>
					<td>
						<?php $show_attribution = get_option( 'nikvotia_show_attribution', '0'  ); ?>
						<label class="nik-vd-checkbox-label" style="display: flex; align-items: center; gap: 8px;">
							<input type="checkbox" name="nikvotia_show_attribution" value="1" <?php checked( $show_attribution, '1' ); ?> />
							<strong><?php esc_html_e( 'Display "Powered by Nik Neural AI Inc." attribution on public site', 'nik-voice-ticketing-ai' ); ?></strong>
						</label>
						<p class="description">
							<?php esc_html_e( 'Per WordPress.org Plugin Directory guidelines, external branding links on public pages are optional and only shown with your explicit consent.', 'nik-voice-ticketing-ai' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	private function render_api_tab() {
		$stt_model = get_option( 'nikvotia_stt_model', 'openai_whisper'  );
		$llm_model = get_option( 'nikvotia_llm_model', 'openai_gpt'  );
		$openai_key = get_option( 'nikvotia_openai_key', ''  );
		$modulate_key = get_option( 'nikvotia_modulate_key', ''  );
		?>
		<div class="nik-vd-settings-section">
			<h3><span class="dashicons dashicons-rest-api"></span> <?php esc_html_e( 'AI Provider & Engine Configuration', 'nik-voice-ticketing-ai' ); ?></h3>
			<table class="form-table nik-vd-table">
				<tr>
					<th scope="row"><label for="nikvotia_stt_model"><?php esc_html_e( 'Speech-to-Text (STT) Engine', 'nik-voice-ticketing-ai' ); ?></label></th>
					<td>
						<select id="nikvotia_stt_model" name="nikvotia_stt_model" class="nik-vd-input-select">
							<option value="openai_whisper" <?php selected( $stt_model, 'openai_whisper' ); ?>><?php esc_html_e( 'OpenAI Whisper (whisper-1)', 'nik-voice-ticketing-ai' ); ?></option>
							<option value="modulate_ai" <?php selected( $stt_model, 'modulate_ai' ); ?>><?php esc_html_e( 'Modulate.ai (Velma-2 Fast STT)', 'nik-voice-ticketing-ai' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="nikvotia_llm_model"><?php esc_html_e( 'LLM Analysis & Triage Engine', 'nik-voice-ticketing-ai' ); ?></label></th>
					<td>
						<select id="nikvotia_llm_model" name="nikvotia_llm_model" class="nik-vd-input-select">
							<option value="openai_gpt" <?php selected( $llm_model, 'openai_gpt' ); ?>><?php esc_html_e( 'OpenAI GPT-4o-mini (Summary & Categorization)', 'nik-voice-ticketing-ai' ); ?></option>
							<option value="modulate_ai" <?php selected( $llm_model, 'modulate_ai' ); ?>><?php esc_html_e( 'Modulate.ai (Velma-2 Batch Analytics)', 'nik-voice-ticketing-ai' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="nikvotia_openai_key"><?php esc_html_e( 'OpenAI API Key', 'nik-voice-ticketing-ai' ); ?></label></th>
					<td>
						<input type="password" id="nikvotia_openai_key" name="nikvotia_openai_key" value="<?php echo esc_attr( $openai_key ); ?>" class="regular-text nik-vd-input-text" autocomplete="off" />
						<p class="description"><?php esc_html_e( 'Used for Whisper audio transcription and GPT-4o-mini summarization.', 'nik-voice-ticketing-ai' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="nikvotia_modulate_key"><?php esc_html_e( 'Modulate.ai API Key', 'nik-voice-ticketing-ai' ); ?></label></th>
					<td>
						<input type="password" id="nikvotia_modulate_key" name="nikvotia_modulate_key" value="<?php echo esc_attr( $modulate_key ); ?>" class="regular-text nik-vd-input-text" autocomplete="off" />
						<p class="description"><?php esc_html_e( 'Used for Velma-2 fast multilingual speech-to-text and voice intelligence analytics.', 'nik-voice-ticketing-ai' ); ?></p>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	private function render_pro_tab() {
		?>
		<div class="nik-vd-settings-section">
			<h3><span class="dashicons dashicons-awards"></span> <?php esc_html_e( 'Pro & Cloud Add-ons', 'nik-voice-ticketing-ai' ); ?></h3>
			<p class="description" style="margin-bottom: 20px; font-size: 13.5px; line-height: 1.5;">
				<?php esc_html_e( 'Nik Voice Ticketing AI is 100% free and fully functional out-of-the-box with no artificial limits. For organizations requiring managed private cloud infrastructure, custom telephony integrations, or dedicated SLAs, Nik Neural AI Inc. offers tailored enterprise engineering.', 'nik-voice-ticketing-ai' ); ?>
			</p>

			<div class="nik-vd-grid-layout" style="margin-top: 16px;">
				<div class="nik-vd-card" style="border: 1px solid #38bdf8; background: linear-gradient(180deg, rgba(56, 189, 248, 0.03) 0%, rgba(2, 132, 199, 0.06) 100%);">
					<div class="nik-vd-card-header">
						<h4 style="margin: 0; font-size: 16px;"><span class="dashicons dashicons-cloud" style="color: #0284c7;"></span> <?php esc_html_e( 'Canadian Private Cloud & Enterprise AI Solutions', 'nik-voice-ticketing-ai' ); ?></h4>
					</div>
					<div class="nik-vd-card-body" style="font-size: 13.5px; line-height: 1.6;">
						<ul style="list-style: disc; padding-left: 20px; color: #475569; margin: 0 0 18px 0;">
							<li><strong><?php esc_html_e( 'PIPEDA & PHIPA Data Residency:', 'nik-voice-ticketing-ai' ); ?></strong> <?php esc_html_e( 'Zero cloud data leakage; customer audio processed entirely within sovereign Canadian borders.', 'nik-voice-ticketing-ai' ); ?></li>
							<li><strong><?php esc_html_e( 'Dedicated High-Speed Inference:', 'nik-voice-ticketing-ai' ); ?></strong> <?php esc_html_e( 'Sub-second transcription and triage pipelines engineered on enterprise hardware.', 'nik-voice-ticketing-ai' ); ?></li>
							<li><strong><?php esc_html_e( 'Custom ERP & CRM Webhooks:', 'nik-voice-ticketing-ai' ); ?></strong> <?php esc_html_e( 'Direct automated routing to Salesforce, Zendesk, HubSpot, and custom insurance management systems.', 'nik-voice-ticketing-ai' ); ?></li>
							<li><strong><?php esc_html_e( '24/7 Enterprise SLA Support:', 'nik-voice-ticketing-ai' ); ?></strong> <?php esc_html_e( 'Direct access to senior AI solutions engineers.', 'nik-voice-ticketing-ai' ); ?></li>
						</ul>
						<div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
							<a href="https://nikneural.ca/pl/voicedesk.php" target="_blank" rel="noopener noreferrer" class="button button-primary button-large" style="background: #0284c7; border-color: #0369a1;">
								<?php esc_html_e( 'Explore Enterprise Overview →', 'nik-voice-ticketing-ai' ); ?>
							</a>
							<a href="https://nikneural.ca/#contact" target="_blank" rel="noopener noreferrer" class="button button-secondary button-large">
								<?php esc_html_e( 'Contact Our Canadian Team', 'nik-voice-ticketing-ai' ); ?>
							</a>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the unified About Page with official company profile, mission, products, and links.
	 */
	public function render_about_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap nik-vd-settings-wrap">
			<div class="nik-vd-admin-console nik-vd-settings-console">
				<!-- Header Bar with Unified Styling & Dark/Light Toggle -->
				<div class="nik-vd-header-bar">
					<div class="nik-vd-header-left">
						<div class="nik-vd-ticket-id">
							<span class="dashicons dashicons-info" style="color: #38bdf8;"></span>
							<span class="nik-vd-id-label"><?php esc_html_e( 'About', 'nik-voice-ticketing-ai' ); ?></span>
							<span class="nik-vd-id-value"><?php esc_html_e( 'Nik Neural AI Inc.', 'nik-voice-ticketing-ai' ); ?></span>
						</div>
						<div class="nik-vd-customer-pill">
							<span class="dashicons dashicons-tag"></span>
							<strong>v<?php echo esc_html( NIKVOTIA_VERSION ); ?></strong>
						</div>
						<span class="nik-status-pill nik-status-resolved"><?php esc_html_e( 'PIPEDA COMPLIANT', 'nik-voice-ticketing-ai' ); ?></span>
					</div>

					<div class="nik-vd-header-right">
						<button type="button" class="nik-vd-theme-btn" id="nik-vd-theme-toggle" aria-label="<?php esc_attr_e( 'Toggle Dark or Light Mode', 'nik-voice-ticketing-ai' ); ?>">
							<span class="nik-vd-theme-icon">🌙</span>
							<span class="nik-vd-theme-text"><?php esc_html_e( 'Dark Mode', 'nik-voice-ticketing-ai' ); ?></span>
						</button>

						<a href="https://nikneural.ca/" target="_blank" rel="noopener noreferrer" class="button button-primary button-large nik-vd-save-btn">
							<span class="dashicons dashicons-external"></span> <?php esc_html_e( 'Visit Official Website', 'nik-voice-ticketing-ai' ); ?>
						</a>
					</div>
				</div>

				<!-- About Hero Banner -->
				<div style="background: linear-gradient(135deg, #0A1128 0%, #001F54 50%, #0B618D 100%); color: #ffffff; border-radius: 12px; padding: 32px 28px; margin-bottom: 24px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px;">
					<div style="max-width: 680px;">
						<div style="display: inline-flex; align-items: center; gap: 8px; background: rgba(18, 130, 162, 0.25); border: 1px solid rgba(18, 130, 162, 0.5); padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; color: #38bdf8; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.5px;">
							<img src="<?php echo esc_url( NIKVOTIA_URL . 'assets/images/favicon-light-32.png' ); ?>" alt="Nik Neural AI Inc." width="16" height="16" style="vertical-align: middle;" />
							<?php esc_html_e( 'Canadian Enterprise AI &bull; Self-Hosted', 'nik-voice-ticketing-ai' ); ?>
						</div>
						<h2 style="font-size: 26px; font-weight: 800; margin: 0 0 10px 0; color: #ffffff; line-height: 1.25;">
							<?php esc_html_e( 'Absolute Privacy. Zero SaaS Tax.', 'nik-voice-ticketing-ai' ); ?><br>
							<span style="color: #38bdf8;"><?php esc_html_e( 'AI Automation for Canadian Enterprises.', 'nik-voice-ticketing-ai' ); ?></span>
						</h2>
						<p style="font-size: 14px; line-height: 1.6; color: #cbd5e1; margin: 0;">
							<?php esc_html_e( 'Nik Neural AI Inc. engineers production-ready, self-hosted AI automation platforms for brokerages, financial institutions, and enterprises. Deployed on your infrastructure — your data never leaves your servers.', 'nik-voice-ticketing-ai' ); ?>
						</p>
					</div>
					<div style="display: flex; flex-direction: column; gap: 10px;">
						<a href="https://nikneural.ca/pl/voicedesk.php" target="_blank" rel="noopener noreferrer" style="background: #FF6D00; color: #ffffff; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: 700; font-size: 14px; text-align: center; box-shadow: 0 4px 12px rgba(255, 109, 0, 0.3);">
							<?php esc_html_e( 'VoiceDesk Overview →', 'nik-voice-ticketing-ai' ); ?>
						</a>
						<a href="https://nikneural.ca/#contact" target="_blank" rel="noopener noreferrer" style="background: rgba(255,255,255,0.12); color: #ffffff; text-decoration: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; font-size: 13px; text-align: center; border: 1px solid rgba(255,255,255,0.25);">
							<?php esc_html_e( 'Book a Consultation', 'nik-voice-ticketing-ai' ); ?>
						</a>
					</div>
				</div>

				<!-- 2-Column Content Grid -->
				<div class="nik-vd-grid-layout">
					<!-- Left Column: Mission & Platform -->
					<div class="nik-vd-col-main">
						<!-- Mission & Philosophy -->
						<div class="nik-vd-card">
							<div class="nik-vd-card-header">
								<h3><span class="dashicons dashicons-shield"></span> <?php esc_html_e( 'Built for Institutions, Not Experiments', 'nik-voice-ticketing-ai' ); ?></h3>
							</div>
							<div class="nik-vd-card-body" style="font-size: 13.5px; line-height: 1.6;">
								<p>
									<?php esc_html_e( 'Unlike generic third-party SaaS chatbots that ingest sensitive client data into multi-tenant cloud servers, Nik Neural AI Inc. designs architectures where privacy is foundational.', 'nik-voice-ticketing-ai' ); ?>
								</p>
								<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; margin-top: 16px;">
									<div style="background: rgba(18, 130, 162, 0.08); border: 1px solid rgba(18, 130, 162, 0.2); border-radius: 8px; padding: 14px;">
										<strong style="color: #0284c7; display: block; margin-bottom: 4px;">🍁 <?php esc_html_e( 'PIPEDA by Default', 'nik-voice-ticketing-ai' ); ?></strong>
										<span style="color: #64748b; font-size: 12.5px;"><?php esc_html_e( 'Structured strictly around Canadian privacy laws and data residency requirements from day one.', 'nik-voice-ticketing-ai' ); ?></span>
									</div>
									<div style="background: rgba(18, 130, 162, 0.08); border: 1px solid rgba(18, 130, 162, 0.2); border-radius: 8px; padding: 14px;">
										<strong style="color: #0284c7; display: block; margin-bottom: 4px;">🔓 <?php esc_html_e( 'No Vendor Lock-In', 'nik-voice-ticketing-ai' ); ?></strong>
										<span style="color: #64748b; font-size: 12.5px;"><?php esc_html_e( 'Full code ownership on delivery. Zero mandatory recurring cloud subscriptions or forced dependencies.', 'nik-voice-ticketing-ai' ); ?></span>
									</div>
									<div style="background: rgba(18, 130, 162, 0.08); border: 1px solid rgba(18, 130, 162, 0.2); border-radius: 8px; padding: 14px;">
										<strong style="color: #0284c7; display: block; margin-bottom: 4px;">🏢 <?php esc_html_e( 'Domain Expertise', 'nik-voice-ticketing-ai' ); ?></strong>
										<span style="color: #64748b; font-size: 12.5px;"><?php esc_html_e( 'Specialized in P&C insurance, commercial lines, strata, and enterprise ticketing workflows.', 'nik-voice-ticketing-ai' ); ?></span>
									</div>
									<div style="background: rgba(18, 130, 162, 0.08); border: 1px solid rgba(18, 130, 162, 0.2); border-radius: 8px; padding: 14px;">
										<strong style="color: #0284c7; display: block; margin-bottom: 4px;">⚡ <?php esc_html_e( 'Zero SaaS Tax', 'nik-voice-ticketing-ai' ); ?></strong>
										<span style="color: #64748b; font-size: 12.5px;"><?php esc_html_e( 'Eliminate per-seat monthly billing fees by running models on your own servers or bring-your-own-keys.', 'nik-voice-ticketing-ai' ); ?></span>
									</div>
								</div>
							</div>
						</div>

						<!-- The Nik AI Platform Showcase -->
						<div class="nik-vd-card">
							<div class="nik-vd-card-header">
								<h3><span class="dashicons dashicons-grid-view"></span> <?php esc_html_e( 'The Nik Neural AI Enterprise Suite', 'nik-voice-ticketing-ai' ); ?></h3>
							</div>
							<div class="nik-vd-card-body">
								<div style="display: flex; flex-direction: column; gap: 14px;">
									<div style="border-left: 3px solid #FF6D00; padding-left: 14px;">
										<strong style="font-size: 15px; color: #0f172a;"><?php esc_html_e( 'Nik VoiceDesk AI', 'nik-voice-ticketing-ai' ); ?></strong>
										<span class="nik-vd-ai-badge" style="margin-left: 8px;"><?php esc_html_e( 'Active Plugin', 'nik-voice-ticketing-ai' ); ?></span>
										<p style="margin: 4px 0 0 0; font-size: 13px; color: #64748b;">
											<?php esc_html_e( 'AI-powered zero-friction voice ticket submission, Whisper/Modulate transcription, automated triage, and customer portal integration.', 'nik-voice-ticketing-ai' ); ?>
										</p>
									</div>

									<div style="border-left: 3px solid #1282A2; padding-left: 14px;">
										<strong style="font-size: 15px; color: #0f172a;"><?php esc_html_e( 'Nik P&C Retention', 'nik-voice-ticketing-ai' ); ?></strong>
										<p style="margin: 4px 0 0 0; font-size: 13px; color: #64748b;">
											<?php esc_html_e( 'Automated renewal outreach and policy lifecycle management for property & casualty brokerages. Predict churn and re-engage clients proactively.', 'nik-voice-ticketing-ai' ); ?>
										</p>
									</div>

									<div style="border-left: 3px solid #0B618D; padding-left: 14px;">
										<strong style="font-size: 15px; color: #0f172a;"><?php esc_html_e( 'Nik Enterprise Hub', 'nik-voice-ticketing-ai' ); ?></strong>
										<p style="margin: 4px 0 0 0; font-size: 13px; color: #64748b;">
											<?php esc_html_e( 'Centralised AI operations dashboard for multi-branch brokerages and institutions with multi-tier permissions and unified telemetry.', 'nik-voice-ticketing-ai' ); ?>
										</p>
									</div>

									<div style="border-left: 3px solid #16a34a; padding-left: 14px;">
										<strong style="font-size: 15px; color: #0f172a;"><?php esc_html_e( 'Nik Privacy Shield', 'nik-voice-ticketing-ai' ); ?></strong>
										<p style="margin: 4px 0 0 0; font-size: 13px; color: #64748b;">
											<?php esc_html_e( 'On-premise Personally Identifiable Information (PII) detection, automated document redaction, and audit-trail generation for regulated industries.', 'nik-voice-ticketing-ai' ); ?>
										</p>
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- Right Column: Company Info & Contact -->
					<div class="nik-vd-col-side">
						<!-- Official Corporate Profile -->
						<div class="nik-vd-card">
							<div class="nik-vd-card-header">
								<h3><span class="dashicons dashicons-building"></span> <?php esc_html_e( 'Corporate Information', 'nik-voice-ticketing-ai' ); ?></h3>
							</div>
							<div class="nik-vd-card-body" style="font-size: 13px;">
								<div class="nik-vd-meta-row">
									<strong><?php esc_html_e( 'Legal Name:', 'nik-voice-ticketing-ai' ); ?></strong>
									<span>Nik Neural AI Inc.</span>
								</div>
								<div class="nik-vd-meta-row">
									<strong><?php esc_html_e( 'Jurisdiction:', 'nik-voice-ticketing-ai' ); ?></strong>
									<span>British Columbia, Canada</span>
								</div>
								<div class="nik-vd-meta-row">
									<strong><?php esc_html_e( 'Headquarters:', 'nik-voice-ticketing-ai' ); ?></strong>
									<span>112-970 Burrard Street, Office# 1760<br>Vancouver, BC V6Z 2R4, Canada</span>
								</div>
								<div class="nik-vd-meta-row">
									<strong><?php esc_html_e( 'Telephone:', 'nik-voice-ticketing-ai' ); ?></strong>
									<a href="tel:6042837353" style="color: #0284c7; text-decoration: none;">(604) 283-7353</a>
								</div>
								<div class="nik-vd-meta-row">
									<strong><?php esc_html_e( 'Official Web:', 'nik-voice-ticketing-ai' ); ?></strong>
									<a href="https://nikneural.ca/" target="_blank" rel="noopener noreferrer" style="color: #0284c7; text-decoration: none;">https://nikneural.ca/</a>
								</div>

								<div style="margin-top: 18px; padding-top: 14px; border-top: 1px solid #e2e8f0;">
									<a href="https://nikneural.ca/#contact" target="_blank" rel="noopener noreferrer" class="button button-primary" style="width: 100%; text-align: center; justify-content: center; height: 38px; line-height: 36px;">
										<span class="dashicons dashicons-email-alt" style="margin-top: 8px;"></span> <?php esc_html_e( 'Get in Touch with Team', 'nik-voice-ticketing-ai' ); ?>
									</a>
								</div>
							</div>
						</div>

						<!-- Enterprise Cloud Card -->
						<div class="nik-vd-card" style="border: 1px solid #38bdf8; background: linear-gradient(180deg, rgba(56, 189, 248, 0.04) 0%, rgba(2, 132, 199, 0.08) 100%);">
							<div class="nik-vd-card-header">
								<h3><span class="dashicons dashicons-awards" style="color: #0284c7;"></span> <?php esc_html_e( 'Enterprise Services', 'nik-voice-ticketing-ai' ); ?></h3>
							</div>
							<div class="nik-vd-card-body" style="font-size: 13px; line-height: 1.5;">
								<p>
									<?php esc_html_e( 'Need dedicated private cloud hosting, custom CRM integrations, or SLA coverage? Discover our Canadian enterprise solutions.', 'nik-voice-ticketing-ai' ); ?>
								</p>
								<div style="margin-top: 14px;">
									<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=voicedesk_ticket&page=nikvotia-settings&tab=pro' ) ); ?>" class="button button-secondary" style="width: 100%; text-align: center; justify-content: center;">
										<?php esc_html_e( 'Explore Pro Services →', 'nik-voice-ticketing-ai' ); ?>
									</a>
								</div>
							</div>
						</div>
					</div>
				</div>


			</div>
		</div>
		<?php
	}
}
