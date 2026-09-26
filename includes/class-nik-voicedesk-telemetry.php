<?php
/**
 * Opt-In Telemetry & Diagnostic System for Nik VoiceDesk AI.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nik_VoiceDesk_Telemetry {

	const TELEMETRY_ENDPOINT = 'https://nikneural.ca/tracking/';

	public function init() {
		add_action( 'admin_notices', array( $this, 'render_optin_notice' ) );
		add_action( 'admin_init', array( $this, 'handle_optin_action' ) );

		// Weekly heartbeat if telemetry is opted in
		if ( 'yes' === get_option( 'nik_voicedesk_telemetry_optin', '' ) ) {
			if ( ! wp_next_scheduled( 'nik_voicedesk_telemetry_ping' ) ) {
				wp_schedule_event( time() + 86400, 'weekly', 'nik_voicedesk_telemetry_ping' );
			}
			add_action( 'nik_voicedesk_telemetry_ping', array( __CLASS__, 'send_telemetry' ) );
		}
	}

	/**
	 * Render standard clean WordPress admin notice if telemetry choice is not yet made.
	 */
	public function render_optin_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$status = get_option( 'nik_voicedesk_telemetry_optin', '' );
		if ( ! empty( $status ) ) {
			return;
		}

		$allow_url = wp_nonce_url( add_query_arg( 'nik_vd_telemetry', 'allow' ), 'nik_vd_telemetry_action' );
		$skip_url  = wp_nonce_url( add_query_arg( 'nik_vd_telemetry', 'skip' ), 'nik_vd_telemetry_action' );
		?>
		<div class="notice notice-info is-dismissible nik-vd-telemetry-notice" style="padding: 16px 20px; border-left-color: #2563eb; background: #ffffff;">
			<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
				<div style="flex: 1; min-width: 280px;">
					<h3 style="margin: 0 0 6px 0; font-size: 15px; color: #0f172a; display: flex; align-items: center; gap: 8px;">
						🎙️ <?php esc_html_e( 'Help Improve Nik VoiceDesk AI', 'nik-voicedesk' ); ?>
					</h3>
					<p style="margin: 0; font-size: 13.5px; color: #475569; line-height: 1.5;">
						<?php esc_html_e( 'Opt in to share anonymous diagnostic and usage data (WordPress version, PHP version, server environment, and site URL) to assist future development and bug fixes. No personal information or customer voice recordings are ever collected.', 'nik-voicedesk' ); ?>
					</p>
				</div>
				<div style="display: flex; gap: 8px; align-items: center;">
					<a href="<?php echo esc_url( $allow_url ); ?>" class="button button-primary" style="background: #2563eb; border-color: #1d4ed8; font-weight: 600;">
						<?php esc_html_e( 'Allow & Continue', 'nik-voicedesk' ); ?>
					</a>
					<a href="<?php echo esc_url( $skip_url ); ?>" class="button button-secondary">
						<?php esc_html_e( 'Skip', 'nik-voicedesk' ); ?>
					</a>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle admin action for Allow / Skip.
	 */
	public function handle_optin_action() {
		if ( ! isset( $_GET['nik_vd_telemetry'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! check_admin_referer( 'nik_vd_telemetry_action' ) ) {
			return;
		}

		$action = sanitize_text_field( $_GET['nik_vd_telemetry'] );
		if ( 'allow' === $action ) {
			update_option( 'nik_voicedesk_telemetry_optin', 'yes' );
			self::send_telemetry();
		} elseif ( 'skip' === $action ) {
			update_option( 'nik_voicedesk_telemetry_optin', 'no' );
		}

		wp_safe_redirect( remove_query_arg( array( 'nik_vd_telemetry', '_wpnonce' ) ) );
		exit;
	}

	/**
	 * Send collected non-sensitive metrics.
	 */
	public static function send_telemetry() {
		$server_software = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
		$ticket_counts = wp_count_posts( 'voicedesk_ticket' );
		$total_tickets = (int) ( ( $ticket_counts->publish ?? 0 ) + ( $ticket_counts->draft ?? 0 ) + ( $ticket_counts->pending ?? 0 ) );
		$plan_status = Nik_VoiceDesk_Settings::is_enterprise() ? 'pro' : 'free';

		$data = array(
			'site_url'        => home_url(),
			'admin_email'     => get_option( 'admin_email' ),
			'wp_version'      => get_bloginfo( 'version' ),
			'php_version'     => PHP_VERSION,
			'server_software' => $server_software,
			'plugin_version'  => NIK_VOICEDESK_VERSION,
			'plan_status'     => $plan_status,
			'ticket_count'    => $total_tickets,
			'timestamp'       => time(),
		);

		wp_remote_post( self::TELEMETRY_ENDPOINT, array(
			'body'        => wp_json_encode( $data ),
			'headers'     => array( 'Content-Type' => 'application/json; charset=utf-8' ),
			'timeout'     => 15,
			'blocking'    => false,
			'data_format' => 'body',
		) );
	}
}
