<?php
/**
 * Settings class for Nik VoiceDesk AI.
 * Unified design matching the Ticket Console, Leaderboard Banner, Pricing Grid, Dark/Light mode, and Automated Licensing.
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

	/**
	 * Reliably detect if current admin view is part of VoiceDesk.
	 */
	public static function is_plugin_admin_page() {
		global $post_type;
		$current_pt = $post_type ?: ( $_GET['post_type'] ?? '' );
		$page = $_GET['page'] ?? '';
		if ( 'voicedesk_ticket' === $current_pt || 'nik-voicedesk-settings' === $page ) {
			return true;
		}
		if ( isset( $_GET['post'] ) && get_post_type( (int) $_GET['post'] ) === 'voicedesk_ticket' ) {
			return true;
		}
		return false;
	}

	public function enqueue_admin_scripts( $hook ) {
		if ( ! self::is_plugin_admin_page() ) {
			return;
		}
		wp_enqueue_style( 'nik-voicedesk-admin', NIK_VOICEDESK_URL . 'assets/css/admin.css', array(), time() );
		wp_enqueue_script( 'nik-voicedesk-admin', NIK_VOICEDESK_URL . 'assets/js/admin.js', array(), time(), true );
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
	 * Render responsive Beauty Leaderboard Ad Banner (Free Tier).
	 */
	public static function render_leaderboard_banner() {
		if ( self::is_enterprise() ) {
			return;
		}
		?>
		<div class="nik-vd-beauty-banner">
			<div class="nik-vd-banner-inner">
				<div class="nik-vd-banner-left">
					<div class="nik-vd-banner-badge">
						<span class="nik-vd-badge-dot"></span> 👑 NIK NEURAL AI PRO
					</div>
					<h3 class="nik-vd-banner-title">
						<?php esc_html_e( 'Supercharge your Support with White-Label VoiceDesk', 'nik-voicedesk' ); ?>
					</h3>
					<p class="nik-vd-banner-desc">
						<?php esc_html_e( 'Upgrade to Enterprise Pro to remove all "Powered by Nik Neural AI" branding, remove admin ad banners, and enjoy a 100% white-label experience for your brand and clients.', 'nik-voicedesk' ); ?>
					</p>
					<div class="nik-vd-banner-tags">
						<span class="nik-vd-tag-item">✓ <?php esc_html_e( '100% Ad-Free Experience', 'nik-voicedesk' ); ?></span>
						<span class="nik-vd-tag-item">✓ <?php esc_html_e( 'Remove Frontend Attribution Bar', 'nik-voicedesk' ); ?></span>
						<span class="nik-vd-tag-item">✓ <?php esc_html_e( 'Remove Admin Leaderboard Banners', 'nik-voicedesk' ); ?></span>
						<span class="nik-vd-tag-item">✓ <?php esc_html_e( 'Clean White-Label for Clients', 'nik-voicedesk' ); ?></span>
						<span class="nik-vd-tag-item nik-vd-tag-price"><?php esc_html_e( '$29.99 USD / year', 'nik-voicedesk' ); ?></span>
					</div>
				</div>
				<div class="nik-vd-banner-right">
					<a href="https://nikneural.ca/voicedesk.php" target="_blank" rel="noopener noreferrer" class="nik-vd-banner-cta-btn">
						<span><?php esc_html_e( 'Upgrade to Enterprise ($29.99/yr) →', 'nik-voicedesk' ); ?></span>
					</a>
				</div>
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
				<!-- Header Bar with Unified Styling & Dark/Light Toggle -->
				<div class="nik-vd-header-bar">
					<div class="nik-vd-header-left">
						<div class="nik-vd-ticket-id">
							<span class="dashicons dashicons-admin-generic" style="color: #38bdf8;"></span>
							<span class="nik-vd-id-label"><?php esc_html_e( 'VoiceDesk', 'nik-voicedesk' ); ?></span>
							<span class="nik-vd-id-value"><?php esc_html_e( 'Settings', 'nik-voicedesk' ); ?></span>
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
						<!-- Theme Switcher Button -->
						<button type="button" class="nik-vd-theme-btn" id="nik-vd-theme-toggle" aria-label="<?php esc_attr_e( 'Toggle Dark or Light Mode', 'nik-voicedesk' ); ?>">
							<span class="nik-vd-theme-icon">🌙</span>
							<span class="nik-vd-theme-text"><?php esc_html_e( 'Dark Mode', 'nik-voicedesk' ); ?></span>
						</button>

						<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=voicedesk_ticket' ) ); ?>" class="button button-secondary nik-vd-back-btn">
							← <?php esc_html_e( 'Tickets Queue', 'nik-voicedesk' ); ?>
						</a>
					</div>
				</div>

				<!-- Unified Navigation Tabs -->
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
						<span class="dashicons dashicons-awards"></span> <?php esc_html_e( 'Plans & License', 'nik-voicedesk' ); ?>
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
							<?php esc_html_e( 'Select the page where you added the shortcode [nik_voicedesk_tickets]. Customers are directed here from confirmation emails and success notifications.', 'nik-voicedesk' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label><?php esc_html_e( 'Portal Shortcode', 'nik-voicedesk' ); ?></label></th>
					<td>
						<code class="nik-vd-code-badge">[nik_voicedesk_tickets]</code>
						<p class="description"><?php esc_html_e( 'Place this shortcode inside any WordPress page to display the user support ticket dashboard.', 'nik-voicedesk' ); ?></p>
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
							<option value="all" <?php selected( $user_status, 'all' ); ?>><?php esc_html_e( 'All Users (Guests & Logged In)', 'nik-voicedesk' ); ?></option>
							<option value="logged_in" <?php selected( $user_status, 'logged_in' ); ?>><?php esc_html_e( 'Logged In Members Only', 'nik-voicedesk' ); ?></option>
							<option value="not_logged_in" <?php selected( $user_status, 'not_logged_in' ); ?>><?php esc_html_e( 'Guests (Not Logged In) Only', 'nik-voicedesk' ); ?></option>
							<option value="specific" <?php selected( $user_status, 'specific' ); ?>><?php esc_html_e( 'Specific Users by ID', 'nik-voicedesk' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="nik_voicedesk_visibility_specific_users"><?php esc_html_e( 'Specific User IDs', 'nik-voicedesk' ); ?></label></th>
					<td>
						<input type="text" id="nik_voicedesk_visibility_specific_users" name="nik_voicedesk_visibility_specific_users" value="<?php echo esc_attr( $specific_users ); ?>" class="regular-text nik-vd-input-text" placeholder="1, 5, 12" />
						<p class="description"><?php esc_html_e( 'Comma-separated user IDs (e.g. 1, 5, 12). Only works when "Specific Users by ID" is chosen.', 'nik-voicedesk' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="nik_voicedesk_departments"><?php esc_html_e( 'Department Categories', 'nik-voicedesk' ); ?></label></th>
					<td>
						<input type="text" id="nik_voicedesk_departments" name="nik_voicedesk_departments" value="<?php echo esc_attr( $departments ); ?>" class="regular-text nik-vd-input-text" />
						<p class="description"><?php esc_html_e( 'Comma-separated list of departments for automated AI routing and triage.', 'nik-voicedesk' ); ?></p>
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
						<div style="display: flex; align-items: center; gap: 10px;">
							<input type="color" id="nik_voicedesk_btn_color" name="nik_voicedesk_btn_color" value="<?php echo esc_attr( $btn_color ); ?>" />
							<span class="nik-vd-color-code"><?php echo esc_html( $btn_color ); ?></span>
						</div>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="nik_voicedesk_icon_color"><?php esc_html_e( 'Button Icon Color', 'nik-voicedesk' ); ?></label></th>
					<td>
						<div style="display: flex; align-items: center; gap: 10px;">
							<input type="color" id="nik_voicedesk_icon_color" name="nik_voicedesk_icon_color" value="<?php echo esc_attr( $icon_color ); ?>" />
							<span class="nik-vd-color-code"><?php echo esc_html( $icon_color ); ?></span>
						</div>
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
						<textarea id="nik_voicedesk_custom_icon" name="nik_voicedesk_custom_icon" rows="4" class="large-text code nik-vd-textarea"><?php echo esc_textarea( $custom_icon ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Paste raw SVG markup here to override the default microphone icon.', 'nik-voicedesk' ); ?></p>
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
							<option value="openai_gpt" <?php selected( $llm_model, 'openai_gpt' ); ?>><?php esc_html_e( 'OpenAI GPT-4o-mini (Summary & Categorization)', 'nik-voicedesk' ); ?></option>
							<option value="modulate_ai" <?php selected( $llm_model, 'modulate_ai' ); ?>><?php esc_html_e( 'Modulate.ai (Velma-2 Batch Analytics)', 'nik-voicedesk' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="nik_voicedesk_openai_key"><?php esc_html_e( 'OpenAI API Key', 'nik-voicedesk' ); ?></label></th>
					<td>
						<input type="password" id="nik_voicedesk_openai_key" name="nik_voicedesk_openai_key" value="<?php echo esc_attr( $openai_key ); ?>" class="regular-text nik-vd-input-text" autocomplete="off" />
						<p class="description"><?php esc_html_e( 'Used for Whisper audio transcription and GPT-4o-mini summarization.', 'nik-voicedesk' ); ?></p>
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
			<h3><span class="dashicons dashicons-awards"></span> <?php esc_html_e( 'Plans & Licensing', 'nik-voicedesk' ); ?></h3>
			
			<!-- Responsive 2-Card Pricing Grid -->
			<div class="nik-vd-pricing-grid">
				<!-- Free Tier Card -->
				<div class="nik-vd-price-card <?php echo ! $is_valid ? 'is-active-plan' : ''; ?>">
					<div class="nik-vd-price-header">
						<span class="nik-vd-plan-pill"><?php esc_html_e( 'COMMUNITY', 'nik-voicedesk' ); ?></span>
						<h4 class="nik-vd-price-title"><?php esc_html_e( 'Free Tier', 'nik-voicedesk' ); ?></h4>
						<div class="nik-vd-price-amount">
							<span class="nik-vd-currency">$</span>0
							<span class="nik-vd-period">/ forever</span>
						</div>
						<p class="nik-vd-price-desc">
							<?php esc_html_e( '100% full-featured AI voice ticketing with community attribution.', 'nik-voicedesk' ); ?>
						</p>
					</div>

					<ul class="nik-vd-price-features">
						<li><span class="dashicons dashicons-yes" style="color: #16a34a;"></span> <?php esc_html_e( 'Vanilla JS Floating Mic Button', 'nik-voicedesk' ); ?></li>
						<li><span class="dashicons dashicons-yes" style="color: #16a34a;"></span> <?php esc_html_e( 'Whisper & Modulate.ai Speech-to-Text', 'nik-voicedesk' ); ?></li>
						<li><span class="dashicons dashicons-yes" style="color: #16a34a;"></span> <?php esc_html_e( 'Automated AI Summary & Department Triage', 'nik-voicedesk' ); ?></li>
						<li><span class="dashicons dashicons-yes" style="color: #16a34a;"></span> <?php esc_html_e( 'Interactive Click Tracking & Element Highlighting', 'nik-voicedesk' ); ?></li>
						<li><span class="dashicons dashicons-yes" style="color: #16a34a;"></span> <?php esc_html_e( 'Unique Ticket ID & Email Delivery', 'nik-voicedesk' ); ?></li>
						<li><span class="dashicons dashicons-yes" style="color: #16a34a;"></span> <?php esc_html_e( 'Customer Portal Shortcode & WooCommerce Integration', 'nik-voicedesk' ); ?></li>
						<li><span class="dashicons dashicons-yes" style="color: #16a34a;"></span> <?php esc_html_e( 'Unlimited Voice Ticket Processing', 'nik-voicedesk' ); ?></li>
						<li class="is-dimmed"><span class="dashicons dashicons-minus" style="color: #94a3b8;"></span> <?php esc_html_e( 'Includes "Powered by Nik Neural AI" branding & ad banners', 'nik-voicedesk' ); ?></li>
					</ul>

					<div class="nik-vd-price-footer">
						<?php if ( ! $is_valid ) : ?>
							<span class="nik-vd-plan-badge-current">
								✓ <?php esc_html_e( 'Current Active Plan', 'nik-voicedesk' ); ?>
							</span>
						<?php else : ?>
							<span class="nik-vd-plan-badge-inactive">
								<?php esc_html_e( 'Free Community', 'nik-voicedesk' ); ?>
							</span>
						<?php endif; ?>
					</div>
				</div>

				<!-- Enterprise Pro Card -->
				<div class="nik-vd-price-card nik-vd-price-card-pro <?php echo $is_valid ? 'is-active-plan' : ''; ?>">
					<div class="nik-vd-badge-popular">
						⭐ <?php esc_html_e( 'RECOMMENDED — 100% AD-FREE', 'nik-voicedesk' ); ?>
					</div>

					<div class="nik-vd-price-header">
						<span class="nik-vd-plan-pill nik-vd-plan-pill-pro"><?php esc_html_e( 'PRO ENTERPRISE', 'nik-voicedesk' ); ?></span>
						<h4 class="nik-vd-price-title"><?php esc_html_e( 'Enterprise Pro', 'nik-voicedesk' ); ?></h4>
						<div class="nik-vd-price-amount">
							<span class="nik-vd-currency">$</span>29.99
							<span class="nik-vd-period">USD / year</span>
						</div>
						<p class="nik-vd-price-desc">
							<?php esc_html_e( '100% White-Label: Removes all ads, frontend attribution, and banners for your brand and clients.', 'nik-voicedesk' ); ?>
						</p>
					</div>

					<ul class="nik-vd-price-features">
						<li><span class="dashicons dashicons-yes" style="color: #38bdf8;"></span> <strong><?php esc_html_e( '100% Ad-Free (All leaderboard ad banners removed)', 'nik-voicedesk' ); ?></strong></li>
						<li><span class="dashicons dashicons-yes" style="color: #38bdf8;"></span> <strong><?php esc_html_e( 'Completely remove "Powered by Nik Neural AI" frontend attribution', 'nik-voicedesk' ); ?></strong></li>
						<li><span class="dashicons dashicons-yes" style="color: #38bdf8;"></span> <strong><?php esc_html_e( 'Completely remove admin console footer credits', 'nik-voicedesk' ); ?></strong></li>
						<li><span class="dashicons dashicons-yes" style="color: #38bdf8;"></span> <?php esc_html_e( '100% White-Label ready for client websites and agencies', 'nik-voicedesk' ); ?></li>
						<li><span class="dashicons dashicons-yes" style="color: #38bdf8;"></span> <?php esc_html_e( 'All current & future AI features included', 'nik-voicedesk' ); ?></li>
						<li><span class="dashicons dashicons-yes" style="color: #38bdf8;"></span> <?php esc_html_e( 'Priority Technical Support & All Future Pro Updates', 'nik-voicedesk' ); ?></li>
					</ul>

					<div class="nik-vd-price-footer">
						<?php if ( $is_valid ) : ?>
							<span class="nik-vd-plan-badge-current nik-vd-pro-active">
								✓ <?php esc_html_e( 'Enterprise Pro Active (Ad-Free)', 'nik-voicedesk' ); ?>
							</span>
						<?php else : ?>
							<a href="https://nikneural.ca/voicedesk.php" target="_blank" rel="noopener noreferrer" class="nik-vd-buy-pro-btn">
								<?php esc_html_e( 'Upgrade to Pro ($29.99/yr) →', 'nik-voicedesk' ); ?>
							</a>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<!-- License Activation Form Card -->
			<div class="nik-vd-activation-box">
				<h4><span class="dashicons dashicons-admin-network"></span> <?php esc_html_e( 'License Key Activation', 'nik-voicedesk' ); ?></h4>
				<p class="description">
					<?php esc_html_e( 'Enter your Enterprise License Key below to remotely verify and activate Pro features. Positive verification is cached for 72 hours.', 'nik-voicedesk' ); ?>
				</p>

				<div class="nik-vd-activation-row">
					<input type="text" id="nik_voicedesk_license_key" name="nik_voicedesk_license_key" value="<?php echo esc_attr( $license ); ?>" class="regular-text nik-vd-input-text nik-vd-license-input" placeholder="NIK-VD-XXXX-XXXX-XXXX" />
					<span class="nik-vd-status-indicator <?php echo $is_valid ? 'is-valid' : 'is-invalid'; ?>">
						<?php if ( $is_valid ) : ?>
							<span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Valid & Verified', 'nik-voicedesk' ); ?>
						<?php else : ?>
							<span class="dashicons dashicons-warning"></span> <?php esc_html_e( 'Unregistered / Free', 'nik-voicedesk' ); ?>
						<?php endif; ?>
					</span>
				</div>

				<?php if ( $last_checked > 0 ) : ?>
					<p style="font-size: 12px; color: #64748b; margin-top: 8px;">
						<?php printf( esc_html__( 'Last validated with remote server: %s', 'nik-voicedesk' ), date_i18n( 'M j, Y g:i A', $last_checked ) ); ?>
					</p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}
