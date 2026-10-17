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
		add_action( 'admin_post_nikvotia_connect_cloud', array( $this, 'handle_connect_cloud' ) );
		add_action( 'admin_post_nikvotia_disconnect_cloud', array( $this, 'handle_disconnect_cloud' ) );
		add_action( 'admin_post_nikvotia_test_cloud', array( $this, 'handle_test_cloud' ) );
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
		register_setting( 'nikvotia_api', 'nikvotia_ai_provider', array(
			'type'              => 'string',
			'sanitize_callback' => array( $this, 'sanitize_ai_provider' ),
			'default'           => 'nik_voice_cloud',
		) );
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
		register_setting( 'nikvotia_api', 'nikvotia_cloud_key', array(
			'type'              => 'string',
			'sanitize_callback' => array( $this, 'sanitize_cloud_key' ),
			'default'           => '',
		) );
		register_setting( 'nikvotia_api', 'nikvotia_cloud_secret', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		) );
		register_setting( 'nikvotia_api', 'nikvotia_gateway_url', array(
			'type'              => 'string',
			'sanitize_callback' => 'esc_url_raw',
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
	 * Sanitize AI Provider selection and enforce lockstep synchronization of STT and LLM engines.
	 *
	 * @param string $input Submitted provider value.
	 * @return string
	 */
	public function sanitize_ai_provider( $input ) {
		$valid    = array( 'nik_voice_cloud', 'openai', 'modulate_ai' );
		$provider = in_array( $input, $valid, true ) ? $input : 'nik_voice_cloud';

		if ( 'nik_voice_cloud' === $provider ) {
			update_option( 'nikvotia_stt_model', 'nik_voice_cloud' );
			update_option( 'nikvotia_llm_model', 'nik_voice_cloud' );
		} elseif ( 'openai' === $provider ) {
			update_option( 'nikvotia_stt_model', 'openai_whisper' );
			update_option( 'nikvotia_llm_model', 'openai_gpt' );
		} elseif ( 'modulate_ai' === $provider ) {
			update_option( 'nikvotia_stt_model', 'modulate_ai' );
			update_option( 'nikvotia_llm_model', 'modulate_ai' );
		}

		return $provider;
	}

	/**
	 * Sanitize Nik Voice Cloud API Key and automatically activate connection.
	 *
	 * @param string $input Submitted API Key ID.
	 * @return string
	 */
	public function sanitize_cloud_key( $input ) {
		$key = sanitize_text_field( trim( $input ) );
		if ( ! empty( $key ) ) {
			update_option( 'nikvotia_cloud_connected', 'yes' );
			update_option( 'nikvotia_stt_model', 'nik_voice_cloud' );
			update_option( 'nikvotia_llm_model', 'nik_voice_cloud' );
		} else {
			update_option( 'nikvotia_cloud_connected', 'no' );
		}
		return $key;
	}

	/**
	 * Get the currently active AI provider ID with self-healing fallback.
	 *
	 * @return string 'nik_voice_cloud', 'openai', or 'modulate_ai'.
	 */
	public static function get_active_ai_provider() {
		$provider = get_option( 'nikvotia_ai_provider', '' );
		if ( ! empty( $provider ) ) {
			return $provider;
		}

		// Self-healing fallback: Prioritize Nik Voice Cloud if connected or selected
		if ( Nikvotia_Cloud_Client::is_connected() || 'nik_voice_cloud' === get_option( 'nikvotia_stt_model' ) ) {
			return 'nik_voice_cloud';
		}

		if ( 'modulate_ai' === get_option( 'nikvotia_stt_model' ) ) {
			return 'modulate_ai';
		}

		return 'openai';
	}

	/**
	 * Get human-readable label for AI provider.
	 *
	 * @param string $provider_id Provider identifier.
	 * @return string
	 */
	public static function get_provider_label( $provider_id ) {
		switch ( $provider_id ) {
			case 'nik_voice_cloud':
				return __( 'Nik Voice Cloud (Groq Whisper-v3 • Sovereign Gateway)', 'nik-voice-ticketing-ai' );
			case 'openai':
				return __( 'OpenAI Cloud (Whisper-1 + GPT-4o-mini)', 'nik-voice-ticketing-ai' );
			case 'modulate_ai':
				return __( 'Modulate.ai (Velma-2 + Voice Intelligence)', 'nik-voice-ticketing-ai' );
			default:
				return __( 'Nik Voice Cloud', 'nik-voice-ticketing-ai' );
		}
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
					<a href="?post_type=voicedesk_ticket&page=nikvotia-settings&tab=logs" class="nik-vd-tab-link <?php echo $active_tab === 'logs' ? 'is-active' : ''; ?>">
						<span class="dashicons dashicons-media-text"></span> <?php esc_html_e( 'System & AI Logs', 'nik-voice-ticketing-ai' ); ?>
					</a>
					<a href="?post_type=voicedesk_ticket&page=nikvotia-settings&tab=pro" class="nik-vd-tab-link <?php echo ( $active_tab === 'pro' || $active_tab === 'license' ) ? 'is-active' : ''; ?>">
						<span class="dashicons dashicons-awards"></span> <?php esc_html_e( 'Pro & Cloud Add-ons', 'nik-voice-ticketing-ai' ); ?>
					</a>
				</div>

				<!-- Tab Content Card -->
				<div class="nik-vd-settings-body">
					<?php if ( 'logs' === $active_tab ) : ?>
						<?php $this->render_logs_tab(); ?>
					<?php else : ?>
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
					<?php endif; ?>
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
		$active_provider = self::get_active_ai_provider();
		$openai_key      = get_option( 'nikvotia_openai_key', '' );
		$modulate_key    = get_option( 'nikvotia_modulate_key', '' );
		$cloud_key       = get_option( 'nikvotia_cloud_key', '' );
		$cloud_secret    = get_option( 'nikvotia_cloud_secret', '' );
		$cloud_gateway   = get_option( 'nikvotia_gateway_url', '' );
		$cloud_connected = Nikvotia_Cloud_Client::is_connected();
		$active_gateway  = Nikvotia_Cloud_Client::get_gateway_url();
		?>
		<?php
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success redirect flag from admin-post handshake.
		$nik_cloud_success = isset( $_GET['nik_cloud_success'] );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only error message redirect parameter from admin-post handshake.
		$nik_cloud_error   = isset( $_GET['nik_cloud_error'] ) ? sanitize_text_field( wp_unslash( $_GET['nik_cloud_error'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only disconnect redirect flag from admin-post handler.
		$nik_cloud_disconn = isset( $_GET['nik_cloud_disconnected'] );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only health check redirect flag.
		$nik_cloud_tested  = isset( $_GET['nik_cloud_tested'] );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only health check engine title.
		$cloud_engine_name = isset( $_GET['cloud_engine'] ) ? sanitize_text_field( wp_unslash( $_GET['cloud_engine'] ) ) : 'Groq Whisper-Large-v3';
		?>
		<?php if ( $nik_cloud_tested ) : ?>
			<div class="notice notice-success is-dismissible" style="margin-bottom: 20px;">
				<p><strong><span class="dashicons dashicons-yes-alt" style="color: #16a34a;"></span> <?php esc_html_e( 'Nik Voice Cloud Gateway Verified Live!', 'nik-voice-ticketing-ai' ); ?></strong> <?php printf( esc_html__( 'Connected to %1$s (Engine: %2$s • Canada PIPEDA Zero-Disk). Diagnostic health status logged in System & AI Logs.', 'nik-voice-ticketing-ai' ), esc_html( $active_gateway ), esc_html( $cloud_engine_name ) ); ?></p>
			</div>
		<?php elseif ( $nik_cloud_success ) : ?>
			<div class="notice notice-success is-dismissible" style="margin-bottom: 20px;">
				<p><strong><span class="dashicons dashicons-yes-alt" style="color: #16a34a;"></span> <?php esc_html_e( 'Successfully connected to Nik Voice Cloud!', 'nik-voice-ticketing-ai' ); ?></strong> <?php esc_html_e( 'Your cryptographic keys are generated and bound to this site domain. Speech-to-Text has been activated.', 'nik-voice-ticketing-ai' ); ?></p>
			</div>
		<?php elseif ( ! empty( $nik_cloud_error ) ) : ?>
			<div class="notice notice-error is-dismissible" style="margin-bottom: 20px;">
				<p><strong><span class="dashicons dashicons-warning" style="color: #dc2626;"></span> <?php esc_html_e( 'Connection Failed:', 'nik-voice-ticketing-ai' ); ?></strong> <?php echo esc_html( $nik_cloud_error ); ?></p>
			</div>
		<?php elseif ( $nik_cloud_disconn ) : ?>
			<div class="notice notice-info is-dismissible" style="margin-bottom: 20px;">
				<p><span class="dashicons dashicons-info" style="color: #0284c7;"></span> <?php esc_html_e( 'Disconnected from Nik Voice Cloud.', 'nik-voice-ticketing-ai' ); ?></p>
			</div>
		<?php endif; ?>

		<div class="nik-vd-settings-section">
			<h3><span class="dashicons dashicons-rest-api"></span> <?php esc_html_e( 'AI Provider & Engine Configuration', 'nik-voice-ticketing-ai' ); ?></h3>

			<!-- Strict Single-Provider Isolation Notice Banner -->
			<div class="nik-vd-card" style="border-left: 4px solid #16a34a; background: #f0fdf4; padding: 14px 18px; border-radius: 8px; margin-bottom: 22px;">
				<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
					<div>
						<strong style="color: #15803d; font-size: 14px;">
							<span class="dashicons dashicons-shield-alt" style="margin-right: 4px;"></span>
							<?php
							/* translators: %s: Active AI engine name */
							printf( esc_html__( 'Active Engine: %s', 'nik-voice-ticketing-ai' ), esc_html( self::get_provider_label( $active_provider ) ) );
							?>
						</strong>
						<p style="margin: 4px 0 0 0; font-size: 13px; color: #166534;">
							<?php esc_html_e( 'Strict Single-Provider Isolation Enforced. Incoming voice tickets will only ever contact this chosen provider for both Speech-to-Text and ticket triage. All other configured APIs are strictly dormant.', 'nik-voice-ticketing-ai' ); ?>
						</p>
					</div>
					<span class="nik-ai-pill nik-ai-cloud" style="font-size: 12px; font-weight: 700; padding: 4px 10px;">
						🔒 <?php esc_html_e( 'Zero Cross-Leakage', 'nik-voice-ticketing-ai' ); ?>
					</span>
				</div>
			</div>

			<table class="form-table nik-vd-table">
				<tr>
					<th scope="row"><label for="nikvotia_ai_provider"><?php esc_html_e( 'Primary AI Provider (Single-Engine Mode)', 'nik-voice-ticketing-ai' ); ?></label></th>
					<td>
						<select id="nikvotia_ai_provider" name="nikvotia_ai_provider" class="nik-vd-input-select" style="min-width: 340px; font-weight: 600;">
							<option value="nik_voice_cloud" <?php selected( $active_provider, 'nik_voice_cloud' ); ?>>
								⚡ <?php esc_html_e( 'Nik Voice Cloud (Groq Whisper-v3 • PIPEDA Zero-Disk)', 'nik-voice-ticketing-ai' ); ?>
							</option>
							<option value="openai" <?php selected( $active_provider, 'openai' ); ?>>
								🤖 <?php esc_html_e( 'OpenAI Cloud (Whisper-1 + GPT-4o-mini)', 'nik-voice-ticketing-ai' ); ?>
							</option>
							<option value="modulate_ai" <?php selected( $active_provider, 'modulate_ai' ); ?>>
								🎙️ <?php esc_html_e( 'Modulate.ai (Velma-2 Fast STT + Voice Intelligence)', 'nik-voice-ticketing-ai' ); ?>
							</option>
						</select>
						<p class="description" style="margin-top: 6px;">
							<?php esc_html_e( 'Selecting an engine binds both Speech-to-Text and triage to that single provider. No secondary or fallback API calls are made.', 'nik-voice-ticketing-ai' ); ?>
						</p>
					</td>
				</tr>

				<!-- Section: Nik Voice Cloud -->
				<tr>
					<th scope="row">
						<?php esc_html_e( 'Nik Voice Cloud Gateway', 'nik-voice-ticketing-ai' ); ?>
						<?php if ( 'nik_voice_cloud' === $active_provider ) : ?>
							<span style="display:block; font-size:11px; color:#16a34a; font-weight:600;"><?php esc_html_e( '● ACTIVE ENGINE', 'nik-voice-ticketing-ai' ); ?></span>
						<?php else : ?>
							<span style="display:block; font-size:11px; color:#94a3b8; font-weight:400;"><?php esc_html_e( '○ Inactive (Dormant)', 'nik-voice-ticketing-ai' ); ?></span>
						<?php endif; ?>
					</th>
					<td>
						<?php if ( $cloud_connected ) : ?>
							<div class="nik-vd-card" style="border: 1px solid #16a34a; background: rgba(22, 163, 74, 0.05); padding: 16px; border-radius: 12px; margin-bottom: 12px;">
								<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
									<div style="display: flex; align-items: center; gap: 10px;">
										<span style="display: inline-block; width: 12px; height: 12px; border-radius: 50%; background: #16a34a; box-shadow: 0 0 8px #16a34a;"></span>
										<strong style="color: #16a34a; font-size: 15px;"><?php esc_html_e( 'Connected & Synchronized', 'nik-voice-ticketing-ai' ); ?></strong>
									</div>
									<div style="display: flex; gap: 8px;">
										<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=nikvotia_test_cloud' ), 'nikvotia_test_cloud_nonce' ) ); ?>" class="button button-secondary" style="font-size: 12px;">
											<span class="dashicons dashicons-heart" style="font-size: 14px; margin-top: 3px; color: #16a34a;"></span> <?php esc_html_e( 'Test Gateway Health', 'nik-voice-ticketing-ai' ); ?>
										</a>
										<a href="<?php echo esc_url( $active_gateway . '/dashboard' ); ?>" target="_blank" rel="noopener noreferrer" class="button button-secondary" style="font-size: 12px;">
											<span class="dashicons dashicons-chart-pie" style="font-size: 14px; margin-top: 3px;"></span> <?php esc_html_e( 'View Cloud Quota & Invoices', 'nik-voice-ticketing-ai' ); ?>
										</a>
										<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=nikvotia_disconnect_cloud' ), 'nikvotia_disconnect_cloud_nonce' ) ); ?>" onclick="return confirm('<?php esc_attr_e( 'Disconnect from Nik Voice Cloud? This will remove your active API keys from this site.', 'nik-voice-ticketing-ai' ); ?>');" class="button button-link-delete" style="color: #dc2626; text-decoration: none; font-size: 12px; line-height: 28px; padding: 0 8px;">
											<?php esc_html_e( 'Disconnect Site', 'nik-voice-ticketing-ai' ); ?>
										</a>
									</div>
								</div>
								<div style="margin-top: 12px; font-size: 13px; color: #475569; display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 8px;">
									<div><strong><?php esc_html_e( 'API Key ID:', 'nik-voice-ticketing-ai' ); ?></strong> <code><?php echo esc_html( $cloud_key ); ?></code></div>
									<div><strong><?php esc_html_e( 'Domain Binding:', 'nik-voice-ticketing-ai' ); ?></strong> <code><?php echo esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ); ?></code></div>
									<div><strong><?php esc_html_e( 'Gateway URL:', 'nik-voice-ticketing-ai' ); ?></strong> <code><?php echo esc_html( $active_gateway ); ?></code></div>
								</div>
								<div style="margin-top: 12px; padding: 10px 14px; background: #fffbeb; border: 1px solid #fef3c7; border-radius: 8px; font-size: 12px; color: #92400e; line-height: 1.5;">
									<strong><?php esc_html_e( '💡 Production Tip for Real-Time Speech:', 'nik-voice-ticketing-ai' ); ?></strong>
									<?php esc_html_e( 'If your tickets display "[Nik Voice Cloud Demo]", the gateway at voice.nikneural.ca is running in demo fallback mode. To enable live speech transcription of customer voices, configure GROQ_API_KEY=gsk_... on your gateway server environment (in docker-compose or .env) and restart the gateway container.', 'nik-voice-ticketing-ai' ); ?>
								</div>
							</div>
						<?php else : ?>
							<div class="nik-vd-card" style="border: 1px solid #1282a2; background: linear-gradient(180deg, rgba(18, 130, 162, 0.05) 0%, rgba(14, 23, 54, 0.03) 100%); padding: 18px; border-radius: 12px; margin-bottom: 12px;">
								<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
									<div>
										<h4 style="margin: 0 0 6px 0; font-size: 15px; color: #0a1128;">
											<span class="dashicons dashicons-cloud" style="color: #1282a2;"></span> <?php esc_html_e( 'Nik Voice Cloud • Groq Whisper v3 Gateway', 'nik-voice-ticketing-ai' ); ?>
										</h4>
										<p style="margin: 0; font-size: 13px; color: #64748b; max-width: 580px;">
											<?php esc_html_e( 'Instant zero-configuration pairing. Provisions 50 free transcription requests/month immediately on Free Starter. Audio streams in-flight with Zero Local Disk Retention (Canadian PIPEDA compliant).', 'nik-voice-ticketing-ai' ); ?>
										</p>
									</div>
									<div>
										<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=nikvotia_connect_cloud' ), 'nikvotia_connect_cloud_nonce' ) ); ?>" class="button button-primary button-large" style="background: #ff6d00; border-color: #ff6d00; color: #ffffff; font-weight: 700; padding: 6px 20px; font-size: 14px; box-shadow: 0 4px 14px rgba(255, 109, 0, 0.35);">
											⚡ <?php esc_html_e( '1-Click Connect to Nik Voice Cloud', 'nik-voice-ticketing-ai' ); ?>
										</a>
									</div>
								</div>
							</div>
						<?php endif; ?>

						<details style="margin-top: 10px; font-size: 13px; color: #64748b; cursor: pointer;" <?php echo ! empty( $cloud_key ) ? 'open' : ''; ?>>
							<summary style="font-weight: 600; color: #1282a2;">
								<?php esc_html_e( '⚙️ Manual API Keys & Custom Gateway Settings (BYOK)', 'nik-voice-ticketing-ai' ); ?>
							</summary>
							<div style="margin-top: 12px; padding: 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
								<div style="margin-bottom: 10px;">
									<label style="display: block; font-weight: 600; margin-bottom: 4px;" for="nikvotia_cloud_key"><?php esc_html_e( 'Nik Voice Cloud Key ID (nik_live_...):', 'nik-voice-ticketing-ai' ); ?></label>
									<input type="text" id="nikvotia_cloud_key" name="nikvotia_cloud_key" value="<?php echo esc_attr( $cloud_key ); ?>" placeholder="nik_live_..." class="regular-text nik-vd-input-text" />
									<p class="description" style="margin-top: 2px; font-size: 12px;"><?php esc_html_e( 'Enter your license or customer API Key ID (nik_live_...). Saving your key connects immediately and activates Groq Whisper-v3 Speech-to-Text.', 'nik-voice-ticketing-ai' ); ?></p>
								</div>
								<div style="margin-bottom: 10px;">
									<label style="display: block; font-weight: 600; margin-bottom: 4px;" for="nikvotia_cloud_secret"><?php esc_html_e( 'Nik Voice Cloud API Secret (nik_sec_... - Optional):', 'nik-voice-ticketing-ai' ); ?></label>
									<input type="password" id="nikvotia_cloud_secret" name="nikvotia_cloud_secret" value="<?php echo esc_attr( $cloud_secret ); ?>" placeholder="nik_sec_..." class="regular-text nik-vd-input-text" autocomplete="off" />
									<p class="description" style="margin-top: 2px; font-size: 12px;"><?php esc_html_e( 'Optional. Only required if your custom gateway enforces HMAC-SHA256 signature verification. Standard API keys do not require a secret.', 'nik-voice-ticketing-ai' ); ?></p>
								</div>
								<div>
									<label style="display: block; font-weight: 600; margin-bottom: 4px;" for="nikvotia_gateway_url"><?php esc_html_e( 'Custom Gateway URL (Optional):', 'nik-voice-ticketing-ai' ); ?></label>
									<input type="url" id="nikvotia_gateway_url" name="nikvotia_gateway_url" value="<?php echo esc_attr( $cloud_gateway ); ?>" placeholder="https://voice.nikneural.ca" class="regular-text nik-vd-input-text" />
									<p class="description" style="margin-top: 2px; font-size: 12px;"><?php esc_html_e( 'Leave empty for official production gateway (https://voice.nikneural.ca). Set to http://127.0.0.1:8080 for local development.', 'nik-voice-ticketing-ai' ); ?></p>
								</div>
							</div>
						</details>
					</td>
				</tr>

				<!-- Section: OpenAI -->
				<tr>
					<th scope="row">
						<label for="nikvotia_openai_key"><?php esc_html_e( 'OpenAI API Key', 'nik-voice-ticketing-ai' ); ?></label>
						<?php if ( 'openai' === $active_provider ) : ?>
							<span style="display:block; font-size:11px; color:#16a34a; font-weight:600;"><?php esc_html_e( '● ACTIVE ENGINE', 'nik-voice-ticketing-ai' ); ?></span>
						<?php else : ?>
							<span style="display:block; font-size:11px; color:#94a3b8; font-weight:400;"><?php esc_html_e( '○ Inactive (Dormant)', 'nik-voice-ticketing-ai' ); ?></span>
						<?php endif; ?>
					</th>
					<td>
						<input type="password" id="nikvotia_openai_key" name="nikvotia_openai_key" value="<?php echo esc_attr( $openai_key ); ?>" class="regular-text nik-vd-input-text" autocomplete="off" />
						<p class="description">
							<?php esc_html_e( 'Used for Whisper audio transcription and GPT-4o-mini summarization when OpenAI is the selected Primary AI Provider.', 'nik-voice-ticketing-ai' ); ?>
							<?php if ( 'openai' !== $active_provider && ! empty( $openai_key ) ) : ?>
								<strong style="color: #ca8a04; display: block; margin-top: 3px;"><?php esc_html_e( '⚠️ Key is saved but currently dormant because another engine is active.', 'nik-voice-ticketing-ai' ); ?></strong>
							<?php endif; ?>
						</p>
					</td>
				</tr>

				<!-- Section: Modulate.ai -->
				<tr>
					<th scope="row">
						<label for="nikvotia_modulate_key"><?php esc_html_e( 'Modulate.ai API Key', 'nik-voice-ticketing-ai' ); ?></label>
						<?php if ( 'modulate_ai' === $active_provider ) : ?>
							<span style="display:block; font-size:11px; color:#16a34a; font-weight:600;"><?php esc_html_e( '● ACTIVE ENGINE', 'nik-voice-ticketing-ai' ); ?></span>
						<?php else : ?>
							<span style="display:block; font-size:11px; color:#94a3b8; font-weight:400;"><?php esc_html_e( '○ Inactive (Dormant)', 'nik-voice-ticketing-ai' ); ?></span>
						<?php endif; ?>
					</th>
					<td>
						<input type="password" id="nikvotia_modulate_key" name="nikvotia_modulate_key" value="<?php echo esc_attr( $modulate_key ); ?>" class="regular-text nik-vd-input-text" autocomplete="off" />
						<p class="description">
							<?php esc_html_e( 'Used for Velma-2 multilingual speech-to-text and voice intelligence analytics when Modulate.ai is the selected Primary AI Provider.', 'nik-voice-ticketing-ai' ); ?>
							<?php if ( 'modulate_ai' !== $active_provider && ! empty( $modulate_key ) ) : ?>
								<strong style="color: #ca8a04; display: block; margin-top: 3px;"><?php esc_html_e( '⚠️ Key is saved but currently dormant because another engine is active.', 'nik-voice-ticketing-ai' ); ?></strong>
							<?php endif; ?>
						</p>
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
	 * Render the System & AI Gateway Activity Logs tab.
	 */
	private function render_logs_tab() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice redirect flag.
		$cleared = isset( $_GET['nik_logs_cleared'] );
		$logs    = Nikvotia_Logger::get_logs( 100 );
		$count   = count( $logs );
		$active_provider = self::get_active_ai_provider();
		?>
		<?php if ( $cleared ) : ?>
			<div class="notice notice-success is-dismissible" style="margin-bottom: 20px;">
				<p><strong><span class="dashicons dashicons-yes-alt" style="color: #16a34a;"></span> <?php esc_html_e( 'System & AI debug logs cleared successfully.', 'nik-voice-ticketing-ai' ); ?></strong></p>
			</div>
		<?php endif; ?>

		<div class="nik-vd-settings-section">
			<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 18px;">
				<div>
					<h3 style="margin: 0 0 4px 0;"><span class="dashicons dashicons-media-text"></span> <?php esc_html_e( 'System & AI Gateway Activity Logs', 'nik-voice-ticketing-ai' ); ?></h3>
					<p class="description" style="margin: 0;">
						<?php
						/* translators: %d: Total log entries count */
						printf( esc_html__( 'Displaying the last %d recorded events for security verification, audio uploads, and AI gateway communications.', 'nik-voice-ticketing-ai' ), intval( $count ) );
						?>
					</p>
				</div>
				<div style="display: flex; gap: 8px;">
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=nikvotia_download_logs' ), 'nikvotia_download_logs_action', 'nikvotia_download_logs_nonce' ) ); ?>" class="button button-secondary">
						<span class="dashicons dashicons-download" style="margin-top: 3px;"></span> <?php esc_html_e( 'Download Logs (.txt)', 'nik-voice-ticketing-ai' ); ?>
					</a>
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=nikvotia_clear_logs' ), 'nikvotia_clear_logs_action', 'nikvotia_clear_logs_nonce' ) ); ?>" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to clear all VoiceDesk debug logs?', 'nik-voice-ticketing-ai' ); ?>');" class="button button-secondary" style="color: #dc2626;">
						<span class="dashicons dashicons-trash" style="margin-top: 3px;"></span> <?php esc_html_e( 'Clear Logs', 'nik-voice-ticketing-ai' ); ?>
					</a>
					<a href="?post_type=voicedesk_ticket&page=nikvotia-settings&tab=logs" class="button button-secondary">
						<span class="dashicons dashicons-update" style="margin-top: 3px;"></span> <?php esc_html_e( 'Refresh', 'nik-voice-ticketing-ai' ); ?>
					</a>
				</div>
			</div>

			<!-- Environment & Engine Status Bar -->
			<div class="nik-vd-card" style="border: 1px solid #e2e8f0; background: #f8fafc; padding: 14px 18px; border-radius: 8px; margin-bottom: 20px;">
				<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; font-size: 13px;">
					<div>
						<strong><?php esc_html_e( 'Active AI Provider:', 'nik-voice-ticketing-ai' ); ?></strong>
						<span style="margin-left: 6px;"><?php echo esc_html( self::get_provider_label( $active_provider ) ); ?></span>
					</div>
					<div>
						<strong><?php esc_html_e( 'Nik Cloud Gateway:', 'nik-voice-ticketing-ai' ); ?></strong>
						<code><?php echo esc_html( Nikvotia_Cloud_Client::get_gateway_url() ); ?></code>
					</div>
					<div>
						<strong><?php esc_html_e( 'WordPress Debug Log:', 'nik-voice-ticketing-ai' ); ?></strong>
						<span style="margin-left: 6px;"><?php echo ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) ? '<span style="color:#16a34a; font-weight:600;">✓ Enabled (wp-content/debug.log)</span>' : '<span style="color:#64748b;">Standard (options-backed)</span>'; ?></span>
					</div>
				</div>
			</div>

			<!-- Logs Table -->
			<?php if ( empty( $logs ) ) : ?>
				<div class="nik-vd-card" style="padding: 30px; text-align: center; color: #64748b; border: 1px dashed #cbd5e1; border-radius: 8px;">
					<span class="dashicons dashicons-yes" style="font-size: 36px; width: 36px; height: 36px; color: #16a34a; margin-bottom: 8px;"></span>
					<p style="margin: 0; font-size: 14px;"><strong><?php esc_html_e( 'No log entries recorded yet.', 'nik-voice-ticketing-ai' ); ?></strong></p>
					<p style="margin: 4px 0 0 0; font-size: 12.5px;"><?php esc_html_e( 'Logs will automatically populate when customers submit tickets or when the gateway processes requests.', 'nik-voice-ticketing-ai' ); ?></p>
				</div>
			<?php else : ?>
				<div style="border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; background: #ffffff;">
					<table class="widefat fixed striped" style="border: none; margin: 0;">
						<thead>
							<tr>
								<th style="width: 160px; font-weight: 600;"><?php esc_html_e( 'Timestamp', 'nik-voice-ticketing-ai' ); ?></th>
								<th style="width: 100px; font-weight: 600;"><?php esc_html_e( 'Severity', 'nik-voice-ticketing-ai' ); ?></th>
								<th style="width: 140px; font-weight: 600;"><?php esc_html_e( 'Category', 'nik-voice-ticketing-ai' ); ?></th>
								<th style="font-weight: 600;"><?php esc_html_e( 'Event & Details', 'nik-voice-ticketing-ai' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $logs as $l ) :
								$lvl = $l['level'] ?? 'INFO';
								$cat = $l['category'] ?? 'GENERAL';
								$time = $l['timestamp'] ?? '';
								$msg = $l['message'] ?? '';
								$ctx = $l['context'] ?? array();

								$badge_class = 'nik-status-open';
								if ( 'SUCCESS' === $lvl ) {
									$badge_class = 'nik-status-resolved';
								} elseif ( 'ERROR' === $lvl ) {
									$badge_class = 'nik-status-closed';
								} elseif ( 'WARNING' === $lvl || 'SECURITY' === $lvl ) {
									$badge_class = 'nik-status-in-progress';
								}
							?>
								<tr>
									<td style="font-size: 12px; color: #64748b; vertical-align: top;">
										<strong><?php echo esc_html( $time ); ?></strong>
										<?php if ( ! empty( $l['time_ago'] ) ) : ?>
											<br><small style="color: #94a3b8;"><?php echo esc_html( $l['time_ago'] . ' ago' ); ?></small>
										<?php endif; ?>
									</td>
									<td style="vertical-align: top;">
										<span class="nik-status-pill <?php echo esc_attr( $badge_class ); ?>" style="font-size: 10.5px; font-weight: 700; padding: 2px 7px;">
											<?php echo esc_html( $lvl ); ?>
										</span>
									</td>
									<td style="vertical-align: top;">
										<span class="nik-dept-badge" style="font-size: 11px;">
											<?php echo esc_html( $cat ); ?>
										</span>
									</td>
									<td style="vertical-align: top; font-size: 13px;">
										<strong><?php echo esc_html( $msg ); ?></strong>
										<?php if ( ! empty( $ctx ) ) : ?>
											<details style="margin-top: 6px; font-size: 12px; color: #64748b;">
												<summary style="cursor: pointer; color: #0284c7;"><?php esc_html_e( 'View Context Data', 'nik-voice-ticketing-ai' ); ?></summary>
												<pre style="margin-top: 6px; background: #f1f5f9; padding: 8px; border-radius: 4px; overflow-x: auto; font-size: 11.5px;"><?php echo esc_html( wp_json_encode( $ctx, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
											</details>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

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

	/**
	 * Handle 1-Click Connect Handshake request from WordPress admin.
	 */
	public function handle_connect_cloud() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized user.', 'nik-voice-ticketing-ai' ) );
		}
		check_admin_referer( 'nikvotia_connect_cloud_nonce' );

		$res = Nikvotia_Cloud_Client::connect_handshake();
		if ( is_wp_error( $res ) ) {
			$msg = rawurlencode( $res->get_error_message() );
			wp_safe_redirect( add_query_arg( array( 'tab' => 'api', 'nik_cloud_error' => $msg ), admin_url( 'edit.php?post_type=voicedesk_ticket&page=nikvotia-settings' ) ) );
			exit;
		}

		// Automatically switch STT engine to Nik Voice Cloud
		update_option( 'nikvotia_stt_model', 'nik_voice_cloud' );

		wp_safe_redirect( add_query_arg( array( 'tab' => 'api', 'nik_cloud_success' => '1' ), admin_url( 'edit.php?post_type=voicedesk_ticket&page=nikvotia-settings' ) ) );
		exit;
	}

	/**
	 * Handle Disconnect request from WordPress admin.
	 */
	public function handle_disconnect_cloud() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized user.', 'nik-voice-ticketing-ai' ) );
		}
		check_admin_referer( 'nikvotia_disconnect_cloud_nonce' );

		Nikvotia_Cloud_Client::disconnect();

		wp_safe_redirect( add_query_arg( array( 'tab' => 'api', 'nik_cloud_disconnected' => '1' ), admin_url( 'edit.php?post_type=voicedesk_ticket&page=nikvotia-settings' ) ) );
		exit;
	}

	/**
	 * Handle manual gateway diagnostic test from WordPress admin.
	 */
	public function handle_test_cloud() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized user.', 'nik-voice-ticketing-ai' ) );
		}
		check_admin_referer( 'nikvotia_test_cloud_nonce' );

		$health = Nikvotia_Cloud_Client::check_health();
		if ( ! empty( $health['ok'] ) ) {
			Nikvotia_Logger::log(
				'SUCCESS',
				'AI_GATEWAY',
				sprintf(
					'Manual Gateway Health Check OK: Service [%s], Engine [%s], Jurisdiction [%s]',
					$health['service'] ?? 'Nik Voice Cloud',
					$health['engine'] ?? 'Groq Whisper-Large-v3',
					$health['jurisdiction'] ?? 'Canada (PIPEDA)'
				),
				$health
			);
			wp_safe_redirect( add_query_arg( array( 'tab' => 'api', 'nik_cloud_tested' => '1', 'cloud_engine' => rawurlencode( $health['engine'] ?? '' ) ), admin_url( 'edit.php?post_type=voicedesk_ticket&page=nikvotia-settings' ) ) );
			exit;
		}

		$err = $health['message'] ?? 'Could not reach gateway.';
		Nikvotia_Logger::log( 'ERROR', 'AI_GATEWAY', 'Manual Gateway Health Check failed: ' . $err );
		wp_safe_redirect( add_query_arg( array( 'tab' => 'api', 'nik_cloud_error' => rawurlencode( $err ) ), admin_url( 'edit.php?post_type=voicedesk_ticket&page=nikvotia-settings' ) ) );
		exit;
	}
}

