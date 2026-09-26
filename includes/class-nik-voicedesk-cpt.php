<?php
/**
 * Custom Post Type and Admin Dashboard class.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nik_VoiceDesk_CPT {

	public function init() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_filter( 'manage_voicedesk_ticket_posts_columns', array( $this, 'set_custom_columns' ) );
		add_action( 'manage_voicedesk_ticket_posts_custom_column' , array( $this, 'custom_column_data' ), 10, 2 );
	}

	public function register_post_type() {
		$labels = array(
			'name'                  => _x( 'VoiceDesk Tickets', 'Post Type General Name', 'nik-voicedesk' ),
			'singular_name'         => _x( 'VoiceDesk Ticket', 'Post Type Singular Name', 'nik-voicedesk' ),
			'menu_name'             => __( 'VoiceDesk Tickets', 'nik-voicedesk' ),
			'name_admin_bar'        => __( 'VoiceDesk Ticket', 'nik-voicedesk' ),
			'archives'              => __( 'Ticket Archives', 'nik-voicedesk' ),
			'attributes'            => __( 'Ticket Attributes', 'nik-voicedesk' ),
			'parent_item_colon'     => __( 'Parent Ticket:', 'nik-voicedesk' ),
			'all_items'             => __( 'All Tickets', 'nik-voicedesk' ),
			'add_new_item'          => __( 'Add New Ticket', 'nik-voicedesk' ),
			'add_new'               => __( 'Add New', 'nik-voicedesk' ),
			'new_item'              => __( 'New Ticket', 'nik-voicedesk' ),
			'edit_item'             => __( 'Edit Ticket', 'nik-voicedesk' ),
			'update_item'           => __( 'Update Ticket', 'nik-voicedesk' ),
			'view_item'             => __( 'View Ticket', 'nik-voicedesk' ),
			'view_items'            => __( 'View Tickets', 'nik-voicedesk' ),
			'search_items'          => __( 'Search Ticket', 'nik-voicedesk' ),
			'not_found'             => __( 'Not found', 'nik-voicedesk' ),
			'not_found_in_trash'    => __( 'Not found in Trash', 'nik-voicedesk' ),
		);
		$args = array(
			'label'                 => __( 'VoiceDesk Ticket', 'nik-voicedesk' ),
			'description'           => __( 'VoiceDesk AI Support Tickets', 'nik-voicedesk' ),
			'labels'                => $labels,
			'supports'              => array( 'title', 'comments' ),
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
				'create_posts' => 'do_not_allow', // Only API should create tickets usually
			),
			'map_meta_cap'          => true,
		);
		register_post_type( 'voicedesk_ticket', $args );
	}

	public function set_custom_columns( $columns ) {
		$new_columns = array();
		foreach ( $columns as $key => $title ) {
			if ( 'title' === $key ) {
				$new_columns['title'] = $title;
				$new_columns['ticket_number'] = __( 'Ticket ID', 'nik-voicedesk' );
			} else {
				$new_columns[$key] = $title;
			}
		}
		
		unset( $new_columns['date'] );
		$new_columns['department'] = __( 'Department', 'nik-voicedesk' );
		$new_columns['reporter']   = __( 'Reporter', 'nik-voicedesk' );
		$new_columns['date']       = __( 'Date', 'nik-voicedesk' );
		return $new_columns;
	}

	public function custom_column_data( $column, $post_id ) {
		switch ( $column ) {
			case 'ticket_number':
				echo esc_html( get_post_meta( $post_id, '_nik_ticket_number', true ) ?: 'N/A' );
				break;
			case 'department':
				echo esc_html( get_post_meta( $post_id, '_nik_department', true ) ?: __( 'Uncategorized', 'nik-voicedesk' ) );
				break;
			case 'reporter':
				$username = get_post_meta( $post_id, '_nik_username', true );
				echo esc_html( $username ? $username : __( 'Guest', 'nik-voicedesk' ) );
				break;
		}
	}

	public function add_meta_boxes() {
		add_meta_box(
			'nik_voicedesk_ticket_details',
			__( 'Ticket Details & AI Analysis', 'nik-voicedesk' ),
			array( $this, 'render_ticket_details_meta_box' ),
			'voicedesk_ticket',
			'normal',
			'high'
		);
	}

	public function render_ticket_details_meta_box( $post ) {
		$ticket_number = get_post_meta( $post->ID, '_nik_ticket_number', true );
		$audio_url = get_post_meta( $post->ID, '_nik_audio_url', true );
		$transcript = get_post_meta( $post->ID, '_nik_transcript', true );
		$summary = get_post_meta( $post->ID, '_nik_summary', true );
		$department = get_post_meta( $post->ID, '_nik_department', true );
		$page_url = get_post_meta( $post->ID, '_nik_page_url', true );
		$environment = get_post_meta( $post->ID, '_nik_environment', true );
		$clicked_elements = get_post_meta( $post->ID, '_nik_clicked_elements', true );
		$status = get_post_meta( $post->ID, '_nik_status', true );

		?>
		<style>
			.nik-vd-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
			.nik-vd-box { background: #f9f9f9; border: 1px solid #e2e4e7; padding: 15px; border-radius: 4px; }
			.nik-vd-box h3 { margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #ddd; }
			.nik-vd-full { grid-column: 1 / -1; }
			.nik-vd-audio { width: 100%; margin-top: 10px; }
		</style>
		<div class="nik-vd-grid">
			
			<div class="nik-vd-box nik-vd-full">
				<h3><?php esc_html_e( 'Ticket Overview', 'nik-voicedesk' ); ?></h3>
				<p><strong><?php esc_html_e( 'Ticket ID:', 'nik-voicedesk' ); ?></strong> <span class="badge"><?php echo esc_html( $ticket_number ?: 'N/A' ); ?></span></p>
				<p><strong><?php esc_html_e( 'Status:', 'nik-voicedesk' ); ?></strong> <?php echo esc_html( $status ?: 'N/A' ); ?></p>
			</div>

			<div class="nik-vd-box">
				<h3><?php esc_html_e( 'Audio & Transcript', 'nik-voicedesk' ); ?></h3>
				<?php if ( $audio_url ) : ?>
					<audio controls class="nik-vd-audio">
						<source src="<?php echo esc_url( $audio_url ); ?>" type="audio/webm">
						<source src="<?php echo esc_url( $audio_url ); ?>" type="audio/wav">
						<?php esc_html_e( 'Your browser does not support the audio element.', 'nik-voicedesk' ); ?>
					</audio>
				<?php else: ?>
					<p><em><?php esc_html_e( 'No audio file attached.', 'nik-voicedesk' ); ?></em></p>
				<?php endif; ?>
				
				<h4><?php esc_html_e( 'Transcript (Whisper AI)', 'nik-voicedesk' ); ?></h4>
				<p><?php echo nl2br( esc_html( $transcript ?: __( 'No transcript available.', 'nik-voicedesk' ) ) ); ?></p>
			</div>

			<div class="nik-vd-box">
				<h3><?php esc_html_e( 'AI Analysis', 'nik-voicedesk' ); ?></h3>
				<p><strong><?php esc_html_e( 'Department:', 'nik-voicedesk' ); ?></strong> <span class="badge"><?php echo esc_html( $department ?: 'N/A' ); ?></span></p>
				<h4><?php esc_html_e( 'AI Summary (GPT-4o-mini)', 'nik-voicedesk' ); ?></h4>
				<div><?php echo wp_kses_post( wpautop( $summary ?: __( 'No summary available.', 'nik-voicedesk' ) ) ); ?></div>
			</div>

			<div class="nik-vd-box nik-vd-full">
				<h3><?php esc_html_e( 'Context & Environment', 'nik-voicedesk' ); ?></h3>
				<p><strong><?php esc_html_e( 'Page URL:', 'nik-voicedesk' ); ?></strong> <a href="<?php echo esc_url( $page_url ); ?>" target="_blank"><?php echo esc_html( $page_url ); ?></a></p>
				<p><strong><?php esc_html_e( 'User Environment:', 'nik-voicedesk' ); ?></strong> <?php echo esc_html( $environment ); ?></p>
				
				<h4><?php esc_html_e( 'Clicked Elements (During Recording)', 'nik-voicedesk' ); ?></h4>
				<?php 
				if ( ! empty( $clicked_elements ) && is_array( $clicked_elements ) ) {
					echo '<ul>';
					foreach ( $clicked_elements as $el ) {
						printf( 
							'<li><code>%s</code> (X: %d, Y: %d) - Time: %s</li>', 
							esc_html( $el['selector'] ?? 'Unknown' ),
							isset( $el['x'] ) ? intval( $el['x'] ) : 0,
							isset( $el['y'] ) ? intval( $el['y'] ) : 0,
							isset( $el['timeOffset'] ) ? esc_html( $el['timeOffset'] ) . 'ms' : '0ms'
						);
					}
					echo '</ul>';
				} else {
					echo '<p><em>' . esc_html__( 'No clicks recorded.', 'nik-voicedesk' ) . '</em></p>';
				}
				?>
			</div>
		</div>
		<?php
	}
}
