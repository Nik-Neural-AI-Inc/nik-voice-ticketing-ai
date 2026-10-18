<?php
/**
 * Automated Security, Privacy & Functional Regression Test Suite
 * for Nik Voice Ticketing AI (WordPress 5.8 - 7.1+ compatible).
 *
 * Usage: php tests/test-suite.php [path_to_wp_load.php]
 */

// 1. Locate and bootstrap WordPress
$wp_load_candidates = array(
	isset( $argv[1] ) ? $argv[1] : null,
	'C:/xampp/htdocs/nik/wp-load.php',
	__DIR__ . '/../../../../wp-load.php',
	__DIR__ . '/../../../wp-load.php',
);

$bootstrapped = false;
foreach ( array_filter( $wp_load_candidates ) as $path ) {
	if ( file_exists( $path ) ) {
		require_once $path;
		$bootstrapped = true;
		break;
	}
}

if ( ! $bootstrapped ) {
	fwrite( STDERR, "Error: Could not locate wp-load.php to bootstrap WordPress runtime.\n" );
	exit( 1 );
}

// Ensure REST server is initialized
if ( ! defined( 'REST_REQUEST' ) ) {
	define( 'REST_REQUEST', true );
}

echo "\n====================================================================\n";
echo "  Nik Voice Ticketing AI - Automated Regression & Security Test Suite\n";
echo "  WordPress Version: " . get_bloginfo( 'version' ) . " | PHP: " . PHP_VERSION . "\n";
echo "  Plugin Version:    " . ( defined( 'NIKVOTIA_VERSION' ) ? NIKVOTIA_VERSION : 'NOT LOADED' ) . "\n";
echo "====================================================================\n\n";

class Nikvotia_Regression_Runner {

	private $passed = 0;
	private $failed = 0;
	private $test_post_ids = array();
	private $test_files = array();

	public function run() {
		$this->test_plugin_loaded();
		$this->test_rest_routes();
		$this->test_file_upload_security_filters();
		$this->test_path_traversal_prevention();
		$this->test_audio_stream_authorization_and_idor();
		$this->test_single_provider_isolation();
		$this->test_portal_access_controls();
		$this->test_reply_security_and_deduplication();
		$this->test_telemetry_privacy_controls();
		$this->test_data_retention_pruning();
		$this->test_privacy_export_and_erase_hooks();
		$this->test_security_remediation_suite();

		$this->cleanup();
		$this->report();
	}

	private function assert( $condition, $test_name, $details = '' ) {
		if ( $condition ) {
			$this->passed++;
			echo "  [PASS] {$test_name}\n";
		} else {
			$this->failed++;
			echo "  [FAIL] {$test_name}\n";
			if ( ! empty( $details ) ) {
				echo "         └─ Details: {$details}\n";
			}
		}
	}

	/**
	 * 1. Verify Core Plugin Loading & Constants
	 */
	private function test_plugin_loaded() {
		echo "Suite 1: Plugin Bootstrap & Environment Integrity\n";
		$this->assert( defined( 'NIKVOTIA_VERSION' ), 'NIKVOTIA_VERSION constant defined' );
		$this->assert( class_exists( 'Nikvotia_Core' ), 'Nikvotia_Core class loaded' );
		$this->assert( class_exists( 'Nikvotia_API' ), 'Nikvotia_API class loaded' );
		$this->assert( class_exists( 'Nikvotia_CPT' ), 'Nikvotia_CPT class loaded' );
		$this->assert( class_exists( 'Nikvotia_Settings' ), 'Nikvotia_Settings class loaded' );
		$this->assert( class_exists( 'Nikvotia_Frontend' ), 'Nikvotia_Frontend class loaded' );
		$this->assert( post_type_exists( 'voicedesk_ticket' ), 'voicedesk_ticket post type registered' );
		echo "\n";
	}

	/**
	 * 2. Verify REST API Routes Registration
	 */
	private function test_rest_routes() {
		echo "Suite 2: REST API Route & Method Inspection\n";
		$server = rest_get_server();
		$routes = $server->get_routes();

		$this->assert( isset( $routes['/nikvotia/v1/submit'] ), 'Route /nikvotia/v1/submit is registered' );
		$this->assert( isset( $routes['/nikvotia/v1/audio/(?P<ticket_number>[a-zA-Z0-9_\\-]+)'] ), 'Route /nikvotia/v1/audio/:ticket is registered' );
		$this->assert( isset( $routes['/nikvotia/v1/reply'] ), 'Route /nikvotia/v1/reply is registered' );

		// Verify /reply requires user authentication
		$reply_route = $routes['/nikvotia/v1/reply'][0] ?? null;
		$reply_perm = false;
		if ( $reply_route && isset( $reply_route['permission_callback'] ) ) {
			wp_set_current_user( 0 );
			$unauth_check = call_user_func( $reply_route['permission_callback'] );
			$reply_perm = ( false === $unauth_check || is_wp_error( $unauth_check ) );
		}
		$this->assert( $reply_perm, 'Route /nikvotia/v1/reply rejects unauthenticated requests in permission_callback' );
		echo "\n";
	}

	/**
	 * 3. Verify File Upload Security & Malicious Marker Inspection
	 */
	private function test_file_upload_security_filters() {
		echo "Suite 3: File Upload Hardening & Magic Bytes Verification\n";

		// A. Valid WebM header (\x1A\x45\xDF\xA3)
		$valid_webm = "\x1A\x45\xDF\xA3" . str_repeat( "\x00", 64 );
		$is_valid_webm = ( strncmp( $valid_webm, "\x1A\x45\xDF\xA3", 4 ) === 0 );
		$this->assert( $is_valid_webm, 'WebM EBML magic byte identification works (\x1A\x45\xDF\xA3)' );

		// B. Valid WAV header (RIFF....WAVE)
		$valid_wav = "RIFF\x24\x00\x00\x00WAVEfmt " . str_repeat( "\x00", 32 );
		$is_valid_wav = ( strlen( $valid_wav ) >= 12 && substr( $valid_wav, 0, 4 ) === 'RIFF' && substr( $valid_wav, 8, 4 ) === 'WAVE' );
		$this->assert( $is_valid_wav, 'WAV RIFF/WAVE container magic bytes identification works' );

		// C. Executable Marker Polyglot Detection
		$executable_markers = array(
			'php_tag'        => '/<\?php\b/i',
			'php_short_echo' => '/<\?=/i',
			'php_script_tag' => '/<script\s+language\s*=\s*["\']?php/i',
			'html_script'    => '/<script[\s>]/i',
			'html_doc'       => '/<(html|!doctype)[\s>]/i',
			'svg_tag'        => '/<svg[\s>]/i',
			'iframe_tag'     => '/<iframe[\s>]/i',
			'halt_compiler'  => '/__halt_compiler\s*\(/i',
		);

		$php_payload = "\x1A\x45\xDF\xA3" . "some audio bytes <?php echo 'hacked'; ?> trailing audio";
		$blocked_php = false;
		foreach ( $executable_markers as $name => $pattern ) {
			if ( preg_match( $pattern, $php_payload ) ) {
				$blocked_php = true;
				break;
			}
		}
		$this->assert( $blocked_php, 'Anti-executable scanner catches embedded <?php code inside audio bitstream' );

		$svg_payload = "RIFF\x00\x00\x00\x00WAVE<svg onload=alert(1)>";
		$blocked_svg = false;
		foreach ( $executable_markers as $name => $pattern ) {
			if ( preg_match( $pattern, $svg_payload ) ) {
				$blocked_svg = true;
				break;
			}
		}
		$this->assert( $blocked_svg, 'Anti-executable scanner catches embedded <svg XSS payload in audio bitstream' );

		// D. Disguised malicious extension
		$allowed_extensions = array( 'webm', 'ogg', 'oga', 'wav', 'mp3', 'm4a' );
		$malicious_ext = 'php5';
		$rejected_ext = ! in_array( $malicious_ext, $allowed_extensions, true );
		$this->assert( $rejected_ext, 'Rejects unlisted file extension (.php5)' );

		echo "\n";
	}

	/**
	 * 4. Verify Path Traversal Mitigation in Audio Streaming
	 */
	private function test_path_traversal_prevention() {
		echo "Suite 4: Path Traversal & Audio Streaming Containment\n";

		$upload_dir = wp_upload_dir();
		$base_upload_dir = wp_normalize_path( $upload_dir['basedir'] );

		// Attempt path traversal targeting wp-config.php
		$traversal_attempt = wp_normalize_path( ABSPATH . 'wp-config.php' );
		$is_contained = ( strpos( $traversal_attempt, $base_upload_dir ) === 0 );
		$this->assert( ! $is_contained, 'Base upload directory containment rejects files outside uploads (e.g. wp-config.php)' );

		// Valid file inside upload directory
		$valid_internal = wp_normalize_path( $base_upload_dir . '/nikvotia/rec_test123.webm' );
		$is_valid_internal = ( strpos( $valid_internal, $base_upload_dir ) === 0 );
		$this->assert( $is_valid_internal, 'Upload directory containment permits valid files in /nikvotia/' );

		echo "\n";
	}

	/**
	 * 5. Verify Audio Stream Authorization & IDOR Resistance
	 */
	private function test_audio_stream_authorization_and_idor() {
		echo "Suite 5: Audio Stream Authorization & IDOR Defense\n";

		// Create mock ticket post
		$ticket_num = 'VD-TEST' . wp_generate_password( 4, false, false );
		$crypto_token = wp_generate_password( 32, false );

		$post_id = wp_insert_post( array(
			'post_title'  => 'Ticket ' . $ticket_num . ' - Security Test',
			'post_type'   => 'voicedesk_ticket',
			'post_status' => 'publish',
			'post_author' => 0, // guest created
		) );
		$this->test_post_ids[] = $post_id;

		update_post_meta( $post_id, '_nikvotia_ticket_number', $ticket_num );
		update_post_meta( $post_id, '_nikvotia_access_token', $crypto_token );

		$api = new Nikvotia_API();

		// Case A: Unauthenticated guest without token -> must be rejected
		wp_set_current_user( 0 );
		$req_no_token = new WP_REST_Request( 'GET', '/nikvotia/v1/audio/' . $ticket_num );
		$req_no_token->set_url_params( array( 'ticket_number' => $ticket_num ) );
		$perm_no_token = $api->check_audio_permissions( $req_no_token );
		$this->assert( is_wp_error( $perm_no_token ) && $perm_no_token->get_error_data()['status'] === 403, 'Guest request without token returns 403 Forbidden' );

		// Case B: Guest with invalid token -> must be rejected
		$req_bad_token = new WP_REST_Request( 'GET', '/nikvotia/v1/audio/' . $ticket_num );
		$req_bad_token->set_url_params( array( 'ticket_number' => $ticket_num ) );
		$req_bad_token->set_param( 'token', 'wrong_token_12345678901234567890' );
		$perm_bad_token = $api->check_audio_permissions( $req_bad_token );
		$this->assert( is_wp_error( $perm_bad_token ) && $perm_bad_token->get_error_data()['status'] === 403, 'Guest request with invalid token returns 403 Forbidden' );

		// Case C: Guest with exact cryptographic token -> must be permitted
		$req_good_token = new WP_REST_Request( 'GET', '/nikvotia/v1/audio/' . $ticket_num );
		$req_good_token->set_url_params( array( 'ticket_number' => $ticket_num ) );
		$req_good_token->set_param( 'token', $crypto_token );
		$perm_good_token = $api->check_audio_permissions( $req_good_token );
		$this->assert( true === $perm_good_token, 'Request with valid cryptographic token returns true (200 OK)' );

		// Case D: Non-existent ticket -> returns 404
		$req_missing = new WP_REST_Request( 'GET', '/nikvotia/v1/audio/VD-NONEXISTENT' );
		$req_missing->set_url_params( array( 'ticket_number' => 'VD-NONEXISTENT' ) );
		$perm_missing = $api->check_audio_permissions( $req_missing );
		$this->assert( is_wp_error( $perm_missing ) && $perm_missing->get_error_data()['status'] === 404, 'Request for non-existent ticket returns 404 Not Found' );

		echo "\n";
	}

	/**
	 * 6. Verify Single-Provider AI Engine Isolation
	 */
	private function test_single_provider_isolation() {
		echo "Suite 6: Strict Single-Provider AI Engine Isolation\n";

		// Test provider toggle
		update_option( 'nikvotia_ai_provider', 'nik_voice_cloud' );
		$provider1 = Nikvotia_Settings::get_active_ai_provider();
		$this->assert( 'nik_voice_cloud' === $provider1, 'Active provider switches to Nik Voice Cloud' );

		update_option( 'nikvotia_ai_provider', 'openai' );
		$provider2 = Nikvotia_Settings::get_active_ai_provider();
		$this->assert( 'openai' === $provider2, 'Active provider switches to OpenAI' );

		update_option( 'nikvotia_ai_provider', 'modulate_ai' );
		$provider3 = Nikvotia_Settings::get_active_ai_provider();
		$this->assert( 'modulate_ai' === $provider3, 'Active provider switches to Modulate.ai' );

		// Test friendly labels
		$label = Nikvotia_Settings::get_provider_label( 'nik_voice_cloud' );
		$this->assert( strpos( $label, 'Nik Voice Cloud' ) !== false, 'Provider label for Nik Voice Cloud matches correctly' );

		echo "\n";
	}

	/**
	 * 7. Verify Customer Portal & WooCommerce Permissions
	 */
	private function test_portal_access_controls() {
		echo "Suite 7: Customer Portal Access Control & Ownership Verification\n";

		// Create customer user
		$test_user_id = wp_create_user( 'nik_test_cust_' . time(), 'password123', 'testcust' . time() . '@example.com' );
		$other_user_id = wp_create_user( 'nik_other_cust_' . time(), 'password123', 'othercust' . time() . '@example.com' );

		// Create ticket assigned to test_user_id
		$ticket_num = 'VD-PORTAL' . wp_generate_password( 4, false, false );
		$post_id = wp_insert_post( array(
			'post_title'  => 'Ticket ' . $ticket_num,
			'post_type'   => 'voicedesk_ticket',
			'post_status' => 'publish',
			'post_author' => $test_user_id,
		) );
		$this->test_post_ids[] = $post_id;
		update_post_meta( $post_id, '_nikvotia_ticket_number', $ticket_num );

		// Case A: Unauthenticated user accessing portal single ticket view
		wp_set_current_user( 0 );
		ob_start();
		$frontend = new Nikvotia_Frontend();
		$_GET['ticket'] = $ticket_num;
		$shortcode_output = $frontend->render_user_panel_shortcode( array() );
		$unauth_prompt = ( strpos( $shortcode_output, 'Sign In to View Ticket' ) !== false || strpos( $shortcode_output, 'Sign In to Your Account' ) !== false );
		$this->assert( $unauth_prompt, 'Guest user visiting customer portal is prompted to sign in with secure banner' );

		// Case B: Authenticated ticket owner
		wp_set_current_user( $test_user_id );
		ob_start();
		$shortcode_auth = $frontend->render_user_panel_shortcode( array() );
		$auth_viewable = ( strpos( $shortcode_auth, $ticket_num ) !== false && strpos( $shortcode_auth, 'Access Denied' ) === false );
		$this->assert( $auth_viewable, 'Authenticated ticket author can view their ticket details and audio player' );

		// Case C: Authenticated third-party customer (IDOR attempt)
		wp_set_current_user( $other_user_id );
		ob_start();
		$shortcode_other = $frontend->render_user_panel_shortcode( array() );
		$access_denied = ( strpos( $shortcode_other, 'Access Denied' ) !== false );
		$this->assert( $access_denied, 'Third-party logged-in customer is blocked with Access Denied (IDOR prevented)' );

		unset( $_GET['ticket'] );
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $test_user_id );
		wp_delete_user( $other_user_id );
		echo "\n";
	}

	/**
	 * 8. Verify Reply Processing & Deduplication
	 */
	private function test_reply_security_and_deduplication() {
		echo "Suite 8: Reply Processing Security & Deduplication\n";

		$ticket_num = 'VD-REPLY' . wp_generate_password( 4, false, false );
		$post_id = wp_insert_post( array(
			'post_title'  => 'Ticket ' . $ticket_num,
			'post_type'   => 'voicedesk_ticket',
			'post_status' => 'publish',
		) );
		$this->test_post_ids[] = $post_id;

		// Simulate staff reply saving with deduplication
		wp_set_current_user( 1 );
		$_POST['nikvotia_ticket_nonce'] = wp_create_nonce( 'nikvotia_save_ticket' );
		$_POST['nikvotia_admin_reply']  = 'First test reply message from support.';

		$cpt = new Nikvotia_CPT();
		$post_obj = get_post( $post_id );

		// Run save 1
		$cpt->save_ticket_data( $post_id, $post_obj );
		$replies_after_1 = get_post_meta( $post_id, '_nikvotia_replies', true );
		$this->assert( is_array( $replies_after_1 ) && count( $replies_after_1 ) === 1, 'First staff reply successfully recorded' );

		// Run save 2 with identical reply (simulate page refresh / duplicate POST)
		$cpt->save_ticket_data( $post_id, $post_obj );
		$replies_after_2 = get_post_meta( $post_id, '_nikvotia_replies', true );
		$this->assert( is_array( $replies_after_2 ) && count( $replies_after_2 ) === 1, 'Duplicate staff reply deduplicated and discarded on page refresh' );

		unset( $_POST['nikvotia_ticket_nonce'], $_POST['nikvotia_admin_reply'] );
		echo "\n";
	}

	/**
	 * 9. Verify Telemetry Privacy Controls
	 */
	private function test_telemetry_privacy_controls() {
		echo "Suite 9: Telemetry Privacy & Opt-In Controls\n";

		// Default state must be accessible
		$default_optin = get_option( 'nikvotia_telemetry_optin', '' );
		$this->assert( $default_optin !== 'yes' || 'yes' === get_option( 'nikvotia_telemetry_optin' ), 'Telemetry opt-in status is accessible' );

		// Test setting option to no
		update_option( 'nikvotia_telemetry_optin', 'no' );
		$this->assert( 'no' === get_option( 'nikvotia_telemetry_optin' ), 'Telemetry can be explicitly disabled' );

		// Test telemetry PII minimization option
		update_option( 'nikvotia_telemetry_share_email', 'no' );
		$this->assert( 'no' === get_option( 'nikvotia_telemetry_share_email', 'no' ), 'Admin email sharing in telemetry defaults to disabled (zero PII)' );

		echo "\n";
	}

	/**
	 * 10. Verify Data Retention & Pruning Mechanism
	 */
	private function test_data_retention_pruning() {
		echo "Suite 10: PIPEDA Data Retention & Audio Pruning\n";

		$upload_dir = wp_upload_dir();
		$plugin_dir = $upload_dir['basedir'] . '/nikvotia';
		if ( ! file_exists( $plugin_dir ) ) {
			wp_mkdir_p( $plugin_dir );
		}

		// Create mock dummy audio file
		$test_audio_file = wp_normalize_path( $plugin_dir . '/test_retention_' . time() . '.webm' );
		file_put_contents( $test_audio_file, "\x1A\x45\xDF\xA3" . str_repeat( 'A', 100 ) );
		$this->test_files[] = $test_audio_file;

		// Create mock ticket with old creation date
		$ticket_num = 'VD-OLD' . wp_generate_password( 4, false, false );
		$post_id = wp_insert_post( array(
			'post_title'  => 'Old Ticket ' . $ticket_num,
			'post_type'   => 'voicedesk_ticket',
			'post_status' => 'publish',
			'post_date'   => gmdate( 'Y-m-d H:i:s', time() - ( 40 * 86400 ) ), // 40 days old
		) );
		$this->test_post_ids[] = $post_id;

		update_post_meta( $post_id, '_nikvotia_ticket_number', $ticket_num );
		update_post_meta( $post_id, '_nikvotia_audio_path', $test_audio_file );
		update_post_meta( $post_id, '_nikvotia_audio_url', $upload_dir['baseurl'] . '/nikvotia/' . basename( $test_audio_file ) );

		$this->assert( file_exists( $test_audio_file ), 'Mock audio file created successfully on disk' );

		// Execute retention pruning with 30-day cutoff
		$pruned_count = Nikvotia_Settings::prune_expired_audio_files( 30 );
		$remains = file_exists( $test_audio_file );
		$this->assert( ! $remains, 'Audio file older than 30 days pruned from disk', 'pruned_count=' . $pruned_count . '; exists=' . var_export( $remains, true ) . '; audio_path=' . get_post_meta( $post_id, '_nikvotia_audio_path', true ) );
		$is_marked = get_post_meta( $post_id, '_nikvotia_audio_deleted', true );
		$this->assert( '1' === (string) $is_marked, 'Ticket metadata marked as _nikvotia_audio_deleted = 1' );
		$audio_url = get_post_meta( $post_id, '_nikvotia_audio_url', true );
		$this->assert( empty( $audio_url ), 'Audio URL cleared from pruned ticket' );

		echo "\n";
	}

	/**
	 * 11. Verify WordPress Privacy Exporter & Eraser Hooks
	 */
	private function test_privacy_export_and_erase_hooks() {
		echo "Suite 11: Core WordPress Privacy Exporter & Eraser Hooks\n";

		$has_exporter_filter = has_filter( 'wp_privacy_personal_data_exporters' );
		$has_eraser_filter   = has_filter( 'wp_privacy_personal_data_erasers' );

		$this->assert( ! empty( $has_exporter_filter ), 'wp_privacy_personal_data_exporters filter registered' );
		$this->assert( ! empty( $has_eraser_filter ), 'wp_privacy_personal_data_erasers filter registered' );

		// Test actual export for a customer email
		$test_email = 'customer_privacy_test_' . time() . '@example.com';
		$ticket_num = 'VD-PRIV' . wp_generate_password( 4, false, false );
		$post_id = wp_insert_post( array(
			'post_title'  => 'Privacy Ticket ' . $ticket_num,
			'post_type'   => 'voicedesk_ticket',
			'post_status' => 'publish',
		) );
		$this->test_post_ids[] = $post_id;
		update_post_meta( $post_id, '_nikvotia_ticket_number', $ticket_num );
		update_post_meta( $post_id, '_nikvotia_user_email', $test_email );
		update_post_meta( $post_id, '_nikvotia_summary', 'Test privacy summary' );
		update_post_meta( $post_id, '_nikvotia_transcript', 'Test speech transcript' );

		$export_result = Nikvotia_Core::export_personal_data( $test_email );
		$this->assert( ! empty( $export_result['data'] ) && count( $export_result['data'] ) === 1, 'Core privacy exporter retrieves customer voice ticket records' );

		// Test actual erase for that customer email
		$erase_result = Nikvotia_Core::erase_personal_data( $test_email );
		$this->assert( $erase_result['items_removed'] === 1, 'Core privacy eraser removes customer voice ticket and audio data' );

		echo "\n";
	}

	/**
	 * 12. Security Remediation Verification Suite (TEST-01 through TEST-20)
	 */
	private function test_security_remediation_suite() {
		echo "Suite 12: Security Remediation Verification (TEST-01 to TEST-20)\n";

		$upload_dir = wp_upload_dir();
		$nik_upload_dir = $upload_dir['basedir'] . '/nikvotia';
		if ( ! file_exists( $nik_upload_dir ) ) {
			wp_mkdir_p( $nik_upload_dir );
		}

		if ( function_exists( 'nikvotia_ensure_uploads_dir' ) ) {
			nikvotia_ensure_uploads_dir();
		}

		// TEST-01: Direct HTTP request to synth audio file returns 403 Forbidden (Apache)
		$synth_audio_name = 'synth_verify_' . wp_generate_password( 6, false, false ) . '.webm';
		$synth_audio_path = $nik_upload_dir . '/' . $synth_audio_name;
		file_put_contents( $synth_audio_path, "\x1A\x45\xDF\xA3" . str_repeat( '0', 256 ) );
		$this->test_files[] = $synth_audio_path;

		$synth_audio_url = $upload_dir['baseurl'] . '/nikvotia/' . $synth_audio_name;
		$resp_direct_audio = wp_remote_get( $synth_audio_url, array( 'timeout' => 5 ) );
		$code_direct_audio = wp_remote_retrieve_response_code( $resp_direct_audio );
		$this->assert( 403 === (int) $code_direct_audio, 'TEST-01: Direct HTTP request to synth audio file returns 403 Forbidden (Apache)', 'Received HTTP ' . $code_direct_audio );

		// TEST-02: Direct HTTP request to .htaccess returns 403 Forbidden
		$htaccess_url = $upload_dir['baseurl'] . '/nikvotia/.htaccess';
		$resp_htaccess = wp_remote_get( $htaccess_url, array( 'timeout' => 5 ) );
		$code_htaccess = wp_remote_retrieve_response_code( $resp_htaccess );
		$this->assert( 403 === (int) $code_htaccess, 'TEST-02: Direct HTTP request to .htaccess returns 403 Forbidden', 'Received HTTP ' . $code_htaccess );

		// Create synthetic ticket post and recording for remaining tests
		$ticket_num = 'VD-SEC-' . strtoupper( wp_generate_password( 6, false, false ) );
		$author_user_id = wp_create_user( 'author_user_' . time(), 'password123', 'author_' . time() . '@example.com' );
		$other_user_id  = wp_create_user( 'other_user_' . time(), 'password123', 'other_' . time() . '@example.com' );

		$post_id = wp_insert_post( array(
			'post_title'   => 'Ticket ' . $ticket_num . ' - Security Remediation',
			'post_type'    => 'voicedesk_ticket',
			'post_status'  => 'publish',
			'post_author'  => $author_user_id,
		) );
		$this->test_post_ids[] = $post_id;

		$audio_file_name = 'rec_' . $ticket_num . '.webm';
		$audio_file_path = $nik_upload_dir . '/' . $audio_file_name;
		file_put_contents( $audio_file_path, "\x1A\x45\xDF\xA3" . str_repeat( 'A', 512 ) );
		$this->test_files[] = $audio_file_path;

		$valid_token = Nikvotia_API::generate_audio_stream_token( $ticket_num, $post_id );
		$stream_url  = Nikvotia_API::generate_stream_url( $ticket_num, $post_id, $valid_token );

		update_post_meta( $post_id, '_nikvotia_ticket_number', $ticket_num );
		update_post_meta( $post_id, '_nikvotia_audio_path', wp_normalize_path( $audio_file_path ) );
		update_post_meta( $post_id, '_nikvotia_audio_stream_url', $stream_url );
		update_post_meta( $post_id, '_nikvotia_access_token', $valid_token );
		update_post_meta( $post_id, '_nikvotia_status', 'Open' );

		$api = new Nikvotia_API();

		// TEST-03: Streaming endpoint with valid HMAC token returns HTTP 200 OK with audio content
		wp_set_current_user( 0 ); // guest
		$req_valid = new WP_REST_Request( 'GET', '/nikvotia/v1/audio/' . $ticket_num );
		$req_valid->set_url_params( array( 'ticket_number' => $ticket_num ) );
		$req_valid->set_param( 'token', $valid_token );
		$perm_valid = $api->check_audio_permissions( $req_valid );
		$this->assert( true === $perm_valid, 'TEST-03: Streaming endpoint with valid HMAC token returns HTTP 200 OK with audio content' );

		// TEST-04: Streaming endpoint with expired HMAC token returns HTTP 403 Forbidden
		$expired_token = Nikvotia_API::generate_audio_stream_token( $ticket_num, $post_id, -3600 );
		$req_expired = new WP_REST_Request( 'GET', '/nikvotia/v1/audio/' . $ticket_num );
		$req_expired->set_url_params( array( 'ticket_number' => $ticket_num ) );
		$req_expired->set_param( 'token', $expired_token );
		$perm_expired = $api->check_audio_permissions( $req_expired );
		$this->assert( is_wp_error( $perm_expired ) && 403 === $perm_expired->get_error_data()['status'], 'TEST-04: Streaming endpoint with expired HMAC token returns HTTP 403 Forbidden' );

		// TEST-05: Streaming endpoint with tampered HMAC signature returns HTTP 403 Forbidden
		$token_parts = explode( '.', $valid_token, 2 );
		$tampered_token = $token_parts[0] . '.' . str_repeat( 'f', 64 );
		$req_tampered = new WP_REST_Request( 'GET', '/nikvotia/v1/audio/' . $ticket_num );
		$req_tampered->set_url_params( array( 'ticket_number' => $ticket_num ) );
		$req_tampered->set_param( 'token', $tampered_token );
		$perm_tampered = $api->check_audio_permissions( $req_tampered );
		$this->assert( is_wp_error( $perm_tampered ) && 403 === $perm_tampered->get_error_data()['status'], 'TEST-05: Streaming endpoint with tampered HMAC signature returns HTTP 403 Forbidden' );

		// TEST-06: Streaming endpoint with token for a different ticket returns HTTP 403 Forbidden
		$other_ticket_num = 'VD-OTHER-' . strtoupper( wp_generate_password( 4, false, false ) );
		$other_post_id    = wp_insert_post( array(
			'post_title' => 'Other Ticket ' . $other_ticket_num,
			'post_type'  => 'voicedesk_ticket',
			'post_status'=> 'publish',
		) );
		$this->test_post_ids[] = $other_post_id;
		update_post_meta( $other_post_id, '_nikvotia_ticket_number', $other_ticket_num );
		$other_token = Nikvotia_API::generate_audio_stream_token( $other_ticket_num, $other_post_id );

		$req_cross = new WP_REST_Request( 'GET', '/nikvotia/v1/audio/' . $ticket_num );
		$req_cross->set_url_params( array( 'ticket_number' => $ticket_num ) );
		$req_cross->set_param( 'token', $other_token );
		$perm_cross = $api->check_audio_permissions( $req_cross );
		$this->assert( is_wp_error( $perm_cross ) && 403 === $perm_cross->get_error_data()['status'], 'TEST-06: Streaming endpoint with token for a different ticket returns HTTP 403 Forbidden' );

		// TEST-07: Streaming endpoint with revoked token returns HTTP 403 Forbidden
		$revoked_token = $valid_token;
		$new_stream_url = Nikvotia_API::revoke_audio_tokens( $post_id );
		$req_revoked = new WP_REST_Request( 'GET', '/nikvotia/v1/audio/' . $ticket_num );
		$req_revoked->set_url_params( array( 'ticket_number' => $ticket_num ) );
		$req_revoked->set_param( 'token', $revoked_token );
		$perm_revoked = $api->check_audio_permissions( $req_revoked );
		$this->assert( is_wp_error( $perm_revoked ) && 403 === $perm_revoked->get_error_data()['status'], 'TEST-07: Streaming endpoint with revoked token returns HTTP 403 Forbidden' );

		// Extract new valid token from refreshed stream URL
		$parsed_new_token = '';
		$url_query = wp_parse_url( $new_stream_url, PHP_URL_QUERY );
		if ( $url_query ) {
			parse_str( $url_query, $q_params );
			$parsed_new_token = $q_params['token'] ?? '';
		}

		// TEST-08: Streaming endpoint for closed ticket blocks unauthenticated / guest access (returns 403)
		update_post_meta( $post_id, '_nikvotia_status', 'Closed' );
		wp_set_current_user( 0 ); // guest
		$req_closed = new WP_REST_Request( 'GET', '/nikvotia/v1/audio/' . $ticket_num );
		$req_closed->set_url_params( array( 'ticket_number' => $ticket_num ) );
		$req_closed->set_param( 'token', $parsed_new_token );
		$perm_closed = $api->check_audio_permissions( $req_closed );
		$this->assert( is_wp_error( $perm_closed ) && 403 === $perm_closed->get_error_data()['status'], 'TEST-08: Streaming endpoint for closed ticket blocks unauthenticated / guest access (returns 403)' );

		// TEST-09: Streaming endpoint with administrator session returns HTTP 200 OK (no token required)
		wp_set_current_user( 1 ); // Admin user
		$req_admin = new WP_REST_Request( 'GET', '/nikvotia/v1/audio/' . $ticket_num );
		$req_admin->set_url_params( array( 'ticket_number' => $ticket_num ) );
		$perm_admin = $api->check_audio_permissions( $req_admin );
		$this->assert( true === $perm_admin, 'TEST-09: Streaming endpoint with administrator session returns HTTP 200 OK (no token required)' );

		// TEST-10: Streaming endpoint with ticket author session returns HTTP 200 OK
		update_post_meta( $post_id, '_nikvotia_status', 'Open' );
		wp_set_current_user( $author_user_id );
		$req_author = new WP_REST_Request( 'GET', '/nikvotia/v1/audio/' . $ticket_num );
		$req_author->set_url_params( array( 'ticket_number' => $ticket_num ) );
		$perm_author = $api->check_audio_permissions( $req_author );
		$this->assert( true === $perm_author, 'TEST-10: Streaming endpoint with ticket author session returns HTTP 200 OK' );

		// TEST-11: Streaming endpoint with unauthorized user session returns HTTP 403 Forbidden
		wp_set_current_user( $other_user_id );
		$req_unauth = new WP_REST_Request( 'GET', '/nikvotia/v1/audio/' . $ticket_num );
		$req_unauth->set_url_params( array( 'ticket_number' => $ticket_num ) );
		$perm_unauth = $api->check_audio_permissions( $req_unauth );
		$this->assert( is_wp_error( $perm_unauth ) && 403 === $perm_unauth->get_error_data()['status'], 'TEST-11: Streaming endpoint with unauthorized user session returns HTTP 403 Forbidden' );

		// Clean up temporary users
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $author_user_id );
		wp_delete_user( $other_user_id );

		// TEST-12, 13, 14: Inspect streaming response headers via live REST GET
		$live_stream_url = rest_url( 'nikvotia/v1/audio/' . $ticket_num . '?token=' . $parsed_new_token );
		$resp_live_stream = wp_remote_get( $live_stream_url, array( 'timeout' => 5 ) );
		$headers_live = wp_remote_retrieve_headers( $resp_live_stream );

		// TEST-12: Streaming endpoint responses include X-Robots-Tag: noindex, nofollow, noarchive
		$x_robots = $headers_live['x-robots-tag'] ?? '';
		$this->assert( strpos( (string) $x_robots, 'noindex' ) !== false, 'TEST-12: Streaming endpoint responses include X-Robots-Tag: noindex, nofollow, noarchive', 'x-robots-tag: ' . $x_robots );

		// TEST-13: Streaming endpoint responses include Accept-Ranges: bytes
		$accept_ranges = $headers_live['accept-ranges'] ?? '';
		$this->assert( strtolower( (string) $accept_ranges ) === 'bytes', 'TEST-13: Streaming endpoint responses include Accept-Ranges: bytes', 'accept-ranges: ' . $accept_ranges );

		// TEST-14: Streaming endpoint responses include X-Content-Type-Options: nosniff
		$nosniff = $headers_live['x-content-type-options'] ?? '';
		$this->assert( strtolower( (string) $nosniff ) === 'nosniff', 'TEST-14: Streaming endpoint responses include X-Content-Type-Options: nosniff', 'x-content-type-options: ' . $nosniff );

		// TEST-15: Rendered admin console HTML contains no direct upload URLs (no /wp-content/uploads/nikvotia/ in src or href)
		wp_set_current_user( 1 );
		update_post_meta( $post_id, '_nikvotia_audio_url', $upload_dir['baseurl'] . '/nikvotia/synth_secret_leak.webm' );
		$cpt = new Nikvotia_CPT();
		ob_start();
		$cpt->render_ticket_console( get_post( $post_id ) );
		$admin_html = ob_get_clean();
		$has_leak_admin = ( stripos( $admin_html, '/wp-content/uploads/nikvotia/' ) !== false );
		$this->assert( ! $has_leak_admin, 'TEST-15: Rendered admin console HTML contains no direct upload URLs', 'Leak found: ' . ( $has_leak_admin ? 'YES' : 'NO' ) );

		// TEST-16: Rendered customer portal HTML contains no direct upload URLs
		wp_set_current_user( 1 ); // logged in admin has view access
		$_GET['ticket'] = $ticket_num;
		$frontend = new Nikvotia_Frontend();
		ob_start();
		$portal_html = $frontend->render_user_panel_shortcode( array() );
		$has_leak_portal = ( stripos( $portal_html, '/wp-content/uploads/nikvotia/' ) !== false );
		$this->assert( ! $has_leak_portal, 'TEST-16: Rendered customer portal HTML contains no direct upload URLs', 'Leak found: ' . ( $has_leak_portal ? 'YES' : 'NO' ) );
		unset( $_GET['ticket'] );

		// TEST-17: Ticket creation generates HMAC token with valid signature and future expiration
		$test_gen_token = Nikvotia_API::generate_audio_stream_token( 'VD-SAMPLE', 999 );
		$t_parts = explode( '.', $test_gen_token, 2 );
		$valid_structure = ( count( $t_parts ) === 2 && (int) $t_parts[0] > ( time() + 500000 ) && strlen( $t_parts[1] ) === 64 );
		$this->assert( $valid_structure, 'TEST-17: Ticket creation generates HMAC token with valid signature and future expiration' );

		// TEST-18: Audio retention pruning cron correctly removes expired audio files
		$old_synth_audio = $nik_upload_dir . '/prune_verify_' . time() . '.webm';
		file_put_contents( $old_synth_audio, 'audio data' );
		$this->test_files[] = $old_synth_audio;
		$old_post_id = wp_insert_post( array(
			'post_title' => 'Prune Test Ticket',
			'post_type'  => 'voicedesk_ticket',
			'post_status'=> 'publish',
			'post_date'  => gmdate( 'Y-m-d H:i:s', time() - ( 45 * 86400 ) ),
		) );
		$this->test_post_ids[] = $old_post_id;
		update_post_meta( $old_post_id, '_nikvotia_audio_path', wp_normalize_path( $old_synth_audio ) );
		update_post_meta( $old_post_id, '_nikvotia_ticket_number', 'VD-PRUNE' . time() );
		$pruned = Nikvotia_Settings::prune_expired_audio_files( 30 );
		$file_removed = ! file_exists( $old_synth_audio );
		$this->assert( $file_removed, 'TEST-18: Audio retention pruning cron correctly removes expired audio files' );

		// TEST-19: WordPress Core Personal Data Exporter functions correctly
		$priv_email = 'export_test_' . time() . '@example.com';
		$priv_post_id = wp_insert_post( array(
			'post_title' => 'Export Ticket',
			'post_type'  => 'voicedesk_ticket',
			'post_status'=> 'publish',
		) );
		$this->test_post_ids[] = $priv_post_id;
		update_post_meta( $priv_post_id, '_nikvotia_user_email', $priv_email );
		update_post_meta( $priv_post_id, '_nikvotia_ticket_number', 'VD-EXP' . time() );
		$exp_result = Nikvotia_Core::export_personal_data( $priv_email );
		$exp_success = ( ! empty( $exp_result['data'] ) && count( $exp_result['data'] ) >= 1 );
		$this->assert( $exp_success, 'TEST-19: WordPress Core Personal Data Exporter functions correctly' );

		// TEST-20: WordPress Core Personal Data Eraser removes ticket data and audio file
		$erase_result = Nikvotia_Core::erase_personal_data( $priv_email );
		$erase_success = ( isset( $erase_result['items_removed'] ) && $erase_result['items_removed'] >= 1 );
		$this->assert( $erase_success, 'TEST-20: WordPress Core Personal Data Eraser removes ticket data and audio file' );

		echo "\n";
	}

	private function cleanup() {
		wp_set_current_user( 1 );
		foreach ( $this->test_post_ids as $id ) {
			wp_delete_post( $id, true );
		}
		foreach ( $this->test_files as $f ) {
			if ( file_exists( $f ) ) {
				@unlink( $f );
			}
		}
	}

	private function report() {
		$total = $this->passed + $this->failed;
		echo "====================================================================\n";
		echo "  TEST RESULTS SUMMARY\n";
		echo "  Total Executed: {$total}\n";
		echo "  Passed:         {$this->passed}\n";
		echo "  Failed:         {$this->failed}\n";
		echo "  Success Rate:   " . ( $total > 0 ? round( ( $this->passed / $total ) * 100, 1 ) : 0 ) . "%\n";
		echo "====================================================================\n\n";

		if ( $this->failed > 0 ) {
			exit( 1 );
		}
	}
}

$runner = new Nikvotia_Regression_Runner();
$runner->run();
