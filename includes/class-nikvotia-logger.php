<?php
/**
 * VoiceDesk AI Central Activity & Debug Logger.
 * Provides transparent, audit-ready logging for ticket submissions, security checks, and AI gateway communications.
 *
 * @package Nik_VoiceDesk_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nikvotia_Logger {

	const OPTION_NAME = 'nikvotia_system_logs';
	const MAX_ENTRIES = 150;

	/**
	 * Initialize logger hooks.
	 */
	public static function init() {
		add_action( 'admin_post_nikvotia_clear_logs', array( __CLASS__, 'handle_clear_logs' ) );
		add_action( 'admin_post_nikvotia_download_logs', array( __CLASS__, 'handle_download_logs' ) );
	}

	/**
	 * Defensively sanitize and redact sensitive credentials from context data.
	 *
	 * @param mixed $data Context data array or scalar.
	 * @return mixed
	 */
	public static function sanitize_context( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		$redacted_patterns = '/(key|secret|token|pass|auth|bearer|credential|signature)/i';
		$clean             = array();

		foreach ( $data as $k => $v ) {
			if ( is_string( $k ) && preg_match( $redacted_patterns, $k ) ) {
				$clean[ $k ] = '[REDACTED]';
			} elseif ( is_array( $v ) ) {
				$clean[ $k ] = self::sanitize_context( $v );
			} elseif ( is_string( $v ) && ( strlen( $v ) > 20 && preg_match( '/^[A-Za-z0-9_\-\.]{32,}$/', $v ) ) ) {
				$clean[ $k ] = '[REDACTED]';
			} else {
				$clean[ $k ] = $v;
			}
		}

		return $clean;
	}

	/**
	 * Record a structured log entry.
	 *
	 * @param string $level    Log severity: 'INFO', 'SUCCESS', 'WARNING', 'ERROR', 'SECURITY'.
	 * @param string $category Log category: 'SUBMISSION', 'SECURITY', 'AI_GATEWAY', 'UPLOAD', 'PORTAL'.
	 * @param string $message  Human-readable description.
	 * @param array  $context  Optional context data.
	 */
	public static function log( $level, $category, $message, $context = array() ) {
		$level         = strtoupper( sanitize_key( $level ) );
		$category      = strtoupper( sanitize_key( $category ) );
		$message       = sanitize_text_field( $message );
		$clean_context = ! empty( $context ) ? self::sanitize_context( (array) $context ) : array();

		$entry = array(
			'id'        => wp_generate_password( 8, false ),
			'timestamp' => current_time( 'mysql' ),
			'time_ago'  => human_time_diff( time() ),
			'level'     => $level,
			'category'  => $category,
			'message'   => $message,
			'context'   => $clean_context,
		);

		// 1. Store in WordPress option (rolling buffer)
		$logs = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $logs ) ) {
			$logs = array();
		}

		array_unshift( $logs, $entry );

		if ( count( $logs ) > self::MAX_ENTRIES ) {
			$logs = array_slice( $logs, 0, self::MAX_ENTRIES );
		}

		update_option( self::OPTION_NAME, $logs, false );

		// 2. Stream to standard PHP/WordPress error_log if WP_DEBUG is enabled
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$context_str = ! empty( $clean_context ) ? ' ' . wp_json_encode( $clean_context ) : '';
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Official debug logging when WP_DEBUG is on.
			error_log( sprintf( '[VoiceDesk AI][%s][%s] %s%s', $level, $category, $message, $context_str ) );
		}
	}

	/**
	 * Retrieve stored logs.
	 *
	 * @param int $limit Maximum records to return.
	 * @return array
	 */
	public static function get_logs( $limit = 100 ) {
		$logs = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $logs ) ) {
			return array();
		}
		$sliced = array_slice( $logs, 0, $limit );
		foreach ( $sliced as &$log_item ) {
			if ( ! empty( $log_item['context'] ) ) {
				$log_item['context'] = self::sanitize_context( $log_item['context'] );
			}
		}
		return $sliced;
	}

	/**
	 * Clear all stored logs.
	 */
	public static function clear_logs() {
		delete_option( self::OPTION_NAME );
	}

	/**
	 * Handle admin-post request to clear logs.
	 */
	public static function handle_clear_logs() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized user capability.', 'nik-voice-ticketing-ai' ) );
		}

		check_admin_referer( 'nikvotia_clear_logs_action', 'nikvotia_clear_logs_nonce' );

		self::clear_logs();

		wp_safe_redirect( admin_url( 'edit.php?post_type=voicedesk_ticket&page=nikvotia-settings&tab=logs&nik_logs_cleared=1' ) );
		exit;
	}

	/**
	 * Handle admin-post request to download log export.
	 */
	public static function handle_download_logs() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized user capability.', 'nik-voice-ticketing-ai' ) );
		}

		check_admin_referer( 'nikvotia_download_logs_action', 'nikvotia_download_logs_nonce' );

		$logs = self::get_logs( 150 );
		$site = wp_parse_url( home_url(), PHP_URL_HOST );
		$filename = 'voicedesk-logs-' . sanitize_key( $site ) . '-' . gmdate( 'Y-m-d-His' ) . '.txt';

		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		echo "# VoiceDesk AI System & AI Gateway Debug Logs\n";
		echo "# Exported: " . esc_html( gmdate( 'Y-m-d H:i:s' ) ) . " UTC\n";
		echo "# Site: " . esc_url( home_url() ) . "\n";
		echo "# Active AI Provider: " . esc_html( Nikvotia_Settings::get_active_ai_provider() ) . "\n";
		echo "# Gateway URL: " . esc_url( Nikvotia_Cloud_Client::get_gateway_url() ) . "\n";
		echo "========================================================================\n\n";

		if ( empty( $logs ) ) {
			echo "No log entries found.\n";
		} else {
			foreach ( $logs as $l ) {
				$time = $l['timestamp'] ?? 'UNKNOWN';
				$lvl  = str_pad( '[' . ( $l['level'] ?? 'INFO' ) . ']', 11 );
				$cat  = str_pad( '[' . ( $l['category'] ?? 'GENERAL' ) . ']', 18 );
				$msg  = $l['message'] ?? '';
				$ctx  = ! empty( $l['context'] ) ? ' | Details: ' . wp_json_encode( $l['context'] ) : '';

				echo esc_html( "{$time} {$lvl} {$cat} {$msg}{$ctx}\n" );
			}
		}

		exit;
	}
}
