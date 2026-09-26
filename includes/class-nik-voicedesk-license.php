<?php
/**
 * Automated Licensing Module for Nik VoiceDesk AI.
 * Handles remote verification with https://nikneural.ca/api/license-verify.php,
 * 72-hour transient caching, offline grace period, and global ad suppression.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nik_VoiceDesk_License {

	const VERIFY_ENDPOINT = 'https://nikneural.ca/api/license-verify.php';
	const TRANSIENT_KEY   = 'nik_voicedesk_license_status';
	const TRANSIENT_EXP   = 259200; // 72 hours (3 days)
	const GRACE_PERIOD    = 1209600; // 14 days

	public function init() {
		// Daily cron check
		add_action( 'nik_voicedesk_daily_license_cron', array( $this, 'cron_verify_license' ) );
		if ( ! wp_next_scheduled( 'nik_voicedesk_daily_license_cron' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'nik_voicedesk_daily_license_cron' );
		}

		// Trigger check on license option update
		add_action( 'update_option_nik_voicedesk_license_key', array( $this, 'on_license_key_update' ), 10, 2 );
	}

	/**
	 * Check if a valid enterprise license is active.
	 */
	public static function is_valid() {
		$license_key = trim( get_option( 'nik_voicedesk_license_key', '' ) );
		if ( empty( $license_key ) ) {
			return false;
		}

		// 1. Check transient cache
		$cached_status = get_transient( self::TRANSIENT_KEY );
		if ( 'valid' === $cached_status ) {
			return true;
		}
		if ( 'invalid' === $cached_status ) {
			return false;
		}

		// 2. Perform verification
		return self::verify_remote( $license_key );
	}

	/**
	 * Execute remote verification via wp_remote_post.
	 */
	public static function verify_remote( $license_key ) {
		if ( empty( $license_key ) ) {
			delete_transient( self::TRANSIENT_KEY );
			update_option( 'nik_voicedesk_license_valid', 0 );
			return false;
		}

		$payload = array(
			'license_key' => $license_key,
			'site_url'    => home_url(),
		);

		$response = wp_remote_post( self::VERIFY_ENDPOINT, array(
			'body'    => $payload,
			'timeout' => 15,
			'headers' => array( 'Accept' => 'application/json' ),
		) );

		if ( is_wp_error( $response ) ) {
			// Offline Grace Period Handling
			$last_success = (int) get_option( 'nik_voicedesk_license_last_success', 0 );
			if ( $last_success > 0 && ( time() - $last_success ) < self::GRACE_PERIOD ) {
				return true;
			}
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 === $code && isset( $body['status'] ) && in_array( strtolower( $body['status'] ), array( 'valid', 'active', 'success' ), true ) ) {
			set_transient( self::TRANSIENT_KEY, 'valid', self::TRANSIENT_EXP );
			update_option( 'nik_voicedesk_license_valid', 1 );
			update_option( 'nik_voicedesk_license_last_success', time() );
			return true;
		}

		// Invalid license
		set_transient( self::TRANSIENT_KEY, 'invalid', DAY_IN_SECONDS );
		update_option( 'nik_voicedesk_license_valid', 0 );
		return false;
	}

	/**
	 * Immediate check when license is saved.
	 */
	public function on_license_key_update( $old_value, $new_value ) {
		delete_transient( self::TRANSIENT_KEY );
		if ( ! empty( $new_value ) ) {
			self::verify_remote( trim( $new_value ) );
		}
	}

	/**
	 * Daily cron check.
	 */
	public function cron_verify_license() {
		$license_key = trim( get_option( 'nik_voicedesk_license_key', '' ) );
		if ( ! empty( $license_key ) ) {
			self::verify_remote( $license_key );
		}
	}
}
