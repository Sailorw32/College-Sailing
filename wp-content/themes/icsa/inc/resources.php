<?php
/**
 * The Resources section: a document library for coaches and conference
 * administrators (bylaws, forms, meeting minutes, rule books). Modeled as
 * a custom post type rather than a page of links so each document gets
 * its own URL, category, and search description — none of which the old
 * site's linked-out documents had.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function () {
	register_post_type( 'resource', [
		'labels' => [
			'name'               => __( 'Resources', 'icsa' ),
			'singular_name'      => __( 'Resource', 'icsa' ),
			'add_new_item'       => __( 'Add New Resource', 'icsa' ),
			'edit_item'          => __( 'Edit Resource', 'icsa' ),
			'all_items'          => __( 'All Resources', 'icsa' ),
			'search_items'       => __( 'Search Resources', 'icsa' ),
			'not_found'          => __( 'No resources found', 'icsa' ),
		],
		'public'       => true,
		'show_in_rest' => true,
		'menu_icon'    => 'dashicons-media-document',
		'supports'     => [ 'title', 'editor', 'custom-fields' ],
		// No archive — the "Resources" nav item is a real Page
		// (page-resources.html) that queries this post type itself, so a
		// competing CPT archive at the same URL would just collide with it.
		'has_archive'  => false,
		'rewrite'      => [ 'slug' => 'resources', 'with_front' => false ],
	] );

	register_taxonomy( 'resource_category', 'resource', [
		'labels' => [
			'name'          => __( 'Categories', 'icsa' ),
			'singular_name' => __( 'Category', 'icsa' ),
		],
		'hierarchical'      => true,
		'public'            => true,
		'show_in_rest'      => true,
		'show_admin_column' => true,
		'rewrite'           => [ 'slug' => 'resources/category' ],
	] );
} );

/**
 * File-attach meta box. Deliberately just the core media picker rather
 * than a custom uploader — editors already know this UI from setting
 * featured images, and it needs no build step to maintain.
 */
add_action( 'add_meta_boxes', function () {
	add_meta_box(
		'icsa_resource_file',
		__( 'Attached file', 'icsa' ),
		'icsa_render_resource_file_meta_box',
		'resource',
		'side',
		'high'
	);
} );

function icsa_render_resource_file_meta_box( $post ) {
	wp_nonce_field( 'icsa_resource_file', 'icsa_resource_file_nonce' );
	$attachment_id = (int) get_post_meta( $post->ID, 'icsa_resource_file_id', true );
	$filename      = $attachment_id ? basename( get_attached_file( $attachment_id ) ) : '';
	?>
	<p>
		<button type="button" class="button" id="icsa-resource-file-button">
			<?php echo $attachment_id ? esc_html__( 'Replace file', 'icsa' ) : esc_html__( 'Select file', 'icsa' ); ?>
		</button>
	</p>
	<p id="icsa-resource-file-name"><?php echo esc_html( $filename ?: __( 'No file attached yet.', 'icsa' ) ); ?></p>
	<input type="hidden" name="icsa_resource_file_id" id="icsa-resource-file-id" value="<?php echo esc_attr( $attachment_id ); ?>">
	<script>
	( function () {
		var frame;
		var button = document.getElementById( 'icsa-resource-file-button' );
		if ( ! button ) { return; }
		button.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			if ( frame ) { frame.open(); return; }
			frame = wp.media( {
				title: <?php echo wp_json_encode( __( 'Select or upload a file', 'icsa' ) ); ?>,
				button: { text: <?php echo wp_json_encode( __( 'Use this file', 'icsa' ) ); ?> },
				multiple: false,
			} );
			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON();
				document.getElementById( 'icsa-resource-file-id' ).value = attachment.id;
				document.getElementById( 'icsa-resource-file-name' ).textContent = attachment.filename;
			} );
			frame.open();
		} );
	} )();
	</script>
	<?php
}

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	global $post_type;
	if ( in_array( $hook, [ 'post.php', 'post-new.php' ], true ) && 'resource' === $post_type ) {
		wp_enqueue_media();
	}
} );

add_action( 'save_post_resource', function ( $post_id ) {
	if ( ! isset( $_POST['icsa_resource_file_nonce'] ) ||
		! wp_verify_nonce( $_POST['icsa_resource_file_nonce'], 'icsa_resource_file' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	update_post_meta( $post_id, 'icsa_resource_file_id', (int) ( $_POST['icsa_resource_file_id'] ?? 0 ) );
} );

/**
 * Template helpers: everything a Resource template needs to know about
 * its attached file, or null if nothing's been uploaded yet.
 */
function icsa_get_resource_file( $post_id ) {
	$attachment_id = (int) get_post_meta( $post_id, 'icsa_resource_file_id', true );
	if ( ! $attachment_id ) {
		return null;
	}
	$path = get_attached_file( $attachment_id );
	if ( ! $path || ! file_exists( $path ) ) {
		return null;
	}
	$ext = strtoupper( pathinfo( $path, PATHINFO_EXTENSION ) );
	return [
		'url'  => wp_get_attachment_url( $attachment_id ),
		'ext'  => $ext,
		'size' => size_format( filesize( $path ) ),
	];
}

/**
 * page-resources.html and single-resource.html each leave an empty
 * <p class="icsa-resource-download-slot"> where the download action goes —
 * simplest way to inject conditional markup (file vs. no file yet) into a
 * block template without a full custom block registration.
 */
add_filter( 'render_block', function ( $block_content, $block ) {
	if ( ( $block['blockName'] ?? '' ) !== 'core/paragraph' ) {
		return $block_content;
	}
	if ( ! str_contains( $block['attrs']['className'] ?? '', 'icsa-resource-download-slot' ) ) {
		return $block_content;
	}
	if ( get_post_type() !== 'resource' ) {
		return $block_content;
	}

	$file = icsa_get_resource_file( get_the_ID() );
	if ( $file ) {
		$markup = sprintf(
			'<p class="icsa-resource-download-slot"><a class="icsa-resource-download" href="%s"><span>%s</span><span class="icsa-resource-meta">%s &middot; %s</span></a></p>',
			esc_url( $file['url'] ),
			esc_html__( 'Download', 'icsa' ),
			esc_html( $file['ext'] ),
			esc_html( $file['size'] )
		);
	} else {
		$markup = '<p class="icsa-resource-download-slot"><span class="icsa-resource-pending">' .
			esc_html__( 'File pending upload', 'icsa' ) . '</span></p>';
	}

	return $markup;
}, 10, 2 );
