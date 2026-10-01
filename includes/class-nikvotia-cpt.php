<?php
/**
 * Custom Post Type and Clean Pro Admin Ticket Dashboard for Nik VoiceDesk AI.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nikvotia_CPT {

	public function init() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'all_admin_notices', array( $this, 'render_listing_leaderboard' ) );
		add_action( 'add_meta_boxes', array( $this, 'configure_meta_boxes' ), 99 );
		add_filter( 'manage_voicedesk_ticket_posts_columns', array( $this, 'set_custom_columns' ) );
		add_action( 'manage_voicedesk_ticket_posts_custom_column' , array( $this, 'custom_column_data' ), 10, 2 );
		add_action( 'save_post_voicedesk_ticket', array( $this, 'save_ticket_data' ), 10, 2 );
	}

	public function render_listing_leaderboard() {
		global $pagenow, $post_type;
		if ( 'edit.php' === $pagenow && 'voicedesk_ticket' === $post_type ) {
			Nikvotia_Settings::render_leaderboard_banner();
			$this->render_listing_header_bar();
		}
	}

	/**
	 * Render unified top header bar on the VoiceDesk Tickets listing page.
	 */
	public function render_listing_header_bar() {
		$counts = wp_count_posts( 'voicedesk_ticket' );
		$total_tickets = (int) ( ( $counts->publish ?? 0 ) + ( $counts->draft ?? 0 ) + ( $counts->pending ?? 0 ) );
		?>
		<div class="nik-vd-admin-console nik-vd-listing-header-bar">
			<div class="nik-vd-header-bar">
				<div class="nik-vd-header-left">
					<div class="nik-vd-ticket-id">
						<span class="dashicons dashicons-tickets-alt" style="color: #0284c7;"></span>
						<span class="nik-vd-id-label"><?php esc_html_e( 'VoiceDesk', 'nik-voice-ticketing-ai' ); ?></span>
						<span class="nik-vd-id-value"><?php esc_html_e( 'Tickets Queue', 'nik-voice-ticketing-ai' ); ?></span>
					</div>

					<div class="nik-vd-customer-pill">
						<span class="dashicons dashicons-tag"></span>
						<strong>v<?php echo esc_html( NIKVOTIA_VERSION ); ?></strong>
					</div>

					<div class="nik-vd-customer-pill">
						<span class="dashicons dashicons-chart-bar"></span>
						<?php /* translators: %d: Total number of tickets */ ?>
						<span><?php echo esc_html( sprintf( __( '%d Total Tickets', 'nik-voice-ticketing-ai' ), $total_tickets ) ); ?></span>
					</div>

					<span class="nik-status-pill nik-status-resolved"><?php esc_html_e( 'ACTIVE', 'nik-voice-ticketing-ai' ); ?></span>
				</div>

				<div class="nik-vd-header-right">
					<button type="button" class="nik-vd-theme-btn" id="nik-vd-theme-toggle" aria-label="<?php esc_attr_e( 'Toggle Dark or Light Mode', 'nik-voice-ticketing-ai' ); ?>">
						<span class="nik-vd-theme-icon">🌙</span>
						<span class="nik-vd-theme-text"><?php esc_html_e( 'Dark Mode', 'nik-voice-ticketing-ai' ); ?></span>
					</button>

					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=voicedesk_ticket&page=nik-voicedesk-settings' ) ); ?>" class="button button-secondary nik-vd-settings-link-btn">
						<span class="dashicons dashicons-admin-generic"></span>
						<span><?php esc_html_e( 'Settings', 'nik-voice-ticketing-ai' ); ?></span>
					</a>
				</div>
			</div>
		</div>
		<?php
	}

	public function register_post_type() {
		$labels = array(
			'name'                  => _x( 'VoiceDesk Tickets', 'Post Type General Name', 'nik-voice-ticketing-ai' ),
			'singular_name'         => _x( 'VoiceDesk Ticket', 'Post Type Singular Name', 'nik-voice-ticketing-ai' ),
			'menu_name'             => __( 'VoiceDesk Tickets', 'nik-voice-ticketing-ai' ),
			'name_admin_bar'        => __( 'VoiceDesk Ticket', 'nik-voice-ticketing-ai' ),
			'archives'              => __( 'Ticket Archives', 'nik-voice-ticketing-ai' ),
			'all_items'             => __( 'All Tickets', 'nik-voice-ticketing-ai' ),
			'add_new'               => __( 'Add Ticket', 'nik-voice-ticketing-ai' ),
			'add_new_item'          => __( 'Add New Ticket', 'nik-voice-ticketing-ai' ),
			'edit_item'             => __( 'Ticket Console', 'nik-voice-ticketing-ai' ),
			'view_item'             => __( 'View Ticket', 'nik-voice-ticketing-ai' ),
			'search_items'          => __( 'Search Tickets', 'nik-voice-ticketing-ai' ),
			'not_found'             => __( 'No tickets found', 'nik-voice-ticketing-ai' ),
			'not_found_in_trash'    => __( 'No tickets found in Trash', 'nik-voice-ticketing-ai' ),
		);

		$args = array(
			'label'                 => __( 'VoiceDesk Ticket', 'nik-voice-ticketing-ai' ),
			'description'           => __( 'VoiceDesk AI Support Tickets', 'nik-voice-ticketing-ai' ),
			'labels'                => $labels,
			// Disable default title and editor to make it 100% clean and structured
			'supports'              => false,
			'hierarchical'          => false,
			'public'                => false,
			'show_ui'               => true,
			'show_in_menu'          => true,
			'menu_position'         => 25,
			'menu_icon'             => 'dashicons-microphone',
			'show_in_admin_bar'     => false,
			'show_in_nav_menus'     => false,
			'can_export'            => true,
			'has_archive'           => false,
			'exclude_from_search'   => true,
			'publicly_queryable'    => false,
			'capability_type'       => 'post',
			'capabilities'          => array(
				'create_posts' => 'do_not_allow',
			),
			'map_meta_cap'          => true,
		);
		register_post_type( 'voicedesk_ticket', $args );
	}

	public function enqueue_admin_assets( $hook ) {
		if ( ! Nikvotia_Settings::is_plugin_admin_page() ) {
			return;
		}

		wp_enqueue_style( 'nikvotia-admin', NIKVOTIA_URL . 'assets/css/admin.css', array(), time() );
		wp_enqueue_script( 'nikvotia-admin', NIKVOTIA_URL . 'assets/js/admin.js', array(), time(), true );
	}

	/**
	 * Remove default clutter metaboxes and add single unified ticket management console.
	 */
	public function configure_meta_boxes() {
		// Remove all standard WordPress post meta boxes to keep it 100% clean
		remove_meta_box( 'submitdiv', 'voicedesk_ticket', 'side' );
		remove_meta_box( 'slugdiv', 'voicedesk_ticket', 'normal' );
		remove_meta_box( 'authordiv', 'voicedesk_ticket', 'normal' );
		remove_meta_box( 'commentsdiv', 'voicedesk_ticket', 'normal' );
		remove_meta_box( 'commentstatusdiv', 'voicedesk_ticket', 'normal' );
		remove_meta_box( 'postcustom', 'voicedesk_ticket', 'normal' );

		add_meta_box(
			'nikvotia_ticket_console',
			__( 'VoiceDesk Ticket Console', 'nik-voice-ticketing-ai' ),
			array( $this, 'render_ticket_console' ),
			'voicedesk_ticket',
			'normal',
			'high'
		);
	}

	public function set_custom_columns( $columns ) {
		$new_columns = array(
			'cb'            => $columns['cb'] ?? '<input type="checkbox" />',
			'ticket_id'     => __( 'Ticket ID', 'nik-voice-ticketing-ai' ),
			'status'        => __( 'Status', 'nik-voice-ticketing-ai' ),
			'department'    => __( 'Department', 'nik-voice-ticketing-ai' ),
			'summary'       => __( 'AI Summary', 'nik-voice-ticketing-ai' ),
			'reporter'      => __( 'Customer', 'nik-voice-ticketing-ai' ),
			'date'          => __( 'Date Created', 'nik-voice-ticketing-ai' ),
		);
		return $new_columns;
	}

	public function custom_column_data( $column, $post_id ) {
		switch ( $column ) {
			case 'ticket_id':
				$ticket_num = get_post_meta( $post_id, '_nikvotia_ticket_number', true ) ?: 'N/A';
				$edit_link = get_edit_post_link( $post_id );
				printf( '<strong><a href="%s" class="nik-admin-ticket-link">#%s</a></strong>', esc_url( $edit_link ), esc_html( $ticket_num ) );
				break;
			case 'status':
				$status = get_post_meta( $post_id, '_nikvotia_status', true ) ?: 'Open';
				$class = 'nik-status-' . sanitize_html_class( strtolower( str_replace( ' ', '-', $status ) ) );
				printf( '<span class="nik-status-pill %s">%s</span>', esc_attr( $class ), esc_html( $status ) );
				break;
			case 'department':
				$dept = get_post_meta( $post_id, '_nikvotia_department', true ) ?: __( 'General Support', 'nik-voice-ticketing-ai' );
				printf( '<span class="nik-dept-badge">%s</span>', esc_html( $dept ) );
				break;
			case 'summary':
				$summary = get_post_meta( $post_id, '_nikvotia_summary', true );
				if ( ! empty( $summary ) ) {
					echo esc_html( wp_trim_words( $summary, 12, '...' ) );
				} else {
					echo '<em>' . esc_html__( 'No summary', 'nik-voice-ticketing-ai' ) . '</em>';
				}
				break;
			case 'reporter':
				$username = get_post_meta( $post_id, '_nikvotia_username', true ) ?: __( 'Guest', 'nik-voice-ticketing-ai' );
				$email = get_post_meta( $post_id, '_nikvotia_user_email', true );
				echo '<strong>' . esc_html( $username ) . '</strong>';
				if ( ! empty( $email ) ) {
					echo '<br><small style="color:#64748b;">' . esc_html( $email ) . '</small>';
				}
				break;
		}
	}

	/**
	 * Render the clean, modern Ticket Management Console.
	 */
	public function render_ticket_console( $post ) {
		wp_nonce_field( 'nikvotia_save_ticket', 'nikvotia_ticket_nonce' );

		$ticket_number = get_post_meta( $post->ID, '_nikvotia_ticket_number', true );
		$audio_url = get_post_meta( $post->ID, '_nikvotia_audio_url', true );
		$stream_url = get_post_meta( $post->ID, '_nikvotia_audio_stream_url', true ) ?: rest_url( 'nikvotia/v1/audio/' . $ticket_number );
		$transcript = get_post_meta( $post->ID, '_nikvotia_transcript', true );
		$summary = get_post_meta( $post->ID, '_nikvotia_summary', true );
		$department = get_post_meta( $post->ID, '_nikvotia_department', true ) ?: 'General Support';
		$status = get_post_meta( $post->ID, '_nikvotia_status', true ) ?: 'Open';
		$priority = get_post_meta( $post->ID, '_nikvotia_priority', true ) ?: 'Normal';
		$page_url = get_post_meta( $post->ID, '_nikvotia_page_url', true );
		$environment = get_post_meta( $post->ID, '_nikvotia_environment', true ) ?: 'Unknown';
		$clicked_elements = get_post_meta( $post->ID, '_nikvotia_clicked_elements', true );
		$username = get_post_meta( $post->ID, '_nikvotia_username', true ) ?: 'Customer';
		$user_email = get_post_meta( $post->ID, '_nikvotia_user_email', true );
		$replies = get_post_meta( $post->ID, '_nikvotia_replies', true );
		if ( ! is_array( $replies ) ) {
			$replies = array();
		}

		$departments_list = array_map( 'trim', explode( ',', get_option( 'nikvotia_departments', 'Sales, Technical Support, Billing, General Support' ) ) );
		if ( ! in_array( $department, $departments_list, true ) ) {
			$departments_list[] = $department;
		}
		?>
		<div class="nik-vd-admin-console">
			<!-- Header Action Bar -->
			<div class="nik-vd-header-bar">
				<div class="nik-vd-header-left">
					<div class="nik-vd-ticket-id">
						<span class="nik-vd-id-label"><?php esc_html_e( 'Ticket', 'nik-voice-ticketing-ai' ); ?></span>
						<span class="nik-vd-id-value">#<?php echo esc_html( $ticket_number ?: $post->ID ); ?></span>
						<button type="button" class="button button-small nik-vd-copy-btn" data-copy="<?php echo esc_attr( $ticket_number ); ?>" title="<?php esc_attr_e( 'Copy Ticket ID', 'nik-voice-ticketing-ai' ); ?>">
							<span class="dashicons dashicons-admin-page"></span>
						</button>
					</div>
					<div class="nik-vd-customer-pill">
						<span class="dashicons dashicons-admin-users"></span>
						<strong><?php echo esc_html( $username ); ?></strong>
						<?php if ( ! empty( $user_email ) ) : ?>
							<a href="mailto:<?php echo esc_attr( $user_email ); ?>">(<?php echo esc_html( $user_email ); ?>)</a>
						<?php endif; ?>
					</div>
					<div class="nik-vd-date-pill">
						<span class="dashicons dashicons-calendar-alt"></span>
						<?php echo esc_html( get_the_date( 'M j, Y \a\t g:i A', $post->ID ) ); ?>
					</div>
				</div>

				<div class="nik-vd-header-right">
					<!-- Theme Switcher Button -->
					<button type="button" class="nik-vd-theme-btn" id="nik-vd-theme-toggle" aria-label="<?php esc_attr_e( 'Toggle Dark or Light Mode', 'nik-voice-ticketing-ai' ); ?>">
						<span class="nik-vd-theme-icon">🌙</span>
						<span class="nik-vd-theme-text"><?php esc_html_e( 'Dark Mode', 'nik-voice-ticketing-ai' ); ?></span>
					</button>

					<div class="nik-vd-control-group">
						<label for="nikvotia_status"><?php esc_html_e( 'Status:', 'nik-voice-ticketing-ai' ); ?></label>
						<select name="nikvotia_status" id="nikvotia_status" class="nik-vd-select">
							<option value="Open" <?php selected( $status, 'Open' ); ?>><?php esc_html_e( 'Open', 'nik-voice-ticketing-ai' ); ?></option>
							<option value="In Progress" <?php selected( $status, 'In Progress' ); ?>><?php esc_html_e( 'In Progress', 'nik-voice-ticketing-ai' ); ?></option>
							<option value="Resolved" <?php selected( $status, 'Resolved' ); ?>><?php esc_html_e( 'Resolved', 'nik-voice-ticketing-ai' ); ?></option>
							<option value="Closed" <?php selected( $status, 'Closed' ); ?>><?php esc_html_e( 'Closed', 'nik-voice-ticketing-ai' ); ?></option>
						</select>
					</div>

					<div class="nik-vd-control-group">
						<label for="nikvotia_department"><?php esc_html_e( 'Department:', 'nik-voice-ticketing-ai' ); ?></label>
						<select name="nikvotia_department" id="nikvotia_department" class="nik-vd-select">
							<?php foreach ( $departments_list as $d ) : ?>
								<option value="<?php echo esc_attr( $d ); ?>" <?php selected( $department, $d ); ?>><?php echo esc_html( $d ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="nik-vd-control-group">
						<label for="nikvotia_priority"><?php esc_html_e( 'Priority:', 'nik-voice-ticketing-ai' ); ?></label>
						<select name="nikvotia_priority" id="nikvotia_priority" class="nik-vd-select">
							<option value="Low" <?php selected( $priority, 'Low' ); ?>><?php esc_html_e( 'Low', 'nik-voice-ticketing-ai' ); ?></option>
							<option value="Normal" <?php selected( $priority, 'Normal' ); ?>><?php esc_html_e( 'Normal', 'nik-voice-ticketing-ai' ); ?></option>
							<option value="High" <?php selected( $priority, 'High' ); ?>><?php esc_html_e( 'High', 'nik-voice-ticketing-ai' ); ?></option>
							<option value="Urgent" <?php selected( $priority, 'Urgent' ); ?>><?php esc_html_e( 'Urgent', 'nik-voice-ticketing-ai' ); ?></option>
						</select>
					</div>

					<button type="submit" name="save" class="button button-primary button-large nik-vd-save-btn">
						<span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Save Ticket', 'nik-voice-ticketing-ai' ); ?>
					</button>
				</div>
			</div>

			<!-- Main 2-Column Content Grid -->
			<div class="nik-vd-grid-layout">
				<!-- Left Column: Context, Audio, AI Analysis -->
				<div class="nik-vd-col-main">
					<!-- Audio Recording Card -->
					<div class="nik-vd-card">
						<div class="nik-vd-card-header">
							<h3><span class="dashicons dashicons-controls-volumeon"></span> <?php esc_html_e( 'Voice Recording', 'nik-voice-ticketing-ai' ); ?></h3>
							<?php if ( $audio_url ) : ?>
								<a href="<?php echo esc_url( $audio_url ); ?>" download class="button button-secondary button-small" target="_blank">
									<span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Download Audio', 'nik-voice-ticketing-ai' ); ?>
								</a>
							<?php endif; ?>
						</div>
						<div class="nik-vd-card-body">
							<?php if ( $audio_url || $stream_url ) : ?>
								<div class="nik-vd-player-wrapper">
									<audio controls preload="metadata" class="nik-vd-audio-player">
										<source src="<?php echo esc_url( $stream_url ); ?>" type="audio/webm">
										<?php if ( $audio_url ) : ?>
											<source src="<?php echo esc_url( $audio_url ); ?>" type="audio/webm">
											<source src="<?php echo esc_url( $audio_url ); ?>" type="audio/wav">
										<?php endif; ?>
										<?php esc_html_e( 'Your browser does not support the audio element.', 'nik-voice-ticketing-ai' ); ?>
									</audio>
								</div>
							<?php else : ?>
								<div class="nik-vd-empty-notice"><?php esc_html_e( 'No audio recording found for this ticket.', 'nik-voice-ticketing-ai' ); ?></div>
							<?php endif; ?>
						</div>
					</div>

					<!-- AI Analysis & Summary Card -->
					<div class="nik-vd-card">
						<div class="nik-vd-card-header">
							<h3><span class="dashicons dashicons-superhero-alt"></span> <?php esc_html_e( 'AI Summary & Insights', 'nik-voice-ticketing-ai' ); ?></h3>
							<span class="nik-vd-ai-badge"><?php esc_html_e( 'AI Processed', 'nik-voice-ticketing-ai' ); ?></span>
						</div>
						<div class="nik-vd-card-body">
							<div class="nik-vd-summary-box">
								<?php echo wp_kses_post( wpautop( esc_html( $summary ?: __( 'No AI summary available.', 'nik-voice-ticketing-ai' ) ) ) ); ?>
							</div>

							<div class="nik-vd-transcript-section">
								<h4><span class="dashicons dashicons-text"></span> <?php esc_html_e( 'Full Voice Transcript', 'nik-voice-ticketing-ai' ); ?></h4>
								<div class="nik-vd-transcript-content">
									<?php echo nl2br( esc_html( $transcript ?: __( 'No transcript available.', 'nik-voice-ticketing-ai' ) ) ); ?>
								</div>
							</div>
						</div>
					</div>

					<!-- Customer Environment & Clicked Elements Card -->
					<div class="nik-vd-card">
						<div class="nik-vd-card-header">
							<h3><span class="dashicons dashicons-visibility"></span> <?php esc_html_e( 'Context & Clicked Elements', 'nik-voice-ticketing-ai' ); ?></h3>
						</div>
						<div class="nik-vd-card-body">
							<div class="nik-vd-meta-row">
								<strong><?php esc_html_e( 'Page Recorded On:', 'nik-voice-ticketing-ai' ); ?></strong>
								<?php if ( $page_url ) : ?>
									<a href="<?php echo esc_url( $page_url ); ?>" target="_blank" class="nik-vd-page-link">
										<?php echo esc_html( $page_url ); ?> <span class="dashicons dashicons-external"></span>
									</a>
								<?php else : ?>
									<em><?php esc_html_e( 'Not recorded', 'nik-voice-ticketing-ai' ); ?></em>
								<?php endif; ?>
							</div>

							<div class="nik-vd-meta-row">
								<strong><?php esc_html_e( 'User Environment:', 'nik-voice-ticketing-ai' ); ?></strong>
								<span class="nik-vd-env-tag">
									<span class="dashicons dashicons-laptop"></span> <?php echo esc_html( $environment ); ?>
								</span>
							</div>

							<div class="nik-vd-clicks-section">
								<h4><span class="dashicons dashicons-location-alt"></span> <?php esc_html_e( 'Clicked Elements Timeline (During Recording)', 'nik-voice-ticketing-ai' ); ?></h4>
								<?php if ( ! empty( $clicked_elements ) && is_array( $clicked_elements ) ) : ?>
									<div class="nik-vd-timeline">
										<?php
										$step = 1;
										foreach ( $clicked_elements as $el ) :
											$selector = $el['selector'] ?? 'Unknown Element';
											$text = $el['text'] ?? '';
											$tag = $el['tag'] ?? 'ELEMENT';
											$offset_sec = isset( $el['timeOffset'] ) ? round( intval( $el['timeOffset'] ) / 1000, 1 ) : 0;
											$x = isset( $el['x'] ) ? intval( $el['x'] ) : 0;
											$y = isset( $el['y'] ) ? intval( $el['y'] ) : 0;
										?>
											<div class="nik-vd-timeline-item">
												<div class="nik-vd-timeline-marker"><?php echo esc_html( $step++ ); ?></div>
												<div class="nik-vd-timeline-content">
													<div class="nik-vd-timeline-title">
														<span class="nik-vd-tag-badge"><?php echo esc_html( strtoupper( $tag ) ); ?></span>
														<?php if ( ! empty( $text ) ) : ?>
															<strong>"<?php echo esc_html( wp_trim_words( $text, 8, '...' ) ); ?>"</strong>
														<?php endif; ?>
														<span class="nik-vd-time-badge"><?php echo esc_html( $offset_sec ); ?>s</span>
													</div>
													<div class="nik-vd-timeline-details">
														<code><?php echo esc_html( $selector ); ?></code>
														<span class="nik-vd-coords">(X: <?php echo esc_html( $x ); ?>, Y: <?php echo esc_html( $y ); ?>)</span>
													</div>
												</div>
											</div>
										<?php endforeach; ?>
									</div>
								<?php else : ?>
									<div class="nik-vd-empty-notice">
										<span class="dashicons dashicons-info"></span> <?php esc_html_e( 'User did not click on any elements while recording this voice note.', 'nik-voice-ticketing-ai' ); ?>
									</div>
								<?php endif; ?>
							</div>
						</div>
					</div>
				</div>

				<!-- Right Column: Conversation & Staff Replies -->
				<div class="nik-vd-col-side">
					<div class="nik-vd-card nik-vd-replies-card">
						<div class="nik-vd-card-header">
							<h3><span class="dashicons dashicons-format-chat"></span> <?php esc_html_e( 'Ticket Conversation', 'nik-voice-ticketing-ai' ); ?></h3>
							<span class="nik-vd-count-badge"><?php echo esc_html( count( $replies ) ); ?></span>
						</div>

						<div class="nik-vd-card-body">
							<!-- Original Voice Ticket Message -->
							<div class="nik-vd-msg-box nik-vd-msg-customer">
								<div class="nik-vd-msg-head">
									<strong><?php echo esc_html( $username ); ?></strong>
									<span class="nik-vd-role-badge nik-role-customer"><?php esc_html_e( 'Customer', 'nik-voice-ticketing-ai' ); ?></span>
									<span class="nik-vd-msg-time"><?php echo esc_html( get_the_date( 'M j, g:i A', $post->ID ) ); ?></span>
								</div>
								<div class="nik-vd-msg-body">
									<em><?php esc_html_e( 'Submitted initial voice ticket:', 'nik-voice-ticketing-ai' ); ?></em>
									<p><strong>"<?php echo esc_html( wp_trim_words( $transcript ?: $summary, 40, '...' ) ); ?>"</strong></p>
								</div>
							</div>

							<!-- Replies Thread -->
							<div class="nik-vd-replies-thread">
								<?php if ( ! empty( $replies ) ) : ?>
									<?php foreach ( $replies as $r ) :
										$is_staff = ( $r['role'] ?? '' ) === 'staff' || ( $r['author'] ?? '' ) === 'Admin';
										$role_class = $is_staff ? 'nik-vd-msg-staff' : 'nik-vd-msg-customer';
										$badge_class = $is_staff ? 'nik-role-staff' : 'nik-role-customer';
										$role_label = $is_staff ? __( 'Support Staff', 'nik-voice-ticketing-ai' ) : __( 'Customer', 'nik-voice-ticketing-ai' );
									?>
										<div class="nik-vd-msg-box <?php echo esc_attr( $role_class ); ?>">
											<div class="nik-vd-msg-head">
												<strong><?php echo esc_html( $r['author'] ); ?></strong>
												<span class="nik-vd-role-badge <?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( $role_label ); ?></span>
												<span class="nik-vd-msg-time"><?php echo esc_html( date_i18n( 'M j, g:i A', strtotime( $r['date'] ?? 'now' ) ) ); ?></span>
											</div>
											<div class="nik-vd-msg-body">
												<?php echo wp_kses_post( wpautop( esc_html( $r['message'] ) ) ); ?>
											</div>
										</div>
									<?php endforeach; ?>
								<?php else : ?>
									<div class="nik-vd-no-replies">
										<p><?php esc_html_e( 'No replies have been sent yet.', 'nik-voice-ticketing-ai' ); ?></p>
									</div>
								<?php endif; ?>
							</div>

							<!-- Staff Reply Composer -->
							<div class="nik-vd-composer">
								<h4><span class="dashicons dashicons-edit"></span> <?php esc_html_e( 'Reply to Customer', 'nik-voice-ticketing-ai' ); ?></h4>
								<textarea name="nikvotia_admin_reply" id="nikvotia_admin_reply" rows="4" placeholder="<?php esc_attr_e( 'Type your response to the customer here...', 'nik-voice-ticketing-ai' ); ?>"></textarea>
								
								<div class="nik-vd-composer-actions">
									<label class="nik-vd-checkbox-label">
										<input type="checkbox" name="nikvotia_email_reply" value="1" <?php checked( ! empty( $user_email ) ); ?> <?php disabled( empty( $user_email ) ); ?>>
										<?php if ( ! empty( $user_email ) ) : ?>
											<span>
												<?php
												/* translators: %s: Customer email address */
												printf( esc_html__( 'Email reply to %s', 'nik-voice-ticketing-ai' ), esc_html( $user_email ) );
												?>
											</span>
										<?php else : ?>
											<span><?php esc_html_e( 'No customer email on file to notify', 'nik-voice-ticketing-ai' ); ?></span>
										<?php endif; ?>
									</label>

									<button type="submit" name="save" class="button button-primary">
										<span class="dashicons dashicons-arrow-right-alt"></span> <?php esc_html_e( 'Post & Send Reply', 'nik-voice-ticketing-ai' ); ?>
									</button>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Save updated ticket metadata and replies.
	 */
	public function save_ticket_data( $post_id, $post ) {
		if ( ! isset( $_POST['nikvotia_ticket_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nikvotia_ticket_nonce'] ) ), 'nikvotia_save_ticket' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Update Status
		if ( isset( $_POST['nikvotia_status'] )  ) {
			$post_status = sanitize_text_field( wp_unslash( $_POST['nikvotia_status']  ) );
			update_post_meta( $post_id, '_nikvotia_status', $post_status );
		}

		// Update Department
		if ( isset( $_POST['nikvotia_department'] )  ) {
			$dept = sanitize_text_field( wp_unslash( $_POST['nikvotia_department']  ) );
			update_post_meta( $post_id, '_nikvotia_department', $dept );
			
			$ticket_num = get_post_meta( $post_id, '_nikvotia_ticket_number', true );
			
			// Unhook to prevent infinite loop
			remove_action( 'save_post_voicedesk_ticket', array( $this, 'save_ticket_data' ), 10 );
			wp_update_post( array(
				'ID'         => $post_id,
				/* translators: 1: Ticket identifier, 2: Department name */
				'post_title' => sprintf( __( 'Ticket %1$s - %2$s', 'nik-voice-ticketing-ai' ), $ticket_num, $dept ),
			) );
			add_action( 'save_post_voicedesk_ticket', array( $this, 'save_ticket_data' ), 10, 2 );
		}

		// Update Priority
		if ( isset( $_POST['nikvotia_priority'] )  ) {
			$post_priority = sanitize_text_field( wp_unslash( $_POST['nikvotia_priority']  ) );
			update_post_meta( $post_id, '_nikvotia_priority', $post_priority );
		}

		// Handle Staff Reply
		$admin_reply_val = $_POST['nikvotia_admin_reply'] ;
		if ( ! empty( $admin_reply_val ) ) {
			$reply_text = sanitize_textarea_field( wp_unslash( $admin_reply_val ) );
			$current_user = wp_get_current_user();
			$staff_name = $current_user->display_name ?: $current_user->user_login;

			$replies = get_post_meta( $post_id, '_nikvotia_replies', true );
			if ( ! is_array( $replies ) ) {
				$replies = array();
			}

			// Deduplication: prevent duplicate reply and duplicate email on browser refresh or resubmission
			$is_duplicate = false;
			if ( ! empty( $replies ) ) {
				$last_reply = end( $replies );
				if (
					isset( $last_reply['role'], $last_reply['message'] ) &&
					'staff' === $last_reply['role'] &&
					trim( $last_reply['message'] ) === trim( $reply_text )
				) {
					$is_duplicate = true;
				}
			}

			if ( ! $is_duplicate && ! empty( $reply_text ) ) {
				$replies[] = array(
					'author'  => $staff_name,
					'role'    => 'staff',
					'message' => $reply_text,
					'date'    => current_time( 'mysql' ),
				);
				update_post_meta( $post_id, '_nikvotia_replies', $replies );

				// Send Email if checked
				if ( ! empty( $_POST['nikvotia_email_reply'] )  ) {
					$customer_email = get_post_meta( $post_id, '_nikvotia_user_email', true );
					$customer_name = get_post_meta( $post_id, '_nikvotia_username', true ) ?: 'Customer';
					$ticket_num = get_post_meta( $post_id, '_nikvotia_ticket_number', true );

					if ( ! empty( $customer_email ) ) {
						$site_name = get_bloginfo( 'name' );
						/* translators: 1: Site name, 2: Ticket number */
						$subject = sprintf( __( '[%1$s] New Reply on Ticket #%2$s', 'nik-voice-ticketing-ai' ), $site_name, $ticket_num );
						$portal_page_id = get_option( 'nikvotia_portal_page_id', 0 );
						$portal_url = $portal_page_id ? get_permalink( $portal_page_id ) : home_url();

						$body = sprintf(
							/* translators: 1: Customer name, 2: Ticket number, 3: Staff reply message, 4: Customer portal URL, 5: Site name */
							__( 'Hello %1$s,

A member of our support team has replied to your ticket #%2$s:

"%3$s"

You can view and reply to this ticket directly in your customer portal:
%4$s

Best regards,
%5$s Support Team', 'nik-voice-ticketing-ai' ),
							$customer_name,
							$ticket_num,
							$reply_text,
							$portal_url,
							$site_name
						);

						wp_mail( $customer_email, $subject, $body );
					}
				}
			}

			// Clear POST variable to prevent re-submission in current lifecycle
			unset( $_POST['nikvotia_admin_reply'] );

			// Hook PRG (Post-Redirect-Get) to clean the redirect URL
			add_filter( 'redirect_post_location', array( $this, 'clean_redirect_url' ), 10, 2 );
		}
	}

	/**
	 * Ensure clean GET redirect after saving ticket to prevent form re-submission on refresh.
	 */
	public function clean_redirect_url( $location, $post_id ) {
		return add_query_arg( 'ticket_updated', '1', remove_query_arg( array( 'nikvotia_admin_reply', 'nikvotia_email_reply' ), $location ) );
	}
}
