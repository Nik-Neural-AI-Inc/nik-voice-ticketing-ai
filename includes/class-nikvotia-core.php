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
		require_once NIKVOTIA_DIR . 'includes/class-nikvotia-logger.php';
		require_once NIKVOTIA_DIR . 'includes/class-nikvotia-telemetry.php';
		require_once NIKVOTIA_DIR . 'includes/class-nikvotia-cloud.php';
		require_once NIKVOTIA_DIR . 'includes/class-nikvotia-settings.php';
		require_once NIKVOTIA_DIR . 'includes/class-nikvotia-cpt.php';
		require_once NIKVOTIA_DIR . 'includes/class-nikvotia-api.php';
		require_once NIKVOTIA_DIR . 'includes/class-nikvotia-frontend.php';
	}

	/**
	 * Instantiates the required classes.
	 */
	private function instantiate() {
		Nikvotia_Logger::init();

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

		// Register core WordPress Privacy Exporters and Erasers (Supports customer data export and erasure requests)
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_privacy_exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_privacy_erasers' ) );
	}

	/**
	 * Register VoiceDesk ticket personal data exporter.
	 *
	 * @param array $exporters Registered exporters.
	 * @return array
	 */
	public static function register_privacy_exporters( $exporters ) {
		$exporters['nikvotia-tickets'] = array(
			'exporter_friendly_name' => __( 'Nik Voice Support Tickets', 'nik-voice-ticketing-ai' ),
			'callback'               => array( __CLASS__, 'export_personal_data' ),
		);
		return $exporters;
	}

	/**
	 * Register VoiceDesk ticket personal data eraser.
	 *
	 * @param array $erasers Registered erasers.
	 * @return array
	 */
	public static function register_privacy_erasers( $erasers ) {
		$erasers['nikvotia-tickets'] = array(
			'eraser_friendly_name' => __( 'Nik Voice Support Tickets', 'nik-voice-ticketing-ai' ),
			'callback'             => array( __CLASS__, 'erase_personal_data' ),
		);
		return $erasers;
	}

	/**
	 * Export customer personal data for VoiceDesk tickets.
	 *
	 * @param string $email_address Email address to export.
	 * @param int    $page          Pagination page.
	 * @return array
	 */
	public static function export_personal_data( $email_address, $page = 1 ) {
		$export_items = array();

		$posts = get_posts( array(
			'post_type'      => 'voicedesk_ticket',
			'post_status'    => 'any',
			'posts_per_page' => 50,
			'paged'          => $page,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required to retrieve personal ticket records by customer email for Core Privacy Exporter.
			'meta_query'     => array(
				array(
					'key'     => '_nikvotia_user_email',
					'value'   => $email_address,
					'compare' => '=',
				),
			),
		) );

		foreach ( $posts as $post ) {
			$ticket_number = get_post_meta( $post->ID, '_nikvotia_ticket_number', true );
			$dept          = get_post_meta( $post->ID, '_nikvotia_department', true ) ?: 'General';
			$status        = get_post_meta( $post->ID, '_nikvotia_status', true ) ?: 'Open';
			$summary       = get_post_meta( $post->ID, '_nikvotia_summary', true );
			$transcript    = get_post_meta( $post->ID, '_nikvotia_transcript', true );

			$export_items[] = array(
				'group_id'    => 'nikvotia_tickets',
				'group_label' => __( 'Voice Support Tickets', 'nik-voice-ticketing-ai' ),
				'item_id'     => 'voicedesk-ticket-' . $post->ID,
				'data'        => array(
					array( 'name' => __( 'Ticket Number', 'nik-voice-ticketing-ai' ), 'value' => $ticket_number ),
					array( 'name' => __( 'Date Created', 'nik-voice-ticketing-ai' ), 'value' => get_the_date( 'Y-m-d H:i:s', $post ) ),
					array( 'name' => __( 'Department', 'nik-voice-ticketing-ai' ), 'value' => $dept ),
					array( 'name' => __( 'Status', 'nik-voice-ticketing-ai' ), 'value' => $status ),
					array( 'name' => __( 'AI Summary', 'nik-voice-ticketing-ai' ), 'value' => $summary ),
					array( 'name' => __( 'Speech Transcript', 'nik-voice-ticketing-ai' ), 'value' => $transcript ),
				),
			);
		}

		return array(
			'data' => $export_items,
			'done' => count( $posts ) < 50,
		);
	}

	/**
	 * Erase customer personal data for VoiceDesk tickets.
	 *
	 * @param string $email_address Email address to erase.
	 * @param int    $page          Pagination page.
	 * @return array
	 */
	public static function erase_personal_data( $email_address, $page = 1 ) {
		$items_removed  = 0;
		$items_retained = 0;
		$messages       = array();

		$posts = get_posts( array(
			'post_type'      => 'voicedesk_ticket',
			'post_status'    => 'any',
			'posts_per_page' => 50,
			'paged'          => $page,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required to retrieve personal ticket records by customer email for Core Privacy Eraser.
			'meta_query'     => array(
				array(
					'key'     => '_nikvotia_user_email',
					'value'   => $email_address,
					'compare' => '=',
				),
			),
		) );

		foreach ( $posts as $post ) {
			$audio_path = get_post_meta( $post->ID, '_nikvotia_audio_path', true );
			if ( ! empty( $audio_path ) && file_exists( $audio_path ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				@unlink( $audio_path );
			}

			// Permanently delete the ticket post and its metadata
			$deleted = wp_delete_post( $post->ID, true );
			if ( $deleted ) {
				$items_removed++;
			} else {
				$items_retained++;
				$messages[] = sprintf(
					/* translators: %d: Ticket post ID */
					__( 'Could not remove ticket #%d.', 'nik-voice-ticketing-ai' ),
					$post->ID
				);
			}
		}

		return array(
			'items_removed'  => $items_removed,
			'items_retained' => $items_retained,
			'messages'       => $messages,
			'done'           => count( $posts ) < 50,
		);
	}
}
