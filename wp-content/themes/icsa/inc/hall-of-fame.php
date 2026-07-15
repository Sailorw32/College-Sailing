<?php
/**
 * Hall of Fame: All-American and All-Academic teams, individual awards,
 * and named regatta trophies. Category names here match what the audit's
 * crawl of the current site actually found (hall-of-fame/all-american,
 * /all-academic, /individuals/competitive-achievement,
 * /regattas/leonard-m.-fowle-trophy) — see docs/audit.md.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function () {
	register_post_type( 'honoree', [
		'labels' => [
			'name'          => __( 'Hall of Fame', 'icsa' ),
			'singular_name' => __( 'Honoree', 'icsa' ),
			'add_new_item'  => __( 'Add New Honoree', 'icsa' ),
			'edit_item'     => __( 'Edit Honoree', 'icsa' ),
			'all_items'     => __( 'All Honorees', 'icsa' ),
			'search_items'  => __( 'Search Honorees', 'icsa' ),
			'not_found'     => __( 'No honorees found', 'icsa' ),
		],
		'public'       => true,
		'show_in_rest' => true,
		'menu_icon'    => 'dashicons-awards',
		'supports'     => [ 'title', 'editor', 'custom-fields' ],
		'has_archive'  => false,
		'rewrite'      => [ 'slug' => 'hall-of-fame', 'with_front' => false ],
	] );

	register_taxonomy( 'honor_category', 'honoree', [
		'labels' => [
			'name'          => __( 'Category', 'icsa' ),
			'singular_name' => __( 'Category', 'icsa' ),
		],
		'hierarchical'      => true,
		'public'            => true,
		'show_in_rest'      => true,
		'show_admin_column' => true,
		'rewrite'           => [ 'slug' => 'hall-of-fame/category' ],
	] );
} );

add_action( 'add_meta_boxes', function () {
	add_meta_box( 'icsa_honor_year', __( 'Year', 'icsa' ), 'icsa_render_honor_year_meta_box', 'honoree', 'side', 'high' );
} );

function icsa_render_honor_year_meta_box( $post ) {
	wp_nonce_field( 'icsa_honor_year', 'icsa_honor_year_nonce' );
	$year = get_post_meta( $post->ID, 'icsa_honor_year', true );
	?>
	<p>
		<label for="icsa_honor_year_field" class="screen-reader-text"><?php esc_html_e( 'Year', 'icsa' ); ?></label>
		<input type="number" id="icsa_honor_year_field" name="icsa_honor_year" value="<?php echo esc_attr( $year ); ?>" style="width:100%" placeholder="2026" min="1930" max="2100">
	</p>
	<?php
}

add_action( 'save_post_honoree', function ( $post_id ) {
	if ( ! isset( $_POST['icsa_honor_year_nonce'] ) ||
		! wp_verify_nonce( $_POST['icsa_honor_year_nonce'], 'icsa_honor_year' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	update_post_meta( $post_id, 'icsa_honor_year', sanitize_text_field( $_POST['icsa_honor_year'] ?? '' ) );
} );

/**
 * Sorts Honoree queries most-recent-year-first. Same pattern as the Racing
 * section's date filter, keyed on post type via block query context rather
 * than a className (see docs/audit.md-adjacent note in inc/racing.php for
 * why: this filter runs on the inner post-template block).
 */
add_filter( 'query_loop_block_query_vars', function ( $query, $block, $page ) {
	if ( ( $block->context['query']['postType'] ?? '' ) !== 'honoree' ) {
		return $query;
	}
	$query['meta_key'] = 'icsa_honor_year';
	$query['orderby']  = 'meta_value_num';
	$query['order']    = 'DESC';
	return $query;
}, 10, 3 );

/**
 * Small "Class of 2026" style year badge, injected the same way the
 * Resources download button and Racing date badge are: a marker paragraph
 * in the template, replaced via render_block based on post type.
 */
add_filter( 'render_block', function ( $block_content, $block ) {
	if ( ( $block['blockName'] ?? '' ) !== 'core/paragraph' ) {
		return $block_content;
	}
	if ( ! str_contains( $block['attrs']['className'] ?? '', 'icsa-honor-year-slot' ) ) {
		return $block_content;
	}
	if ( get_post_type() !== 'honoree' ) {
		return $block_content;
	}

	$year = get_post_meta( get_the_ID(), 'icsa_honor_year', true );
	if ( ! $year ) {
		return '';
	}
	return '<p class="icsa-honor-year-slot"><span class="icsa-honor-year">' . esc_html( $year ) . '</span></p>';
}, 10, 2 );
