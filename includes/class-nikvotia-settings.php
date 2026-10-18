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
		add_action( 'admin_post_nikvotia_purge_expired_audio', array( $this, 'handle_purge_expired_audio' ) );

		// Scheduled daily audio data retention cleanup
		add_action( 'nikvotia_daily_data_retention_cleanup', array( __CLASS__, 'run_daily_retention_cleanup' ) );
		if ( ! wp_next_scheduled( 'nikvotia_daily_data_retention_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'nikvotia_daily_data_retention_cleanup' );
		}
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
		register_setting( 'nikvotia_general', 'nikvotia_telemetry_share_email', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'no',
		) );
		register_setting( 'nikvotia_general', 'nikvotia_audio_retention_days', array(
			'type'              => 'integer',
			'sanitize_callback' => 'intval',
			'default'           => 30,
		) );
		register_setting( 'nikvotia_general', 'nikvotia_enable_consent_notice', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '1',
		) );
		register_setting( 'nikvotia_general', 'nikvotia_consent_notice_text', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => __( 'By recording, you consent to AI processing of your voice per our privacy policy.', 'nik-voice-ticketing-ai' ),
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
			<h3><span class="dashicons dashicons-shield"></span> <?php esc_html_e( 'Data Retention, Privacy & Storage Limitation Controls', 'nik-voice-ticketing-ai' ); ?></h3>
			<table class="form-table nik-vd-table">
				<tr>
					<th scope="row"><label for="nikvotia_audio_retention_days"><?php esc_html_e( 'Audio Data Retention Window', 'nik-voice-ticketing-ai' ); ?></label></th>
					<td>
						<?php
						$retention_days = (int) get_option( 'nikvotia_audio_retention_days', 30 );
						$purge_url      = wp_nonce_url( admin_url( 'admin-post.php?action=nikvotia_purge_expired_audio' ), 'nikvotia_purge_audio_action', 'nikvotia_purge_audio_nonce' );
						?>
						<div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
							<select id="nikvotia_audio_retention_days" name="nikvotia_audio_retention_days" class="nik-vd-input-select">
								<option value="0" <?php selected( $retention_days, 0 ); ?>><?php esc_html_e( '0 Days (Purge audio immediately after AI transcription)', 'nik-voice-ticketing-ai' ); ?></option>
								<option value="7" <?php selected( $retention_days, 7 ); ?>><?php esc_html_e( '7 Days (Strict Minimization / Healthcare & Financial Workflows)', 'nik-voice-ticketing-ai' ); ?></option>
								<option value="14" <?php selected( $retention_days, 14 ); ?>><?php esc_html_e( '14 Days', 'nik-voice-ticketing-ai' ); ?></option>
								<option value="30" <?php selected( $retention_days, 30 ); ?>><?php esc_html_e( '30 Days (Recommended Support Default)', 'nik-voice-ticketing-ai' ); ?></option>
								<option value="60" <?php selected( $retention_days, 60 ); ?>><?php esc_html_e( '60 Days', 'nik-voice-ticketing-ai' ); ?></option>
								<option value="90" <?php selected( $retention_days, 90 ); ?>><?php esc_html_e( '90 Days (Quarterly)', 'nik-voice-ticketing-ai' ); ?></option>
								<option value="180" <?php selected( $retention_days, 180 ); ?>><?php esc_html_e( '180 Days (Half-Year)', 'nik-voice-ticketing-ai' ); ?></option>
								<option value="365" <?php selected( $retention_days, 365 ); ?>><?php esc_html_e( '365 Days (1 Year)', 'nik-voice-ticketing-ai' ); ?></option>
								<option value="-1" <?php selected( $retention_days, -1 ); ?>><?php esc_html_e( 'Indefinite (Never auto-delete audio recordings)', 'nik-voice-ticketing-ai' ); ?></option>
							</select>
							<a href="<?php echo esc_url( $purge_url ); ?>" class="button button-secondary" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to run audio retention pruning right now? Audio recordings older than the configured retention window will be permanently removed from disk.', 'nik-voice-ticketing-ai' ) ); ?>');">
								<span class="dashicons dashicons-trash" style="vertical-align: middle; margin-right: 2px;"></span> <?php esc_html_e( 'Purge Expired Audio Now', 'nik-voice-ticketing-ai' ); ?>
							</a>
						</div>
						<p class="description">
							<?php esc_html_e( 'Automatically purges stored audio recording files from your server once tickets exceed the retention period. AI summaries, ticket metadata, and conversation threads remain intact. Designed to assist organizations implementing storage limitation practices under PIPEDA Principle 4.5 and GDPR Article 5(1)(e). Technical controls do not constitute legal compliance certification.', 'nik-voice-ticketing-ai' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Recording Consent Notice', 'nik-voice-ticketing-ai' ); ?></th>
					<td>
						<?php
						$consent_enabled = get_option( 'nikvotia_enable_consent_notice', '1' );
						$consent_text    = get_option( 'nikvotia_consent_notice_text', __( 'By recording, you consent to AI processing of your voice per our privacy policy.', 'nik-voice-ticketing-ai' ) );
						?>
						<label class="nik-vd-checkbox-label" style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
							<input type="hidden" name="nikvotia_enable_consent_notice" value="0" />
							<input type="checkbox" name="nikvotia_enable_consent_notice" value="1" <?php checked( $consent_enabled, '1' ); ?> />
							<strong><?php esc_html_e( 'Display explicit AI audio processing consent notice on frontend dock', 'nik-voice-ticketing-ai' ); ?></strong>
						</label>
						<input type="text" id="nikvotia_consent_notice_text" name="nikvotia_consent_notice_text" value="<?php echo esc_attr( $consent_text ); ?>" class="large-text nik-vd-input-text" />
						<p class="description"><?php esc_html_e( 'Notice displayed to visitors before/during voice recording to fulfill consent disclosure requirements.', 'nik-voice-ticketing-ai' ); ?></p>
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
				<tr>
					<th scope="row"><?php esc_html_e( 'Telemetry PII Minimization', 'nik-voice-ticketing-ai' ); ?></th>
					<td>
						<?php $share_email = get_option( 'nikvotia_telemetry_share_email', 'no' ); ?>
						<label class="nik-vd-checkbox-label" style="display: flex; align-items: center; gap: 8px;">
							<input type="hidden" name="nikvotia_telemetry_share_email" value="no" />
							<input type="checkbox" name="nikvotia_telemetry_share_email" value="yes" <?php checked( $share_email, 'yes' ); ?> />
							<span><?php esc_html_e( 'Include site administrator email in diagnostic telemetry for direct critical security notifications', 'nik-voice-ticketing-ai' ); ?></span>
						</label>
						<p class="description"><?php esc_html_e( 'When unchecked (recommended for data minimization), telemetry packets contain zero personal identifiers.', 'nik-voice-ticketing-ai' ); ?></p>
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
				<p>
					<strong><span class="dashicons dashicons-yes-alt" style="color: #16a34a;"></span> <?php esc_html_e( 'Nik Voice Cloud Gateway Verified Live!', 'nik-voice-ticketing-ai' ); ?></strong>
					<?php
					/* translators: 1: Active Gateway URL, 2: Cloud engine name */
					printf( esc_html__( 'Connected to %1$s (Engine: %2$s • Sovereign Ephemeral Cloud STT Gateway). Diagnostic health status logged in System & AI Logs.', 'nik-voice-ticketing-ai' ), esc_html( $active_gateway ), esc_html( $cloud_engine_name ) );
					?>
				</p>
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
			<div class="nik-vd-card nik-api-isolation-banner">
				<div class="nik-api-isolation-content">
					<div>
						<strong class="nik-api-isolation-title">
							<span class="dashicons dashicons-shield-alt"></span>
							<?php
							/* translators: %s: Active AI engine name */
							printf( esc_html__( 'Active Engine: %s', 'nik-voice-ticketing-ai' ), esc_html( self::get_provider_label( $active_provider ) ) );
							?>
						</strong>
						<p class="nik-api-isolation-desc">
							<?php esc_html_e( 'Strict Single-Provider Isolation Enforced. Incoming voice tickets will only ever contact this chosen provider for both Speech-to-Text and ticket triage. All other configured APIs are strictly dormant.', 'nik-voice-ticketing-ai' ); ?>
						</p>
					</div>
					<span class="nik-ai-pill nik-ai-cloud nik-isolation-pill">
						🔒 <?php esc_html_e( 'Strict Provider Isolation', 'nik-voice-ticketing-ai' ); ?>
					</span>
				</div>
			</div>

			<table class="form-table nik-vd-table">
				<tr>
					<th scope="row"><label for="nikvotia_ai_provider"><?php esc_html_e( 'Primary AI Provider (Single-Engine Mode)', 'nik-voice-ticketing-ai' ); ?></label></th>
					<td>
						<select id="nikvotia_ai_provider" name="nikvotia_ai_provider" class="nik-vd-input-select" style="min-width: 340px; font-weight: 600;">
							<option value="nik_voice_cloud" <?php selected( $active_provider, 'nik_voice_cloud' ); ?>>
								⚡ <?php esc_html_e( 'Nik Voice Cloud (Groq Whisper-v3 • Ephemeral Cloud Inference)', 'nik-voice-ticketing-ai' ); ?>
							</option>
							<option value="openai" <?php selected( $active_provider, 'openai' ); ?>>
								🤖 <?php esc_html_e( 'OpenAI Cloud (Whisper-1 + GPT-4o-mini)', 'nik-voice-ticketing-ai' ); ?>
							</option>
							<option value="modulate_ai" <?php selected( $active_provider, 'modulate_ai' ); ?>>
								🎙️ <?php esc_html_e( 'Modulate.ai (Velma-2 Fast STT + Voice Intelligence)', 'nik-voice-ticketing-ai' ); ?>
							</option>
						</select>
						<p class="description" style="margin-top: 6px;">
							<?php esc_html_e( 'Selecting an engine binds both Speech-to-Text and triage to that single provider. Audio recordings are stored locally on your WordPress server and retained according to your Data Retention settings; the cloud gateway performs ephemeral inference without retaining audio files on cloud storage.', 'nik-voice-ticketing-ai' ); ?>
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
							<div class="nik-vd-card nik-api-cloud-card nik-api-cloud-connected">
								<div class="nik-api-cloud-header">
									<div class="nik-api-cloud-status">
										<span class="nik-api-status-dot"></span>
										<strong class="nik-api-status-label"><?php esc_html_e( 'Connected & Synchronized', 'nik-voice-ticketing-ai' ); ?></strong>
									</div>
									<div class="nik-api-cloud-actions">
										<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=nikvotia_test_cloud' ), 'nikvotia_test_cloud_nonce' ) ); ?>" class="button button-secondary nik-api-btn-sm">
											<span class="dashicons dashicons-heart" style="color: #16a34a;"></span> <?php esc_html_e( 'Test Gateway Health', 'nik-voice-ticketing-ai' ); ?>
										</a>
										<a href="<?php echo esc_url( $active_gateway . '/dashboard' ); ?>" target="_blank" rel="noopener noreferrer" class="button button-secondary nik-api-btn-sm">
											<span class="dashicons dashicons-chart-pie"></span> <?php esc_html_e( 'View Cloud Quota & Invoices', 'nik-voice-ticketing-ai' ); ?>
										</a>
										<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=nikvotia_disconnect_cloud' ), 'nikvotia_disconnect_cloud_nonce' ) ); ?>" onclick="return confirm('<?php esc_attr_e( 'Disconnect from Nik Voice Cloud? This will remove your active API keys from this site.', 'nik-voice-ticketing-ai' ); ?>');" class="button button-link-delete nik-api-btn-disconnect">
											<?php esc_html_e( 'Disconnect Site', 'nik-voice-ticketing-ai' ); ?>
										</a>
									</div>
								</div>
								<table class="nik-api-meta-table">
									<tbody>
										<tr>
											<th scope="row" class="nik-api-meta-th">
												<span class="dashicons dashicons-admin-network"></span>
												<?php esc_html_e( 'Gateway URL', 'nik-voice-ticketing-ai' ); ?>
											</th>
											<td class="nik-api-meta-td">
												<code class="nik-api-code-val"><?php echo esc_html( $active_gateway ); ?></code>
											</td>
											<th scope="row" class="nik-api-meta-th">
												<span class="dashicons dashicons-admin-site-alt3"></span>
												<?php esc_html_e( 'Domain Binding', 'nik-voice-ticketing-ai' ); ?>
											</th>
											<td class="nik-api-meta-td">
												<code class="nik-api-code-val"><?php echo esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ); ?></code>
											</td>
										</tr>
										<tr>
											<th scope="row" class="nik-api-meta-th">
												<span class="dashicons dashicons-key"></span>
												<?php esc_html_e( 'API Key ID', 'nik-voice-ticketing-ai' ); ?>
											</th>
											<td colspan="3" class="nik-api-meta-td">
												<code class="nik-api-code-val nik-api-code-key"><?php echo esc_html( $cloud_key ); ?></code>
											</td>
										</tr>
									</tbody>
								</table>
								<div class="nik-api-tip-box">
									<strong><?php esc_html_e( '💡 Production Tip for Real-Time Speech:', 'nik-voice-ticketing-ai' ); ?></strong>
									<?php esc_html_e( 'If your tickets display "[Nik Voice Cloud Demo]", the gateway at voice.nikneural.ca is running in demo fallback mode. To enable live speech transcription of customer voices, configure GROQ_API_KEY=gsk_... on your gateway server environment (in docker-compose or .env) and restart the gateway container.', 'nik-voice-ticketing-ai' ); ?>
								</div>
							</div>
						<?php else : ?>
							<div class="nik-vd-card nik-api-cloud-card nik-api-cloud-unconnected">
								<div class="nik-api-cloud-inner">
									<div>
										<h4 class="nik-api-cloud-title">
											<span class="dashicons dashicons-cloud"></span> <?php esc_html_e( 'Nik Voice Cloud • Groq Whisper v3 Gateway', 'nik-voice-ticketing-ai' ); ?>
										</h4>
										<p class="nik-api-cloud-desc">
											<?php esc_html_e( 'Instant zero-configuration pairing. Provisions 50 free transcription requests/month on Free Starter. Ephemeral cloud AI inference with configurable local storage retention on your WordPress server.', 'nik-voice-ticketing-ai' ); ?>
										</p>
									</div>
									<div>
										<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=nikvotia_connect_cloud' ), 'nikvotia_connect_cloud_nonce' ) ); ?>" class="button button-primary button-large nik-api-connect-btn">
											⚡ <?php esc_html_e( '1-Click Connect to Nik Voice Cloud', 'nik-voice-ticketing-ai' ); ?>
										</a>
									</div>
								</div>
							</div>
						<?php endif; ?>

						<details class="nik-api-byok-details" <?php echo ! empty( $cloud_key ) ? 'open' : ''; ?>>
							<summary class="nik-api-byok-summary">
								<?php esc_html_e( '⚙️ Manual API Keys & Custom Gateway Settings (BYOK)', 'nik-voice-ticketing-ai' ); ?>
							</summary>
							<div class="nik-api-byok-box">
								<div class="nik-api-field-group">
									<label class="nik-api-label" for="nikvotia_cloud_key"><?php esc_html_e( 'Nik Voice Cloud Key ID (nik_live_...):', 'nik-voice-ticketing-ai' ); ?></label>
									<input type="text" id="nikvotia_cloud_key" name="nikvotia_cloud_key" value="<?php echo esc_attr( $cloud_key ); ?>" placeholder="nik_live_..." class="regular-text nik-vd-input-text" />
									<p class="description"><?php esc_html_e( 'Enter your license or customer API Key ID (nik_live_...). Saving your key connects immediately and activates Groq Whisper-v3 Speech-to-Text.', 'nik-voice-ticketing-ai' ); ?></p>
								</div>
								<div class="nik-api-field-group">
									<label class="nik-api-label" for="nikvotia_cloud_secret"><?php esc_html_e( 'Nik Voice Cloud API Secret (nik_sec_... - Optional):', 'nik-voice-ticketing-ai' ); ?></label>
									<input type="password" id="nikvotia_cloud_secret" name="nikvotia_cloud_secret" value="<?php echo esc_attr( $cloud_secret ); ?>" placeholder="nik_sec_..." class="regular-text nik-vd-input-text" autocomplete="off" />
									<p class="description"><?php esc_html_e( 'Optional. Only required if your custom gateway enforces HMAC-SHA256 signature verification. Standard API keys do not require a secret.', 'nik-voice-ticketing-ai' ); ?></p>
								</div>
								<div class="nik-api-field-group">
									<label class="nik-api-label" for="nikvotia_gateway_url"><?php esc_html_e( 'Custom Gateway URL (Optional):', 'nik-voice-ticketing-ai' ); ?></label>
									<input type="url" id="nikvotia_gateway_url" name="nikvotia_gateway_url" value="<?php echo esc_attr( $cloud_gateway ); ?>" placeholder="https://voice.nikneural.ca" class="regular-text nik-vd-input-text" />
									<p class="description"><?php esc_html_e( 'Leave empty for official production gateway (https://voice.nikneural.ca). Set to http://127.0.0.1:8080 for local development.', 'nik-voice-ticketing-ai' ); ?></p>
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
			<div class="nik-pro-hero-banner">
				<div class="nik-pro-hero-left">
					<div class="nik-pro-badge">
						<span class="dashicons dashicons-shield"></span>
						<?php esc_html_e( 'Nik Neural AI Inc. • Sovereign Canadian Enterprise', 'nik-voice-ticketing-ai' ); ?>
					</div>
					<h3 class="nik-pro-hero-title"><?php esc_html_e( 'Enterprise Cloud & Sovereign Infrastructure Solutions', 'nik-voice-ticketing-ai' ); ?></h3>
					<p class="nik-pro-hero-desc">
						<?php esc_html_e( 'Nik Voice Ticketing AI is 100% free and open-source with zero artificial limits. For organizations requiring managed private cloud gateways, Canadian data residency SLAs, custom CRM and webhook integrations, or enterprise ERP connectors, Nik Neural AI Inc. delivers bespoke mission-critical engineering.', 'nik-voice-ticketing-ai' ); ?>
					</p>
				</div>
				<div class="nik-pro-hero-actions">
					<a href="https://nikneural.ca/voicedesk-ai/" target="_blank" rel="noopener noreferrer" class="button button-primary button-large nik-pro-hero-btn">
						<?php esc_html_e( 'Explore VoiceDesk AI Enterprise →', 'nik-voice-ticketing-ai' ); ?>
					</a>
					<a href="https://nikneural.ca/#contact" target="_blank" rel="noopener noreferrer" class="button button-secondary button-large nik-pro-hero-btn-alt">
						<?php esc_html_e( 'Contact Solutions Team', 'nik-voice-ticketing-ai' ); ?>
					</a>
				</div>
			</div>

			<!-- 3 Pillar Feature Cards -->
			<div class="nik-pro-cards-grid">
				<div class="nik-vd-card nik-pro-card">
					<div class="nik-pro-card-header">
						<span class="dashicons dashicons-cloud nik-pro-card-icon" style="color: #0284c7;"></span>
						<h4><?php esc_html_e( 'Sovereign Canadian Cloud', 'nik-voice-ticketing-ai' ); ?></h4>
					</div>
					<div class="nik-pro-card-body">
						<p><?php esc_html_e( 'Managed ephemeral cloud speech processing within sovereign Canadian borders (audio is stored locally on WordPress per your retention setting; the cloud gateway holds data only in-flight during inference).', 'nik-voice-ticketing-ai' ); ?></p>
						<ul class="nik-pro-bullet-list">
							<li>✓ <?php esc_html_e( 'Managed Gateway at voice.nikneural.ca', 'nik-voice-ticketing-ai' ); ?></li>
							<li>✓ <?php esc_html_e( 'Sub-second Groq Whisper-v3 Inference', 'nik-voice-ticketing-ai' ); ?></li>
							<li>✓ <?php esc_html_e( 'Ephemeral Cloud Inference (Zero Cloud Storage)', 'nik-voice-ticketing-ai' ); ?></li>
						</ul>
					</div>
				</div>

				<div class="nik-vd-card nik-pro-card">
					<div class="nik-pro-card-header">
						<span class="dashicons dashicons-rest-api nik-pro-card-icon" style="color: #16a34a;"></span>
						<h4><?php esc_html_e( 'Enterprise CRM & Webhooks', 'nik-voice-ticketing-ai' ); ?></h4>
					</div>
					<div class="nik-pro-card-body">
						<p><?php esc_html_e( 'Automatically dispatch parsed voice tickets, audio streams, and sentiment scores to your core enterprise software in real time.', 'nik-voice-ticketing-ai' ); ?></p>
						<ul class="nik-pro-bullet-list">
							<li>✓ <?php esc_html_e( 'Salesforce & HubSpot Live Sync', 'nik-voice-ticketing-ai' ); ?></li>
							<li>✓ <?php esc_html_e( 'Zendesk & Jira Service Desk Integration', 'nik-voice-ticketing-ai' ); ?></li>
							<li>✓ <?php esc_html_e( 'Custom Webhook JSON Payloads & Headers', 'nik-voice-ticketing-ai' ); ?></li>
						</ul>
					</div>
				</div>

				<div class="nik-vd-card nik-pro-card">
					<div class="nik-pro-card-header">
						<span class="dashicons dashicons-admin-tools nik-pro-card-icon" style="color: #f59e0b;"></span>
						<h4><?php esc_html_e( 'Dedicated Support & Custom Models', 'nik-voice-ticketing-ai' ); ?></h4>
					</div>
					<div class="nik-pro-card-body">
						<p><?php esc_html_e( 'Direct SLA-backed engineering support with custom domain acoustic model tuning for specialized terminology and dialects.', 'nik-voice-ticketing-ai' ); ?></p>
						<ul class="nik-pro-bullet-list">
							<li>✓ <?php esc_html_e( '24/7 Dedicated Support & SLA', 'nik-voice-ticketing-ai' ); ?></li>
							<li>✓ <?php esc_html_e( 'Industry-Specific Terminology Tuning', 'nik-voice-ticketing-ai' ); ?></li>
							<li>✓ <?php esc_html_e( 'Custom Acoustic Audio Profiles', 'nik-voice-ticketing-ai' ); ?></li>
						</ul>
					</div>
				</div>
			</div>

			<!-- Plan Matrix Table -->
			<div class="nik-vd-card nik-pro-matrix-card">
				<div class="nik-vd-card-header">
					<h4><span class="dashicons dashicons-list-view"></span> <?php esc_html_e( 'Feature Matrix: Community vs. Enterprise Cloud', 'nik-voice-ticketing-ai' ); ?></h4>
				</div>
				<div class="nik-pro-matrix-wrapper">
					<table class="widefat fixed striped nik-pro-matrix-table">
						<thead>
							<tr>
								<th style="width: 40%; font-weight: 700;"><?php esc_html_e( 'Capability', 'nik-voice-ticketing-ai' ); ?></th>
								<th style="width: 30%; font-weight: 700; text-align: center;"><?php esc_html_e( 'Community Edition (Active)', 'nik-voice-ticketing-ai' ); ?></th>
								<th style="width: 30%; font-weight: 700; text-align: center; color: #0284c7;"><?php esc_html_e( 'Nik Voice Cloud Enterprise', 'nik-voice-ticketing-ai' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><strong><?php esc_html_e( 'Audio Capture & In-Browser Recording', 'nik-voice-ticketing-ai' ); ?></strong></td>
								<td style="text-align: center;"><span class="nik-matrix-check">✓</span> <?php esc_html_e( 'Unlimited', 'nik-voice-ticketing-ai' ); ?></td>
								<td style="text-align: center;"><span class="nik-matrix-check">✓</span> <?php esc_html_e( 'Unlimited + High Fidelity', 'nik-voice-ticketing-ai' ); ?></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'AI Speech-to-Text & Triage Engine', 'nik-voice-ticketing-ai' ); ?></strong></td>
								<td style="text-align: center;"><?php esc_html_e( 'BYOK (OpenAI, Modulate, Free Cloud)', 'nik-voice-ticketing-ai' ); ?></td>
								<td style="text-align: center; font-weight: 600; color: #0284c7;"><?php esc_html_e( 'Dedicated Groq Whisper-v3 TPU', 'nik-voice-ticketing-ai' ); ?></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'Audio Stream Security', 'nik-voice-ticketing-ai' ); ?></strong></td>
								<td style="text-align: center;"><?php esc_html_e( 'Time-Bounded HMAC-SHA256', 'nik-voice-ticketing-ai' ); ?></td>
								<td style="text-align: center;"><?php esc_html_e( 'HMAC-SHA256 + Private S3 / KMS', 'nik-voice-ticketing-ai' ); ?></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'Automated Audio Pruning', 'nik-voice-ticketing-ai' ); ?></strong></td>
								<td style="text-align: center;"><span class="nik-matrix-check">✓</span> <?php esc_html_e( 'Daily WP Cron', 'nik-voice-ticketing-ai' ); ?></td>
								<td style="text-align: center;"><span class="nik-matrix-check">✓</span> <?php esc_html_e( 'Zero Cloud Retention (Ephemeral Inference)', 'nik-voice-ticketing-ai' ); ?></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'WordPress Core Privacy Exporter & Eraser', 'nik-voice-ticketing-ai' ); ?></strong></td>
								<td style="text-align: center;"><span class="nik-matrix-check">✓</span> <?php esc_html_e( 'Included', 'nik-voice-ticketing-ai' ); ?></td>
								<td style="text-align: center;"><span class="nik-matrix-check">✓</span> <?php esc_html_e( 'Included + CRM Cascading', 'nik-voice-ticketing-ai' ); ?></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'Customer Portal Integration', 'nik-voice-ticketing-ai' ); ?></strong></td>
								<td style="text-align: center;"><span class="nik-matrix-check">✓</span> <?php esc_html_e( 'Self-Healing Auto-Page', 'nik-voice-ticketing-ai' ); ?></td>
								<td style="text-align: center;"><span class="nik-matrix-check">✓</span> <?php esc_html_e( 'Auto-Page + SSO / SAML', 'nik-voice-ticketing-ai' ); ?></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'Support & Service Level Agreement', 'nik-voice-ticketing-ai' ); ?></strong></td>
								<td style="text-align: center;"><?php esc_html_e( 'Community Forums', 'nik-voice-ticketing-ai' ); ?></td>
								<td style="text-align: center; font-weight: 600; color: #16a34a;"><?php esc_html_e( '24/7 Dedicated SLA & Engineering', 'nik-voice-ticketing-ai' ); ?></td>
							</tr>
						</tbody>
					</table>
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
				<div class="nik-logs-header-actions">
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=nikvotia_download_logs' ), 'nikvotia_download_logs_action', 'nikvotia_download_logs_nonce' ) ); ?>" class="button button-secondary nik-logs-btn">
						<span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Download Logs (.txt)', 'nik-voice-ticketing-ai' ); ?>
					</a>
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=nikvotia_clear_logs' ), 'nikvotia_clear_logs_action', 'nikvotia_clear_logs_nonce' ) ); ?>" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to clear all VoiceDesk debug logs?', 'nik-voice-ticketing-ai' ); ?>');" class="button button-secondary nik-logs-btn nik-logs-btn-danger">
						<span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Clear Logs', 'nik-voice-ticketing-ai' ); ?>
					</a>
					<a href="?post_type=voicedesk_ticket&page=nikvotia-settings&tab=logs" class="button button-secondary nik-logs-btn">
						<span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Refresh', 'nik-voice-ticketing-ai' ); ?>
					</a>
				</div>
			</div>

			<!-- Environment & Engine Status Bar -->
			<div class="nik-vd-card nik-logs-status-bar">
				<div class="nik-logs-status-grid">
					<div class="nik-logs-status-item">
						<span class="nik-logs-status-label"><?php esc_html_e( 'Active AI Provider', 'nik-voice-ticketing-ai' ); ?></span>
						<span class="nik-logs-status-val"><?php echo esc_html( self::get_provider_label( $active_provider ) ); ?></span>
					</div>
					<div class="nik-logs-status-item">
						<span class="nik-logs-status-label"><?php esc_html_e( 'Nik Cloud Gateway', 'nik-voice-ticketing-ai' ); ?></span>
						<code class="nik-logs-code"><?php echo esc_html( Nikvotia_Cloud_Client::get_gateway_url() ); ?></code>
					</div>
					<div class="nik-logs-status-item">
						<span class="nik-logs-status-label"><?php esc_html_e( 'WordPress Debug Log', 'nik-voice-ticketing-ai' ); ?></span>
						<span class="nik-logs-status-val"><?php echo ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) ? '<span class="nik-logs-val-success">✓ Enabled (wp-content/debug.log)</span>' : '<span class="nik-logs-val-muted">Standard (options-backed)</span>'; ?></span>
					</div>
				</div>
			</div>

			<!-- Logs Table -->
			<?php if ( empty( $logs ) ) : ?>
				<div class="nik-vd-card nik-logs-empty-card">
					<span class="dashicons dashicons-yes nik-logs-empty-icon"></span>
					<p class="nik-logs-empty-title"><strong><?php esc_html_e( 'No log entries recorded yet.', 'nik-voice-ticketing-ai' ); ?></strong></p>
					<p class="nik-logs-empty-desc"><?php esc_html_e( 'Logs will automatically populate when customers submit tickets or when the gateway processes requests.', 'nik-voice-ticketing-ai' ); ?></p>
				</div>
			<?php else : ?>
				<div class="nik-logs-table-wrapper">
					<table class="widefat fixed striped nik-logs-table">
						<thead>
							<tr>
								<th class="nik-col-timestamp"><?php esc_html_e( 'Timestamp', 'nik-voice-ticketing-ai' ); ?></th>
								<th class="nik-col-severity"><?php esc_html_e( 'Severity', 'nik-voice-ticketing-ai' ); ?></th>
								<th class="nik-col-category"><?php esc_html_e( 'Category', 'nik-voice-ticketing-ai' ); ?></th>
								<th class="nik-col-event"><?php esc_html_e( 'Event & Details', 'nik-voice-ticketing-ai' ); ?></th>
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
									<td class="nik-cell-timestamp">
										<strong><?php echo esc_html( $time ); ?></strong>
										<?php if ( ! empty( $l['time_ago'] ) ) : ?>
											<br><small class="nik-logs-time-ago"><?php echo esc_html( $l['time_ago'] . ' ago' ); ?></small>
										<?php endif; ?>
									</td>
									<td class="nik-cell-severity">
										<span class="nik-status-pill <?php echo esc_attr( $badge_class ); ?> nik-logs-sev-pill">
											<?php echo esc_html( $lvl ); ?>
										</span>
									</td>
									<td class="nik-cell-category">
										<span class="nik-dept-badge nik-logs-cat-pill">
											<?php echo esc_html( $cat ); ?>
										</span>
									</td>
									<td class="nik-cell-event">
										<strong class="nik-logs-event-msg"><?php echo esc_html( $msg ); ?></strong>
										<?php if ( ! empty( $ctx ) ) : ?>
											<details class="nik-logs-context-details">
												<summary class="nik-logs-context-summary"><?php esc_html_e( 'View Context Data', 'nik-voice-ticketing-ai' ); ?></summary>
												<pre class="nik-logs-context-pre"><?php echo esc_html( wp_json_encode( $ctx, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
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
						<span class="nik-status-pill nik-status-resolved"><?php esc_html_e( 'CANADIAN DATA RESIDENCY', 'nik-voice-ticketing-ai' ); ?></span>
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
				<div class="nik-about-hero-banner">
					<div class="nik-about-hero-text">
						<div class="nik-about-hero-pill">
							<img src="<?php echo esc_url( NIKVOTIA_URL . 'assets/images/favicon-light-32.png' ); ?>" alt="Nik Neural AI Inc." width="16" height="16" class="nik-about-favicon" />
							<?php esc_html_e( 'Canadian Enterprise AI • Self-Hosted', 'nik-voice-ticketing-ai' ); ?>
						</div>
						<h2 class="nik-about-hero-heading">
							<?php esc_html_e( 'Absolute Privacy. Zero SaaS Tax.', 'nik-voice-ticketing-ai' ); ?><br>
							<span class="nik-about-hero-highlight"><?php esc_html_e( 'AI Automation for Canadian Enterprises.', 'nik-voice-ticketing-ai' ); ?></span>
						</h2>
						<p class="nik-about-hero-summary">
							<?php esc_html_e( 'Nik Neural AI Inc. engineers production-ready, self-hosted AI automation platforms for brokerages, financial institutions, and enterprises. Deployed on your infrastructure — your data never leaves your servers.', 'nik-voice-ticketing-ai' ); ?>
						</p>
					</div>
					<div class="nik-about-hero-ctas">
						<a href="https://nikneural.ca/voicedesk-ai/" target="_blank" rel="noopener noreferrer" class="nik-about-btn-primary">
							<?php esc_html_e( 'VoiceDesk AI Overview →', 'nik-voice-ticketing-ai' ); ?>
						</a>
						<a href="https://nikneural.ca/#contact" target="_blank" rel="noopener noreferrer" class="nik-about-btn-secondary">
							<?php esc_html_e( 'Book a Consultation', 'nik-voice-ticketing-ai' ); ?>
						</a>
					</div>
				</div>

				<!-- 2-Column Content Grid -->
				<div class="nik-vd-grid-layout">
					<!-- Left Column: Mission & Platform -->
					<div class="nik-vd-col-main">
						<!-- v1.4.1 Architecture & Security Highlights Card -->
						<div class="nik-vd-card nik-about-card">
							<div class="nik-vd-card-header">
								<h3><span class="dashicons dashicons-shield"></span> <?php esc_html_e( 'v1.4.1 Architecture & Security Highlights', 'nik-voice-ticketing-ai' ); ?></h3>
							</div>
							<div class="nik-vd-card-body">
								<p class="nik-about-intro">
									<?php esc_html_e( 'Nik Voice Ticketing AI v1.4.1 introduces hardened enterprise security, privacy-respecting technical safeguards, and multi-mode contrast architecture:', 'nik-voice-ticketing-ai' ); ?>
								</p>
								<div class="nik-about-features-grid">
									<div class="nik-about-feature-box">
										<strong class="nik-about-feature-title">🍁 <?php esc_html_e( 'Sovereign Cloud Gateway', 'nik-voice-ticketing-ai' ); ?></strong>
										<span class="nik-about-feature-desc"><?php esc_html_e( 'Instant pairing with voice.nikneural.ca for Groq Whisper-v3 STT with ephemeral cloud inference (local audio retained per site policy).', 'nik-voice-ticketing-ai' ); ?></span>
									</div>
									<div class="nik-about-feature-box">
										<strong class="nik-about-feature-title">🔒 <?php esc_html_e( 'HMAC-SHA256 Audio Streaming', 'nik-voice-ticketing-ai' ); ?></strong>
										<span class="nik-about-feature-desc"><?php esc_html_e( 'Direct physical upload access is blocked with .htaccess. Playback routes through cryptographic time-bounded HMAC tokens.', 'nik-voice-ticketing-ai' ); ?></span>
									</div>
									<div class="nik-about-feature-box">
										<strong class="nik-about-feature-title">🧹 <?php esc_html_e( 'Daily Data Retention Cron', 'nik-voice-ticketing-ai' ); ?></strong>
										<span class="nik-about-feature-desc"><?php esc_html_e( 'Configurable 0-365 day retention window. Automatically unlinks expired physical recordings from disk to assist data minimization practices.', 'nik-voice-ticketing-ai' ); ?></span>
									</div>
									<div class="nik-about-feature-box">
										<strong class="nik-about-feature-title">⚖️ <?php esc_html_e( 'WP Core Privacy Exporter/Eraser', 'nik-voice-ticketing-ai' ); ?></strong>
										<span class="nik-about-feature-desc"><?php esc_html_e( 'Native integration with Tools > Export Personal Data and Tools > Erase Personal Data to support privacy and data subject rights.', 'nik-voice-ticketing-ai' ); ?></span>
									</div>
									<div class="nik-about-feature-box">
										<strong class="nik-about-feature-title">🚪 <?php esc_html_e( 'Customer Portal Auto-Creation', 'nik-voice-ticketing-ai' ); ?></strong>
										<span class="nik-about-feature-desc"><?php esc_html_e( 'Self-healing auto-provisioning creates and publishes Support Tickets page with [nikvotia_tickets] and WooCommerce sync.', 'nik-voice-ticketing-ai' ); ?></span>
									</div>
									<div class="nik-about-feature-box">
										<strong class="nik-about-feature-title">🌓 <?php esc_html_e( 'Unified High-Contrast Theming', 'nik-voice-ticketing-ai' ); ?></strong>
										<span class="nik-about-feature-desc"><?php esc_html_e( 'Zero text bleeding on dark themes with smart automatic luminance detection and manual toggle.', 'nik-voice-ticketing-ai' ); ?></span>
									</div>
								</div>
							</div>
						</div>

						<!-- The Nik AI Platform Showcase -->
						<div class="nik-vd-card nik-about-card">
							<div class="nik-vd-card-header">
								<h3><span class="dashicons dashicons-grid-view"></span> <?php esc_html_e( 'The Nik Neural AI Enterprise Suite', 'nik-voice-ticketing-ai' ); ?></h3>
							</div>
							<div class="nik-vd-card-body">
								<div class="nik-about-suite-list">
									<div class="nik-about-suite-item" style="border-left-color: #FF6D00;">
										<strong class="nik-about-suite-title"><?php esc_html_e( 'Nik VoiceDesk AI', 'nik-voice-ticketing-ai' ); ?></strong>
										<span class="nik-vd-ai-badge"><?php esc_html_e( 'Active Plugin (v1.4.1)', 'nik-voice-ticketing-ai' ); ?></span>
										<p class="nik-about-suite-desc">
											<?php esc_html_e( 'AI-powered zero-friction voice ticket submission, Whisper/Modulate transcription, automated triage, and customer portal integration.', 'nik-voice-ticketing-ai' ); ?>
										</p>
									</div>

									<div class="nik-about-suite-item" style="border-left-color: #1282A2;">
										<strong class="nik-about-suite-title"><?php esc_html_e( 'Nik P&C Retention', 'nik-voice-ticketing-ai' ); ?></strong>
										<p class="nik-about-suite-desc">
											<?php esc_html_e( 'Automated renewal outreach and policy lifecycle management for property & casualty brokerages. Predict churn and re-engage clients proactively.', 'nik-voice-ticketing-ai' ); ?>
										</p>
									</div>

									<div class="nik-about-suite-item" style="border-left-color: #0B618D;">
										<strong class="nik-about-suite-title"><?php esc_html_e( 'Nik Enterprise Hub', 'nik-voice-ticketing-ai' ); ?></strong>
										<p class="nik-about-suite-desc">
											<?php esc_html_e( 'Centralised AI operations dashboard for multi-branch brokerages and institutions with multi-tier permissions and unified telemetry.', 'nik-voice-ticketing-ai' ); ?>
										</p>
									</div>

									<div class="nik-about-suite-item" style="border-left-color: #16a34a;">
										<strong class="nik-about-suite-title"><?php esc_html_e( 'Nik Privacy Shield', 'nik-voice-ticketing-ai' ); ?></strong>
										<p class="nik-about-suite-desc">
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
						<div class="nik-vd-card nik-about-card">
							<div class="nik-vd-card-header">
								<h3><span class="dashicons dashicons-building"></span> <?php esc_html_e( 'Corporate & Plugin Records', 'nik-voice-ticketing-ai' ); ?></h3>
							</div>
							<div class="nik-vd-card-body">
								<div class="nik-vd-meta-row">
									<strong><?php esc_html_e( 'Company:', 'nik-voice-ticketing-ai' ); ?></strong>
									<span>Nik Neural AI Inc.</span>
								</div>
								<div class="nik-vd-meta-row">
									<strong><?php esc_html_e( 'Jurisdiction:', 'nik-voice-ticketing-ai' ); ?></strong>
									<span>British Columbia, Canada</span>
								</div>
								<div class="nik-vd-meta-row">
									<strong><?php esc_html_e( 'Headquarters:', 'nik-voice-ticketing-ai' ); ?></strong>
									<span>112-970 Burrard St, Suite 1760<br>Vancouver, BC V6Z 2R4</span>
								</div>
								<div class="nik-vd-meta-row">
									<strong><?php esc_html_e( 'Telephone:', 'nik-voice-ticketing-ai' ); ?></strong>
									<a href="tel:6042837353">(604) 283-7353</a>
								</div>
								<div class="nik-vd-meta-row">
									<strong><?php esc_html_e( 'Company Web:', 'nik-voice-ticketing-ai' ); ?></strong>
									<a href="https://nikneural.ca/" target="_blank" rel="noopener noreferrer">https://nikneural.ca/</a>
								</div>
								<div class="nik-vd-meta-row">
									<strong><?php esc_html_e( 'Product Page:', 'nik-voice-ticketing-ai' ); ?></strong>
									<a href="https://nikneural.ca/voicedesk-ai/" target="_blank" rel="noopener noreferrer">VoiceDesk AI Overview</a>
								</div>
								<div class="nik-vd-meta-row">
									<strong><?php esc_html_e( 'Contributors:', 'nik-voice-ticketing-ai' ); ?></strong>
									<span>nikneuralaiinc, aboozaresmaili</span>
								</div>
								<div class="nik-vd-meta-row">
									<strong><?php esc_html_e( 'GitHub:', 'nik-voice-ticketing-ai' ); ?></strong>
									<a href="https://github.com/Nik-Neural-AI-Inc/nik-voice-ticketing-ai" target="_blank" rel="noopener noreferrer">nik-voice-ticketing-ai</a>
								</div>
								<div class="nik-vd-meta-row">
									<strong><?php esc_html_e( 'WordPress.org:', 'nik-voice-ticketing-ai' ); ?></strong>
									<a href="https://wordpress.org/plugins/nik-voice-ticketing-ai/" target="_blank" rel="noopener noreferrer">nik-voice-ticketing-ai</a>
								</div>

								<div class="nik-about-card-footer">
									<a href="https://nikneural.ca/#contact" target="_blank" rel="noopener noreferrer" class="button button-primary nik-about-contact-btn">
										<span class="dashicons dashicons-email-alt"></span> <?php esc_html_e( 'Get in Touch with Team', 'nik-voice-ticketing-ai' ); ?>
									</a>
								</div>
							</div>
						</div>

						<!-- Enterprise Cloud Card -->
						<div class="nik-vd-card nik-about-card nik-about-pro-cta-card">
							<div class="nik-vd-card-header">
								<h3><span class="dashicons dashicons-awards" style="color: #0284c7;"></span> <?php esc_html_e( 'Enterprise Services', 'nik-voice-ticketing-ai' ); ?></h3>
							</div>
							<div class="nik-vd-card-body">
								<p class="nik-about-cta-text">
									<?php esc_html_e( 'Need dedicated cloud infrastructure, custom CRM integrations, or enhanced enterprise SLA coverage? Explore our enterprise options.', 'nik-voice-ticketing-ai' ); ?>
								</p>
								<div style="margin-top: 14px;">
									<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=voicedesk_ticket&page=nikvotia-settings&tab=pro' ) ); ?>" class="button button-secondary nik-about-pro-link">
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
					$health['jurisdiction'] ?? 'Canada'
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

	/**
	 * Prune audio recording files older than specified retention window.
	 *
	 * Supports retention management aligned with data minimization principles.
	 *
	 * @param int|null $days Retention period in days. Defaults to option value.
	 * @return int Number of audio files pruned.
	 */
	public static function prune_expired_audio_files( $days = null ) {
		if ( null === $days ) {
			$days = (int) get_option( 'nikvotia_audio_retention_days', 30 );
		}

		if ( $days < 0 ) {
			// -1 indicates indefinite retention / no automatic pruning
			return 0;
		}

		$pruned_count = 0;
		$cutoff_time  = time() - ( $days * 86400 );
		$cutoff_date  = gmdate( 'Y-m-d H:i:s', $cutoff_time );

		// Query tickets with unpruned audio older than cutoff date
		$posts = get_posts( array(
			'post_type'      => 'voicedesk_ticket',
			'post_status'    => 'any',
			'posts_per_page' => 100,
			'date_query'     => array(
				array(
					'before'    => $cutoff_date,
					'inclusive' => true,
				),
			),
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Batch pruning requires filtering tickets by unpruned audio status.
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => '_nikvotia_audio_deleted',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => '_nikvotia_audio_path',
					'value'   => '',
					'compare' => '!=',
				),
			),
		) );

		$upload_dir      = wp_upload_dir();
		$base_upload_dir = wp_normalize_path( $upload_dir['basedir'] );

		foreach ( $posts as $post ) {
			$audio_path      = get_post_meta( $post->ID, '_nikvotia_audio_path', true ) ?: get_post_meta( $post->ID, '_nik_audio_path', true );
			$normalized_file = wp_normalize_path( (string) $audio_path );

			// Containment check: only unlink files residing strictly within the WordPress uploads directory
			if ( ! empty( $audio_path ) && stripos( $normalized_file, $base_upload_dir ) === 0 && file_exists( $audio_path ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				@unlink( $audio_path );
			}

			update_post_meta( $post->ID, '_nikvotia_audio_deleted', '1' );
			update_post_meta( $post->ID, '_nikvotia_audio_url', '' );
			update_post_meta( $post->ID, '_nikvotia_audio_stream_url', '' );

			$ticket_num = get_post_meta( $post->ID, '_nikvotia_ticket_number', true );
			Nikvotia_Logger::log(
				'INFO',
				'RETENTION',
				sprintf( 'Audio pruned for Ticket #%s (post ID %d) per %d-day data retention policy', $ticket_num, $post->ID, $days ),
				array(
					'post_id' => $post->ID,
					'ticket'  => $ticket_num,
					'days'    => $days,
				)
			);

			$pruned_count++;
		}

		return $pruned_count;
	}

	/**
	 * Daily scheduled WP Cron worker for audio data retention.
	 */
	public static function run_daily_retention_cleanup() {
		$count = self::prune_expired_audio_files();
		if ( $count > 0 ) {
			Nikvotia_Logger::log( 'SUCCESS', 'RETENTION', sprintf( 'Daily data retention cycle completed: pruned %d expired audio recordings.', $count ) );
		}
	}

	/**
	 * Handle admin-post action to purge expired audio recordings immediately.
	 */
	public function handle_purge_expired_audio() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized user.', 'nik-voice-ticketing-ai' ) );
		}
		check_admin_referer( 'nikvotia_purge_audio_action', 'nikvotia_purge_audio_nonce' );

		$pruned = self::prune_expired_audio_files();

		wp_safe_redirect( add_query_arg( array( 'tab' => 'general', 'nik_audio_purged' => $pruned ), admin_url( 'edit.php?post_type=voicedesk_ticket&page=nikvotia-settings' ) ) );
		exit;
	}
}

