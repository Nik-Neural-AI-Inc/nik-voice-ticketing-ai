<?php
/**
 * Frontend class for asset injection, modern recording dock, modal, and customer User Panel.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nik_VoiceDesk_Frontend {

	public function init() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_footer', array( $this, 'inject_ui' ) );
		add_action( 'wp_head', array( $this, 'inject_custom_css' ) );
		add_shortcode( 'nik_voicedesk_tickets', array( $this, 'render_user_panel_shortcode' ) );

		// WooCommerce "My Account" Endpoint & Dashboard Integration
		add_action( 'init', array( $this, 'wc_add_endpoint' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'wc_add_menu_item' ) );
		add_action( 'woocommerce_account_voicedesk-tickets_endpoint', array( $this, 'wc_endpoint_content' ) );
		add_action( 'woocommerce_account_dashboard', array( $this, 'wc_account_dashboard_ticket_btn' ) );
		add_action( 'show_user_profile', array( $this, 'render_user_profile_ticket_section' ) );
		add_action( 'edit_user_profile', array( $this, 'render_user_profile_ticket_section' ) );
	}

	private function is_visible() {
		// 1. User Status Check
		$user_status = get_option( 'nik_voicedesk_visibility_user_status', 'all' );
		if ( 'logged_in' === $user_status && ! is_user_logged_in() ) {
			return false;
		}
		if ( 'not_logged_in' === $user_status && is_user_logged_in() ) {
			return false;
		}
		if ( 'specific' === $user_status ) {
			$specific_users = get_option( 'nik_voicedesk_visibility_specific_users', '' );
			$allowed_ids = array_map( 'trim', explode( ',', $specific_users ) );
			$current_user_id = get_current_user_id();
			if ( ! in_array( (string) $current_user_id, $allowed_ids, true ) ) {
				return false;
			}
		}

		// 2. Post Type Check
		$allowed_post_types = get_option( 'nik_voicedesk_visibility_post_types', array() );
		if ( ! empty( $allowed_post_types ) ) {
			if ( ! is_singular( $allowed_post_types ) && ! is_front_page() && ! is_home() ) {
				return false;
			}
			if ( ( is_front_page() || is_home() ) && ! in_array( 'page', (array) $allowed_post_types, true ) ) {
				return false;
			}
		}

		return true;
	}

	public function enqueue_scripts() {
		if ( ! $this->is_visible() ) {
			return;
		}

		wp_enqueue_style( 'nik-voicedesk-frontend', NIK_VOICEDESK_URL . 'assets/css/frontend.css', array(), NIK_VOICEDESK_VERSION );
		wp_enqueue_script( 'nik-voicedesk-frontend', NIK_VOICEDESK_URL . 'assets/js/frontend.js', array(), NIK_VOICEDESK_VERSION, true );

		$portal_page_id = get_option( 'nik_voicedesk_portal_page_id', 0 );
		$portal_url = $portal_page_id ? get_permalink( $portal_page_id ) : '';
		if ( empty( $portal_url ) ) {
			if ( class_exists( 'WooCommerce' ) && function_exists( 'wc_get_account_endpoint_url' ) ) {
				$portal_url = wc_get_account_endpoint_url( 'voicedesk-tickets' );
			} else {
				global $wpdb;
				$found_id = $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish' AND post_content LIKE '%nik_voicedesk_tickets%' LIMIT 1" );
				if ( $found_id ) {
					$portal_url = get_permalink( $found_id );
				}
			}
		}

		wp_localize_script( 'nik-voicedesk-frontend', 'nikVoiceDeskData', array(
			'restUrl'      => esc_url_raw( rest_url( 'nik-voicedesk/v1/submit' ) ),
			'nonce'        => wp_create_nonce( 'wp_rest' ),
			'portalUrl'    => esc_url( $portal_url ),
			'isEnterprise' => Nik_VoiceDesk_Settings::is_enterprise(),
		) );
	}

	public function inject_custom_css() {
		if ( ! $this->is_visible() ) {
			return;
		}

		$btn_color = get_option( 'nik_voicedesk_btn_color', '#ffd700' );
		$icon_color = get_option( 'nik_voicedesk_icon_color', '#333333' );
		
		echo "<style id='nik-vd-dynamic-css'>
			:root {
				--nik-vd-btn-bg: {$btn_color} !important;
				--nik-vd-icon-color: {$icon_color} !important;
			}
		</style>";
	}

	/**
	 * Inject modern floating dock, click pins, and permanent success modal.
	 */
	public function inject_ui() {
		if ( ! $this->is_visible() ) {
			return;
		}
		
		$is_enterprise = Nik_VoiceDesk_Settings::is_enterprise();
		$show_attribution = '1' === (string) get_option( 'nik_voicedesk_show_attribution', '0' );
		$custom_icon = get_option( 'nik_voicedesk_custom_icon', '' );
		$portal_page_id = get_option( 'nik_voicedesk_portal_page_id', 0 );
		$portal_url = $portal_page_id ? get_permalink( $portal_page_id ) : '';
		if ( empty( $portal_url ) ) {
			if ( class_exists( 'WooCommerce' ) && function_exists( 'wc_get_account_endpoint_url' ) ) {
				$portal_url = wc_get_account_endpoint_url( 'voicedesk-tickets' );
			} else {
				global $wpdb;
				$found_id = $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish' AND post_content LIKE '%nik_voicedesk_tickets%' LIMIT 1" );
				if ( $found_id ) {
					$portal_url = get_permalink( $found_id );
				}
			}
		}

		if ( empty( $custom_icon ) ) {
			$custom_icon = '<svg viewBox="0 0 24 24" width="26" height="26" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"></path><path d="M19 10v2a7 7 0 0 1-14 0v-2"></path><line x1="12" y1="19" x2="12" y2="23"></line><line x1="8" y1="23" x2="16" y2="23"></line></svg>';
		}
		?>
		<div id="nik-vd-root">
			<!-- Floating Mic Button & Compact Attribution Wrap (Always Upper / Above) -->
			<div id="nik-vd-trigger-wrap">
				<button id="nik-vd-mic-btn" aria-label="<?php esc_attr_e( 'Record Voice Ticket', 'nik-voicedesk' ); ?>" title="<?php esc_attr_e( 'Click to record a voice support ticket', 'nik-voicedesk' ); ?>">
					<?php echo $custom_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</button>
				
				<?php if ( $show_attribution && ! $is_enterprise ) : ?>
					<a id="nik-vd-branding-btn" class="nik-vd-branding" href="https://nikneural.ca/voicedesk.php" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Powered by', 'nik-voicedesk' ); ?>
						<img src="https://nikneural.ca/fav/favicon-light-32.png" alt="Nik Neural AI Inc." class="nik-vd-company-logo" width="14" height="14" />
					</a>
				<?php endif; ?>
			</div>

			<!-- Floating Modern Recording Dock -->
			<div id="nik-vd-dock" class="nik-vd-hidden">
				<div class="nik-vd-rec-indicator">
					<div class="nik-vd-rec-dot"></div>
					<span id="nik-vd-timer">00:00</span>
				</div>

				<div class="nik-vd-waveform">
					<span></span><span></span><span></span><span></span><span></span>
				</div>

				<div id="nik-vd-click-counter" class="nik-vd-hidden">0 Clicks Logged</div>

				<button id="nik-vd-stop-btn" class="nik-vd-dock-btn">
					✓ <?php esc_html_e( 'Finish & Submit', 'nik-voicedesk' ); ?>
				</button>

				<button id="nik-vd-cancel-btn" class="nik-vd-dock-btn" title="<?php esc_attr_e( 'Discard recording', 'nik-voicedesk' ); ?>">
					✕ <?php esc_html_e( 'Cancel', 'nik-voicedesk' ); ?>
				</button>
			</div>

			<?php if ( $show_attribution && ! $is_enterprise ) : ?>
				<!-- Freemium Attribution Bar (Free Tier) -->
				<div id="nik-vd-attribution-bar" class="nik-vd-attribution-bar nik-vd-hidden">
					<?php esc_html_e( 'Voice Support Powered by', 'nik-voicedesk' ); ?> 
					<a href="https://nikneural.ca/voicedesk.php" target="_blank" rel="noopener noreferrer">
						Nik Neural AI Inc.
						<img src="https://nikneural.ca/fav/favicon-light-32.png" alt="Nik Neural AI Inc." class="nik-vd-company-logo nik-vd-logo-light" width="14" height="14" />
					</a>
				</div>
			<?php endif; ?>

			<!-- Floating Processing Spinner -->
			<div id="nik-vd-processing" class="nik-vd-hidden">
				<div class="nik-vd-spinner"></div>
				<span><?php esc_html_e( 'Analyzing voice with AI...', 'nik-voicedesk' ); ?></span>
			</div>

			<!-- Dedicated Success Confirmation Modal (Doesn't auto-disappear) -->
			<div id="nik-vd-modal" class="nik-vd-hidden">
				<div class="nik-vd-modal-card">
					<div class="nik-vd-success-icon">✓</div>
					<h2><?php esc_html_e( 'Ticket Submitted!', 'nik-voicedesk' ); ?></h2>
					<p class="nik-vd-modal-subtitle"><?php esc_html_e( 'Your voice ticket has been recorded and processed by AI.', 'nik-voicedesk' ); ?></p>

					<div class="nik-vd-id-container">
						<span id="nik-vd-modal-ticket-id">#VD-000000</span>
						<button type="button" id="nik-vd-copy-id-btn">
							📋 <?php esc_html_e( 'Copy ID', 'nik-voicedesk' ); ?>
						</button>
					</div>

					<div class="nik-vd-modal-meta">
						<p><strong><?php esc_html_e( 'Department:', 'nik-voicedesk' ); ?></strong> <span id="nik-vd-modal-dept" class="nik-vd-dept-pill"><?php esc_html_e( 'General Support', 'nik-voicedesk' ); ?></span></p>
						<p><strong><?php esc_html_e( 'Summary:', 'nik-voicedesk' ); ?></strong> <span id="nik-vd-modal-summary"><?php esc_html_e( 'Voice ticket received.', 'nik-voicedesk' ); ?></span></p>
					</div>

					<p class="nik-vd-email-notice">
						✉️ <?php esc_html_e( 'A confirmation with your Ticket ID has been sent to your email.', 'nik-voicedesk' ); ?>
					</p>

					<div class="nik-vd-modal-actions">
						<a href="<?php echo esc_url( $portal_url ); ?>" id="nik-vd-view-tickets-btn" class="nik-vd-btn-primary">
							<?php esc_html_e( 'View My Ticket', 'nik-voicedesk' ); ?>
						</a>
						<button type="button" id="nik-vd-modal-close" class="nik-vd-btn-secondary">
							<?php esc_html_e( 'Close', 'nik-voicedesk' ); ?>
						</button>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the complete, modern Customer User Panel via shortcode.
	 * [nik_voicedesk_tickets]
	 */
	public function render_user_panel_shortcode( $atts ) {
		if ( ! is_user_logged_in() ) {
			return $this->render_guest_login_prompt();
		}

		ob_start();
		
		// If viewing a specific ticket
		if ( isset( $_GET['ticket'] ) ) {
			$this->render_single_ticket_view( sanitize_text_field( $_GET['ticket'] ) );
		} else {
			$this->render_user_tickets_list();
		}

		return ob_get_clean();
	}

	public function wc_add_endpoint() {
		add_rewrite_endpoint( 'voicedesk-tickets', EP_ROOT | EP_PAGES );
	}

	public function wc_add_menu_item( $items ) {
		$items['voicedesk-tickets'] = __( 'Voice Support Tickets', 'nik-voicedesk' );
		return $items;
	}

	public function wc_endpoint_content() {
		if ( isset( $_GET['ticket'] ) ) {
			$this->render_single_ticket_view( sanitize_text_field( $_GET['ticket'] ) );
		} else {
			$this->render_user_tickets_list();
		}
	}

	private function render_guest_login_prompt() {
		$login_url = wp_login_url( get_permalink() );
		return '
		<div style="max-width: 520px; margin: 40px auto; padding: 32px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.05); font-family: -apple-system, sans-serif;">
			<div style="font-size: 40px; margin-bottom: 12px;">🔒</div>
			<h3 style="margin: 0 0 10px 0; color: #0f172a; font-size: 20px;">' . esc_html__( 'Customer Ticket Portal', 'nik-voicedesk' ) . '</h3>
			<p style="color: #64748b; font-size: 14px; margin-bottom: 24px;">' . esc_html__( 'Please sign in to your account to view your voice tickets, track status, and read replies from our support team.', 'nik-voicedesk' ) . '</p>
			<a href="' . esc_url( $login_url ) . '" style="display: inline-block; background: #2563eb; color: #ffffff; text-decoration: none; padding: 12px 28px; border-radius: 6px; font-weight: 600; font-size: 14px;">' . esc_html__( 'Sign In to Your Account', 'nik-voicedesk' ) . '</a>
		</div>';
	}

	/**
	 * Render user's tickets list in modern responsive UI.
	 */
	private function render_user_tickets_list() {
		$user_id = get_current_user_id();
		$args = array(
			'post_type'      => 'voicedesk_ticket',
			'post_status'    => 'publish',
			'author'         => $user_id,
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		$query = new WP_Query( $args );
		$current_url = remove_query_arg( array( 'ticket', 'reply_saved' ) );
		?>
		<style>
			.nik-portal-wrap { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #1e293b; max-width: 960px; margin: 20px auto; }
			.nik-portal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #edf2f7; padding-bottom: 16px; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
			.nik-portal-title { margin: 0; font-size: 22px; font-weight: 700; color: #0f172a; }
			.nik-portal-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px 22px; margin-bottom: 14px; display: flex; align-items: center; justify-content: space-between; transition: box-shadow 0.2s ease; gap: 16px; flex-wrap: wrap; }
			.nik-portal-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.06); }
			.nik-portal-card-left { flex: 1; min-width: 250px; }
			.nik-portal-ticket-id { font-weight: 700; font-size: 16px; color: #0284c7; margin-right: 10px; }
			.nik-portal-dept-pill { background: #f1f5f9; color: #475569; font-size: 12px; font-weight: 600; padding: 3px 8px; border-radius: 4px; }
			.nik-portal-summary { font-size: 13.5px; color: #64748b; margin-top: 6px; line-height: 1.5; }
			.nik-portal-card-right { display: flex; align-items: center; gap: 14px; }
			.nik-portal-btn { background: #2563eb; color: #ffffff !important; text-decoration: none !important; padding: 8px 16px; border-radius: 6px; font-weight: 600; font-size: 13px; display: inline-block; white-space: nowrap; }
			.nik-portal-btn:hover { background: #1d4ed8; }
			.nik-portal-pill { font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 3px 9px; border-radius: 12px; }
			.nik-p-open { background: #dbeafe; color: #1e40af; }
			.nik-p-in-progress { background: #fef3c7; color: #92400e; }
			.nik-p-resolved { background: #dcfce7; color: #166534; }
			.nik-p-closed { background: #f1f5f9; color: #475569; }
			.nik-portal-empty { background: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 12px; padding: 40px 20px; text-align: center; color: #64748b; }
		</style>

		<div class="nik-portal-wrap">
			<div class="nik-portal-header">
				<h2 class="nik-portal-title"><?php esc_html_e( 'My Voice Support Tickets', 'nik-voicedesk' ); ?></h2>
				<span style="color: #64748b; font-size: 14px;">
					<?php printf( esc_html__( '%d Tickets Total', 'nik-voicedesk' ), $query->found_posts ); ?>
				</span>
			</div>

			<?php if ( $query->have_posts() ) : ?>
				<div class="nik-portal-list">
					<?php while ( $query->have_posts() ) : $query->the_post();
						$post_id = get_the_ID();
						$ticket_num = get_post_meta( $post_id, '_nik_ticket_number', true ) ?: $post_id;
						$dept = get_post_meta( $post_id, '_nik_department', true ) ?: __( 'General Support', 'nik-voicedesk' );
						$summary = get_post_meta( $post_id, '_nik_summary', true );
						$status = get_post_meta( $post_id, '_nik_status', true ) ?: 'Open';
						$status_class = 'nik-p-' . sanitize_html_class( strtolower( str_replace( ' ', '-', $status ) ) );
						$replies = get_post_meta( $post_id, '_nik_replies', true );
						$reply_count = is_array( $replies ) ? count( $replies ) : 0;
						$view_url = add_query_arg( 'ticket', $ticket_num, $current_url );
					?>
						<div class="nik-portal-card">
							<div class="nik-portal-card-left">
								<div>
									<span class="nik-portal-ticket-id">#<?php echo esc_html( $ticket_num ); ?></span>
									<span class="nik-portal-dept-pill"><?php echo esc_html( $dept ); ?></span>
									<span style="font-size: 12px; color: #94a3b8; margin-left: 8px;"><?php echo esc_html( get_the_date( 'M j, Y' ) ); ?></span>
								</div>
								<div class="nik-portal-summary">
									<?php echo esc_html( wp_trim_words( $summary ?: __( 'Voice note ticket received.', 'nik-voicedesk' ), 16, '...' ) ); ?>
								</div>
							</div>

							<div class="nik-portal-card-right">
								<span class="nik-portal-pill <?php echo esc_attr( $status_class ); ?>">
									<?php echo esc_html( $status ); ?>
								</span>
								<?php if ( $reply_count > 0 ) : ?>
									<span style="font-size: 12px; color: #64748b;">💬 <?php echo esc_html( $reply_count ); ?></span>
								<?php endif; ?>
								<a href="<?php echo esc_url( $view_url ); ?>" class="nik-portal-btn">
									<?php esc_html_e( 'View Details →', 'nik-voicedesk' ); ?>
								</a>
							</div>
						</div>
					<?php endwhile; wp_reset_postdata(); ?>
				</div>
			<?php else : ?>
				<div class="nik-portal-empty">
					<div style="font-size: 36px; margin-bottom: 8px;">🎙️</div>
					<h3 style="margin: 0 0 6px 0; color: #0f172a;"><?php esc_html_e( 'No tickets found', 'nik-voicedesk' ); ?></h3>
					<p style="margin: 0; font-size: 14px;"><?php esc_html_e( 'You haven\'t recorded any voice support tickets yet. Use the microphone button in the bottom right corner of the page to speak your issue!', 'nik-voicedesk' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( '1' === (string) get_option( 'nik_voicedesk_show_attribution', '0' ) && ! Nik_VoiceDesk_Settings::is_enterprise() ) : ?>
				<div class="nik-portal-footer-attribution">
					<?php esc_html_e( 'Voice Support Powered by', 'nik-voicedesk' ); ?> 
					<a href="https://nikneural.ca/voicedesk.php" target="_blank" rel="noopener noreferrer">
						Nik Neural AI Inc.
						<img src="https://nikneural.ca/fav/favicon-light-32.png" alt="Nik Neural AI Inc." class="nik-vd-company-logo nik-vd-logo-light" width="14" height="14" />
						<img src="https://nikneural.ca/fav/favicon-dark-32.png" alt="Nik Neural AI Inc." class="nik-vd-company-logo nik-vd-logo-dark" width="14" height="14" />
					</a>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render single ticket details with Audio Player, AI summary, and Reply Thread.
	 */
	private function render_single_ticket_view( $ticket_number ) {
		$user_id = get_current_user_id();

		// Query ticket by ticket number
		$args = array(
			'post_type'      => 'voicedesk_ticket',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'meta_query'     => array(
				array(
					'key'   => '_nik_ticket_number',
					'value' => $ticket_number,
				),
			),
		);
		$posts = get_posts( $args );

		if ( empty( $posts ) ) {
			echo '<p style="color: #ef4444;">' . esc_html__( 'Ticket not found.', 'nik-voicedesk' ) . '</p>';
			return;
		}

		$post = $posts[0];

		// Strict Security Check: only the author or admin can view this ticket
		if ( (int) $post->post_author !== $user_id && ! current_user_can( 'manage_options' ) ) {
			echo '<div style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 20px; border-radius: 8px; text-align: center;">';
			echo '<strong>' . esc_html__( 'Access Denied', 'nik-voicedesk' ) . '</strong><br>';
			echo esc_html__( 'You do not have permission to view this support ticket.', 'nik-voicedesk' );
			echo '</div>';
			return;
		}

		$audio_url = get_post_meta( $post->ID, '_nik_audio_url', true );
		$stream_url = get_post_meta( $post->ID, '_nik_audio_stream_url', true ) ?: rest_url( 'nik-voicedesk/v1/audio/' . $ticket_number );
		$transcript = get_post_meta( $post->ID, '_nik_transcript', true );
		$summary = get_post_meta( $post->ID, '_nik_summary', true );
		$dept = get_post_meta( $post->ID, '_nik_department', true ) ?: 'General Support';
		$status = get_post_meta( $post->ID, '_nik_status', true ) ?: 'Open';
		$replies = get_post_meta( $post->ID, '_nik_replies', true );
		if ( ! is_array( $replies ) ) {
			$replies = array();
		}

		$back_url = remove_query_arg( array( 'ticket', 'reply_sent' ) );

		// Process User Reply Submission
		if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['nik_user_reply_nonce'] ) && wp_verify_nonce( $_POST['nik_user_reply_nonce'], 'nik_user_reply' ) ) {
			$user_msg = sanitize_textarea_field( wp_unslash( $_POST['nik_user_message'] ?? '' ) );
			if ( ! empty( $user_msg ) ) {
				$current_user = wp_get_current_user();
				$author_name = $current_user->display_name ?: $current_user->user_login;

				// Deduplication: prevent duplicate reply on refresh or resubmission
				$is_duplicate = false;
				if ( ! empty( $replies ) ) {
					$last_reply = end( $replies );
					if (
						isset( $last_reply['role'], $last_reply['message'] ) &&
						'customer' === $last_reply['role'] &&
						trim( $last_reply['message'] ) === trim( $user_msg )
					) {
						$is_duplicate = true;
					}
				}

				if ( ! $is_duplicate ) {
					$replies[] = array(
						'author'  => $author_name,
						'role'    => 'customer',
						'message' => $user_msg,
						'date'    => current_time( 'mysql' ),
					);
					update_post_meta( $post->ID, '_nik_replies', $replies );
					update_post_meta( $post->ID, '_nik_status', 'Customer Replied' );

					// Notify admin
					$admin_email = get_option( 'admin_email' );
					$site_name = get_bloginfo( 'name' );
					$subject = sprintf( __( '[%s] New Customer Reply on Ticket #%s', 'nik-voicedesk' ), $site_name, $ticket_number );
					$body = sprintf(
						__( "Customer %s posted a new reply on Ticket #%s:\n\n\"%s\"\n\nManage Ticket:\n%s", 'nik-voicedesk' ),
						$author_name,
						$ticket_number,
						$user_msg,
						admin_url( 'post.php?post=' . $post->ID . '&action=edit' )
					);
					wp_mail( $admin_email, $subject, $body );
				}

				// If headers not yet sent, redirect with GET to prevent browser refresh re-submission
				if ( ! headers_sent() ) {
					wp_safe_redirect( add_query_arg( 'reply_sent', '1' ) );
					exit;
				}

				echo '<div style="background: #dcfce7; border: 1px solid #bbf7d0; color: #166534; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px;">' . esc_html__( 'Your reply has been sent to our support team.', 'nik-voicedesk' ) . '</div>';
			}
		}

		if ( isset( $_GET['reply_sent'] ) ) {
			echo '<div style="background: #dcfce7; border: 1px solid #bbf7d0; color: #166534; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px;">' . esc_html__( 'Your reply has been sent to our support team.', 'nik-voicedesk' ) . '</div>';
		}
		?>
		<style>
			.nik-detail-wrap { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #1e293b; max-width: 860px; margin: 20px auto; }
			.nik-detail-back { display: inline-flex; align-items: center; color: #2563eb; text-decoration: none; font-size: 14px; font-weight: 600; margin-bottom: 16px; }
			.nik-detail-header { background: #0f172a; color: #ffffff; padding: 22px 26px; border-radius: 12px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
			.nik-detail-box { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; margin-bottom: 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.04); }
			.nik-detail-box h3 { margin: 0 0 12px 0; font-size: 16px; font-weight: 700; color: #0f172a; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px; }
			.nik-audio-player { width: 100%; outline: none; margin-top: 10px; border-radius: 8px; }
			.nik-reply-card { border-radius: 8px; padding: 14px; margin-bottom: 12px; font-size: 14px; line-height: 1.5; }
			.nik-reply-customer { background: #f8fafc; border: 1px solid #e2e8f0; }
			.nik-reply-staff { background: #f0f9ff; border: 1px solid #bae6fd; }
			.nik-reply-btn { background: #2563eb; color: #ffffff; border: none; padding: 10px 22px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 14px; }
			.nik-reply-btn:hover { background: #1d4ed8; }
		</style>

		<div class="nik-detail-wrap">
			<a href="<?php echo esc_url( $back_url ); ?>" class="nik-detail-back">
				← <?php esc_html_e( 'Back to All Tickets', 'nik-voicedesk' ); ?>
			</a>

			<!-- Header -->
			<div class="nik-detail-header">
				<div>
					<div style="font-size: 22px; font-weight: 800; color: #38bdf8;">#<?php echo esc_html( $ticket_number ); ?></div>
					<div style="font-size: 13px; color: #94a3b8; margin-top: 4px;">
						<?php echo esc_html( $dept ); ?> &bull; <?php echo esc_html( get_the_date( 'M j, Y \a\t g:i A', $post->ID ) ); ?>
					</div>
				</div>
				<div>
					<span style="background: #2563eb; color: #fff; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; text-transform: uppercase;">
						<?php echo esc_html( $status ); ?>
					</span>
				</div>
			</div>

			<!-- Audio Recording & AI Summary -->
			<div class="nik-detail-box">
				<h3>🎙️ <?php esc_html_e( 'Your Voice Note & AI Summary', 'nik-voicedesk' ); ?></h3>
				
				<?php if ( $audio_url || $stream_url ) : ?>
					<audio controls class="nik-audio-player">
						<source src="<?php echo esc_url( $stream_url ); ?>" type="audio/webm">
						<?php if ( $audio_url ) : ?>
							<source src="<?php echo esc_url( $audio_url ); ?>" type="audio/webm">
							<source src="<?php echo esc_url( $audio_url ); ?>" type="audio/wav">
						<?php endif; ?>
					</audio>
				<?php endif; ?>

				<div style="margin-top: 18px; background: #f8fafc; border-left: 4px solid #38bdf8; padding: 14px 18px; border-radius: 0 8px 8px 0; font-size: 14px; line-height: 1.6;">
					<strong><?php esc_html_e( 'AI Summary:', 'nik-voicedesk' ); ?></strong>
					<p style="margin: 6px 0 0 0;"><?php echo esc_html( $summary ?: __( 'Voice ticket received and being processed.', 'nik-voicedesk' ) ); ?></p>
				</div>
			</div>

			<!-- Conversation & Replies -->
			<div class="nik-detail-box">
				<h3>💬 <?php esc_html_e( 'Support Conversation & Updates', 'nik-voicedesk' ); ?></h3>

				<div style="margin-bottom: 20px;">
					<?php if ( ! empty( $replies ) ) : ?>
						<?php foreach ( $replies as $r ) :
							$is_staff = ( $r['role'] ?? '' ) === 'staff' || ( $r['author'] ?? '' ) === 'Admin';
							$card_class = $is_staff ? 'nik-reply-staff' : 'nik-reply-customer';
							$badge_title = $is_staff ? __( 'Support Agent', 'nik-voicedesk' ) : __( 'You', 'nik-voicedesk' );
							$badge_color = $is_staff ? '#0284c7' : '#475569';
						?>
							<div class="nik-reply-card <?php echo esc_attr( $card_class ); ?>">
								<div style="display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 12.5px;">
									<strong>
										<?php echo esc_html( $r['author'] ); ?> 
										<span style="background: <?php echo esc_attr( $badge_color ); ?>; color: #fff; padding: 2px 6px; border-radius: 4px; font-size: 10px; margin-left: 4px;"><?php echo esc_html( $badge_title ); ?></span>
									</strong>
									<span style="color: #94a3b8;"><?php echo esc_html( date_i18n( 'M j, g:i A', strtotime( $r['date'] ?? 'now' ) ) ); ?></span>
								</div>
								<div style="margin: 0; color: #1e293b;">
									<?php echo wpautop( esc_html( $r['message'] ) ); ?>
								</div>
							</div>
						<?php endforeach; ?>
					<?php else : ?>
						<p style="color: #64748b; font-size: 13.5px;"><?php esc_html_e( 'Our support team has received your ticket and is preparing a response.', 'nik-voicedesk' ); ?></p>
					<?php endif; ?>
				</div>

				<!-- Customer Reply Box -->
				<form method="post" style="border-top: 1px solid #edf2f7; padding-top: 20px;">
					<?php wp_nonce_field( 'nik_user_reply', 'nik_user_reply_nonce' ); ?>
					<h4 style="margin: 0 0 10px 0; font-size: 14px; font-weight: 600;"><?php esc_html_e( 'Send a Message to Support', 'nik-voicedesk' ); ?></h4>
					<textarea name="nik_user_message" rows="4" style="width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 12px; font-size: 14px; margin-bottom: 12px; box-sizing: border-box;" placeholder="<?php esc_attr_e( 'Type your question or additional details here...', 'nik-voicedesk' ); ?>" required></textarea>
					<button type="submit" class="nik-reply-btn">
						<?php esc_html_e( 'Submit Reply', 'nik-voicedesk' ); ?>
					</button>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Render prominent Voice Support Card on WooCommerce My Account Dashboard.
	 */
	public function wc_account_dashboard_ticket_btn() {
		$portal_url = function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'voicedesk-tickets' ) : '';
		if ( empty( $portal_url ) ) {
			$portal_page_id = get_option( 'nik_voicedesk_portal_page_id', 0 );
			$portal_url = $portal_page_id ? get_permalink( $portal_page_id ) : home_url();
		}
		?>
		<div class="nik-vd-woo-dashboard-card" style="background: #0f172a; color: #ffffff; padding: 22px 26px; border-radius: 12px; margin: 24px 0; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
			<div>
				<div style="font-size: 18px; font-weight: 700; color: #38bdf8; display: flex; align-items: center; gap: 8px;">
					🎙️ <?php esc_html_e( 'Voice Support & Tickets', 'nik-voicedesk' ); ?>
				</div>
				<div style="font-size: 13.5px; color: #94a3b8; margin-top: 4px;">
					<?php esc_html_e( 'Have a question about your order or need assistance? Submit or track your voice tickets.', 'nik-voicedesk' ); ?>
				</div>
			</div>
			<a href="<?php echo esc_url( $portal_url ); ?>" style="background: #2563eb; color: #ffffff !important; text-decoration: none !important; padding: 10px 22px; border-radius: 8px; font-weight: 600; font-size: 14px; display: inline-block;">
				<?php esc_html_e( 'Manage Voice Tickets →', 'nik-voicedesk' ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Add Voice Support link to WordPress / WooCommerce User Profile screen.
	 */
	public function render_user_profile_ticket_section( $user ) {
		if ( ! current_user_can( 'manage_options' ) && get_current_user_id() !== $user->ID ) {
			return;
		}
		$portal_page_id = get_option( 'nik_voicedesk_portal_page_id', 0 );
		$portal_url = $portal_page_id ? get_permalink( $portal_page_id ) : ( function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'voicedesk-tickets' ) : home_url() );
		?>
		<h2><?php esc_html_e( 'VoiceDesk Support Tickets', 'nik-voicedesk' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><label><?php esc_html_e( 'Customer Support Portal', 'nik-voicedesk' ); ?></label></th>
				<td>
					<a href="<?php echo esc_url( $portal_url ); ?>" class="button button-secondary" target="_blank">
						🎙️ <?php esc_html_e( 'View Customer Voice Tickets', 'nik-voicedesk' ); ?>
					</a>
					<p class="description"><?php esc_html_e( 'Direct link to the customer voice ticketing portal.', 'nik-voicedesk' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}
}
