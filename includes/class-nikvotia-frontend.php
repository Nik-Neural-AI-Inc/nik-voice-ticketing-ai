<?php
/**
 * Frontend class for asset injection, modern recording dock, modal, and customer User Panel.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nikvotia_Frontend {

	public function init() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_footer', array( $this, 'inject_ui' ) );
		add_action( 'wp_head', array( $this, 'inject_custom_css' ) );
		add_shortcode( 'nikvotia_tickets', array( $this, 'render_user_panel_shortcode' ) );

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
		$user_status = get_option( 'nikvotia_visibility_user_status', 'all'   );
		if ( 'logged_in' === $user_status && ! is_user_logged_in() ) {
			return false;
		}
		if ( 'not_logged_in' === $user_status && is_user_logged_in() ) {
			return false;
		}
		if ( 'specific' === $user_status ) {
			$specific_users = get_option( 'nikvotia_visibility_specific_users', ''   );
			$allowed_ids = array_map( 'trim', explode( ',', $specific_users ) );
			$current_user_id = get_current_user_id();
			if ( ! in_array( (string) $current_user_id, $allowed_ids, true ) ) {
				return false;
			}
		}

		// 2. Post Type Check
		$allowed_post_types = get_option( 'nikvotia_visibility_post_types', array(  ) );
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

		wp_enqueue_style( 'nikvotia-frontend', NIKVOTIA_URL . 'assets/css/frontend.css', array(), NIKVOTIA_VERSION );
		wp_enqueue_script( 'nikvotia-frontend', NIKVOTIA_URL . 'assets/js/frontend.js', array(), NIKVOTIA_VERSION, true );

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

		wp_localize_script( 'nikvotia-frontend', 'nikvotiaData', array(
			'restUrl'      => esc_url_raw( rest_url( 'nikvotia/v1/submit' ) ),
			'nonce'        => wp_create_nonce( 'wp_rest' ),
			'portalUrl'    => esc_url( $portal_url ),
		) );

		// Dynamic styles via standard wp_add_inline_style
		$btn_color = sanitize_hex_color( get_option( 'nikvotia_btn_color', '#ffd700'   ) ) ?: '#ffd700';
		$icon_color = sanitize_hex_color( get_option( 'nikvotia_icon_color', '#333333'   ) ) ?: '#333333';
		$custom_css = ":root { --nik-vd-btn-bg: {$btn_color} !important; --nik-vd-icon-color: {$icon_color} !important; }";
		wp_add_inline_style( 'nikvotia-frontend', $custom_css );
	}

	public function inject_custom_css() {
		// Handled cleanly via wp_add_inline_style in enqueue_assets
	}

	/**
	 * Inject modern floating dock, click pins, and permanent success modal.
	 */
	public function inject_ui() {
		if ( ! $this->is_visible() ) {
			return;
		}
		
		$show_attribution = '1' === (string) get_option( 'nikvotia_show_attribution', '0'   );
		$custom_icon = get_option( 'nikvotia_custom_icon', ''   );
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

		if ( empty( $custom_icon ) ) {
			$custom_icon = '<svg viewBox="0 0 24 24" width="26" height="26" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"></path><path d="M19 10v2a7 7 0 0 1-14 0v-2"></path><line x1="12" y1="19" x2="12" y2="23"></line><line x1="8" y1="23" x2="16" y2="23"></line></svg>';
		}

		$allowed_svg = array(
			'svg'   => array(
				'viewbox'        => true,
				'viewBox'        => true,
				'width'          => true,
				'height'         => true,
				'stroke'         => true,
				'stroke-width'   => true,
				'fill'           => true,
				'stroke-linecap' => true,
				'stroke-linejoin'=> true,
				'class'          => true,
				'xmlns'          => true,
			),
			'path'  => array(
				'd'      => true,
				'fill'   => true,
				'stroke' => true,
			),
			'line'  => array(
				'x1'     => true,
				'y1'     => true,
				'x2'     => true,
				'y2'     => true,
				'stroke' => true,
			),
			'circle'=> array(
				'cx'     => true,
				'cy'     => true,
				'r'      => true,
				'fill'   => true,
				'stroke' => true,
			),
		);
		?>
		<div id="nik-vd-root">
			<!-- Floating Mic Button & Compact Attribution Wrap (Always Upper / Above) -->
			<div id="nik-vd-trigger-wrap">
				<button id="nik-vd-mic-btn" aria-label="<?php esc_attr_e( 'Record Voice Ticket', 'nik-voice-ticketing-ai' ); ?>" title="<?php esc_attr_e( 'Click to record a voice support ticket', 'nik-voice-ticketing-ai' ); ?>">
					<?php echo wp_kses( $custom_icon, $allowed_svg ); ?>
				</button>
				
				<?php if ( $show_attribution ) : ?>
					<a id="nik-vd-branding-btn" class="nik-vd-branding" href="https://nikneural.ca/pl/voicedesk.php" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Powered by', 'nik-voice-ticketing-ai' ); ?>
						<img src="<?php echo esc_url( NIKVOTIA_URL . 'assets/images/favicon-light-32.png' ); ?>" alt="Nik Neural AI Inc." class="nik-vd-company-logo" width="14" height="14" />
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
					✓ <?php esc_html_e( 'Finish & Submit', 'nik-voice-ticketing-ai' ); ?>
				</button>

				<button id="nik-vd-cancel-btn" class="nik-vd-dock-btn" title="<?php esc_attr_e( 'Discard recording', 'nik-voice-ticketing-ai' ); ?>">
					✕ <?php esc_html_e( 'Cancel', 'nik-voice-ticketing-ai' ); ?>
				</button>
			</div>

			<?php if ( $show_attribution ) : ?>
				<!-- Freemium Attribution Bar (Free Tier) -->
				<div id="nik-vd-attribution-bar" class="nik-vd-attribution-bar nik-vd-hidden">
					<?php esc_html_e( 'Voice Support Powered by', 'nik-voice-ticketing-ai' ); ?> 
					<a href="https://nikneural.ca/pl/voicedesk.php" target="_blank" rel="noopener noreferrer">
						Nik Neural AI Inc.
						<img src="<?php echo esc_url( NIKVOTIA_URL . 'assets/images/favicon-light-32.png' ); ?>" alt="Nik Neural AI Inc." class="nik-vd-company-logo nik-vd-logo-light" width="14" height="14" />
					</a>
				</div>
			<?php endif; ?>

			<!-- Floating Processing Spinner -->
			<div id="nik-vd-processing" class="nik-vd-hidden">
				<div class="nik-vd-spinner"></div>
				<span><?php esc_html_e( 'Analyzing voice with AI...', 'nik-voice-ticketing-ai' ); ?></span>
			</div>

			<!-- Dedicated Success Confirmation Modal (Doesn't auto-disappear) -->
			<div id="nik-vd-modal" class="nik-vd-hidden">
				<div class="nik-vd-modal-card">
					<div class="nik-vd-success-icon">✓</div>
					<h2><?php esc_html_e( 'Ticket Submitted!', 'nik-voice-ticketing-ai' ); ?></h2>
					<p class="nik-vd-modal-subtitle"><?php esc_html_e( 'Your voice ticket has been recorded and processed by AI.', 'nik-voice-ticketing-ai' ); ?></p>

					<div class="nik-vd-id-container">
						<span id="nik-vd-modal-ticket-id">#VD-000000</span>
						<button type="button" id="nik-vd-copy-id-btn">
							📋 <?php esc_html_e( 'Copy ID', 'nik-voice-ticketing-ai' ); ?>
						</button>
					</div>

					<div class="nik-vd-modal-meta">
						<p><strong><?php esc_html_e( 'Department:', 'nik-voice-ticketing-ai' ); ?></strong> <span id="nik-vd-modal-dept" class="nik-vd-dept-pill"><?php esc_html_e( 'General Support', 'nik-voice-ticketing-ai' ); ?></span></p>
						<p><strong><?php esc_html_e( 'Summary:', 'nik-voice-ticketing-ai' ); ?></strong> <span id="nik-vd-modal-summary"><?php esc_html_e( 'Voice ticket received.', 'nik-voice-ticketing-ai' ); ?></span></p>
					</div>

					<p class="nik-vd-email-notice">
						✉️ <?php esc_html_e( 'A confirmation with your Ticket ID has been sent to your email.', 'nik-voice-ticketing-ai' ); ?>
					</p>

					<div class="nik-vd-modal-actions">
						<a href="<?php echo esc_url( $portal_url ); ?>" id="nik-vd-view-tickets-btn" class="nik-vd-btn-primary">
							<?php esc_html_e( 'View My Ticket', 'nik-voice-ticketing-ai' ); ?>
						</a>
						<button type="button" id="nik-vd-modal-close" class="nik-vd-btn-secondary">
							<?php esc_html_e( 'Close', 'nik-voice-ticketing-ai' ); ?>
						</button>
					</div>
				</div>
			</div>

			<!-- In-Page Error / Notification Toast (Replaces raw browser alert popups) -->
			<div id="nik-vd-notice" class="nik-vd-notice nik-vd-hidden" role="alert" aria-live="assertive">
				<div class="nik-vd-notice-card">
					<div class="nik-vd-notice-icon" id="nik-vd-notice-icon">⚠️</div>
					<div class="nik-vd-notice-body">
						<strong id="nik-vd-notice-title" class="nik-vd-notice-title"><?php esc_html_e( 'Notice', 'nik-voice-ticketing-ai' ); ?></strong>
						<p id="nik-vd-notice-msg" class="nik-vd-notice-msg"></p>
					</div>
					<button type="button" id="nik-vd-notice-close" class="nik-vd-notice-close" aria-label="<?php esc_attr_e( 'Close notification', 'nik-voice-ticketing-ai' ); ?>">&times;</button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the complete, modern Customer User Panel via shortcode.
	 * [nikvotia_tickets]
	 */
	public function render_user_panel_shortcode( $atts ) {
		if ( ! is_user_logged_in() ) {
			return $this->render_guest_login_prompt();
		}

		wp_enqueue_style( 'nikvotia-frontend', NIKVOTIA_URL . 'assets/css/frontend.css', array(), NIKVOTIA_VERSION );
		wp_enqueue_script( 'nikvotia-frontend', NIKVOTIA_URL . 'assets/js/frontend.js', array(), NIKVOTIA_VERSION, true );

		ob_start();
		
		if ( isset( $_GET['ticket'] ) ) {
			$ticket_num = sanitize_text_field( wp_unslash( $_GET['ticket'] ) );
			$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'nikvotia_view_ticket_' . $ticket_num ) ) {
				echo '<div style="background: #fee2e2; border: 1px solid #fecaca; color: #991b1b; padding: 14px 18px; border-radius: 8px; margin-bottom: 20px;">' . esc_html__( 'Security check failed. Please refresh the page or return to your tickets list.', 'nik-voice-ticketing-ai' ) . '</div>';
			} else {
				$this->render_single_ticket_view( $ticket_num );
			}
		} else {
			$this->render_user_tickets_list();
		}

		return ob_get_clean();
	}

	public function wc_add_endpoint() {
		add_rewrite_endpoint( 'voicedesk-tickets', EP_ROOT | EP_PAGES );
	}

	public function wc_add_menu_item( $items ) {
		$items['voicedesk-tickets'] = __( 'Voice Support Tickets', 'nik-voice-ticketing-ai' );
		return $items;
	}

	public function wc_endpoint_content() {
		if ( ! is_user_logged_in() ) {
			echo $this->render_guest_login_prompt();
			return;
		}

		wp_enqueue_style( 'nikvotia-frontend', NIKVOTIA_URL . 'assets/css/frontend.css', array(), NIKVOTIA_VERSION );
		wp_enqueue_script( 'nikvotia-frontend', NIKVOTIA_URL . 'assets/js/frontend.js', array(), NIKVOTIA_VERSION, true );

		if ( isset( $_GET['ticket'] ) ) {
			$ticket_num = sanitize_text_field( wp_unslash( $_GET['ticket'] ) );
			$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'nikvotia_view_ticket_' . $ticket_num ) ) {
				echo '<div style="background: #fee2e2; border: 1px solid #fecaca; color: #991b1b; padding: 14px 18px; border-radius: 8px; margin-bottom: 20px;">' . esc_html__( 'Security check failed. Please refresh the page or return to your tickets list.', 'nik-voice-ticketing-ai' ) . '</div>';
				return;
			}
			$this->render_single_ticket_view( $ticket_num );
		} else {
			$this->render_user_tickets_list();
		}
	}

	private function render_guest_login_prompt() {
		$login_url = wp_login_url( get_permalink() );
		return '
		<div style="max-width: 520px; margin: 40px auto; padding: 32px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.05); font-family: -apple-system, sans-serif;">
			<div style="font-size: 40px; margin-bottom: 12px;">🔒</div>
			<h3 style="margin: 0 0 10px 0; color: #0f172a; font-size: 20px;">' . esc_html__( 'Customer Ticket Portal', 'nik-voice-ticketing-ai' ) . '</h3>
			<p style="color: #64748b; font-size: 14px; margin-bottom: 24px;">' . esc_html__( 'Please sign in to your account to view your voice tickets, track status, and read replies from our support team.', 'nik-voice-ticketing-ai' ) . '</p>
			<a href="' . esc_url( $login_url ) . '" style="display: inline-block; background: #2563eb; color: #ffffff; text-decoration: none; padding: 12px 28px; border-radius: 6px; font-weight: 600; font-size: 14px;">' . esc_html__( 'Sign In to Your Account', 'nik-voice-ticketing-ai' ) . '</a>
		</div>';
	}

	/**
	 * Render user\'s tickets list in modern responsive UI.
	 */
	private function render_user_tickets_list() {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return;
		}
		$args = array(
			'post_type'      => 'voicedesk_ticket',
			'post_status'    => 'publish',
			'author__in'     => array( $user_id ),
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		$query = new WP_Query( $args );
		$current_url = remove_query_arg( array( 'ticket', 'reply_saved' ) );
		?>
		<div class="nik-portal-wrap" id="nik-portal-root">
			<div class="nik-portal-header">
				<div>
					<h2 class="nik-portal-title"><?php esc_html_e( 'My Voice Support Tickets', 'nik-voice-ticketing-ai' ); ?></h2>
					<span class="nik-portal-count-label" style="color: #64748b; font-size: 14px;">
						<?php
						/* translators: %d: Total number of tickets */
						echo esc_html( sprintf( __( '%d Tickets Total', 'nik-voice-ticketing-ai' ), (int) $query->found_posts ) );
						?>
					</span>
				</div>
				<button type="button" class="nik-portal-theme-btn" id="nik-portal-theme-btn" aria-label="<?php esc_attr_e( 'Toggle Dark or Light Mode', 'nik-voice-ticketing-ai' ); ?>">
					<span class="nik-portal-theme-icon">🌙</span>
					<span class="nik-portal-theme-text"><?php esc_html_e( 'Dark Mode', 'nik-voice-ticketing-ai' ); ?></span>
				</button>
			</div>

			<?php if ( $query->have_posts() ) : ?>
				<div class="nik-portal-list">
					<?php while ( $query->have_posts() ) : $query->the_post();
						$post_id = get_the_ID();
						$ticket_num = get_post_meta( $post_id, '_nikvotia_ticket_number', true ) ?: $post_id;
						$dept = get_post_meta( $post_id, '_nikvotia_department', true ) ?: __( 'General Support', 'nik-voice-ticketing-ai' );
						$summary = get_post_meta( $post_id, '_nikvotia_summary', true );
						$status = get_post_meta( $post_id, '_nikvotia_status', true ) ?: 'Open';
						$status_class = 'nik-p-' . sanitize_html_class( strtolower( str_replace( ' ', '-', $status ) ) );
						$replies = get_post_meta( $post_id, '_nikvotia_replies', true );
						$reply_count = is_array( $replies ) ? count( $replies ) : 0;
						$view_url = wp_nonce_url( add_query_arg( 'ticket', $ticket_num, $current_url ), 'nikvotia_view_ticket_' . $ticket_num );
					?>
						<div class="nik-portal-card">
							<div class="nik-portal-card-left">
								<div>
									<span class="nik-portal-ticket-id">#<?php echo esc_html( $ticket_num ); ?></span>
									<span class="nik-portal-dept-pill"><?php echo esc_html( $dept ); ?></span>
									<span style="font-size: 12px; color: #94a3b8; margin-left: 8px;"><?php echo esc_html( get_the_date( 'M j, Y' ) ); ?></span>
								</div>
								<div class="nik-portal-summary">
									<?php echo esc_html( wp_trim_words( $summary ?: __( 'Voice note ticket received.', 'nik-voice-ticketing-ai' ), 16, '...' ) ); ?>
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
									<?php esc_html_e( 'View Details →', 'nik-voice-ticketing-ai' ); ?>
								</a>
							</div>
						</div>
					<?php endwhile; wp_reset_postdata(); ?>
				</div>
			<?php else : ?>
				<div class="nik-portal-empty">
					<div style="font-size: 36px; margin-bottom: 8px;">🎙️</div>
					<h3 style="margin: 0 0 6px 0; color: #0f172a;"><?php esc_html_e( 'No tickets found', 'nik-voice-ticketing-ai' ); ?></h3>
					<p style="margin: 0; font-size: 14px;"><?php esc_html_e( 'You haven\'t recorded any voice support tickets yet. Use the microphone button in the bottom right corner of the page to speak your issue!', 'nik-voice-ticketing-ai' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( '1' === (string) get_option( 'nikvotia_show_attribution', '0'   ) ) : ?>
				<div class="nik-portal-footer-attribution">
					<?php esc_html_e( 'Voice Support Powered by', 'nik-voice-ticketing-ai' ); ?> 
					<a href="https://nikneural.ca/pl/voicedesk.php" target="_blank" rel="noopener noreferrer">
						Nik Neural AI Inc.
						<img src="<?php echo esc_url( NIKVOTIA_URL . 'assets/images/favicon-light-32.png' ); ?>" alt="Nik Neural AI Inc." class="nik-vd-company-logo nik-vd-logo-light" width="14" height="14" />
						<img src="<?php echo esc_url( NIKVOTIA_URL . 'assets/images/favicon-dark-32.png' ); ?>" alt="Nik Neural AI Inc." class="nik-vd-company-logo nik-vd-logo-dark" width="14" height="14" />
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
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Indexed ticket lookup by unique ticket ID.
			'meta_query'     => array(
				array(
					'key'   => '_nikvotia_ticket_number',
					'value' => $ticket_number,
				),
			),
		);
		$posts = get_posts( $args );

		if ( empty( $posts ) ) {
			echo '<p style="color: #ef4444;">' . esc_html__( 'Ticket not found.', 'nik-voice-ticketing-ai' ) . '</p>';
			return;
		}

		$post = $posts[0];

		// Strict Security Check: only the authenticated author (user_id > 0) or admin can view this ticket
		$post_author_id = (int) $post->post_author;
		$can_view = false;
		if ( current_user_can( 'manage_options' ) ) {
			$can_view = true;
		} elseif ( $user_id > 0 && $post_author_id === $user_id ) {
			$can_view = true;
		}

		if ( ! $can_view ) {
			echo '<div style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 20px; border-radius: 8px; text-align: center;">';
			echo '<strong>' . esc_html__( 'Access Denied', 'nik-voice-ticketing-ai' ) . '</strong><br>';
			echo esc_html__( 'You do not have permission to view this support ticket.', 'nik-voice-ticketing-ai' );
			echo '</div>';
			return;
		}

		$audio_url = get_post_meta( $post->ID, '_nikvotia_audio_url', true );
		$stream_url = get_post_meta( $post->ID, '_nikvotia_audio_stream_url', true ) ?: rest_url( 'nikvotia/v1/audio/' . $ticket_number );
		$transcript = get_post_meta( $post->ID, '_nikvotia_transcript', true );
		$summary = get_post_meta( $post->ID, '_nikvotia_summary', true );
		$dept = get_post_meta( $post->ID, '_nikvotia_department', true ) ?: 'General Support';
		$status = get_post_meta( $post->ID, '_nikvotia_status', true ) ?: 'Open';
		$replies = get_post_meta( $post->ID, '_nikvotia_replies', true );
		if ( ! is_array( $replies ) ) {
			$replies = array();
		}

		$back_url = remove_query_arg( array( 'ticket', 'reply_sent' ) );

		// Process User Reply Submission
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['nikvotia_user_reply_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nikvotia_user_reply_nonce'] ) ), 'nikvotia_user_reply' ) ) {
			$user_msg = sanitize_textarea_field( wp_unslash( $_POST['nikvotia_user_message'] ?? '' ) );
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
					update_post_meta( $post->ID, '_nikvotia_replies', $replies );

					// Notify admin
					$admin_email = get_option( 'admin_email' );
					$site_name = get_bloginfo( 'name' );
					/* translators: 1: Site name, 2: Ticket number */
					$subject = sprintf( __( '[%1$s] New Customer Reply on Ticket #%2$s', 'nik-voice-ticketing-ai' ), $site_name, $ticket_number );
					$body = sprintf(
						/* translators: 1: Customer name, 2: Ticket number, 3: Staff reply message, 4: Ticket admin URL */
						__( 'Customer %1$s posted a new reply on Ticket #%2$s:

"%3$s"

Manage Ticket:
%4$s', 'nik-voice-ticketing-ai' ),
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

				echo '<div style="background: #dcfce7; border: 1px solid #bbf7d0; color: #166534; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px;">' . esc_html__( 'Your reply has been sent to our support team.', 'nik-voice-ticketing-ai' ) . '</div>';
			}
		}

		if ( isset( $_GET['reply_sent'] ) ) {
			echo '<div style="background: #dcfce7; border: 1px solid #bbf7d0; color: #166534; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px;">' . esc_html__( 'Your reply has been sent to our support team.', 'nik-voice-ticketing-ai' ) . '</div>';
		}
		?>
		<div class="nik-detail-wrap" id="nik-detail-root">
			<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
				<a href="<?php echo esc_url( $back_url ); ?>" class="nik-detail-back">
					← <?php esc_html_e( 'Back to All Tickets', 'nik-voice-ticketing-ai' ); ?>
				</a>
				<button type="button" class="nik-detail-theme-btn" id="nik-detail-theme-btn" aria-label="<?php esc_attr_e( 'Toggle Dark or Light Mode', 'nik-voice-ticketing-ai' ); ?>">
					<span class="nik-detail-theme-icon">🌙</span>
					<span class="nik-detail-theme-text"><?php esc_html_e( 'Dark Mode', 'nik-voice-ticketing-ai' ); ?></span>
				</button>
			</div>

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
				<h3>🎙️ <?php esc_html_e( 'Your Voice Note & AI Summary', 'nik-voice-ticketing-ai' ); ?></h3>
				
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
					<strong><?php esc_html_e( 'AI Summary:', 'nik-voice-ticketing-ai' ); ?></strong>
					<p style="margin: 6px 0 0 0;"><?php echo esc_html( $summary ?: __( 'Voice ticket received and being processed.', 'nik-voice-ticketing-ai' ) ); ?></p>
				</div>
			</div>

			<!-- Conversation & Replies -->
			<div class="nik-detail-box">
				<h3>💬 <?php esc_html_e( 'Support Conversation & Updates', 'nik-voice-ticketing-ai' ); ?></h3>

				<div style="margin-bottom: 20px;">
					<?php if ( ! empty( $replies ) ) : ?>
						<?php foreach ( $replies as $r ) :
							$is_staff = ( $r['role'] ?? '' ) === 'staff' || ( $r['author'] ?? '' ) === 'Admin';
							$card_class = $is_staff ? 'nik-reply-staff' : 'nik-reply-customer';
							$badge_title = $is_staff ? __( 'Support Agent', 'nik-voice-ticketing-ai' ) : __( 'You', 'nik-voice-ticketing-ai' );
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
									<?php echo wp_kses_post( wpautop( esc_html( $r['message'] ) ) ); ?>
								</div>
							</div>
						<?php endforeach; ?>
					<?php else : ?>
						<p style="color: #64748b; font-size: 13.5px;"><?php esc_html_e( 'Our support team has received your ticket and is preparing a response.', 'nik-voice-ticketing-ai' ); ?></p>
					<?php endif; ?>
				</div>

				<!-- Customer Reply Box -->
				<form method="post" style="border-top: 1px solid #edf2f7; padding-top: 20px;">
					<?php wp_nonce_field( 'nikvotia_user_reply', 'nikvotia_user_reply_nonce' ); ?>
					<h4 style="margin: 0 0 10px 0; font-size: 14px; font-weight: 600;"><?php esc_html_e( 'Send a Message to Support', 'nik-voice-ticketing-ai' ); ?></h4>
					<textarea name="nikvotia_user_message" rows="4" style="width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 12px; font-size: 14px; margin-bottom: 12px; box-sizing: border-box;" placeholder="<?php esc_attr_e( 'Type your question or additional details here...', 'nik-voice-ticketing-ai' ); ?>" required></textarea>
					<button type="submit" class="nik-reply-btn">
						<?php esc_html_e( 'Submit Reply', 'nik-voice-ticketing-ai' ); ?>
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
			$portal_page_id = get_option( 'nikvotia_portal_page_id', 0   );
			$portal_url = $portal_page_id ? get_permalink( $portal_page_id ) : home_url();
		}
		?>
		<div class="nik-vd-woo-dashboard-card" style="background: #0f172a; color: #ffffff; padding: 22px 26px; border-radius: 12px; margin: 24px 0; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
			<div>
				<div style="font-size: 18px; font-weight: 700; color: #38bdf8; display: flex; align-items: center; gap: 8px;">
					🎙️ <?php esc_html_e( 'Voice Support & Tickets', 'nik-voice-ticketing-ai' ); ?>
				</div>
				<div style="font-size: 13.5px; color: #94a3b8; margin-top: 4px;">
					<?php esc_html_e( 'Have a question about your order or need assistance? Submit or track your voice tickets.', 'nik-voice-ticketing-ai' ); ?>
				</div>
			</div>
			<a href="<?php echo esc_url( $portal_url ); ?>" style="background: #2563eb; color: #ffffff !important; text-decoration: none !important; padding: 10px 22px; border-radius: 8px; font-weight: 600; font-size: 14px; display: inline-block;">
				<?php esc_html_e( 'Manage Voice Tickets →', 'nik-voice-ticketing-ai' ); ?>
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
		$portal_page_id = get_option( 'nikvotia_portal_page_id', 0   );
		$portal_url = $portal_page_id ? get_permalink( $portal_page_id ) : ( function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'voicedesk-tickets' ) : home_url() );
		?>
		<h2><?php esc_html_e( 'VoiceDesk Support Tickets', 'nik-voice-ticketing-ai' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><label><?php esc_html_e( 'Customer Support Portal', 'nik-voice-ticketing-ai' ); ?></label></th>
				<td>
					<a href="<?php echo esc_url( $portal_url ); ?>" class="button button-secondary" target="_blank">
						🎙️ <?php esc_html_e( 'View Customer Voice Tickets', 'nik-voice-ticketing-ai' ); ?>
					</a>
					<p class="description"><?php esc_html_e( 'Direct link to the customer voice ticketing portal.', 'nik-voice-ticketing-ai' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}
}
