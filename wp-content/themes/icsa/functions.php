<?php
/**
 * ICSA theme setup.
 *
 * Several of these fixes exist specifically to close out findings from the
 * audit of the previous site (see docs/audit.md): a real per-page <title>,
 * a per-page meta description, and a robots.txt Sitemap pointer.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require get_theme_file_path( 'inc/resources.php' );
require get_theme_file_path( 'inc/racing.php' );

/**
 * Block themes don't auto-load style.css on the front end the way classic
 * themes do — theme.json covers design tokens, but the hand-written CSS
 * helpers in style.css (icsa-hero, icsa-card, dark mode overrides) still
 * need an explicit enqueue.
 */
add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style( 'icsa-style', get_stylesheet_uri(), [], wp_get_theme()->get( 'Version' ) );
} );

add_action( 'after_setup_theme', function () {
	// Lets WP generate a unique, correct <title> per page instead of the
	// old site's single hardcoded string repeated on every URL.
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'editor-styles' );
	add_editor_style( 'style.css' );
} );

/**
 * Trim the default WP <head> output down to what this site actually uses.
 * None of this changes visitor-facing behavior; it just stops shipping
 * unused meta tags and links, the same category of cruft the audit flagged
 * on the old site (IE8 shims, unused feed links, etc).
 */
add_action( 'init', function () {
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'wp_generator' );
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
} );

/**
 * Per-page SEO description: a simple post meta field, editable from the
 * block editor's sidebar, output as <meta name="description">. Falls back
 * to the post excerpt so News posts aren't left blank if an editor skips it.
 */
add_action( 'init', function () {
	register_post_meta( '', 'icsa_meta_description', [
		'type'              => 'string',
		'single'            => true,
		'show_in_rest'      => true,
		'sanitize_callback' => 'sanitize_text_field',
		'auth_callback'     => function () {
			return current_user_can( 'edit_posts' );
		},
	] );
} );

/**
 * Plain meta box for the SEO description field above — deliberately not a
 * React sidebar panel, so it needs no JS build step and still renders fine
 * in the block editor.
 */
add_action( 'add_meta_boxes', function () {
	foreach ( get_post_types( [ 'public' => true ] ) as $post_type ) {
		add_meta_box(
			'icsa_meta_description',
			__( 'Search description', 'icsa' ),
			function ( $post ) {
				wp_nonce_field( 'icsa_meta_description', 'icsa_meta_description_nonce' );
				$value = get_post_meta( $post->ID, 'icsa_meta_description', true );
				echo '<p><label for="icsa_meta_description_field">' .
					esc_html__( 'Shown in search results and when this page is shared. Leave blank to use the excerpt.', 'icsa' ) .
					'</label></p>';
				echo '<textarea id="icsa_meta_description_field" name="icsa_meta_description" rows="3" style="width:100%;">' .
					esc_textarea( $value ) . '</textarea>';
			},
			$post_type,
			'normal',
			'high'
		);
	}
} );

add_action( 'save_post', function ( $post_id ) {
	if ( ! isset( $_POST['icsa_meta_description_nonce'] ) ||
		! wp_verify_nonce( $_POST['icsa_meta_description_nonce'], 'icsa_meta_description' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	update_post_meta( $post_id, 'icsa_meta_description', sanitize_text_field( $_POST['icsa_meta_description'] ?? '' ) );
} );

add_action( 'wp_head', function () {
	if ( is_singular() ) {
		$post        = get_queried_object();
		$description = get_post_meta( $post->ID, 'icsa_meta_description', true );
		if ( ! $description ) {
			$description = wp_strip_all_tags( get_the_excerpt( $post ) );
		}
	} else {
		$description = get_bloginfo( 'description' );
	}

	if ( $description ) {
		printf( '<meta name="description" content="%s">' . "\n", esc_attr( $description ) );
	}
}, 1 );

/**
 * Point robots.txt at the core XML sitemap (built into WP since 5.5, no
 * plugin required) — the old site had neither.
 */
add_filter( 'robots_txt', function ( $output ) {
	return $output . "\nSitemap: " . home_url( '/wp-sitemap.xml' ) . "\n";
} );

/**
 * Register block patterns used by the templates (hero, news feed intro,
 * quick-links, sponsor strip).
 */
add_action( 'init', function () {
	register_block_pattern_category( 'icsa', [ 'label' => __( 'ICSA', 'icsa' ) ] );
} );

/**
 * The homepage hero uses a plain navy chart-line texture by default. If
 * whoever's editing the Home page sets a featured image, use that as the
 * hero photo instead — no template change needed, just add an image.
 */
add_filter( 'render_block', function ( $block_content, $block ) {
	if ( ! is_front_page() || ( $block['blockName'] ?? '' ) !== 'core/group' ) {
		return $block_content;
	}
	if ( ! str_contains( $block['attrs']['className'] ?? '', 'icsa-hero' ) ) {
		return $block_content;
	}

	$home_id = (int) get_option( 'page_on_front' );
	if ( ! $home_id || ! has_post_thumbnail( $home_id ) ) {
		return $block_content;
	}

	$url            = esc_url( get_the_post_thumbnail_url( $home_id, 'full' ) );
	$block_content  = str_replace( 'icsa-hero', 'icsa-hero has-photo', $block_content );
	$block_content  = preg_replace(
		'/style="/',
		'style="--icsa-hero-photo:url(' . $url . ');',
		$block_content,
		1
	);

	return $block_content;
}, 10, 2 );
