<?php
/**
 * Homepage hero background: video, then a photo, then the gradient-glow
 * treatment as the default. Priority order matches what actually reads as
 * "exciting" on real sports sites — video > photo > abstract graphic —
 * without ever leaving the hero broken if nothing's been uploaded yet.
 *
 * Photo uses the Home page's ordinary featured image (no extra field
 * needed). Video is a dedicated meta field since WP has no "featured
 * video" concept — reuses the same plain wp.media picker pattern as the
 * Resources file-attach box (see inc/resources.php).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'add_meta_boxes', function () {
	add_meta_box( 'icsa_hero_video', __( 'Hero video (Home page only)', 'icsa' ), 'icsa_render_hero_video_meta_box', 'page', 'side', 'default' );
} );

function icsa_render_hero_video_meta_box( $post ) {
	if ( (int) get_option( 'page_on_front' ) !== $post->ID ) {
		echo '<p>' . esc_html__( 'Only used on the page set as the site homepage.', 'icsa' ) . '</p>';
		return;
	}
	wp_nonce_field( 'icsa_hero_video', 'icsa_hero_video_nonce' );
	$attachment_id = (int) get_post_meta( $post->ID, 'icsa_hero_video_id', true );
	$filename      = $attachment_id ? basename( get_attached_file( $attachment_id ) ) : '';
	?>
	<p>
		<button type="button" class="button" id="icsa-hero-video-button">
			<?php echo $attachment_id ? esc_html__( 'Replace video', 'icsa' ) : esc_html__( 'Select video', 'icsa' ); ?>
		</button>
		<?php if ( $attachment_id ) : ?>
			<button type="button" class="button-link-delete" id="icsa-hero-video-remove" style="margin-left:8px;">
				<?php esc_html_e( 'Remove', 'icsa' ); ?>
			</button>
		<?php endif; ?>
	</p>
	<p id="icsa-hero-video-name"><?php echo esc_html( $filename ?: __( 'No video set — falls back to the featured image, then the default gradient.', 'icsa' ) ); ?></p>
	<input type="hidden" name="icsa_hero_video_id" id="icsa-hero-video-id" value="<?php echo esc_attr( $attachment_id ); ?>">
	<script>
	( function () {
		var frame;
		var button = document.getElementById( 'icsa-hero-video-button' );
		var remove = document.getElementById( 'icsa-hero-video-remove' );
		if ( ! button ) { return; }
		button.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			if ( frame ) { frame.open(); return; }
			frame = wp.media( {
				title: <?php echo wp_json_encode( __( 'Select or upload a video', 'icsa' ) ); ?>,
				library: { type: 'video' },
				button: { text: <?php echo wp_json_encode( __( 'Use this video', 'icsa' ) ); ?> },
				multiple: false,
			} );
			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON();
				document.getElementById( 'icsa-hero-video-id' ).value = attachment.id;
				document.getElementById( 'icsa-hero-video-name' ).textContent = attachment.filename;
			} );
			frame.open();
		} );
		if ( remove ) {
			remove.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				document.getElementById( 'icsa-hero-video-id' ).value = '';
				document.getElementById( 'icsa-hero-video-name' ).textContent = <?php echo wp_json_encode( __( 'No video set — falls back to the featured image, then the default gradient.', 'icsa' ) ); ?>;
			} );
		}
	} )();
	</script>
	<?php
}

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	global $post_type;
	if ( in_array( $hook, [ 'post.php', 'post-new.php' ], true ) && 'page' === $post_type ) {
		wp_enqueue_media();
	}
} );

add_action( 'save_post_page', function ( $post_id ) {
	if ( ! isset( $_POST['icsa_hero_video_nonce'] ) ||
		! wp_verify_nonce( $_POST['icsa_hero_video_nonce'], 'icsa_hero_video' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	update_post_meta( $post_id, 'icsa_hero_video_id', (int) ( $_POST['icsa_hero_video_id'] ?? 0 ) );
} );

/**
 * Renders whichever hero background is actually available. Runs on the
 * icsa-hero group block, homepage only.
 */
add_filter( 'render_block', function ( $block_content, $block ) {
	if ( ! is_front_page() || ( $block['blockName'] ?? '' ) !== 'core/group' ) {
		return $block_content;
	}
	if ( ! str_contains( $block['attrs']['className'] ?? '', 'icsa-hero' ) ) {
		return $block_content;
	}

	$home_id = (int) get_option( 'page_on_front' );
	if ( ! $home_id ) {
		return $block_content;
	}

	$video_id = (int) get_post_meta( $home_id, 'icsa_hero_video_id', true );
	if ( $video_id && get_attached_file( $video_id ) ) {
		$video_url     = esc_url( wp_get_attachment_url( $video_id ) );
		$block_content = str_replace( 'icsa-hero', 'icsa-hero has-video', $block_content );
		$media_markup  = '<video class="icsa-hero-media" autoplay muted loop playsinline>' .
			'<source src="' . $video_url . '" type="video/mp4"></video><div class="icsa-hero-scrim"></div>';
		return preg_replace( '/(<section[^>]*>)/', '$1' . $media_markup, $block_content, 1 );
	}

	if ( has_post_thumbnail( $home_id ) ) {
		$url            = esc_url( get_the_post_thumbnail_url( $home_id, 'full' ) );
		$block_content  = str_replace( 'icsa-hero', 'icsa-hero has-photo', $block_content );
		$block_content  = preg_replace(
			'/style="/',
			'style="--icsa-hero-photo:url(' . $url . ');',
			$block_content,
			1
		);
		return $block_content;
	}

	return $block_content;
}, 10, 2 );
