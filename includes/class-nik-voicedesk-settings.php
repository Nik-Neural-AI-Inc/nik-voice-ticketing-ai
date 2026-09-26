<?php
/**
 * Settings class for admin panel.
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
		if ( 'voicedesk_ticket_page_nik-voicedesk-settings' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'nik-voicedesk-admin-css', NIK_VOICEDESK_URL . 'assets/css/admin.css', array(), NIK_VOICEDESK_VERSION );
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
		register_setting( 'nik_voicedesk_appearance', 'nik_voicedesk_overlay_color' );
		register_setting( 'nik_voicedesk_appearance', 'nik_voicedesk_tooltip_text' );
		register_setting( 'nik_voicedesk_appearance', 'nik_voicedesk_custom_icon' );

		// License
		register_setting( 'nik_voicedesk_license', 'nik_voicedesk_license_key' );
	}

	public static function is_enterprise() {
		$license = get_option( 'nik_voicedesk_license_key', '' );
		return ! empty( $license );
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'general';
		?>
		<div class="wrap nik-voicedesk-wrap">
			<h1><?php esc_html_e( 'Nik VoiceDesk AI Settings', 'nik-voicedesk' ); ?></h1>
			
			<h2 class="nav-tab-wrapper">
				<a href="?post_type=voicedesk_ticket&page=nik-voicedesk-settings&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'General', 'nik-voicedesk' ); ?></a>
				<a href="?post_type=voicedesk_ticket&page=nik-voicedesk-settings&tab=appearance" class="nav-tab <?php echo $active_tab === 'appearance' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Appearance', 'nik-voicedesk' ); ?></a>
				<a href="?post_type=voicedesk_ticket&page=nik-voicedesk-settings&tab=api" class="nav-tab <?php echo $active_tab === 'api' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'API Integrations', 'nik-voicedesk' ); ?></a>
				<a href="?post_type=voicedesk_ticket&page=nik-voicedesk-settings&tab=license" class="nav-tab <?php echo $active_tab === 'license' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'License', 'nik-voicedesk' ); ?></a>
			</h2>

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
				
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	private function render_general_tab() {
		$post_types_selected = get_option( 'nik_voicedesk_visibility_post_types', array() );
		$user_status = get_option( 'nik_voicedesk_visibility_user_status', 'all' );
		$specific_users = get_option( 'nik_voicedesk_visibility_specific_users', '' );
		$departments = get_option( 'nik_voicedesk_departments', 'Sales, Technical Support, Billing' );
		
		$public_post_types = get_post_types( array( 'public' => true ), 'objects' );
		$portal_page_id = get_option( 'nik_voicedesk_portal_page_id', 0 );
		?>
		<table class="form-table">
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
					) );
					?>
					<p class="description">
						<?php esc_html_e( 'Select the page where you added the shortcode [nik_voicedesk_tickets]. Customers will be directed here from confirmation emails and success modals.', 'nik-voicedesk' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label><?php esc_html_e( 'Portal Shortcode', 'nik-voicedesk' ); ?></label></th>
				<td>
					<code>[nik_voicedesk_tickets]</code>
					<p class="description"><?php esc_html_e( 'Insert this shortcode on any page to display the user support ticket portal.', 'nik-voicedesk' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label><?php esc_html_e( 'Visibility by Content Type', 'nik-voicedesk' ); ?></label></th>
				<td>
					<select name="nik_voicedesk_visibility_post_types[]" multiple="multiple" size="5" style="width: 300px;">
						<?php foreach ( $public_post_types as $pt ) : ?>
							<option value="<?php echo esc_attr( $pt->name ); ?>" <?php echo in_array( $pt->name, (array) $post_types_selected, true ) ? 'selected' : ''; ?>><?php echo esc_html( $pt->label ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Hold CTRL (or CMD on Mac) to select multiple post types where the button should appear.', 'nik-voicedesk' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="nik_voicedesk_visibility_user_status"><?php esc_html_e( 'Visibility by User Status', 'nik-voicedesk' ); ?></label></th>
				<td>
					<select id="nik_voicedesk_visibility_user_status" name="nik_voicedesk_visibility_user_status">
						<option value="all" <?php selected( $user_status, 'all' ); ?>><?php esc_html_e( 'All Users', 'nik-voicedesk' ); ?></option>
						<option value="logged_in" <?php selected( $user_status, 'logged_in' ); ?>><?php esc_html_e( 'Logged In Users Only', 'nik-voicedesk' ); ?></option>
						<option value="not_logged_in" <?php selected( $user_status, 'not_logged_in' ); ?>><?php esc_html_e( 'Not Logged In Users Only', 'nik-voicedesk' ); ?></option>
						<option value="specific" <?php selected( $user_status, 'specific' ); ?>><?php esc_html_e( 'Specific Users', 'nik-voicedesk' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="nik_voicedesk_visibility_specific_users"><?php esc_html_e( 'Specific User IDs', 'nik-voicedesk' ); ?></label></th>
				<td>
					<input type="text" id="nik_voicedesk_visibility_specific_users" name="nik_voicedesk_visibility_specific_users" value="<?php echo esc_attr( $specific_users ); ?>" class="regular-text" />
					<p class="description"><?php esc_html_e( 'Comma-separated user IDs (e.g. 1,5,12). Only works if "Specific Users" is selected above.', 'nik-voicedesk' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="nik_voicedesk_departments"><?php esc_html_e( 'Departments', 'nik-voicedesk' ); ?></label></th>
				<td>
					<input type="text" id="nik_voicedesk_departments" name="nik_voicedesk_departments" value="<?php echo esc_attr( $departments ); ?>" class="regular-text" />
					<p class="description"><?php esc_html_e( 'Comma-separated list of departments for AI routing.', 'nik-voicedesk' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	private function render_appearance_tab() {
		$btn_color = get_option( 'nik_voicedesk_btn_color', '#ffd700' );
		$icon_color = get_option( 'nik_voicedesk_icon_color', '#333333' );
		$overlay_color = get_option( 'nik_voicedesk_overlay_color', 'rgba(255, 215, 0, 0.4)' );
		$tooltip_text = get_option( 'nik_voicedesk_tooltip_text', 'Speak your issue and click on the problematic areas on this page.' );
		$custom_icon = get_option( 'nik_voicedesk_custom_icon', '' );
		?>
		<table class="form-table">
			<tr>
				<th scope="row"><label for="nik_voicedesk_btn_color"><?php esc_html_e( 'Button Color', 'nik-voicedesk' ); ?></label></th>
				<td><input type="color" id="nik_voicedesk_btn_color" name="nik_voicedesk_btn_color" value="<?php echo esc_attr( $btn_color ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="nik_voicedesk_icon_color"><?php esc_html_e( 'Icon Color', 'nik-voicedesk' ); ?></label></th>
				<td><input type="color" id="nik_voicedesk_icon_color" name="nik_voicedesk_icon_color" value="<?php echo esc_attr( $icon_color ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="nik_voicedesk_overlay_color"><?php esc_html_e( 'Overlay Color (CSS)', 'nik-voicedesk' ); ?></label></th>
				<td>
					<input type="text" id="nik_voicedesk_overlay_color" name="nik_voicedesk_overlay_color" value="<?php echo esc_attr( $overlay_color ); ?>" class="regular-text" />
					<p class="description"><?php esc_html_e( 'e.g., rgba(255, 215, 0, 0.4) or #ffcc00', 'nik-voicedesk' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="nik_voicedesk_tooltip_text"><?php esc_html_e( 'Tooltip Text', 'nik-voicedesk' ); ?></label></th>
				<td>
					<input type="text" id="nik_voicedesk_tooltip_text" name="nik_voicedesk_tooltip_text" value="<?php echo esc_attr( $tooltip_text ); ?>" class="regular-text" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="nik_voicedesk_custom_icon"><?php esc_html_e( 'Custom SVG Icon', 'nik-voicedesk' ); ?></label></th>
				<td>
					<textarea id="nik_voicedesk_custom_icon" name="nik_voicedesk_custom_icon" rows="5" class="large-text code"><?php echo esc_textarea( $custom_icon ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Paste raw SVG code here to override the default microphone icon.', 'nik-voicedesk' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	private function render_api_tab() {
		$stt_model = get_option( 'nik_voicedesk_stt_model', 'openai_whisper' );
		$llm_model = get_option( 'nik_voicedesk_llm_model', 'openai_gpt' );
		$openai_key = get_option( 'nik_voicedesk_openai_key', '' );
		$modulate_key = get_option( 'nik_voicedesk_modulate_key', '' );
		?>
		<table class="form-table">
			<tr>
				<th scope="row"><label for="nik_voicedesk_stt_model"><?php esc_html_e( 'Speech-to-Text Model', 'nik-voicedesk' ); ?></label></th>
				<td>
					<select id="nik_voicedesk_stt_model" name="nik_voicedesk_stt_model">
						<option value="openai_whisper" <?php selected( $stt_model, 'openai_whisper' ); ?>><?php esc_html_e( 'OpenAI Whisper', 'nik-voicedesk' ); ?></option>
						<option value="modulate_ai" <?php selected( $stt_model, 'modulate_ai' ); ?>><?php esc_html_e( 'Modulate.ai', 'nik-voicedesk' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="nik_voicedesk_llm_model"><?php esc_html_e( 'LLM (Analysis) Model', 'nik-voicedesk' ); ?></label></th>
				<td>
					<select id="nik_voicedesk_llm_model" name="nik_voicedesk_llm_model">
						<option value="openai_gpt" <?php selected( $llm_model, 'openai_gpt' ); ?>><?php esc_html_e( 'OpenAI GPT-4o-mini', 'nik-voicedesk' ); ?></option>
						<option value="modulate_ai" <?php selected( $llm_model, 'modulate_ai' ); ?>><?php esc_html_e( 'Modulate.ai (Analytics)', 'nik-voicedesk' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="nik_voicedesk_openai_key"><?php esc_html_e( 'OpenAI API Key', 'nik-voicedesk' ); ?></label></th>
				<td><input type="password" id="nik_voicedesk_openai_key" name="nik_voicedesk_openai_key" value="<?php echo esc_attr( $openai_key ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="nik_voicedesk_modulate_key"><?php esc_html_e( 'Modulate.ai API Key', 'nik-voicedesk' ); ?></label></th>
				<td><input type="password" id="nik_voicedesk_modulate_key" name="nik_voicedesk_modulate_key" value="<?php echo esc_attr( $modulate_key ); ?>" class="regular-text" /></td>
			</tr>
		</table>
		<?php
	}

	private function render_license_tab() {
		$license = get_option( 'nik_voicedesk_license_key', '' );
		?>
		<table class="form-table">
			<tr>
				<th scope="row"><label for="nik_voicedesk_license_key"><?php esc_html_e( 'Enterprise License Key', 'nik-voicedesk' ); ?></label></th>
				<td>
					<input type="text" id="nik_voicedesk_license_key" name="nik_voicedesk_license_key" value="<?php echo esc_attr( $license ); ?>" class="regular-text" />
					<p class="description"><?php esc_html_e( 'Enter a valid commercial license key to remove the "Powered by Nik Neural AI" branding.', 'nik-voicedesk' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}
}
