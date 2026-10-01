<?php
/**
 * REST API and AI Processing class for Nik VoiceDesk AI.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nikvotia_API {

	public function init() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		$namespaces = array( 'nikvotia/v1' );

		foreach ( $namespaces as $ns ) {
			register_rest_route( $ns, '/submit', array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_submission' ),
				'permission_callback' => '__return_true',
			) );

			register_rest_route( $ns, '/audio/(?P<ticket_number>[a-zA-Z0-9_\-]+)', array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'stream_audio' ),
				'permission_callback' => array( $this, 'check_audio_permissions' ),
			) );

			register_rest_route( $ns, '/reply', array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_user_reply' ),
				'permission_callback' => function() {
					return is_user_logged_in();
				},
			) );
		}
	}

	/**
	 * Main ticket submission handler.
	 */
	public function handle_submission( WP_REST_Request $request ) {
		// Clean any previous output buffer to prevent PHP notices from polluting JSON response
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
		ob_start();

		$files = $request->get_file_params();
		if ( empty( $files['audio'] ) ) {
			return new WP_Error( 'no_audio', __( 'No audio recording provided.', 'nik-voice-ticketing-ai' ), array( 'status' => 400 ) );
		}

		$audio_file = $files['audio'];
		$page_url_raw = $request->get_param( 'page_url' );
		$page_url = ! empty( $page_url_raw ) ? sanitize_url( (string) $page_url_raw ) : '';
		$raw_env = (string) ( $request->get_param( 'environment' ) ?: '' );
		$guest_email_raw = $request->get_param( 'guest_email' );
		$guest_email = ! empty( $guest_email_raw ) ? sanitize_email( (string) $guest_email_raw ) : '';
		$clicked_elements_raw = (string) ( $request->get_param( 'clicked_elements' ) ?: '' );
		$clicked_elements = ! empty( $clicked_elements_raw ) ? json_decode( $clicked_elements_raw, true ) : array();
		$sanitized_clicks = array();
		if ( is_array( $clicked_elements ) ) {
			foreach ( $clicked_elements as $click ) {
				$sanitized_clicks[] = array(
					'selector'  => sanitize_text_field( $click['selector'] ?? '' ),
					'tag'       => sanitize_text_field( $click['tag'] ?? '' ),
					'text'      => sanitize_text_field( $click['text'] ?? '' ),
					'x'         => intval( $click['x'] ?? 0 ),
					'y'         => intval( $click['y'] ?? 0 ),
					'offset_ms' => intval( $click['offset_ms'] ?? 0 ),
				);
			}
		}

		// 1. Clean Environment (OS + Browser only)
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$clean_environment = self::parse_user_agent( ! empty( $raw_env ) ? $raw_env : $user_agent );

		// 2. Save Audio File using standard WordPress upload handler
		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$upload_mimes_filter = function( $mimes ) {
			$mimes['webm'] = 'audio/webm';
			$mimes['ogg']  = 'audio/ogg';
			$mimes['oga']  = 'audio/ogg';
			$mimes['wav']  = 'audio/wav';
			$mimes['mp3']  = 'audio/mpeg';
			$mimes['m4a']  = 'audio/mp4';
			return $mimes;
		};
		add_filter( 'upload_mimes', $upload_mimes_filter, 999 );

		$check_filetype_filter = function( $data, $file, $filename, $mimes ) {
			$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
			$allowed = array(
				'webm' => 'audio/webm',
				'ogg'  => 'audio/ogg',
				'oga'  => 'audio/ogg',
				'wav'  => 'audio/wav',
				'mp3'  => 'audio/mpeg',
				'm4a'  => 'audio/mp4',
			);
			if ( isset( $allowed[ $ext ] ) ) {
				return array(
					'ext'             => $ext,
					'type'            => $allowed[ $ext ],
					'proper_filename' => false,
				);
			}
			return $data;
		};
		add_filter( 'wp_check_filetype_and_ext', $check_filetype_filter, 999, 4 );

		$upload_overrides = array(
			'test_form' => false,
			'test_type' => false,
			'mimes'     => array(
				'webm' => 'audio/webm',
				'ogg'  => 'audio/ogg',
				'oga'  => 'audio/ogg',
				'wav'  => 'audio/wav',
				'mp3'  => 'audio/mpeg',
				'm4a'  => 'audio/mp4',
			),
		);

		$movefile = wp_handle_upload( $audio_file, $upload_overrides );
		remove_filter( 'upload_mimes', $upload_mimes_filter, 999 );
		remove_filter( 'wp_check_filetype_and_ext', $check_filetype_filter, 999 );

		$saved    = false;
		$filepath = '';
		$file_url = '';

		if ( $movefile && empty( $movefile['error'] ) && ! empty( $movefile['file'] ) && file_exists( $movefile['file'] ) ) {
			$filepath = $movefile['file'];
			$file_url = $movefile['url'];
			$saved    = true;
		}

		// Fallback: If standard wp_handle_upload failed, save using standard WordPress core API wp_upload_bits (zero forbidden functions)
		if ( ! $saved && ! empty( $audio_file['tmp_name'] ) && file_exists( $audio_file['tmp_name'] ) ) {
			$orig_ext = strtolower( pathinfo( $audio_file['name'] ?? '', PATHINFO_EXTENSION ) );
			$allowed_exts = array( 'webm', 'ogg', 'wav', 'mp3', 'm4a' );
			$ext = in_array( $orig_ext, $allowed_exts, true ) ? $orig_ext : 'webm';
			$target_name = 'rec_' . wp_generate_password( 16, false ) . '.' . $ext;

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$audio_bits = file_get_contents( $audio_file['tmp_name'] );
			if ( false !== $audio_bits && strlen( $audio_bits ) > 0 ) {
				$upload_bits = wp_upload_bits( $target_name, null, $audio_bits );
				if ( empty( $upload_bits['error'] ) && ! empty( $upload_bits['file'] ) && file_exists( $upload_bits['file'] ) ) {
					$filepath = $upload_bits['file'];
					$file_url = $upload_bits['url'];
					$saved    = true;
				}
			}
		}

		if ( ! $saved || empty( $filepath ) || ! file_exists( $filepath ) || filesize( $filepath ) === 0 ) {
			$err_msg = ! empty( $movefile['error'] ) ? $movefile['error'] : __( 'Could not save audio recording file.', 'nik-voice-ticketing-ai' );
			return new WP_Error( 'upload_failed', $err_msg, array( 'status' => 500 ) );
		}

		// 3. Generate Unique Ticket Number
		$user_id = get_current_user_id();
		$user_email = '';
		$username = 'Guest';

		if ( $user_id ) {
			$user_info = get_userdata( $user_id );
			$username = $user_info->display_name ?: $user_info->user_login;
			$user_email = $user_info->user_email;
		} elseif ( ! empty( $guest_email ) ) {
			$user_email = $guest_email;
			$username = substr( $guest_email, 0, strpos( $guest_email, '@' ) );
		}

		$ticket_number = 'VD-' . strtoupper( wp_generate_password( 8, false ) );

		// 4. Create Support Ticket Post
		$post_data = array(
			/* translators: %s: Ticket unique identifier */
			'post_title'   => sprintf( __( 'Ticket %s - Processing', 'nik-voice-ticketing-ai' ), $ticket_number ),
			'post_content' => '',
			'post_status'  => 'publish',
			'post_type'    => 'voicedesk_ticket',
			'post_author'  => $user_id ?: 0,
		);

		$post_id = wp_insert_post( $post_data );

		if ( is_wp_error( $post_id ) ) {
			return new WP_Error( 'post_creation_failed', __( 'Could not create support ticket.', 'nik-voice-ticketing-ai' ), array( 'status' => 500 ) );
		}

		// Secure cryptographic access token for audio streaming
		$access_token = wp_generate_password( 32, false );
		$stream_url   = add_query_arg( 'token', $access_token, rest_url( 'nikvotia/v1/audio/' . $ticket_number ) );

		update_post_meta( $post_id, '_nikvotia_ticket_number', $ticket_number );
		update_post_meta( $post_id, '_nik_ticket_number', $ticket_number );
		update_post_meta( $post_id, '_nikvotia_access_token', $access_token );
		update_post_meta( $post_id, '_nik_access_token', $access_token );
		update_post_meta( $post_id, '_nikvotia_audio_url', $file_url );
		update_post_meta( $post_id, '_nik_audio_url', $file_url );
		update_post_meta( $post_id, '_nikvotia_audio_stream_url', $stream_url );
		update_post_meta( $post_id, '_nik_audio_stream_url', $stream_url );
		update_post_meta( $post_id, '_nikvotia_audio_path', $filepath );
		update_post_meta( $post_id, '_nik_audio_path', $filepath );
		update_post_meta( $post_id, '_nikvotia_page_url', $page_url  );
		update_post_meta( $post_id, '_nikvotia_environment', $clean_environment  );
		update_post_meta( $post_id, '_nikvotia_clicked_elements', $sanitized_clicks  );
		update_post_meta( $post_id, '_nikvotia_username', $username  );
		update_post_meta( $post_id, '_nikvotia_user_email', $user_email  );
		update_post_meta( $post_id, '_nikvotia_status', 'Processing'  );
		update_post_meta( $post_id, '_nikvotia_priority', 'Normal'  );

		// 5. Process AI Pipeline (Safely wrapped so API delays or errors never crash ticket creation)
		$summary = __( 'Voice ticket received.', 'nik-voice-ticketing-ai' );
		$department = __( 'General Support', 'nik-voice-ticketing-ai' );

		try {
			$stt_model = get_option( 'nikvotia_stt_model', 'openai_whisper' );
			$llm_model = get_option( 'nikvotia_llm_model', 'openai_gpt' );

			$transcript = '';

			// Execute STT
			if ( 'openai_whisper' === $stt_model ) {
				$transcript = $this->call_openai_whisper( $filepath );
			} elseif ( 'modulate_ai' === $stt_model ) {
				$transcript = $this->call_modulate_stt( $filepath );
			}

			if ( is_wp_error( $transcript ) ) {
				$transcript_error = $transcript->get_error_message();
				/* translators: %s: Speech transcription error message */
				update_post_meta( $post_id, '_nikvotia_transcript', sprintf( __( 'STT Note: %s', 'nik-voice-ticketing-ai' ), $transcript_error ) );
				update_post_meta( $post_id, '_nikvotia_status', 'Open' );
			} else {
				update_post_meta( $post_id, '_nikvotia_transcript', $transcript );

				// Execute LLM Analysis
				$departments = get_option( 'nikvotia_departments', 'Sales, Technical Support, Billing, General Support' );

				if ( 'openai_gpt' === $llm_model ) {
					$analysis = $this->call_openai_gpt( $transcript, $departments );
					if ( ! is_wp_error( $analysis ) && is_array( $analysis ) ) {
						$summary = $analysis['summary'] ?? $summary;
						$department = $analysis['department'] ?? $department;
					}
				} elseif ( 'modulate_ai' === $llm_model ) {
					$analysis = $this->call_modulate_analysis( $filepath, $transcript, $departments );
					if ( ! is_wp_error( $analysis ) && is_array( $analysis ) ) {
						$summary = $analysis['summary'] ?? $summary;
						$department = $analysis['department'] ?? $department;
					}
				}

				update_post_meta( $post_id, '_nikvotia_summary', $summary );
				update_post_meta( $post_id, '_nikvotia_department', $department );
				update_post_meta( $post_id, '_nikvotia_status', 'Open' );

				// Update post title with department
				wp_update_post( array(
					'ID'         => $post_id,
					/* translators: 1: Ticket unique identifier, 2: Department name */
					'post_title' => sprintf( __( 'Ticket %1$s - %2$s', 'nik-voice-ticketing-ai' ), $ticket_number, $department ),
				) );
			}
		} catch ( Throwable $e ) {
			// Catch any API or parsing exception gracefully
			update_post_meta( $post_id, '_nikvotia_status', 'Open' );
		}

		// 6. Send Email Confirmation (Safely wrapped)
		try {
			if ( ! empty( $user_email ) ) {
				self::send_ticket_confirmation_email( $user_email, $username, $ticket_number, $department, $summary, $page_url );
			}

			// Also notify site admin
			$admin_email = get_option( 'admin_email' );
			if ( ! empty( $admin_email ) && $admin_email !== $user_email ) {
				self::send_admin_new_ticket_email( $admin_email, $ticket_number, $username, $department, $summary, $post_id );
			}
		} catch ( Throwable $e ) {
			// Email delivery failure should not block user success response
		}

		$portal_page_id = get_option( 'nikvotia_portal_page_id', 0   );
		$portal_url = $portal_page_id ? get_permalink( $portal_page_id ) : '';
		if ( empty( $portal_url ) ) {
			if ( class_exists( 'WooCommerce' ) && function_exists( 'wc_get_account_endpoint_url' ) ) {
				$portal_url = wc_get_account_endpoint_url( 'voicedesk-tickets' );
			} else {
				$pages = get_posts( array(
					'post_type'      => 'page',
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					's'              => 'nikvotia_tickets',
					'fields'         => 'ids',
				) );
				if ( ! empty( $pages ) ) {
					$portal_url = get_permalink( $pages[0] );
				}
			}
		}
		$ticket_url = ! empty( $portal_url ) ? add_query_arg( 'ticket', $ticket_number, $portal_url ) : '';

		// Clean output buffer to ensure pure JSON response without PHP notices
		if ( ob_get_length() ) {
			ob_clean();
		}

		return rest_ensure_response( array(
			'success'       => true,
			'ticket_number' => $ticket_number,
			'department'    => $department,
			'summary'       => $summary,
			'user_email'    => $user_email,
			'portal_url'    => $portal_url,
			'ticket_url'    => $ticket_url,
			'message'       => __( 'Ticket submitted successfully!', 'nik-voice-ticketing-ai' ),
		) );
	}

	/**
	 * Secure permission check for audio streaming REST endpoint.
	 *
	 * Allows access if:
	 * 1. Current user can edit the ticket or has manage_options capability.
	 * 2. Current user is the logged-in author of the ticket.
	 * 3. Request contains the valid cryptographic access token matching _nikvotia_access_token.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return true|WP_Error
	 */
	public function check_audio_permissions( WP_REST_Request $request ) {
		$ticket_number = sanitize_text_field( $request->get_param( 'ticket_number' ) );
		if ( empty( $ticket_number ) ) {
			return new WP_Error( 'rest_forbidden', __( 'Ticket number required.', 'nik-voice-ticketing-ai' ), array( 'status' => 403 ) );
		}

		$args = array(
			'post_type'      => 'voicedesk_ticket',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Indexed ticket lookup by unique ticket ID.
			'meta_query'     => array(
				'relation' => 'OR',
				array(
					'key'   => '_nikvotia_ticket_number',
					'value' => $ticket_number,
				),
				array(
					'key'   => '_nik_ticket_number',
					'value' => $ticket_number,
				),
			),
		);
		$posts = get_posts( $args );
		if ( empty( $posts ) ) {
			return new WP_Error( 'rest_ticket_not_found', __( 'Ticket not found.', 'nik-voice-ticketing-ai' ), array( 'status' => 404 ) );
		}

		$post = $posts[0];

		// 1. Administrators or agents with edit capabilities
		if ( current_user_can( 'edit_post', $post->ID ) || current_user_can( 'manage_options' ) ) {
			return true;
		}

		// 2. Ticket author if logged in
		$current_user_id = get_current_user_id();
		if ( $current_user_id > 0 && (int) $post->post_author === $current_user_id ) {
			return true;
		}

		// 3. Cryptographic token-based authorization (for guest customers or verified direct links)
		$token = sanitize_text_field( $request->get_param( 'token' ) );
		$stored_token = get_post_meta( $post->ID, '_nikvotia_access_token', true ) ?: get_post_meta( $post->ID, '_nik_access_token', true );
		if ( ! empty( $stored_token ) && ! empty( $token ) && hash_equals( (string) $stored_token, (string) $token ) ) {
			return true;
		}

		return new WP_Error( 'rest_forbidden', __( 'You do not have permission to access this recording.', 'nik-voice-ticketing-ai' ), array( 'status' => 403 ) );
	}

	/**
	 * Securely stream or proxy audio files.
	 */
	public function stream_audio( WP_REST_Request $request ) {
		$ticket_number = sanitize_text_field( $request->get_param( 'ticket_number' ) );

		$args = array(
			'post_type'      => 'voicedesk_ticket',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Indexed ticket lookup by unique ticket ID.
			'meta_query'     => array(
				'relation' => 'OR',
				array(
					'key'   => '_nikvotia_ticket_number',
					'value' => $ticket_number,
				),
				array(
					'key'   => '_nik_ticket_number',
					'value' => $ticket_number,
				),
			),
		);
		$posts = get_posts( $args );
		if ( empty( $posts ) ) {
			return new WP_Error( 'not_found', __( 'Ticket not found.', 'nik-voice-ticketing-ai' ), array( 'status' => 404 ) );
		}

		$post = $posts[0];
		$filepath = get_post_meta( $post->ID, '_nikvotia_audio_path', true ) ?: get_post_meta( $post->ID, '_nik_audio_path', true );

		$upload_dir = wp_upload_dir();
		$base_upload_dir = wp_normalize_path( $upload_dir['basedir'] );
		$normalized_file = wp_normalize_path( (string) $filepath );

		if ( empty( $filepath ) || ! file_exists( $filepath ) || strpos( $normalized_file, $base_upload_dir ) !== 0 ) {
			return new WP_Error( 'file_not_found', __( 'Audio file not found on server.', 'nik-voice-ticketing-ai' ), array( 'status' => 404 ) );
		}

		$filesize = filesize( $filepath );
		$mime_type = 'audio/webm';

		header( 'Content-Type: ' . $mime_type );
		header( 'Content-Length: ' . $filesize );
		header( 'Accept-Ranges: bytes' );
		header( 'Cache-Control: public, max-age=31536000' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Direct output streaming of audio file.
		readfile( $filepath );
		exit;
	}

	/**
	 * Handle user reply from the frontend User Panel.
	 */
	public function handle_user_reply( WP_REST_Request $request ) {
		$current_user_id = get_current_user_id();
		$ticket_id = intval( $request->get_param( 'ticket_id' ) );
		$message = sanitize_textarea_field( $request->get_param( 'message' ) );

		if ( empty( $message ) || empty( $ticket_id ) ) {
			return new WP_Error( 'missing_data', __( 'Reply message is required.', 'nik-voice-ticketing-ai' ), array( 'status' => 400 ) );
		}

		$post = get_post( $ticket_id );
		if ( ! $post || 'voicedesk_ticket' !== $post->post_type ) {
			return new WP_Error( 'not_found', __( 'Ticket not found.', 'nik-voice-ticketing-ai' ), array( 'status' => 404 ) );
		}

		// Verify ownership
		if ( (int) $post->post_author !== $current_user_id && ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'forbidden', __( 'You do not have permission to reply to this ticket.', 'nik-voice-ticketing-ai' ), array( 'status' => 403 ) );
		}

		$user_info = get_userdata( $current_user_id );
		$author_name = $user_info ? ( $user_info->display_name ?: $user_info->user_login ) : 'User';

		$replies = get_post_meta( $ticket_id, '_nikvotia_replies', true );
		if ( ! is_array( $replies ) ) {
			$replies = array();
		}

		$replies[] = array(
			'author'  => $author_name,
			'role'    => current_user_can( 'manage_options' ) ? 'staff' : 'customer',
			'message' => $message,
			'date'    => current_time( 'mysql' ),
		);

		update_post_meta( $ticket_id, '_nikvotia_replies', $replies );
		update_post_meta( $ticket_id, '_nik_replies', $replies );
		update_post_meta( $ticket_id, '_nikvotia_status', current_user_can( 'manage_options' ) ? 'Replied' : 'Customer Replied' );
		update_post_meta( $ticket_id, '_nik_status', current_user_can( 'manage_options' ) ? 'Replied' : 'Customer Replied' );

		// Notify site admin if customer replied
		if ( ! current_user_can( 'manage_options' ) ) {
			$admin_email = get_option( 'admin_email' );
			$ticket_number = get_post_meta( $ticket_id, '_nikvotia_ticket_number', true );
			/* translators: 1: Ticket identifier, 2: Author display name */
			$subject = sprintf( __( '[Ticket #%1$s] New Customer Reply from %2$s', 'nik-voice-ticketing-ai' ), $ticket_number, $author_name );
			$body = sprintf(
				/* translators: 1: Author display name, 2: Ticket identifier, 3: Customer reply text, 4: Admin ticket URL */
				__( "Customer %1\$s posted a new reply on Ticket #%2\$s:\n\n\"%3\$s\"\n\nManage ticket: %4\$s", 'nik-voice-ticketing-ai' ),
				$author_name,
				$ticket_number,
				$message,
				admin_url( 'post.php?post=' . $ticket_id . '&action=edit' )
			);
			wp_mail( $admin_email, $subject, $body );
		}

		return rest_ensure_response( array(
			'success' => true,
			'message' => __( 'Reply posted successfully.', 'nik-voice-ticketing-ai' ),
			'replies' => $replies,
		) );
	}

	/**
	 * Call OpenAI Whisper STT API.
	 */
	private function call_openai_whisper( $filepath ) {
		$api_key = get_option( 'nikvotia_openai_key', ''   );
		if ( empty( $api_key ) ) {
			return new WP_Error( 'missing_key', __( 'OpenAI API key is missing in VoiceDesk Settings.', 'nik-voice-ticketing-ai' ) );
		}

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
			'timeout' => 25,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code ) {
			$err = $body['error']['message'] ?? ( 'OpenAI HTTP Error ' . $code );
			return new WP_Error( 'openai_stt_error', $err );
		}

		return $body['text'] ?? '';
	}

	/**
	 * Call Modulate.ai Velma-2 STT Batch API.
	 * Specs from https://docs.modulate.ai/api-reference/stt/batch-multilingual-vfast
	 */
	private function call_modulate_stt( $filepath ) {
		$api_key = get_option( 'nikvotia_modulate_key', ''   );
		if ( empty( $api_key ) ) {
			return new WP_Error( 'missing_key', __( 'Modulate API key is missing in VoiceDesk Settings.', 'nik-voice-ticketing-ai' ) );
		}

		$boundary = wp_generate_password( 24, false );
		$headers  = array(
			'X-API-Key'    => trim( $api_key ),
			'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
		);

		// According to Modulate OpenAPI spec:
		// Field name MUST be "upload_file"
		$payload = '--' . $boundary . "\r\n";
		$payload .= 'Content-Disposition: form-data; name="upload_file"; filename="' . basename( $filepath ) . '"' . "\r\n";
		$payload .= 'Content-Type: audio/webm' . "\r\n\r\n";
		$payload .= file_get_contents( $filepath ) . "\r\n";
		$payload .= '--' . $boundary . "\r\n";
		$payload .= 'Content-Disposition: form-data; name="language"' . "\r\n\r\nen\r\n";
		$payload .= '--' . $boundary . "--\r\n";

		$url = 'https://platform.modulate.ai/api/velma-2-stt-batch-multilingual-vfast';
		$response = wp_remote_post( $url, array(
			'headers' => $headers,
			'body'    => $payload,
			'timeout' => 25,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$raw_body = wp_remote_retrieve_body( $response );
		$body = json_decode( $raw_body, true );

		if ( 200 !== $code ) {
			$detail = $body['detail'] ?? '';
			if ( is_array( $detail ) ) {
				$detail = wp_json_encode( $detail );
			}
			return new WP_Error( 'modulate_stt_error', $detail ?: ( 'Modulate API Error HTTP ' . $code ) );
		}

		if ( isset( $body['text'] ) ) {
			return trim( $body['text'] );
		}

		return '';
	}

	/**
	 * Call OpenAI GPT for Summarization & Department Categorization.
	 */
	private function call_openai_gpt( $transcript, $departments ) {
		$api_key = get_option( 'nikvotia_openai_key', ''   );
		if ( empty( $api_key ) ) {
			return new WP_Error( 'missing_key', 'OpenAI API key missing.' );
		}

		$system_prompt = "You are an expert AI support ticket triage assistant. Analyze the user audio transcript and provide:
1. 'summary': A clear, concise 2-3 sentence executive summary of the customer's problem or inquiry.
2. 'department': Pick the single best department from this list: {$departments}. If none match, choose 'General Support'.
Return strictly valid JSON with keys 'summary' and 'department'.";

		$body = array(
			'model'           => 'gpt-4o-mini',
			'messages'        => array(
				array( 'role' => 'system', 'content' => $system_prompt ),
				array( 'role' => 'user', 'content' => $transcript ),
			),
			'response_format' => array( 'type' => 'json_object' ),
		);

		$response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $body ),
			'timeout' => 20,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$res_body = json_decode( wp_remote_retrieve_body( $response ), true );
		$content = $res_body['choices'][0]['message']['content'] ?? '{}';
		return json_decode( $content, true );
	}

	/**
	 * Call Modulate.ai Velma-2 Batch Analysis API.
	 * Specs from https://docs.modulate.ai/api-reference/velma/batch
	 */
	private function call_modulate_analysis( $filepath, $transcript, $departments ) {
		$api_key = get_option( 'nikvotia_modulate_key', ''   );
		if ( empty( $api_key ) ) {
			return new WP_Error( 'missing_key', 'Modulate API key missing.' );
		}

		$boundary = wp_generate_password( 24, false );
		$headers  = array(
			'X-API-Key'    => trim( $api_key ),
			'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
		);

		$payload = '--' . $boundary . "\r\n";
		$payload .= 'Content-Disposition: form-data; name="upload_file"; filename="' . basename( $filepath ) . '"' . "\r\n";
		$payload .= 'Content-Type: audio/webm' . "\r\n\r\n";
		$payload .= file_get_contents( $filepath ) . "\r\n";
		$payload .= '--' . $boundary . "\r\n";
		$payload .= 'Content-Disposition: form-data; name="config"' . "\r\n\r\ndefault\r\n";
		$payload .= '--' . $boundary . "--\r\n";

		$url = 'https://platform.modulate.ai/api/velma-2-batch';
		$response = wp_remote_post( $url, array(
			'headers' => $headers,
			'body'    => $payload,
			'timeout' => 25,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 === $code ) {
			$summary = $body['summary'] ?? ( 'Summary: ' . substr( $transcript, 0, 100 ) . '...' );
			$topics = $body['topics'] ?? array();
			$department = ! empty( $topics[0] ) ? ucwords( $topics[0] ) : 'General Support';
			return array(
				'summary'    => $summary,
				'department' => $department,
			);
		}

		// Fallback
		return array(
			'summary'    => ! empty( $transcript ) ? substr( $transcript, 0, 120 ) . '...' : 'Voice ticket received.',
			'department' => 'General Support',
		);
	}

	/**
	 * Cleanly parse User Agent into strictly:
	 * OS name + version | Browser name + version
	 */
	public static function parse_user_agent( $ua ) {
		if ( empty( $ua ) ) {
			return 'Unknown OS | Unknown Browser';
		}

		// 1. Detect OS
		$os = 'Unknown OS';
		if ( preg_match( '/windows nt 10\.0/i', $ua ) ) {
			$os = 'Windows 10 / 11';
		} elseif ( preg_match( '/windows nt 6\.3/i', $ua ) ) {
			$os = 'Windows 8.1';
		} elseif ( preg_match( '/windows nt 6\.2/i', $ua ) ) {
			$os = 'Windows 8';
		} elseif ( preg_match( '/windows nt 6\.1/i', $ua ) ) {
			$os = 'Windows 7';
		} elseif ( preg_match( '/macintosh|mac os x/i', $ua ) ) {
			if ( preg_match( '/mac os x ([\d_]+)/i', $ua, $matches ) ) {
				$os = 'macOS ' . str_replace( '_', '.', $matches[1] );
			} else {
				$os = 'macOS';
			}
		} elseif ( preg_match( '/android ([\d\.]+)/i', $ua, $matches ) ) {
			$os = 'Android ' . $matches[1];
		} elseif ( preg_match( '/iphone|ipad|ipod/i', $ua ) ) {
			if ( preg_match( '/os ([\d_]+)/i', $ua, $matches ) ) {
				$os = 'iOS ' . str_replace( '_', '.', $matches[1] );
			} else {
				$os = 'iOS';
			}
		} elseif ( preg_match( '/cros/i', $ua ) ) {
			$os = 'ChromeOS';
		} elseif ( preg_match( '/linux/i', $ua ) ) {
			$os = 'Linux';
		}

		// 2. Detect Browser
		$browser = 'Unknown Browser';
		if ( preg_match( '/edg\/([\d\.]+)/i', $ua, $matches ) ) {
			$browser = 'Edge ' . $matches[1];
		} elseif ( preg_match( '/samsungbrowser\/([\d\.]+)/i', $ua, $matches ) ) {
			$browser = 'Samsung Internet ' . $matches[1];
		} elseif ( preg_match( '/opr\/([\d\.]+)/i', $ua, $matches ) ) {
			$browser = 'Opera ' . $matches[1];
		} elseif ( preg_match( '/chrome\/([\d\.]+)/i', $ua, $matches ) ) {
			$browser = 'Chrome ' . $matches[1];
		} elseif ( preg_match( '/firefox\/([\d\.]+)/i', $ua, $matches ) ) {
			$browser = 'Firefox ' . $matches[1];
		} elseif ( preg_match( '/version\/([\d\.]+).*safari/i', $ua, $matches ) ) {
			$browser = 'Safari ' . $matches[1];
		}

		return $os . ' | ' . $browser;
	}

	/**
	 * Send high-converting, professional HTML ticket confirmation email to customer.
	 */
	public static function send_ticket_confirmation_email( $to_email, $username, $ticket_number, $department, $summary, $page_url ) {
		$site_name = get_bloginfo( 'name' );
		/* translators: 1: Site name, 2: Ticket unique identifier */
		$subject = sprintf( __( '[%1$s] Ticket Confirmation - #%2$s', 'nik-voice-ticketing-ai' ), $site_name, $ticket_number );

		// Portal URL
		$portal_page_id = get_option( 'nikvotia_portal_page_id', 0   );
		$portal_url = $portal_page_id ? get_permalink( $portal_page_id ) : home_url();

		$html = '
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="utf-8">
		</head>
		<body style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; background-color: #f4f5f7; margin: 0; padding: 20px; color: #2d3748;">
			<div style="max-width: 580px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;">
				<div style="background: #0f172a; color: #ffffff; padding: 24px; text-align: center;">
					<h1 style="margin: 0; font-size: 20px; font-weight: 600; color: #ffffff;">' . esc_html( $site_name ) . ' ' . esc_html__( 'Support', 'nik-voice-ticketing-ai' ) . '</h1>
				</div>
				<div style="padding: 24px; line-height: 1.6;">
					<p>' . sprintf(
						/* translators: %s: Customer user name */
						esc_html__( 'Hello %s,', 'nik-voice-ticketing-ai' ),
						esc_html( $username )
					) . '</p>
					<p>' . esc_html__( 'Your voice ticket has been received and processed by our AI system. Here are your ticket details:', 'nik-voice-ticketing-ai' ) . '</p>
					
					<div style="text-align: center;">
						<div style="display: inline-block; background: #e0f2fe; color: #0369a1; font-weight: 700; font-size: 16px; padding: 6px 14px; border-radius: 6px; letter-spacing: 0.5px; margin: 10px 0;">#' . esc_html( $ticket_number ) . '</div>
					</div>

					<table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
						<tr>
							<td style="padding: 10px 12px; border-bottom: 1px solid #edf2f7; font-size: 14px; font-weight: 600; color: #64748b; width: 35%;">' . esc_html__( 'Status', 'nik-voice-ticketing-ai' ) . '</td>
							<td style="padding: 10px 12px; border-bottom: 1px solid #edf2f7; font-size: 14px;"><span style="color: #059669; font-weight: 600;">' . esc_html__( 'Open', 'nik-voice-ticketing-ai' ) . '</span></td>
						</tr>
						<tr>
							<td style="padding: 10px 12px; border-bottom: 1px solid #edf2f7; font-size: 14px; font-weight: 600; color: #64748b; width: 35%;">' . esc_html__( 'Assigned Department', 'nik-voice-ticketing-ai' ) . '</td>
							<td style="padding: 10px 12px; border-bottom: 1px solid #edf2f7; font-size: 14px;">' . esc_html( $department ) . '</td>
						</tr>
						<tr>
							<td style="padding: 10px 12px; border-bottom: 1px solid #edf2f7; font-size: 14px; font-weight: 600; color: #64748b; width: 35%;">' . esc_html__( 'Page Referenced', 'nik-voice-ticketing-ai' ) . '</td>
							<td style="padding: 10px 12px; border-bottom: 1px solid #edf2f7; font-size: 14px;"><a href="' . esc_url( $page_url ) . '" target="_blank">' . esc_html( $page_url ) . '</a></td>
						</tr>
						<tr>
							<td style="padding: 10px 12px; border-bottom: 1px solid #edf2f7; font-size: 14px; font-weight: 600; color: #64748b; width: 35%;">' . esc_html__( 'AI Summary', 'nik-voice-ticketing-ai' ) . '</td>
							<td style="padding: 10px 12px; border-bottom: 1px solid #edf2f7; font-size: 14px;">' . esc_html( $summary ) . '</td>
						</tr>
					</table>

					<p>' . esc_html__( 'Our team is reviewing your ticket and will update you shortly. You can track your ticket and replies directly in your account:', 'nik-voice-ticketing-ai' ) . '</p>
					
					<div style="text-align: center;">
						<a href="' . esc_url( $portal_url ) . '" style="display: inline-block; background: #2563eb; color: #ffffff !important; text-decoration: none; padding: 12px 24px; border-radius: 6px; font-weight: 600; font-size: 14px; margin-top: 15px;">' . esc_html__( 'View My Tickets', 'nik-voice-ticketing-ai' ) . '</a>
					</div>
				</div>
			</div>
			<div style="text-align: center; margin-top: 20px; font-size: 12px; color: #94a3b8;">
				&copy; ' . wp_date( 'Y' ) . ' ' . esc_html( $site_name ) . '. ' . esc_html__( 'All rights reserved.', 'nik-voice-ticketing-ai' ) . '
			</div>
		</body>
		</html>';

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $site_name . ' <' . ( get_option( 'admin_email' ) ) . '>',
		);

		return wp_mail( $to_email, $subject, $html, $headers );
	}

	/**
	 * Send alert email to site admin.
	 */
	public static function send_admin_new_ticket_email( $admin_email, $ticket_number, $username, $department, $summary, $post_id ) {
		$site_name = get_bloginfo( 'name' );
		/* translators: 1: Ticket identifier, 2: Reporter username, 3: Department name */
		$subject = sprintf( __( '[New Ticket #%1$s] %2$s - %3$s', 'nik-voice-ticketing-ai' ), $ticket_number, $username, $department );
		$admin_url = admin_url( 'post.php?post=' . $post_id . '&action=edit' );

		$message = sprintf(
			/* translators: 1: Site name, 2: Ticket number, 3: Reporter username, 4: Department name, 5: AI summary text, 6: Ticket admin URL */
			__( 'A new voice ticket has been submitted on %1$s:

Ticket Number: %2$s
Reporter: %3$s
Department: %4$s

AI Summary:
%5$s

Manage Ticket:
%6$s', 'nik-voice-ticketing-ai' ),
			$site_name,
			$ticket_number,
			$username,
			$department,
			$summary,
			$admin_url
		);

		return wp_mail( $admin_email, $subject, $message );
	}
}
