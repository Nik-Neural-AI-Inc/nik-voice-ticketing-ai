<?php
/**
 * Plugin Name: Nik VoiceDesk AI
 * Plugin URI:  https://example.com/nik-voicedesk
 * Description: A zero-friction, AI-powered voice ticketing system for end-users.
 * Version:     1.0.0
 * Author:      Nik Neural AI
 * Author URI:  https://example.com
 * Text Domain: nik-voicedesk
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define Plugin Constants
define( 'NIK_VOICEDESK_VERSION', '1.0.0' );
define( 'NIK_VOICEDESK_FILE', __FILE__ );
define( 'NIK_VOICEDESK_DIR', plugin_dir_path( __FILE__ ) );
define( 'NIK_VOICEDESK_URL', plugin_dir_url( __FILE__ ) );

// Include core class
require_once NIK_VOICEDESK_DIR . 'includes/class-nik-voicedesk-core.php';

/**
 * Initialize the plugin.
 */
function nik_voicedesk_init() {
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
	// Require core to access CPT registration
	require_once NIK_VOICEDESK_DIR . 'includes/class-nik-voicedesk-core.php';
	require_once NIK_VOICEDESK_DIR . 'includes/class-nik-voicedesk-cpt.php';
	
	// Register CPT so flush_rewrite_rules works correctly
	$cpt = new Nik_VoiceDesk_CPT();
	$cpt->register_post_type();
	
	// Flush rewrite rules for the custom post type
	flush_rewrite_rules();

	// Create uploads directory for audio files
	$upload_dir = wp_upload_dir();
	$plugin_upload_dir = $upload_dir['basedir'] . '/nik-voicedesk';
	
	if ( ! file_exists( $plugin_upload_dir ) ) {
		wp_mkdir_p( $plugin_upload_dir );
	}

	// Protect the uploads directory
	$htaccess_file = $plugin_upload_dir . '/.htaccess';
	if ( ! file_exists( $htaccess_file ) ) {
		$htaccess_content = "Options -Indexes\n<Files *.wav>\nOrder Deny,Allow\nDeny from all\n</Files>\n<Files *.webm>\nOrder Deny,Allow\nDeny from all\n</Files>";
		file_put_contents( $htaccess_file, $htaccess_content );
	}

	$index_file = $plugin_upload_dir . '/index.php';
	if ( ! file_exists( $index_file ) ) {
		file_put_contents( $index_file, "<?php\n// Silence is golden." );
	}
}
register_activation_hook( __FILE__, 'nik_voicedesk_activate' );

/**
 * Plugin deactivation hook.
 */
function nik_voicedesk_deactivation() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'nik_voicedesk_deactivation' );
