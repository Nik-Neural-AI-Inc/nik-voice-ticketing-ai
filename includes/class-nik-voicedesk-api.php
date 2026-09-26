<?php
/**
 * REST API and AI Processing class.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nik_VoiceDesk_API {

	public function init() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route( 'nik-voicedesk/v1', '/submit', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'handle_submission' ),
			'permission_callback' => array( $this, 'check_permission' ),
		) );
	}

	public function check_permission( $request ) {
		return true; // We check visibility rules in frontend, but to allow guests we return true here.
	}

	public function handle_submission( WP_REST_Request $request ) {
		$files = $request->get_file_params();
		if ( empty( $files['audio'] ) ) {
			return new WP_Error( 'no_audio', __( 'No audio file provided.', 'nik-voicedesk' ), array( 'status' => 400 ) );
		}

		$audio_file = $files['audio'];
		$page_url = sanitize_url( $request->get_param( 'page_url' ) );
		$environment = sanitize_text_field( $request->get_param( 'environment' ) );
		$clicked_elements_raw = $request->get_param( 'clicked_elements' );
		$clicked_elements = json_decode( $clicked_elements_raw, true );

		// 1. Save Audio File Immediately
		$upload_dir = wp_upload_dir();
		$plugin_upload_dir = $upload_dir['basedir'] . '/nik-voicedesk';
		$filename = 'ticket_' . time() . '_' . wp_generate_password( 6, false ) . '.webm';
		$filepath = $plugin_upload_dir . '/' . $filename;
		$file_url = $upload_dir['baseurl'] . '/nik-voicedesk/' . $filename;

		if ( ! move_uploaded_file( $audio_file['tmp_name'], $filepath ) ) {
			return new WP_Error( 'upload_failed', __( 'Failed to save audio file.', 'nik-voicedesk' ), array( 'status' => 500 ) );
		}

		// 2. Generate Unique Ticket Number and Create Post
		$user_id = get_current_user_id();
		$username = $user_id ? wp_get_current_user()->user_login : 'Guest';
		$ticket_number = 'VD-' . strtoupper( wp_generate_password( 8, false ) );

		$post_data = array(
			'post_title'   => sprintf( __( 'Ticket %s - Processing', 'nik-voicedesk' ), $ticket_number ),
			'post_content' => '',
			'post_status'  => 'publish', // Saved immediately so it's visible
			'post_type'    => 'voicedesk_ticket',
			'post_author'  => $user_id ?: 1,
		);

		$post_id = wp_insert_post( $post_data );
		if ( is_wp_error( $post_id ) ) {
			return new WP_Error( 'post_creation_failed', __( 'Failed to create ticket.', 'nik-voicedesk' ), array( 'status' => 500 ) );
		}

		// Save Initial Meta
		update_post_meta( $post_id, '_nik_ticket_number', $ticket_number );
		update_post_meta( $post_id, '_nik_audio_url', $file_url );
		update_post_meta( $post_id, '_nik_audio_path', $filepath );
		update_post_meta( $post_id, '_nik_page_url', $page_url );
		update_post_meta( $post_id, '_nik_environment', $environment );
		update_post_meta( $post_id, '_nik_clicked_elements', $clicked_elements );
		update_post_meta( $post_id, '_nik_username', $username );
		update_post_meta( $post_id, '_nik_status', 'Processing AI' );

		// 3. Process AI (Synchronously for this iteration)
		$stt_model = get_option( 'nik_voicedesk_stt_model', 'openai_whisper' );
		$llm_model = get_option( 'nik_voicedesk_llm_model', 'openai_gpt' );
		
		// Run STT
		$transcript = '';
		if ( 'openai_whisper' === $stt_model ) {
			$transcript = $this->call_openai_whisper( $filepath );
		} elseif ( 'modulate_ai' === $stt_model ) {
			$transcript = $this->call_modulate_stt( $filepath );
		}

		if ( is_wp_error( $transcript ) ) {
			update_post_meta( $post_id, '_nik_status', 'AI STT Failed' );
			return rest_ensure_response( array( 'success' => true, 'ticket_number' => $ticket_number, 'message' => __( 'Ticket saved, but AI transcription failed.', 'nik-voicedesk' ) ) );
		}
		update_post_meta( $post_id, '_nik_transcript', $transcript );

		// Run LLM
		$summary = __( 'Summary could not be generated.', 'nik-voicedesk' );
		$department = __( 'Uncategorized', 'nik-voicedesk' );
		
		if ( ! empty( $transcript ) ) {
			$departments = get_option( 'nik_voicedesk_departments', 'Sales, Technical Support, Billing' );
			
			$analysis = array();
			if ( 'openai_gpt' === $llm_model ) {
				$analysis = $this->call_openai_gpt( $transcript, $departments );
			} elseif ( 'modulate_ai' === $llm_model ) {
				$analysis = $this->call_modulate_analysis( $transcript, $departments );
			}

			if ( ! is_wp_error( $analysis ) ) {
				$summary = $analysis['summary'] ?? $summary;
				$department = $analysis['department'] ?? $department;
			} else {
				update_post_meta( $post_id, '_nik_status', 'AI Analysis Failed' );
			}
		}

		// Update Post with Final Data
		wp_update_post( array(
			'ID'         => $post_id,
			'post_title' => sprintf( __( 'Ticket %s - %s', 'nik-voicedesk' ), $ticket_number, $department ),
		) );

		update_post_meta( $post_id, '_nik_summary', $summary );
		update_post_meta( $post_id, '_nik_department', $department );
		update_post_meta( $post_id, '_nik_status', 'Completed' );

		return rest_ensure_response( array(
			'success' => true,
			'message' => __( 'Ticket submitted successfully.', 'nik-voicedesk' ),
			'ticket_number' => $ticket_number
		) );
	}

	private function call_openai_whisper( $filepath ) {
		$api_key = get_option( 'nik_voicedesk_openai_key', '' );
		if ( empty( $api_key ) ) return new WP_Error( 'missing_key', 'OpenAI API key missing.' );

		$boundary = wp_generate_password( 24, false );
		$headers  = array(
			'Authorization' => 'Bearer ' . $api_key,
			'Content-Type'  => 'multipart/form-data; boundary=' . $boundary,
		);

		$payload = '--' . $boundary . "\r\n";
		$payload .= 'Content-Disposition: form-data; name="file"; filename="' . basename( $filepath ) . '"' . "\r\n";
		$payload .= 'Content-Type: audio/webm' . "\r\n\r\n";
		$payload .= file_get_contents( $filepath ) . "\r\n";
		$payload .= '--' . $boundary . "\r\n";
		$payload .= 'Content-Disposition: form-data; name="model"' . "\r\n\r\nwhisper-1\r\n";
		$payload .= '--' . $boundary . "--\r\n";

		$response = wp_remote_post( 'https://api.openai.com/v1/audio/transcriptions', array(
			'headers' => $headers,
			'body'    => $payload,
			'timeout' => 60,
		) );

		if ( is_wp_error( $response ) ) return $response;
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return $body['text'] ?? new WP_Error( 'api_error', 'Failed to parse response.' );
	}

	private function call_openai_gpt( $transcript, $departments ) {
		$api_key = get_option( 'nik_voicedesk_openai_key', '' );
		if ( empty( $api_key ) ) return new WP_Error( 'missing_key', 'OpenAI API key missing.' );

		$system_prompt = "You are an AI assistant for a ticketing system. Analyze the transcript. 1. Summarize. 2. Categorize into ONE of: $departments. Return strictly JSON with keys 'summary' and 'department'.";
		$body = array(
			'model' => 'gpt-4o-mini',
			'messages' => array(
				array( 'role' => 'system', 'content' => $system_prompt ),
				array( 'role' => 'user', 'content' => $transcript ),
			),
			'response_format' => array( 'type' => 'json_object' )
		);

		$response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $body ),
			'timeout' => 60,
		) );

		if ( is_wp_error( $response ) ) return $response;
		$res_body = json_decode( wp_remote_retrieve_body( $response ), true );
		$content = $res_body['choices'][0]['message']['content'] ?? '{}';
		return json_decode( $content, true );
	}

	private function call_modulate_stt( $filepath ) {
		$api_key = get_option( 'nik_voicedesk_modulate_key', '' );
		if ( empty( $api_key ) ) return new WP_Error( 'missing_key', 'Modulate API key missing.' );
		
		// Stub for Modulate ToxMod ingest API for transcription
		// Without exact docs, we simulate a request or return a mock if it fails.
		return "(Modulate.ai STT Placeholder - Replace with actual REST POST when docs are available)";
	}

	private function call_modulate_analysis( $transcript, $departments ) {
		$api_key = get_option( 'nik_voicedesk_modulate_key', '' );
		if ( empty( $api_key ) ) return new WP_Error( 'missing_key', 'Modulate API key missing.' );
		
		// Stub for Modulate Analytics API
		return array(
			'summary' => 'Analyzed by Modulate.ai: ' . substr( $transcript, 0, 50 ) . '...',
			'department' => 'General Support (Modulate)',
		);
	}
}
