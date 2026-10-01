<?php
/**
 * Plugin Name:       Nik Voice Ticketing AI
 * Plugin URI:        https://nikneural.ca/pl/voicedesk.php
 * Description:       Zero-friction, AI-powered voice ticketing system for WordPress with Whisper and Modulate.ai speech-to-text.
 * Version:           1.2.3
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Nik Neural AI Inc.
 * Author URI:        https://nikneural.ca/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       nik-voice-ticketing-ai
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define Plugin Constants
define( 'NIKVOTIA_VERSION', '1.2.3' );
define( 'NIKVOTIA_FILE', __FILE__ );
define( 'NIKVOTIA_DIR', plugin_dir_path( __FILE__ ) );
define( 'NIKVOTIA_URL', plugin_dir_url( __FILE__ ) );



// Include core class
require_once NIKVOTIA_DIR . 'includes/class-nikvotia-core.php';

/**
 * Clean up blocking .htaccess if previously generated
 */
function nikvotia_ensure_uploads_dir() {
	$upload_dir = wp_upload_dir();
	$plugin_upload_dir = $upload_dir['basedir'] . '/nikvotia';
	
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
function nikvotia_init() {
	nikvotia_ensure_uploads_dir();

	// Initialize the core plugin class
	$core = new Nikvotia_Core();
	$core->init();
}
add_action( 'plugins_loaded', 'nikvotia_init' );

/**
 * Plugin activation hook.
 */
function nikvotia_activate() {
	require_once NIKVOTIA_DIR . 'includes/class-nikvotia-core.php';
	require_once NIKVOTIA_DIR . 'includes/class-nikvotia-cpt.php';
	
	$cpt = new Nikvotia_CPT();
	$cpt->register_post_type();
	flush_rewrite_rules();

	nikvotia_ensure_uploads_dir();
}
register_activation_hook( __FILE__, 'nikvotia_activate' );

/**
 * Plugin deactivation hook.
 */
function nikvotia_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'nikvotia_deactivate' );
