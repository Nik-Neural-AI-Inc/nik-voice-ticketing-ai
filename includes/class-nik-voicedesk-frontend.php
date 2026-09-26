<?php
/**
 * Frontend class for injecting assets, the mic button, and shortcodes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nik_VoiceDesk_Frontend {

	public function init() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_footer', array( $this, 'inject_ui' ) );
		add_action( 'wp_head', array( $this, 'inject_custom_css' ) );
		add_shortcode( 'nik_voicedesk_tickets', array( $this, 'render_shortcode' ) );

		// WooCommerce Integration
		add_action( 'init', array( $this, 'wc_add_endpoint' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'wc_add_menu_item' ) );
		add_action( 'woocommerce_account_voicedesk-tickets_endpoint', array( $this, 'wc_endpoint_content' ) );
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
			// If we are not on a singular page (e.g. archive), and it's not allowed
			if ( ! is_singular( $allowed_post_types ) && ! is_front_page() && ! is_home() ) {
				return false;
			}
			// Special handling for front page/home if they are pages
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

		$tooltip_text = get_option( 'nik_voicedesk_tooltip_text', __( 'Speak your issue and click on the problematic areas on this page.', 'nik-voicedesk' ) );

		wp_localize_script( 'nik-voicedesk-frontend', 'nikVoiceDeskData', array(
			'restUrl'  => esc_url_raw( rest_url( 'nik-voicedesk/v1/submit' ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'strings'  => array(
				'recording'  => esc_js( $tooltip_text ),
				'processing' => __( 'Processing your request...', 'nik-voicedesk' ),
				'success'    => __( 'Ticket submitted successfully! ID: ', 'nik-voicedesk' ),
				'error'      => __( 'An error occurred. Please try again.', 'nik-voicedesk' ),
				'stop'       => __( 'Stop & Send', 'nik-voicedesk' ),
			),
			'isEnterprise' => Nik_VoiceDesk_Settings::is_enterprise()
		) );
	}

	public function inject_custom_css() {
		if ( ! $this->is_visible() ) {
			return;
		}

		$btn_color = get_option( 'nik_voicedesk_btn_color', '#ffd700' );
		$icon_color = get_option( 'nik_voicedesk_icon_color', '#333333' );
		$overlay_color = get_option( 'nik_voicedesk_overlay_color', 'rgba(255, 215, 0, 0.4)' );
		
		echo "<style>
			:root {
				--nik-vd-btn-bg: {$btn_color};
				--nik-vd-icon-color: {$icon_color};
				--nik-vd-overlay-bg: {$overlay_color};
			}
			#nik-vd-mic-btn { background-color: var(--nik-vd-btn-bg) !important; color: var(--nik-vd-icon-color) !important; }
			#nik-vd-overlay { background-color: var(--nik-vd-overlay-bg) !important; }
		</style>";
	}

	public function inject_ui() {
		if ( ! $this->is_visible() ) {
			return;
		}
		
		$is_enterprise = Nik_VoiceDesk_Settings::is_enterprise();
		$tooltip_text = get_option( 'nik_voicedesk_tooltip_text', __( 'Speak your issue and click on the problematic areas on this page.', 'nik-voicedesk' ) );
		$custom_icon = get_option( 'nik_voicedesk_custom_icon', '' );

		if ( empty( $custom_icon ) ) {
			$custom_icon = '<svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"></path><path d="M19 10v2a7 7 0 0 1-14 0v-2"></path><line x1="12" y1="19" x2="12" y2="23"></line><line x1="8" y1="23" x2="16" y2="23"></line></svg>';
		}
		?>
		<div id="nik-vd-app">
			<button id="nik-vd-mic-btn" aria-label="<?php esc_attr_e( 'Record Voice Ticket', 'nik-voicedesk' ); ?>">
				<?php echo $custom_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</button>
			
			<?php if ( ! $is_enterprise ) : ?>
				<div id="nik-vd-branding-btn" class="nik-vd-branding"><?php esc_html_e( 'Powered by Nik Neural AI', 'nik-voicedesk' ); ?></div>
			<?php endif; ?>

			<div id="nik-vd-overlay" class="nik-vd-hidden">
				<div class="nik-vd-overlay-content">
					<div id="nik-vd-pulse" class="nik-vd-pulse"></div>
					<div id="nik-vd-tooltip"><?php echo esc_html( $tooltip_text ); ?></div>
					<button id="nik-vd-stop-btn"><?php esc_html_e( 'Stop & Send', 'nik-voicedesk' ); ?></button>
					<div id="nik-vd-status" class="nik-vd-hidden"></div>
					<?php if ( ! $is_enterprise ) : ?>
						<div class="nik-vd-branding nik-vd-branding-overlay"><?php esc_html_e( 'Powered by Nik Neural AI', 'nik-voicedesk' ); ?></div>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	public function render_shortcode( $atts ) {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to view your tickets.', 'nik-voicedesk' ) . '</p>';
		}
		
		ob_start();
		$this->render_user_tickets();
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
		$this->render_user_tickets();
	}

	private function render_user_tickets() {
		$user_id = get_current_user_id();
		$args = array(
			'post_type'      => 'voicedesk_ticket',
			'post_status'    => 'publish',
			'author'         => $user_id,
			'posts_per_page' => -1,
		);
		$query = new WP_Query( $args );

		echo '<div class="nik-vd-frontend-dashboard">';
		echo '<h2>' . esc_html__( 'My Voice Support Tickets', 'nik-voicedesk' ) . '</h2>';

		if ( $query->have_posts() ) {
			echo '<ul class="nik-vd-ticket-list">';
			while ( $query->have_posts() ) {
				$query->the_post();
				$department = get_post_meta( get_the_ID(), '_nik_department', true );
				$summary = get_post_meta( get_the_ID(), '_nik_summary', true );
				$ticket_number = get_post_meta( get_the_ID(), '_nik_ticket_number', true );
				?>
				<li class="nik-vd-ticket-item" style="border:1px solid #ddd; padding: 15px; margin-bottom: 15px; border-radius: 4px;">
					<h3><?php echo esc_html( $ticket_number ? 'Ticket ' . $ticket_number : get_the_title() ); ?></h3>
					<p><strong><?php esc_html_e( 'Department:', 'nik-voicedesk' ); ?></strong> <?php echo esc_html( $department ); ?></p>
					<div class="nik-vd-ticket-summary">
						<strong><?php esc_html_e( 'AI Summary:', 'nik-voicedesk' ); ?></strong>
						<?php echo wp_kses_post( wpautop( $summary ) ); ?>
					</div>
					<?php 
					$comments = get_comments( array( 'post_id' => get_the_ID() ) );
					if ( $comments ) {
						echo '<div class="nik-vd-ticket-replies" style="margin-top: 15px; background: #f5f5f5; padding: 10px; border-radius: 4px;">';
						echo '<h4>' . esc_html__( 'Support Replies', 'nik-voicedesk' ) . '</h4>';
						foreach ( $comments as $comment ) {
							echo '<div class="nik-vd-reply" style="margin-bottom: 10px;">';
							echo '<strong>' . esc_html( $comment->comment_author ) . ':</strong> ';
							echo wp_kses_post( wpautop( $comment->comment_content ) );
							echo '</div>';
						}
						echo '</div>';
					}
					?>
				</li>
				<?php
			}
			echo '</ul>';
			wp_reset_postdata();
		} else {
			echo '<p>' . esc_html__( 'You have not submitted any voice tickets yet.', 'nik-voicedesk' ) . '</p>';
		}
		echo '</div>';
	}
}
