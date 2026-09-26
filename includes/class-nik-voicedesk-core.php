<?php
/**
 * Core plugin class.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nik_VoiceDesk_Core {

	/**
	 * Initializes the plugin by including required files and instantiating classes.
	 */
	public function init() {
		$this->includes();
		$this->instantiate();
	}

	/**
	 * Includes all necessary files.
	 */
	private function includes() {
		require_once NIK_VOICEDESK_DIR . 'includes/class-nik-voicedesk-settings.php';
		require_once NIK_VOICEDESK_DIR . 'includes/class-nik-voicedesk-cpt.php';
		require_once NIK_VOICEDESK_DIR . 'includes/class-nik-voicedesk-api.php';
		require_once NIK_VOICEDESK_DIR . 'includes/class-nik-voicedesk-frontend.php';
	}

	/**
	 * Instantiates the required classes.
	 */
	private function instantiate() {
		$settings = new Nik_VoiceDesk_Settings();
		$settings->init();

		$cpt = new Nik_VoiceDesk_CPT();
		$cpt->init();

		$api = new Nik_VoiceDesk_API();
		$api->init();

		$frontend = new Nik_VoiceDesk_Frontend();
		$frontend->init();
	}
}
