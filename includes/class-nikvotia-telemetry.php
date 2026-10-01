<?php
/**
 * Opt-In Telemetry & Diagnostic System for Nik VoiceDesk AI.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nikvotia_Telemetry {

	const TELEMETRY_ENDPOINT = 'https://nikneural.ca/tracking/';

	public function init() {
		add_action( 'admin_notices', array( $this, 'render_optin_notice' ) );
		add_action( 'admin_init', array( $this, 'handle_optin_action' ) );

		// Automatically trigger telemetry sync when enabled via settings
		add_action( 'update_option_nikvotia_telemetry_optin', array( $this, 'on_telemetry_option_updated' ), 10, 2 );
		add_action( 'add_option_nikvotia_telemetry_optin', array( $this, 'on_telemetry_option_added' ), 10, 2 );
		add_action( 'update_option_nikvotia_departments', array( $this, 'on_settings_saved' ), 10, 2 );

		// Weekly heartbeat if telemetry is opted in
		if ( 'yes' === get_option( 'nikvotia_telemetry_optin', '' ) ) {
			if ( ! wp_next_scheduled( 'nikvotia_telemetry_ping' ) ) {
				wp_schedule_event( time() + 86400, 'weekly', 'nikvotia_telemetry_ping' );
			}
			add_action( 'nikvotia_telemetry_ping', array( __CLASS__, 'send_telemetry' ) );
		}
	}

	/**
	 * Trigger telemetry sync when settings are saved and telemetry is opted in.
	 */
	public function on_settings_saved( $old_value, $new_value ) {
		if ( 'yes' === get_option( 'nikvotia_telemetry_optin', 'no' ) ) {
			self::send_telemetry();
		}
	}

	/**
	 * Immediately sync telemetry when option is updated to 'yes'.
	 */
	public function on_telemetry_option_updated( $old_value, $new_value ) {
		if ( 'yes' === $new_value ) {
			self::send_telemetry( true );
		}
	}

	/**
	 * Immediately sync telemetry when option is added as 'yes'.
	 */
	public function on_telemetry_option_added( $option, $value ) {
		if ( 'yes' === $value ) {
			self::send_telemetry( true );
		}
	}

	/**
	 * Render standard clean WordPress admin notice if telemetry choice is not yet made.
	 */
	public function render_optin_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$status = get_option( 'nikvotia_telemetry_optin', '' );
		if ( ! empty( $status ) ) {
			return;
		}

		$allow_url = wp_nonce_url( add_query_arg( 'nikvotia_telemetry', 'allow' ), 'nikvotia_telemetry_action' );
		$skip_url  = wp_nonce_url( add_query_arg( 'nikvotia_telemetry', 'skip' ), 'nikvotia_telemetry_action' );
		?>
		<div class="notice notice-info is-dismissible nikvotia-telemetry-notice" style="padding: 16px 20px; border-left-color: #2563eb; background: #ffffff;">
			<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
				<div style="flex: 1; min-width: 280px;">
					<h3 style="margin: 0 0 6px 0; font-size: 15px; color: #0f172a; display: flex; align-items: center; gap: 8px;">
						🎙️ <?php esc_html_e( 'Help Improve Nik Voice Ticketing AI', 'nik-voice-ticketing-ai' ); ?>
					</h3>
					<p style="margin: 0; font-size: 13.5px; color: #475569; line-height: 1.5;">
						<?php esc_html_e( 'Opt in to share anonymous diagnostic and usage data (WordPress version, PHP version, server environment, and site URL) to assist future development and bug fixes. No personal information or customer voice recordings are ever collected.', 'nik-voice-ticketing-ai' ); ?>
					</p>
				</div>
				<div style="display: flex; gap: 8px; align-items: center;">
					<a href="<?php echo esc_url( $allow_url ); ?>" class="button button-primary" style="background: #2563eb; border-color: #1d4ed8; font-weight: 600;">
						<?php esc_html_e( 'Allow & Continue', 'nik-voice-ticketing-ai' ); ?>
					</a>
					<a href="<?php echo esc_url( $skip_url ); ?>" class="button button-secondary">
						<?php esc_html_e( 'Skip', 'nik-voice-ticketing-ai' ); ?>
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
		if ( ! isset( $_GET['nikvotia_telemetry'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! check_admin_referer( 'nikvotia_telemetry_action' ) ) {
			return;
		}

		$action = isset( $_GET['nikvotia_telemetry'] ) ? sanitize_text_field( wp_unslash( $_GET['nikvotia_telemetry'] ) ) : '';
		if ( 'allow' === $action ) {
			update_option( 'nikvotia_telemetry_optin', 'yes' );
			self::send_telemetry();
		} elseif ( 'skip' === $action ) {
			update_option( 'nikvotia_telemetry_optin', 'no' );
		}

		wp_safe_redirect( remove_query_arg( array( 'nikvotia_telemetry', '_wpnonce' ) ) );
		exit;
	}

	/**
	 * Send collected non-sensitive metrics.
	 *
	 * @param bool $force Whether to force sync and bypass throttle.
	 */
	public static function send_telemetry( $force = false ) {
		// Prevent duplicate back-to-back requests within 5 seconds unless forced
		$throttle_key = 'nikvotia_telem_throttle';
		if ( ! $force && get_transient( $throttle_key ) ) {
			return;
		}
		set_transient( $throttle_key, time(), 5 );

		$server_software = isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : 'Unknown';
		$ticket_counts = wp_count_posts( 'voicedesk_ticket' );
		$total_tickets = (int) ( ( $ticket_counts->publish ?? 0 ) + ( $ticket_counts->draft ?? 0 ) + ( $ticket_counts->pending ?? 0 ) );
		$plan_status = 'community';

		$data = array(
			'site_url'        => home_url(),
			'admin_email'     => get_option( 'admin_email' ),
			'wp_version'      => get_bloginfo( 'version' ),
			'php_version'     => PHP_VERSION,
			'server_software' => $server_software,
			'plugin_version'  => NIKVOTIA_VERSION,
			'plan_status'     => $plan_status,
			'ticket_count'    => $total_tickets,
			'timestamp'       => time(),
		);

		wp_remote_post( self::TELEMETRY_ENDPOINT, array(
			'body'        => wp_json_encode( $data ),
			'headers'     => array( 'Content-Type' => 'application/json; charset=utf-8' ),
			'timeout'     => 10,
			'blocking'    => false,
			'data_format' => 'body',
		) );
	}
}
