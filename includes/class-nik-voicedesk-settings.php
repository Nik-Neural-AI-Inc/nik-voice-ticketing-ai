<?php
/**
 * Settings class for Nik VoiceDesk AI.
 * Unified design matching the Ticket Console, Leaderboard Banner, and Automated Licensing.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nik_VoiceDesk_Settings {

	public function init() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	public function enqueue_admin_scripts( $hook ) {
		if ( 'voicedesk_ticket_page_nik-voicedesk-settings' !== $hook && 'edit.php' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'nik-voicedesk-admin', NIK_VOICEDESK_URL . 'assets/css/admin.css', array(), NIK_VOICEDESK_VERSION );
		wp_enqueue_script( 'nik-voicedesk-admin', NIK_VOICEDESK_URL . 'assets/js/admin.js', array(), NIK_VOICEDESK_VERSION, true );
	}

	public function add_settings_page() {
		add_submenu_page(
			'edit.php?post_type=voicedesk_ticket',
			__( 'VoiceDesk Settings', 'nik-voicedesk' ),
			__( 'Settings', 'nik-voicedesk' ),
			'manage_options',
			'nik-voicedesk-settings',
			array( $this, 'render_settings_page' )
		);
	}

	public function register_settings() {
		// General Settings
		register_setting( 'nik_voicedesk_general', 'nik_voicedesk_portal_page_id' );
		register_setting( 'nik_voicedesk_general', 'nik_voicedesk_visibility_post_types' );
		register_setting( 'nik_voicedesk_general', 'nik_voicedesk_visibility_user_status' );
		register_setting( 'nik_voicedesk_general', 'nik_voicedesk_visibility_specific_users' );
		register_setting( 'nik_voicedesk_general', 'nik_voicedesk_departments' );

		// API Integrations
		register_setting( 'nik_voicedesk_api', 'nik_voicedesk_stt_model' );
		register_setting( 'nik_voicedesk_api', 'nik_voicedesk_llm_model' );
		register_setting( 'nik_voicedesk_api', 'nik_voicedesk_openai_key' );
		register_setting( 'nik_voicedesk_api', 'nik_voicedesk_modulate_key' );

		// Appearance Settings
		register_setting( 'nik_voicedesk_appearance', 'nik_voicedesk_btn_color' );
		register_setting( 'nik_voicedesk_appearance', 'nik_voicedesk_icon_color' );
		register_setting( 'nik_voicedesk_appearance', 'nik_voicedesk_tooltip_text' );
		register_setting( 'nik_voicedesk_appearance', 'nik_voicedesk_custom_icon' );

		// License
		register_setting( 'nik_voicedesk_license', 'nik_voicedesk_license_key' );
	}

	/**
	 * Check if Enterprise License is active.
	 */
	public static function is_enterprise() {
		return Nik_VoiceDesk_License::is_valid();
	}

	/**
	 * Render responsive Leaderboard Ad Banner (Free Tier).
	 */
	public static function render_leaderboard_banner() {
		if ( self::is_enterprise() ) {
			return;
		}
		?>
		<div class="nik-vd-leaderboard-banner">
			<div class="nik-vd-banner-left">
				<div class="nik-vd-banner-badge">NIK NEURAL AI PRO</div>
				<div class="nik-vd-banner-title">
					<?php esc_html_e( 'Supercharge your Support with Automated Workflows & Unlimited Transcripts', 'nik-voicedesk' ); ?>
				</div>
				<div class="nik-vd-banner-desc">
					<?php esc_html_e( 'Upgrade to remove all branding, unlock Modulate.ai toxicity & emotion detection, and customize customer routing.', 'nik-voicedesk' ); ?>
				</div>
			</div>
			<div class="nik-vd-banner-right">
				<a href="https://nikneural.ca/voicedesk.php" target="_blank" rel="noopener noreferrer" class="nik-vd-banner-btn">
					<?php esc_html_e( 'Upgrade to Enterprise →', 'nik-voicedesk' ); ?>
				</a>
			</div>
		</div>
		<?php
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'general';
		?>
		<div class="wrap nik-vd-settings-wrap">
			<?php self::render_leaderboard_banner(); ?>

			<div class="nik-vd-admin-console nik-vd-settings-console">
				<!-- Header Bar Matching Ticket Console -->
				<div class="nik-vd-header-bar">
					<div class="nik-vd-header-left">
						<div class="nik-vd-ticket-id">
							<span class="dashicons dashicons-admin-generic" style="color: #38bdf8;"></span>
							<span class="nik-vd-id-label"><?php esc_html_e( 'VoiceDesk', 'nik-voicedesk' ); ?></span>
							<span class="nik-vd-id-value"><?php esc_html_e( 'Settings & Configuration', 'nik-voicedesk' ); ?></span>
						</div>
						<div class="nik-vd-customer-pill">
							<span class="dashicons dashicons-tag"></span>
							<strong>v<?php echo esc_html( NIK_VOICEDESK_VERSION ); ?></strong>
						</div>
						<?php if ( self::is_enterprise() ) : ?>
							<span class="nik-status-pill nik-status-resolved">ENTERPRISE ACTIVE</span>
						<?php else : ?>
							<span class="nik-status-pill nik-status-open">FREE TIER</span>
						<?php endif; ?>
					</div>

					<div class="nik-vd-header-right">
						<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=voicedesk_ticket' ) ); ?>" class="button button-secondary">
							← <?php esc_html_e( 'View All Tickets', 'nik-voicedesk' ); ?>
						</a>
					</div>
				</div>

				<!-- Navigation Tabs -->
				<div class="nik-vd-settings-tabs-bar">
					<a href="?post_type=voicedesk_ticket&page=nik-voicedesk-settings&tab=general" class="nik-vd-tab-link <?php echo $active_tab === 'general' ? 'is-active' : ''; ?>">
						<span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e( 'General & Visibility', 'nik-voicedesk' ); ?>
					</a>
					<a href="?post_type=voicedesk_ticket&page=nik-voicedesk-settings&tab=appearance" class="nik-vd-tab-link <?php echo $active_tab === 'appearance' ? 'is-active' : ''; ?>">
						<span class="dashicons dashicons-art"></span> <?php esc_html_e( 'Button & Appearance', 'nik-voicedesk' ); ?>
					</a>
					<a href="?post_type=voicedesk_ticket&page=nik-voicedesk-settings&tab=api" class="nik-vd-tab-link <?php echo $active_tab === 'api' ? 'is-active' : ''; ?>">
						<span class="dashicons dashicons-rest-api"></span> <?php esc_html_e( 'AI & API Providers', 'nik-voicedesk' ); ?>
					</a>
					<a href="?post_type=voicedesk_ticket&page=nik-voicedesk-settings&tab=license" class="nik-vd-tab-link <?php echo $active_tab === 'license' ? 'is-active' : ''; ?>">
						<span class="dashicons dashicons-awards"></span> <?php esc_html_e( 'Enterprise License', 'nik-voicedesk' ); ?>
					</a>
				</div>

				<!-- Tab Content Card -->
				<div class="nik-vd-settings-body">
					<form method="post" action="options.php">
						<?php
						if ( 'general' === $active_tab ) {
							settings_fields( 'nik_voicedesk_general' );
							$this->render_general_tab();
						} elseif ( 'appearance' === $active_tab ) {
							settings_fields( 'nik_voicedesk_appearance' );
							$this->render_appearance_tab();
						} elseif ( 'api' === $active_tab ) {
							settings_fields( 'nik_voicedesk_api' );
							$this->render_api_tab();
						} elseif ( 'license' === $active_tab ) {
							settings_fields( 'nik_voicedesk_license' );
							$this->render_license_tab();
						}
						
						echo '<div class="nik-vd-form-footer">';
						submit_button( __( 'Save All Changes', 'nik-voicedesk' ), 'primary button-large nik-vd-save-btn', 'submit', false );
						echo '</div>';
						?>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	private function render_general_tab() {
		$post_types_selected = get_option( 'nik_voicedesk_visibility_post_types', array() );
		$user_status = get_option( 'nik_voicedesk_visibility_user_status', 'all' );
		$specific_users = get_option( 'nik_voicedesk_visibility_specific_users', '' );
		$departments = get_option( 'nik_voicedesk_departments', 'Sales, Technical Support, Billing, General Support' );
		$public_post_types = get_post_types( array( 'public' => true ), 'objects' );
		$portal_page_id = get_option( 'nik_voicedesk_portal_page_id', 0 );
		?>
		<div class="nik-vd-settings-section">
			<h3><span class="dashicons dashicons-admin-home"></span> <?php esc_html_e( 'Customer Portal & Shortcode', 'nik-voicedesk' ); ?></h3>
			<table class="form-table nik-vd-table">
				<tr>
					<th scope="row"><label for="nik_voicedesk_portal_page_id"><?php esc_html_e( 'Customer Portal Page', 'nik-voicedesk' ); ?></label></th>
					<td>
						<?php
						wp_dropdown_pages( array(
							'name'              => 'nik_voicedesk_portal_page_id',
							'id'                => 'nik_voicedesk_portal_page_id',
							'show_option_none'  => __( '— Select a Page —', 'nik-voicedesk' ),
							'option_none_value' => '0',
							'selected'          => $portal_page_id,
							'class'             => 'nik-vd-input-select',
						) );
						?>
						<p class="description">
							<?php esc_html_e( 'The page where you placed the shortcode [nik_voicedesk_tickets]. Customers will be directed here from confirmation emails and success modals.', 'nik-voicedesk' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label><?php esc_html_e( 'Portal Shortcode', 'nik-voicedesk' ); ?></label></th>
					<td>
						<code style="background: #e2e8f0; padding: 4px 8px; border-radius: 4px; font-weight: 700; color: #0f172a;">[nik_voicedesk_tickets]</code>
						<p class="description"><?php esc_html_e( 'Insert this shortcode into any WordPress page to display the user support ticket console.', 'nik-voicedesk' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<div class="nik-vd-settings-section">
			<h3><span class="dashicons dashicons-visibility"></span> <?php esc_html_e( 'Button Visibility Rules', 'nik-voicedesk' ); ?></h3>
			<table class="form-table nik-vd-table">
				<tr>
					<th scope="row"><label><?php esc_html_e( 'Display on Content Types', 'nik-voicedesk' ); ?></label></th>
					<td>
						<select name="nik_voicedesk_visibility_post_types[]" multiple="multiple" size="5" class="nik-vd-multi-select">
							<?php foreach ( $public_post_types as $pt ) : ?>
								<option value="<?php echo esc_attr( $pt->name ); ?>" <?php echo in_array( $pt->name, (array) $post_types_selected, true ) ? 'selected' : ''; ?>><?php echo esc_html( $pt->label ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Hold CTRL (CMD on Mac) to select multiple post types where the microphone button should appear.', 'nik-voicedesk' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="nik_voicedesk_visibility_user_status"><?php esc_html_e( 'Target Audience', 'nik-voicedesk' ); ?></label></th>
					<td>
						<select id="nik_voicedesk_visibility_user_status" name="nik_voicedesk_visibility_user_status" class="nik-vd-input-select">
							<option value="all" <?php selected( $user_status, 'all' ); ?>><?php esc_html_e( 'All Users (Guests & Members)', 'nik-voicedesk' ); ?></option>
							<option value="logged_in" <?php selected( $user_status, 'logged_in' ); ?>><?php esc_html_e( 'Logged In Members Only', 'nik-voicedesk' ); ?></option>
							<option value="not_logged_in" <?php selected( $user_status, 'not_logged_in' ); ?>><?php esc_html_e( 'Guests (Not Logged In) Only', 'nik-voicedesk' ); ?></option>
							<option value="specific" <?php selected( $user_status, 'specific' ); ?>><?php esc_html_e( 'Specific Users by ID', 'nik-voicedesk' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="nik_voicedesk_visibility_specific_users"><?php esc_html_e( 'Specific User IDs', 'nik-voicedesk' ); ?></label></th>
					<td>
						<input type="text" id="nik_voicedesk_visibility_specific_users" name="nik_voicedesk_visibility_specific_users" value="<?php echo esc_attr( $specific_users ); ?>" class="regular-text nik-vd-input-text" />
						<p class="description"><?php esc_html_e( 'Comma-separated user IDs (e.g. 1, 5, 12). Only applicable when "Specific Users by ID" is chosen.', 'nik-voicedesk' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="nik_voicedesk_departments"><?php esc_html_e( 'Department Categories', 'nik-voicedesk' ); ?></label></th>
					<td>
						<input type="text" id="nik_voicedesk_departments" name="nik_voicedesk_departments" value="<?php echo esc_attr( $departments ); ?>" class="regular-text nik-vd-input-text" />
						<p class="description"><?php esc_html_e( 'Comma-separated list of departments for AI routing and user classification.', 'nik-voicedesk' ); ?></p>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	private function render_appearance_tab() {
		$btn_color = get_option( 'nik_voicedesk_btn_color', '#ffd700' );
		$icon_color = get_option( 'nik_voicedesk_icon_color', '#333333' );
		$tooltip_text = get_option( 'nik_voicedesk_tooltip_text', 'Speak your issue and click on problematic areas.' );
		$custom_icon = get_option( 'nik_voicedesk_custom_icon', '' );
		?>
		<div class="nik-vd-settings-section">
			<h3><span class="dashicons dashicons-art"></span> <?php esc_html_e( 'Button & Visual Customization', 'nik-voicedesk' ); ?></h3>
			<table class="form-table nik-vd-table">
				<tr>
					<th scope="row"><label for="nik_voicedesk_btn_color"><?php esc_html_e( 'Button Background Color', 'nik-voicedesk' ); ?></label></th>
					<td>
						<input type="color" id="nik_voicedesk_btn_color" name="nik_voicedesk_btn_color" value="<?php echo esc_attr( $btn_color ); ?>" />
						<span style="margin-left: 8px; font-family: monospace;"><?php echo esc_html( $btn_color ); ?></span>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="nik_voicedesk_icon_color"><?php esc_html_e( 'Button Icon Color', 'nik-voicedesk' ); ?></label></th>
					<td>
						<input type="color" id="nik_voicedesk_icon_color" name="nik_voicedesk_icon_color" value="<?php echo esc_attr( $icon_color ); ?>" />
						<span style="margin-left: 8px; font-family: monospace;"><?php echo esc_html( $icon_color ); ?></span>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="nik_voicedesk_tooltip_text"><?php esc_html_e( 'Dock Instruction Text', 'nik-voicedesk' ); ?></label></th>
					<td>
						<input type="text" id="nik_voicedesk_tooltip_text" name="nik_voicedesk_tooltip_text" value="<?php echo esc_attr( $tooltip_text ); ?>" class="regular-text nik-vd-input-text" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="nik_voicedesk_custom_icon"><?php esc_html_e( 'Custom SVG Icon', 'nik-voicedesk' ); ?></label></th>
					<td>
						<textarea id="nik_voicedesk_custom_icon" name="nik_voicedesk_custom_icon" rows="4" class="large-text code" style="border-radius: 6px;"><?php echo esc_textarea( $custom_icon ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Paste raw SVG code here to override the default microphone icon.', 'nik-voicedesk' ); ?></p>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	private function render_api_tab() {
		$stt_model = get_option( 'nik_voicedesk_stt_model', 'openai_whisper' );
		$llm_model = get_option( 'nik_voicedesk_llm_model', 'openai_gpt' );
		$openai_key = get_option( 'nik_voicedesk_openai_key', '' );
		$modulate_key = get_option( 'nik_voicedesk_modulate_key', '' );
		?>
		<div class="nik-vd-settings-section">
			<h3><span class="dashicons dashicons-rest-api"></span> <?php esc_html_e( 'AI Provider & Engine Configuration', 'nik-voicedesk' ); ?></h3>
			<table class="form-table nik-vd-table">
				<tr>
					<th scope="row"><label for="nik_voicedesk_stt_model"><?php esc_html_e( 'Speech-to-Text (STT) Engine', 'nik-voicedesk' ); ?></label></th>
					<td>
						<select id="nik_voicedesk_stt_model" name="nik_voicedesk_stt_model" class="nik-vd-input-select">
							<option value="openai_whisper" <?php selected( $stt_model, 'openai_whisper' ); ?>><?php esc_html_e( 'OpenAI Whisper (whisper-1)', 'nik-voicedesk' ); ?></option>
							<option value="modulate_ai" <?php selected( $stt_model, 'modulate_ai' ); ?>><?php esc_html_e( 'Modulate.ai (Velma-2 Fast STT)', 'nik-voicedesk' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="nik_voicedesk_llm_model"><?php esc_html_e( 'LLM Analysis & Triage Engine', 'nik-voicedesk' ); ?></label></th>
					<td>
						<select id="nik_voicedesk_llm_model" name="nik_voicedesk_llm_model" class="nik-vd-input-select">
							<option value="openai_gpt" <?php selected( $llm_model, 'openai_gpt' ); ?>><?php esc_html_e( 'OpenAI GPT-4o-mini (Summarization & Categorization)', 'nik-voicedesk' ); ?></option>
							<option value="modulate_ai" <?php selected( $llm_model, 'modulate_ai' ); ?>><?php esc_html_e( 'Modulate.ai (Velma-2 Batch Analytics)', 'nik-voicedesk' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="nik_voicedesk_openai_key"><?php esc_html_e( 'OpenAI API Key', 'nik-voicedesk' ); ?></label></th>
					<td>
						<input type="password" id="nik_voicedesk_openai_key" name="nik_voicedesk_openai_key" value="<?php echo esc_attr( $openai_key ); ?>" class="regular-text nik-vd-input-text" autocomplete="off" />
						<p class="description"><?php esc_html_e( 'Used for Whisper transcription and GPT-4o-mini summarization.', 'nik-voicedesk' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="nik_voicedesk_modulate_key"><?php esc_html_e( 'Modulate.ai API Key', 'nik-voicedesk' ); ?></label></th>
					<td>
						<input type="password" id="nik_voicedesk_modulate_key" name="nik_voicedesk_modulate_key" value="<?php echo esc_attr( $modulate_key ); ?>" class="regular-text nik-vd-input-text" autocomplete="off" />
						<p class="description"><?php esc_html_e( 'Used for Velma-2 fast multilingual speech-to-text and voice intelligence analytics.', 'nik-voicedesk' ); ?></p>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	private function render_license_tab() {
		$license = get_option( 'nik_voicedesk_license_key', '' );
		$is_valid = self::is_enterprise();
		$last_checked = (int) get_option( 'nik_voicedesk_license_last_success', 0 );
		?>
		<div class="nik-vd-settings-section">
			<h3><span class="dashicons dashicons-awards"></span> <?php esc_html_e( 'Enterprise License & Verification', 'nik-voicedesk' ); ?></h3>
			
			<div style="background: <?php echo $is_valid ? '#f0fdf4' : '#fef2f2'; ?>; border: 1px solid <?php echo $is_valid ? '#bbf7d0' : '#fecaca'; ?>; padding: 16px 20px; border-radius: 8px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
				<div>
					<div style="font-size: 15px; font-weight: 700; color: <?php echo $is_valid ? '#15803d' : '#b91c1c'; ?>; display: flex; align-items: center; gap: 6px;">
						<?php echo $is_valid ? '✓ ' . esc_html__( 'ENTERPRISE LICENSE ACTIVE', 'nik-voicedesk' ) : '⚠️ ' . esc_html__( 'FREE TIER (NO ACTIVE LICENSE)', 'nik-voicedesk' ); ?>
					</div>
					<div style="font-size: 13px; color: #475569; margin-top: 4px;">
						<?php if ( $is_valid ) : ?>
							<?php esc_html_e( 'Verified remotely. All promotional banners and branding are globally suppressed across all interfaces.', 'nik-voicedesk' ); ?>
							<?php if ( $last_checked > 0 ) : ?>
								<br><small style="color: #64748b;"><?php printf( esc_html__( 'Last validated: %s', 'nik-voicedesk' ), date_i18n( 'M j, Y g:i A', $last_checked ) ); ?></small>
							<?php endif; ?>
						<?php else : ?>
							<?php esc_html_e( 'Running on Free Tier. Attribution bar and promotional banners are displayed. Enter your commercial license key to unlock full white-labeling.', 'nik-voicedesk' ); ?>
						<?php endif; ?>
					</div>
				</div>
				<?php if ( ! $is_valid ) : ?>
					<a href="https://nikneural.ca/voicedesk.php" target="_blank" rel="noopener noreferrer" class="button button-primary" style="background: #2563eb; border-color: #1d4ed8; font-weight: 600;">
						<?php esc_html_e( 'Get an Enterprise License →', 'nik-voicedesk' ); ?>
					</a>
				<?php endif; ?>
			</div>

			<table class="form-table nik-vd-table">
				<tr>
					<th scope="row"><label for="nik_voicedesk_license_key"><?php esc_html_e( 'License Key', 'nik-voicedesk' ); ?></label></th>
					<td>
						<input type="text" id="nik_voicedesk_license_key" name="nik_voicedesk_license_key" value="<?php echo esc_attr( $license ); ?>" class="regular-text nik-vd-input-text" placeholder="NIK-VD-XXXX-XXXX-XXXX" />
						<p class="description"><?php esc_html_e( 'Your license key is verified automatically with https://nikneural.ca/api/license-verify.php and cached for 72 hours.', 'nik-voicedesk' ); ?></p>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}
}
