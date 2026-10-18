<?php
/**
 * Nik Voice Cloud Integration Client for WordPress.
 * Connects Nik VoiceDesk directly to https://voice.nikneural.ca/ for cloud-based voice processing.
 *
 * @package Nik_VoiceDesk_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nikvotia_Cloud_Client {

	const DEFAULT_GATEWAY_URL = 'https://voice.nikneural.ca';

	/**
	 * Get the active Gateway URL.
	 */
	public static function get_gateway_url() {
		if ( defined( 'NIKVOTIA_GATEWAY_URL' ) && ! empty( NIKVOTIA_GATEWAY_URL ) ) {
			return untrailingslashit( NIKVOTIA_GATEWAY_URL );
		}
		$custom = get_option( 'nikvotia_gateway_url', '' );
		if ( ! empty( $custom ) ) {
			return untrailingslashit( $custom );
		}
		return self::DEFAULT_GATEWAY_URL;
	}

	/**
	 * Check if the site is currently connected.
	 */
	public static function is_connected() {
		return ! empty( get_option( 'nikvotia_cloud_key', '' ) );
	}

	/**
	 * Disconnect the site from Nik Voice Cloud.
	 */
	public static function disconnect() {
		delete_option( 'nikvotia_cloud_key' );
		delete_option( 'nikvotia_cloud_secret' );
		update_option( 'nikvotia_cloud_connected', 'no' );
	}

	/**
	 * Perform 1-Click Handshake with Nik Voice Cloud.
	 */
	public static function connect_handshake() {
		$site_url    = home_url();
		$admin_email = get_option( 'admin_email' );
		$endpoint    = self::get_gateway_url() . '/v1/auth/connect';

		Nikvotia_Logger::log( 'INFO', 'AI_GATEWAY', 'Attempting 1-Click Handshake with Nik Voice Cloud gateway (' . $endpoint . ')' );

		$response = wp_remote_post( $endpoint, array(
			'headers' => array(
				'Content-Type' => 'application/json; charset=utf-8',
				'Accept'       => 'application/json',
			),
			'body'    => wp_json_encode( array(
				'site_url'    => $site_url,
				'admin_email' => $admin_email,
			) ),
			'timeout' => 15,
		) );

		if ( is_wp_error( $response ) ) {
			Nikvotia_Logger::log( 'ERROR', 'AI_GATEWAY', 'Nik Voice Cloud handshake HTTP error: ' . $response->get_error_message() );
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! empty( $body['success'] ) && ! empty( $body['key_id'] ) ) {
			update_option( 'nikvotia_cloud_key', sanitize_text_field( $body['key_id'] ) );
			update_option( 'nikvotia_cloud_secret', sanitize_text_field( $body['secret_key'] ) );
			update_option( 'nikvotia_cloud_connected', 'yes' );
			Nikvotia_Logger::log( 'SUCCESS', 'AI_GATEWAY', 'Nik Voice Cloud handshake succeeded. Key ID: ' . sanitize_text_field( $body['key_id'] ) );
			return $body;
		}

		$errMsg = $body['error'] ?? ( $body['message'] ?? 'Handshake failed with gateway.' );
		Nikvotia_Logger::log( 'ERROR', 'AI_GATEWAY', 'Nik Voice Cloud handshake rejected: ' . $errMsg );
		return new WP_Error( 'connect_failed', $errMsg );
	}

	/**
	 * Stream audio file directly to Nik Voice Cloud gateway with HMAC-SHA256 signature
	 * and retrieve full Voice Intelligence results (Transcription, Summary, Department, Sentiment, Urgency, Action Items).
	 *
	 * @param string $filepath Path to temporary audio recording.
	 * @param string $departments Optional comma-separated departments.
	 * @return array|WP_Error Array with text, summary, department, sentiment, urgency, action_items.
	 */
	public static function transcribe_full( $filepath, $departments = '' ) {
		$key_id     = get_option( 'nikvotia_cloud_key', '' );
		$secret_key = get_option( 'nikvotia_cloud_secret', '' );

		if ( empty( $key_id ) ) {
			// Attempt auto-connect handshake on first execution
			$connect = self::connect_handshake();
			if ( is_wp_error( $connect ) ) {
				return $connect;
			}
			$key_id     = get_option( 'nikvotia_cloud_key', '' );
			$secret_key = get_option( 'nikvotia_cloud_secret', '' );
		}

		if ( ! file_exists( $filepath ) ) {
			return new WP_Error( 'file_not_found', __( 'Audio file not found on disk.', 'nik-voice-ticketing-ai' ) );
		}

		// Read file contents via WordPress Filesystem abstraction for Plugin Check compliance
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;
		$file_content = $wp_filesystem ? $wp_filesystem->get_contents( $filepath ) : false;
		if ( false === $file_content ) {
			return new WP_Error( 'file_read_error', __( 'Could not read audio file.', 'nik-voice-ticketing-ai' ) );
		}

		$boundary = wp_generate_password( 24, false );
		$headers  = array(
			'Authorization' => 'Bearer ' . $key_id,
			'X-Nik-Key'     => $key_id,
			'Content-Type'  => 'multipart/form-data; boundary=' . $boundary,
			'Origin'        => home_url(),
		);

		// Include HMAC signature only if secret key is present
		if ( ! empty( $secret_key ) ) {
			$timestamp                  = time();
			$secret_hash                = hash( 'sha256', $secret_key );
			$headers['X-Nik-Timestamp'] = (string) $timestamp;
			$headers['X-Nik-Signature'] = hash_hmac( 'sha256', (string) $timestamp, $secret_hash );
		}

		// Stream multipart payload
		$payload  = '--' . $boundary . "\r\n";
		$payload .= 'Content-Disposition: form-data; name="file"; filename="' . basename( $filepath ) . '"' . "\r\n";
		$payload .= 'Content-Type: audio/webm' . "\r\n\r\n";
		$payload .= $file_content . "\r\n";
		$payload .= '--' . $boundary . "\r\n";
		$payload .= 'Content-Disposition: form-data; name="model"' . "\r\n\r\nwhisper-large-v3\r\n";

		if ( ! empty( $departments ) ) {
			$payload .= '--' . $boundary . "\r\n";
			$payload .= 'Content-Disposition: form-data; name="departments"' . "\r\n\r\n" . $departments . "\r\n";
		}

		$payload .= '--' . $boundary . "--\r\n";

		$endpoint = self::get_gateway_url() . '/v1/audio/transcriptions';
		Nikvotia_Logger::log(
			'INFO',
			'AI_GATEWAY',
			sprintf( 'Streaming audio to Nik Voice Cloud (%s, %s)', basename( $filepath ), size_format( strlen( $file_content ) ) ),
			array( 'endpoint' => $endpoint, 'key_id' => $key_id )
		);

		$response = wp_remote_post( $endpoint, array(
			'headers' => $headers,
			'body'    => $payload,
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			Nikvotia_Logger::log( 'ERROR', 'AI_GATEWAY', 'Nik Voice Cloud network error: ' . $response->get_error_message() );
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code ) {
			$err = $body['error']['message'] ?? ( 'Voice Cloud HTTP Error ' . $code );
			Nikvotia_Logger::log( 'ERROR', 'AI_GATEWAY', sprintf( 'Nik Voice Cloud returned HTTP %d: %s', $code, $err ) );
			return new WP_Error( 'voice_cloud_error', $err );
		}

		$text       = $body['text'] ?? '';
		$model_resp = $body['model'] ?? '';
		$is_demo    = ( false !== strpos( $model_resp, 'demo-mode' ) ) || ( false !== strpos( $text, '[Nik Voice Cloud Demo]' ) );

		if ( $is_demo ) {
			Nikvotia_Logger::log(
				'WARNING',
				'AI_GATEWAY',
				'Nik Voice Cloud gateway responded in Demo Mode because GROQ_API_KEY is not configured on the gateway server (voice.nikneural.ca). Live customer speech requires GROQ_API_KEY in the server environment.',
				array(
					'model'    => $model_resp,
					'endpoint' => $endpoint,
				)
			);
		} else {
			Nikvotia_Logger::log(
				'SUCCESS',
				'AI_GATEWAY',
				sprintf( 'Nik Voice Cloud returned voice intelligence successfully (HTTP 200, %d chars)', strlen( $text ) ),
				array( 'model' => $model_resp )
			);
		}

		return array(
			'text'         => $text,
			'summary'      => $body['summary'] ?? $text,
			'department'   => $body['department'] ?? 'General Support',
			'sentiment'    => $body['sentiment'] ?? 'neutral',
			'urgency'      => $body['urgency'] ?? 'normal',
			'action_items' => $body['action_items'] ?? array(),
			'duration'     => $body['duration'] ?? 0,
			'model'        => $model_resp,
		);
	}

	/**
	 * Stream audio file directly to Nik Voice Cloud gateway and return transcription text.
	 *
	 * @param string $filepath Path to temporary audio recording.
	 * @param string $departments Optional comma-separated departments.
	 * @return string|WP_Error Transcribed text.
	 */
	public static function transcribe( $filepath, $departments = '' ) {
		$res = self::transcribe_full( $filepath, $departments );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return $res['text'] ?? '';
	}

	/**
	 * Triage and summarize transcript sovereignly via Nik Voice Cloud gateway or local sovereign engine.
	 * Routes requests directly to the configured gateway under single-provider isolation.
	 *
	 * @param string $transcript Text of customer voice note.
	 * @param string $departments Comma-separated list of departments.
	 * @return array Array containing 'summary', 'department', and 'source'.
	 */
	public static function summarize( $transcript, $departments = '' ) {
		$key_id     = get_option( 'nikvotia_cloud_key', '' );
		$secret_key = get_option( 'nikvotia_cloud_secret', '' );

		if ( empty( $departments ) ) {
			$departments = 'Sales, Technical Support, Billing, General Support';
		}

		$dept_list    = array_map( 'trim', explode( ',', $departments ) );
		$default_dept = $dept_list[0] ?? 'General Support';

		// First, attempt cloud completions from the gateway if configured
		if ( ! empty( $key_id ) ) {
			$endpoint = self::get_gateway_url() . '/v1/chat/completions';
			$payload  = wp_json_encode( array(
				'model'       => 'nik-neural-triage',
				'messages'    => array(
					array(
						'role'    => 'system',
						'content' => 'You are an intelligent support ticket triage assistant. Analyze the user voice transcript and return JSON with keys "department" (choose from: ' . esc_attr( $departments ) . ') and "summary" (concise 1-2 sentence summary).',
					),
					array(
						'role'    => 'user',
						'content' => $transcript,
					),
				),
				'temperature' => 0.2,
			) );

			$headers = array(
				'Authorization' => 'Bearer ' . $key_id,
				'X-Nik-Key'     => $key_id,
				'Content-Type'  => 'application/json; charset=utf-8',
				'Accept'        => 'application/json',
				'Origin'        => home_url(),
			);

			// Include HMAC signature only if secret key is present
			if ( ! empty( $secret_key ) ) {
				$timestamp                  = time();
				$secret_hash                = hash( 'sha256', $secret_key );
				$headers['X-Nik-Timestamp'] = (string) $timestamp;
				$headers['X-Nik-Signature'] = hash_hmac( 'sha256', (string) $timestamp, $secret_hash );
			}

			$response = wp_remote_post( $endpoint, array(
				'headers' => $headers,
				'body'    => $payload,
				'timeout' => 15,
			) );

			if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
				$body    = json_decode( wp_remote_retrieve_body( $response ), true );
				$content = $body['choices'][0]['message']['content'] ?? '';
				if ( ! empty( $content ) ) {
					$parsed = json_decode( $content, true );
					if ( is_array( $parsed ) && ! empty( $parsed['summary'] ) ) {
						return array(
							'summary'    => sanitize_text_field( $parsed['summary'] ),
							'department' => sanitize_text_field( $parsed['department'] ?? $default_dept ),
							'source'     => 'Nik Voice Cloud Gateway (AI Triage)',
						);
					}
				}
			}
		}

		// Sovereign Local Triage Fallback (zero external calls, instant, on-premise execution)
		return self::sovereign_local_triage( $transcript, $dept_list, $default_dept );
	}

	/**
	 * Sovereign local heuristic triage engine.
	 * Analyzes transcripts locally on-premise without dispatching any data to third-party APIs.
	 *
	 * @param string $transcript   Raw voice transcript.
	 * @param array  $dept_list    Configured department options.
	 * @param string $default_dept Default department fallback.
	 * @return array
	 */
	public static function sovereign_local_triage( $transcript, $dept_list, $default_dept ) {
		$clean_text   = strtolower( $transcript );
		$matched_dept = $default_dept;

		// Keyword heuristics for intelligent department matching
		$department_keywords = array(
			'billing'   => array( 'invoice', 'charge', 'card', 'billing', 'refund', 'payment', 'subscription', 'price', 'cost', 'renew', 'receipt', 'credit' ),
			'sales'     => array( 'quote', 'buy', 'purchase', 'pricing', 'enterprise', 'demo', 'upgrade', 'license', 'discount', 'contract', 'deal' ),
			'technical' => array( 'error', 'bug', 'crash', 'broken', 'issue', 'not working', 'fail', 'slow', 'login', 'password', 'code', 'database', 'server', 'down', 'glitch' ),
			'support'   => array( 'help', 'assist', 'question', 'how to', 'guide', 'setup', 'configure' ),
		);

		foreach ( $dept_list as $dept_name ) {
			$dept_lower = strtolower( $dept_name );
			foreach ( $department_keywords as $cat => $keywords ) {
				if ( false !== strpos( $dept_lower, $cat ) ) {
					foreach ( $keywords as $kw ) {
						if ( false !== strpos( $clean_text, $kw ) ) {
							$matched_dept = $dept_name;
							break 3;
						}
					}
				}
			}
		}

		// Concise summary extraction
		$sentences = preg_split( '/(?<=[.?!])\s+/', trim( $transcript ), -1, PREG_SPLIT_NO_EMPTY );
		if ( ! empty( $sentences ) ) {
			$summary = implode( ' ', array_slice( $sentences, 0, 2 ) );
			if ( strlen( $summary ) > 220 ) {
				$summary = wp_trim_words( $summary, 30, '...' );
			}
		} else {
			$summary = wp_trim_words( $transcript, 25, '...' );
		}

		if ( empty( $summary ) ) {
			$summary = __( 'Voice ticket submitted.', 'nik-voice-ticketing-ai' );
		}

		return array(
			'summary'    => $summary,
			'department' => $matched_dept,
			'source'     => 'Nik Voice Cloud (Sovereign Triage)',
		);
	}

	/**
	 * Probe gateway /v1/health for real-time diagnostic checks.
	 *
	 * @return array Health status and diagnostic metrics.
	 */
	public static function check_health() {
		$endpoint = self::get_gateway_url() . '/v1/health';
		$response = wp_remote_get( $endpoint, array( 'timeout' => 8 ) );

		if ( is_wp_error( $response ) ) {
			return array(
				'ok'      => false,
				'message' => $response->get_error_message(),
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 === $code && is_array( $body ) ) {
			return array(
				'ok'           => true,
				'service'      => $body['service'] ?? 'Nik Voice Cloud',
				'engine'       => $body['engine'] ?? 'Groq Whisper-Large-v3',
				'jurisdiction' => $body['jurisdiction'] ?? 'Canada',
				'status'       => $body['status'] ?? 'ok',
				'raw'          => $body,
			);
		}

		return array(
			'ok'      => false,
			'message' => sprintf( 'Gateway returned HTTP %d', $code ),
		);
	}
}
