<?php
/**
 * Core plugin class.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nikvotia_Core {

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
		require_once NIKVOTIA_DIR . 'includes/class-nikvotia-telemetry.php';
		require_once NIKVOTIA_DIR . 'includes/class-nikvotia-settings.php';
		require_once NIKVOTIA_DIR . 'includes/class-nikvotia-cpt.php';
		require_once NIKVOTIA_DIR . 'includes/class-nikvotia-api.php';
		require_once NIKVOTIA_DIR . 'includes/class-nikvotia-frontend.php';
	}

	/**
	 * Instantiates the required classes.
	 */
	private function instantiate() {
		$telemetry = new Nikvotia_Telemetry();
		$telemetry->init();

		$settings = new Nikvotia_Settings();
		$settings->init();

		$cpt = new Nikvotia_CPT();
		$cpt->init();

		$api = new Nikvotia_API();
		$api->init();

		$frontend = new Nikvotia_Frontend();
		$frontend->init();
	}
}
