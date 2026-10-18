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
		add_shortcode( 'nik_voicedesk_tickets', array( $this, 'render_user_panel_shortcode' ) );

		// Default portal page auto-provisioning
		add_action( 'init', array( $this, 'ensure_portal_page_on_init' ) );

		// WooCommerce "My Account" Endpoint & Dashboard Integration
		add_action( 'init', array( $this, 'wc_add_endpoint' ) );
		add_filter( 'query_vars', array( $this, 'wc_add_query_vars' ), 0 );
		add_filter( 'woocommerce_get_query_vars', array( $this, 'wc_add_query_vars' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'wc_add_menu_item' ) );
		add_action( 'woocommerce_account_voicedesk-tickets_endpoint', array( $this, 'wc_endpoint_content' ) );
		add_action( 'woocommerce_account_dashboard', array( $this, 'wc_account_dashboard_ticket_btn' ) );
		add_action( 'woocommerce_before_customer_login_form', array( $this, 'wc_before_login_ticket_notice' ) );
		add_action( 'show_user_profile', array( $this, 'render_user_profile_ticket_section' ) );
		add_action( 'edit_user_profile', array( $this, 'render_user_profile_ticket_section' ) );

		// Self-healing rewrite rules, 404 fallback & guest security redirect
		add_action( 'wp_loaded', array( $this, 'maybe_flush_rewrite_rules' ) );
		add_action( 'template_redirect', array( $this, 'handle_404_endpoint_fallback' ) );
		add_action( 'template_redirect', array( $this, 'maybe_redirect_guest_portal' ) );
	}

	/**
	 * Automatically ensure a default frontend page with the required shortcode exists.
	 *
	 * If WooCommerce is not installed, this method ensures a published WordPress page
	 * containing [nikvotia_tickets] is created and configured as the active portal page.
	 *
	 * @return int Page ID.
	 */
	public static function ensure_portal_page() {
		$portal_page_id = (int) get_option( 'nikvotia_portal_page_id', 0 );

		if ( $portal_page_id > 0 ) {
			$page = get_post( $portal_page_id );
			if ( $page && 'page' === $page->post_type && 'publish' === $page->post_status ) {
				// Ensure shortcode is present in content
				if ( false === strpos( $page->post_content, '[nikvotia_tickets]' ) && false === strpos( $page->post_content, '[nik_voicedesk_tickets]' ) ) {
					wp_update_post( array(
						'ID'           => $page->ID,
						'post_content' => $page->post_content . "\n\n<!-- wp:shortcode -->\n[nikvotia_tickets]\n<!-- /wp:shortcode -->",
					) );
				}
				return $page->ID;
			}
		}

		// Check if an existing published page contains our shortcode
		$existing_pages = get_posts( array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			's'              => 'nikvotia_tickets',
		) );

		if ( ! empty( $existing_pages ) ) {
			update_option( 'nikvotia_portal_page_id', $existing_pages[0]->ID );
			return $existing_pages[0]->ID;
		}

		// Check if a page with slug 'support-tickets' or 'my-tickets' exists
		$existing_by_slug = get_page_by_path( 'support-tickets' ) ?: get_page_by_path( 'my-tickets' );
		if ( $existing_by_slug && 'publish' === $existing_by_slug->post_status ) {
			if ( false === strpos( $existing_by_slug->post_content, '[nikvotia_tickets]' ) && false === strpos( $existing_by_slug->post_content, '[nik_voicedesk_tickets]' ) ) {
				wp_update_post( array(
					'ID'           => $existing_by_slug->ID,
					'post_content' => $existing_by_slug->post_content . "\n\n<!-- wp:shortcode -->\n[nikvotia_tickets]\n<!-- /wp:shortcode -->",
				) );
			}
			update_option( 'nikvotia_portal_page_id', $existing_by_slug->ID );
			return $existing_by_slug->ID;
		}

		// Automatically create a default frontend support tickets page
		$new_page_id = wp_insert_post( array(
			'post_title'     => __( 'Support Tickets', 'nik-voice-ticketing-ai' ),
			'post_name'      => 'support-tickets',
			'post_status'    => 'publish',
			'post_type'      => 'page',
			'post_content'   => "<!-- wp:paragraph -->\n<p>" . __( 'Track your voice tickets, listen to audio recordings, and view support updates.', 'nik-voice-ticketing-ai' ) . "</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n[nikvotia_tickets]\n<!-- /wp:shortcode -->",
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
		) );

		if ( ! is_wp_error( $new_page_id ) && $new_page_id > 0 ) {
			update_option( 'nikvotia_portal_page_id', $new_page_id );
			return $new_page_id;
		}

		return 0;
	}

	/**
	 * Fast init check: ensure default portal page exists when WooCommerce is not active.
	 */
	public function ensure_portal_page_on_init() {
		$portal_page_id = (int) get_option( 'nikvotia_portal_page_id', 0 );
		if ( $portal_page_id > 0 && get_post_status( $portal_page_id ) === 'publish' ) {
			return;
		}
		if ( ! class_exists( 'WooCommerce' ) ) {
			self::ensure_portal_page();
		}
	}

	/**
	 * Get the canonical customer portal URL (WooCommerce endpoint or custom portal page).
	 *
	 * @param string $ticket_num Optional ticket number to append.
	 * @return string Canonical URL.
	 */
	public static function get_portal_url( $ticket_num = '' ) {
		$portal_url = '';

		// 1. If WooCommerce is installed, default to WooCommerce "My Account" voicedesk-tickets endpoint
		if ( class_exists( 'WooCommerce' ) && function_exists( 'wc_get_account_endpoint_url' ) ) {
			$portal_url = wc_get_account_endpoint_url( 'voicedesk-tickets' );
			if ( empty( $portal_url ) && function_exists( 'wc_get_page_permalink' ) ) {
				$portal_url = add_query_arg( 'voicedesk-tickets', '1', wc_get_page_permalink( 'myaccount' ) );
			}
		}

		// 2. Check explicitly configured portal page or automatically ensure default page exists
		$portal_page_id = (int) get_option( 'nikvotia_portal_page_id', 0 );
		if ( $portal_page_id > 0 ) {
			$page_link = get_permalink( $portal_page_id );
			if ( ! empty( $page_link ) ) {
				// If WooCommerce is not installed, custom page takes precedence
				if ( ! class_exists( 'WooCommerce' ) || empty( $portal_url ) ) {
					$portal_url = $page_link;
				}
			}
		}

		// 3. If WooCommerce is not installed and no valid portal URL exists, automatically create & use default page
		if ( empty( $portal_url ) && ! class_exists( 'WooCommerce' ) ) {
			$ensured_id = self::ensure_portal_page();
			if ( $ensured_id > 0 ) {
				$portal_url = get_permalink( $ensured_id );
			}
		}

		// 4. Absolute fallback
		if ( empty( $portal_url ) ) {
			$portal_url = home_url( '/' );
		}

		if ( ! empty( $ticket_num ) ) {
			$portal_url = add_query_arg( 'ticket', rawurlencode( $ticket_num ), $portal_url );
		}

		return esc_url_raw( $portal_url );
	}

	/**
	 * Get the secure destination URL for viewing a ticket.
	 * If the user is logged in, links directly to the ticket portal.
	 * If the user is NOT logged in, redirects to the login page for security,
	 * with a safe redirect_to pointing back to the ticket once authenticated.
	 *
	 * @param string $ticket_num Optional ticket number.
	 * @return string Secure destination URL.
	 */
	public static function get_view_ticket_url( $ticket_num = '' ) {
		$target_ticket_url = self::get_portal_url( $ticket_num );

		// If user is authenticated, link directly to the ticket viewing page
		if ( is_user_logged_in() ) {
			return $target_ticket_url;
		}

		// If user is NOT logged in: redirect to login page for security
		if ( class_exists( 'WooCommerce' ) && function_exists( 'wc_get_page_permalink' ) ) {
			$myaccount_url = wc_get_page_permalink( 'myaccount' );
			if ( ! empty( $myaccount_url ) ) {
				$args = array(
					'redirect_to' => rawurlencode( $target_ticket_url ),
				);
				if ( ! empty( $ticket_num ) ) {
					$args['ticket'] = rawurlencode( $ticket_num );
				}
				return esc_url_raw( add_query_arg( $args, $myaccount_url ) );
			}
		}

		// Default WordPress login URL with safe redirect to the canonical ticket portal URL
		return esc_url_raw( wp_login_url( $target_ticket_url ) );
	}

	/**
	 * Security Enforcer: If an unauthenticated user accesses the ticket portal page
	 * or WooCommerce endpoint, redirect them to the login page with redirect_to parameter.
	 */
	public function maybe_redirect_guest_portal() {
		if ( is_user_logged_in() ) {
			return;
		}

		$is_portal = false;
		$portal_page_id = (int) get_option( 'nikvotia_portal_page_id', 0 );

		if ( $portal_page_id > 0 && is_page( $portal_page_id ) ) {
			$is_portal = true;
		} elseif ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'voicedesk-tickets' ) ) {
			$is_portal = true;
		}

		if ( ! $is_portal && is_singular( 'page' ) ) {
			global $post;
			if ( $post && ( false !== strpos( $post->post_content, '[nikvotia_tickets]' ) || false !== strpos( $post->post_content, '[nik_voicedesk_tickets]' ) ) ) {
				$is_portal = true;
			}
		}

		if ( $is_portal ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$ticket_num = isset( $_GET['ticket'] ) ? sanitize_text_field( wp_unslash( $_GET['ticket'] ) ) : '';
			$login_url  = self::get_view_ticket_url( $ticket_num );
			wp_safe_redirect( $login_url );
			exit;
		}
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

		$portal_url = self::get_portal_url();

		wp_localize_script( 'nikvotia-frontend', 'nikvotiaData', array(
			'restUrl'      => esc_url_raw( rest_url( 'nikvotia/v1/submit' ) ),
			'nonce'        => wp_create_nonce( 'wp_rest' ),
			'portalUrl'    => esc_url_raw( self::get_portal_url() ),
			'viewUrl'      => esc_url_raw( self::get_view_ticket_url() ),
			'isLoggedIn'   => is_user_logged_in(),
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
		$enable_consent   = '1' === (string) get_option( 'nikvotia_enable_consent_notice', '1' );
		$consent_text     = get_option( 'nikvotia_consent_notice_text', __( 'By recording, you consent to AI processing of your voice per our privacy policy.', 'nik-voice-ticketing-ai' ) );
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
					<a id="nik-vd-branding-btn" class="nik-vd-branding" href="https://nikneural.ca/voicedesk-ai/" target="_blank" rel="noopener noreferrer">
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

				<?php if ( $enable_consent && ! empty( $consent_text ) ) : ?>
					<div id="nik-vd-consent-notice" class="nik-vd-consent-notice">
						🔒 <?php echo esc_html( $consent_text ); ?>
					</div>
				<?php endif; ?>

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
					<a href="https://nikneural.ca/voicedesk-ai/" target="_blank" rel="noopener noreferrer">
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
					<button type="button" id="nik-vd-modal-theme-toggle" class="nik-vd-modal-theme-toggle" aria-label="<?php esc_attr_e( 'Toggle Dark or Light Mode', 'nik-voice-ticketing-ai' ); ?>" title="<?php esc_attr_e( 'Toggle Dark/Light Mode', 'nik-voice-ticketing-ai' ); ?>">
						<span id="nik-vd-modal-theme-icon">🌙</span>
					</button>
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
						<a href="<?php echo esc_url( self::get_view_ticket_url() ); ?>" id="nik-vd-view-tickets-btn" class="nik-vd-btn-primary">
							<?php echo esc_html( is_user_logged_in() ? __( 'View My Ticket', 'nik-voice-ticketing-ai' ) : __( 'Sign In to View Ticket', 'nik-voice-ticketing-ai' ) ); ?>
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
	 * [nikvotia_tickets] or [nik_voicedesk_tickets]
	 */
	public function render_user_panel_shortcode( $atts ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only ticket identifier parameter in URL.
		$ticket_num = isset( $_GET['ticket'] ) ? sanitize_text_field( wp_unslash( $_GET['ticket'] ) ) : '';

		if ( ! is_user_logged_in() ) {
			return $this->render_guest_login_prompt( $ticket_num );
		}

		wp_enqueue_style( 'nikvotia-frontend', NIKVOTIA_URL . 'assets/css/frontend.css', array(), NIKVOTIA_VERSION );
		wp_enqueue_script( 'nikvotia-frontend', NIKVOTIA_URL . 'assets/js/frontend.js', array(), NIKVOTIA_VERSION, true );

		ob_start();
		
		if ( ! empty( $ticket_num ) ) {
			$this->render_single_ticket_view( $ticket_num );
		} else {
			$this->render_user_tickets_list();
		}

		return ob_get_clean();
	}

	public function wc_add_endpoint() {
		add_rewrite_endpoint( 'voicedesk-tickets', EP_ROOT | EP_PAGES );
	}

	public function wc_add_query_vars( $vars ) {
		$vars['voicedesk-tickets'] = 'voicedesk-tickets';
		return $vars;
	}

	/**
	 * Automatically flush rewrite rules if endpoint rule is not yet registered in WordPress.
	 */
	public function maybe_flush_rewrite_rules() {
		$version_key = 'nikvotia_endpoint_flushed_' . NIKVOTIA_VERSION;
		if ( ! get_option( $version_key ) ) {
			$this->wc_add_endpoint();
			flush_rewrite_rules( false );
			update_option( $version_key, '1' );
		}
	}

	/**
	 * Self-healing 404 interceptor for the voicedesk-tickets WooCommerce endpoint.
	 * If rewrite rules were not yet saved in Settings > Permalinks, routes into My Account smoothly.
	 */
	public function handle_404_endpoint_fallback() {
		if ( ! is_404() ) {
			return;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( false === strpos( $request_uri, 'voicedesk-tickets' ) ) {
			return;
		}

		// Re-flush rewrite rules in the background so future requests match native rewrite rules
		flush_rewrite_rules( false );

		if ( function_exists( 'wc_get_page_id' ) ) {
			$myaccount_page_id = (int) wc_get_page_id( 'myaccount' );
			if ( $myaccount_page_id > 0 ) {
				global $wp_query, $post;
				$wp_query->is_404            = false;
				$wp_query->is_page           = true;
				$wp_query->queried_object_id = $myaccount_page_id;
				$wp_query->queried_object    = get_post( $myaccount_page_id );
				$post                        = $wp_query->queried_object;
				status_header( 200 );
				set_query_var( 'voicedesk-tickets', '1' );

				$template = get_page_template();
				if ( $template && file_exists( $template ) ) {
					include $template;
					exit;
				}
			}
		}
	}

	/**
	 * Render notice above WooCommerce Customer Login form when arriving with ?ticket=...
	 */
	public function wc_before_login_ticket_notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only ticket query parameter for login notice banner.
		if ( isset( $_GET['ticket'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only ticket query parameter for login notice banner.
			$ticket_num = sanitize_text_field( wp_unslash( $_GET['ticket'] ) );
			?>
			<div class="nik-vd-login-ticket-notice" style="background: #eff6ff; border: 1px solid #bfdbfe; border-left: 4px solid #2563eb; padding: 14px 18px; border-radius: 8px; margin-bottom: 24px; color: #1e3a8a; font-size: 14px; line-height: 1.5;">
				<strong style="display: block; font-size: 15px; margin-bottom: 4px;">
					🔒 <?php
					/* translators: %s: Ticket Number */
					printf( esc_html__( 'Sign in to view Ticket #%s', 'nik-voice-ticketing-ai' ), esc_html( $ticket_num ) );
					?>
				</strong>
				<?php esc_html_e( 'Your voice ticket was received and is protected for your privacy. Please sign in or register with your account to access your voice recording, AI analysis, and support updates.', 'nik-voice-ticketing-ai' ); ?>
			</div>
			<?php
		}
	}

	public function wc_add_menu_item( $items ) {
		$items['voicedesk-tickets'] = __( 'Voice Support Tickets', 'nik-voice-ticketing-ai' );
		return $items;
	}

	public function wc_endpoint_content() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only ticket identifier parameter in URL.
		$ticket_num = isset( $_GET['ticket'] ) ? sanitize_text_field( wp_unslash( $_GET['ticket'] ) ) : '';

		if ( ! is_user_logged_in() ) {
			echo wp_kses_post( $this->render_guest_login_prompt( $ticket_num ) );
			return;
		}

		wp_enqueue_style( 'nikvotia-frontend', NIKVOTIA_URL . 'assets/css/frontend.css', array(), NIKVOTIA_VERSION );
		wp_enqueue_script( 'nikvotia-frontend', NIKVOTIA_URL . 'assets/js/frontend.js', array(), NIKVOTIA_VERSION, true );

		if ( ! empty( $ticket_num ) ) {
			$this->render_single_ticket_view( $ticket_num );
		} else {
			$this->render_user_tickets_list();
		}
	}

	private function render_guest_login_prompt( $ticket_num = '' ) {
		$target_url = self::get_portal_url( $ticket_num );
		$login_url  = wp_login_url( $target_url );

		$title = ! empty( $ticket_num )
			/* translators: %s: Ticket Number */
			? sprintf( __( 'Sign In to View Ticket #%s', 'nik-voice-ticketing-ai' ), esc_html( $ticket_num ) )
			: __( 'Customer Ticket Portal', 'nik-voice-ticketing-ai' );

		$desc = ! empty( $ticket_num )
			/* translators: %s: Ticket Number */
			? sprintf( __( 'Your voice ticket #%s has been securely received. To protect your privacy and conversation details, support tickets can only be viewed by registered account holders. Please sign in or register to view your ticket and support responses.', 'nik-voice-ticketing-ai' ), esc_html( $ticket_num ) )
			: __( 'Please sign in to your account to view your voice tickets, track status, and read replies from our support team.', 'nik-voice-ticketing-ai' );

		return '
		<div style="max-width: 540px; margin: 40px auto; padding: 32px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.05); font-family: -apple-system, sans-serif;">
			<div style="font-size: 40px; margin-bottom: 12px;">🔒</div>
			<h3 style="margin: 0 0 10px 0; color: #0f172a; font-size: 20px;">' . esc_html( $title ) . '</h3>
			<p style="color: #64748b; font-size: 14px; margin-bottom: 24px; line-height: 1.6;">' . esc_html( $desc ) . '</p>
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
					<a href="https://nikneural.ca/voicedesk-ai/" target="_blank" rel="noopener noreferrer">
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

		// Strict Security Check: only the authenticated author (user_id > 0), admin, or matching verified email can view this ticket
		$post_author_id = (int) $post->post_author;
		$can_view       = false;
		if ( current_user_can( 'manage_options' ) ) {
			$can_view = true;
		} elseif ( $user_id > 0 && $post_author_id === $user_id ) {
			$can_view = true;
		}

		// If ticket was recorded while guest (author 0), bind to account if email matches
		if ( ! $can_view && $user_id > 0 ) {
			$ticket_email = get_post_meta( $post->ID, '_nikvotia_user_email', true );
			$current_user = wp_get_current_user();
			$user_email   = $current_user->user_email;

			if ( ! empty( $ticket_email ) && ! empty( $user_email ) && strtolower( trim( (string) $ticket_email ) ) === strtolower( trim( (string) $user_email ) ) ) {
				$can_view = true;
				if ( 0 === $post_author_id ) {
					wp_update_post( array(
						'ID'          => $post->ID,
						'post_author' => $user_id,
					) );
				}
			}
		}

		if ( ! $can_view ) {
			echo '<div style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 20px; border-radius: 8px; text-align: center;">';
			echo '<strong>' . esc_html__( 'Access Denied', 'nik-voice-ticketing-ai' ) . '</strong><br>';
			echo esc_html__( 'You do not have permission to view this support ticket. Please ensure you are logged in with the account associated with this ticket.', 'nik-voice-ticketing-ai' );
			echo '</div>';
			return;
		}

		$audio_path = get_post_meta( $post->ID, '_nikvotia_audio_path', true ) ?: get_post_meta( $post->ID, '_nik_audio_path', true );
		$has_audio  = ! empty( $audio_path ) && file_exists( $audio_path );
		$stream_url = get_post_meta( $post->ID, '_nikvotia_audio_stream_url', true ) ?: ( class_exists( 'Nikvotia_API' ) ? Nikvotia_API::generate_stream_url( $ticket_number, $post->ID ) : rest_url( 'nikvotia/v1/audio/' . $ticket_number ) );
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

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success notice parameter from PRG redirect.
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
				
				<?php if ( $has_audio && $stream_url ) : ?>
					<audio controls class="nik-audio-player">
						<source src="<?php echo esc_url( $stream_url ); ?>" type="audio/webm">
						<?php esc_html_e( 'Your browser does not support the audio element.', 'nik-voice-ticketing-ai' ); ?>
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
		$portal_url = self::get_portal_url();
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
		$portal_url = self::get_portal_url();
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
