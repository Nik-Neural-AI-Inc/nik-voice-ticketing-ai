<?php
/**
 * Plugin Name:       Nik VoiceDesk AI
 * Plugin URI:        https://nikneural.ca/voicedesk.php
 * Description:       Zero-friction, AI-powered voice ticketing system for WordPress with Whisper and Modulate.ai speech-to-text.
 * Version:           1.2.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Nik Neural AI Inc.
 * Author URI:        https://nikneural.ca/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       nik-voicedesk
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define Plugin Constants
define( 'NIK_VOICEDESK_VERSION', '1.2.0' );
define( 'NIK_VOICEDESK_FILE', __FILE__ );
define( 'NIK_VOICEDESK_DIR', plugin_dir_path( __FILE__ ) );
define( 'NIK_VOICEDESK_URL', plugin_dir_url( __FILE__ ) );

// Include core class
require_once NIK_VOICEDESK_DIR . 'includes/class-nik-voicedesk-core.php';

/**
 * Clean up blocking .htaccess if previously generated
 */
function nik_voicedesk_ensure_uploads_dir() {
	$upload_dir = wp_upload_dir();
	$plugin_upload_dir = $upload_dir['basedir'] . '/nik-voicedesk';
	
	if ( ! file_exists( $plugin_upload_dir ) ) {
		wp_mkdir_p( $plugin_upload_dir );
	}

	$htaccess_file = $plugin_upload_dir . '/.htaccess';
	// Prevent directory browsing, but permit browser playback of audio files
	if ( ! file_exists( $htaccess_file ) ) {
		$htaccess_content = "Options -Indexes\n<IfModule mod_headers.c>\n    Header set Access-Control-Allow-Origin \"*\"\n</IfModule>\n";
		file_put_contents( $htaccess_file, $htaccess_content );
	}

	$index_file = $plugin_upload_dir . '/index.php';
	if ( ! file_exists( $index_file ) ) {
		file_put_contents( $index_file, "<?php\n// Silence is golden." );
	}
}

/**
 * Initialize the plugin.
 */
function nik_voicedesk_init() {
	nik_voicedesk_ensure_uploads_dir();

	// Load plugin text domain
	load_plugin_textdomain( 'nik-voicedesk', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	// Initialize the core plugin class
	$core = new Nik_VoiceDesk_Core();
	$core->init();
}
add_action( 'plugins_loaded', 'nik_voicedesk_init' );

/**
 * Plugin activation hook.
 */
function nik_voicedesk_activate() {
	require_once NIK_VOICEDESK_DIR . 'includes/class-nik-voicedesk-core.php';
	require_once NIK_VOICEDESK_DIR . 'includes/class-nik-voicedesk-cpt.php';
	
	$cpt = new Nik_VoiceDesk_CPT();
	$cpt->register_post_type();
	flush_rewrite_rules();

	nik_voicedesk_ensure_uploads_dir();
}
register_activation_hook( __FILE__, 'nik_voicedesk_activate' );

/**
 * Plugin deactivation hook.
 */
function nik_voicedesk_deactivation() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'nik_voicedesk_deactivation' );
