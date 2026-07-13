<?php
/**
 * The Racing section: a real, structured event schedule instead of the old
 * site's links out to a dozen separate Google Sheets. Modeled as a custom
 * post type so each regatta gets its own URL, search description, and a
 * proper place in the homepage/Racing page schedule — none of which a
 * spreadsheet link can do.
 *
 * Rankings deliberately aren't rebuilt here: scores.collegesailing.org is
 * a real, actively maintained system (see docs/audit.md — it was the one
 * property that came out of the audit looking modern already), so the
 * Racing page links out to it rather than faking a duplicate table.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function () {
	register_post_type( 'event', [
		'labels' => [
			'name'          => __( 'Events', 'icsa' ),
			'singular_name' => __( 'Event', 'icsa' ),
			'add_new_item'  => __( 'Add New Event', 'icsa' ),
			'edit_item'     => __( 'Edit Event', 'icsa' ),
			'all_items'     => __( 'All Events', 'icsa' ),
			'search_items'  => __( 'Search Events', 'icsa' ),
			'not_found'     => __( 'No events found', 'icsa' ),
		],
		'public'       => true,
		'show_in_rest' => true,
		'menu_icon'    => 'dashicons-calendar-alt',
		'supports'     => [ 'title', 'editor', 'custom-fields' ],
		'has_archive'  => false,
		'rewrite'      => [ 'slug' => 'racing/events', 'with_front' => false ],
	] );

	register_taxonomy( 'championship_type', 'event', [
		'labels' => [
			'name'          => __( 'Championship Type', 'icsa' ),
			'singular_name' => __( 'Championship Type', 'icsa' ),
		],
		'hierarchical'      => true,
		'public'            => true,
		'show_in_rest'      => true,
		'show_admin_column' => true,
		'rewrite'           => [ 'slug' => 'racing/championship' ],
	] );
} );

define( 'ICSA_EVENT_STATUSES', [
	'open'         => 'Registration open',
	'coming-soon'  => 'Coming soon',
	'completed'    => 'Results posted',
] );

add_action( 'add_meta_boxes', function () {
	add_meta_box( 'icsa_event_details', __( 'Event details', 'icsa' ), 'icsa_render_event_meta_box', 'event', 'side', 'high' );
} );

function icsa_render_event_meta_box( $post ) {
	wp_nonce_field( 'icsa_event_details', 'icsa_event_details_nonce' );
	$start  = get_post_meta( $post->ID, 'icsa_event_start', true );
	$end    = get_post_meta( $post->ID, 'icsa_event_end', true );
	$loc    = get_post_meta( $post->ID, 'icsa_event_location', true );
	$status = get_post_meta( $post->ID, 'icsa_event_status', true ) ?: 'coming-soon';
	$link   = get_post_meta( $post->ID, 'icsa_event_link', true );
	?>
	<p>
		<label for="icsa_event_start"><?php esc_html_e( 'Start date', 'icsa' ); ?></label><br>
		<input type="date" id="icsa_event_start" name="icsa_event_start" value="<?php echo esc_attr( $start ); ?>" style="width:100%">
	</p>
	<p>
		<label for="icsa_event_end"><?php esc_html_e( 'End date', 'icsa' ); ?></label><br>
		<input type="date" id="icsa_event_end" name="icsa_event_end" value="<?php echo esc_attr( $end ); ?>" style="width:100%">
	</p>
	<p>
		<label for="icsa_event_location"><?php esc_html_e( 'Location', 'icsa' ); ?></label><br>
		<input type="text" id="icsa_event_location" name="icsa_event_location" value="<?php echo esc_attr( $loc ); ?>" style="width:100%" placeholder="Host school — City, State">
	</p>
	<p>
		<label for="icsa_event_status"><?php esc_html_e( 'Status', 'icsa' ); ?></label><br>
		<select id="icsa_event_status" name="icsa_event_status" style="width:100%">
			<?php foreach ( ICSA_EVENT_STATUSES as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
	</p>
	<p>
		<label for="icsa_event_link"><?php esc_html_e( 'Event info / registration link', 'icsa' ); ?></label><br>
		<input type="url" id="icsa_event_link" name="icsa_event_link" value="<?php echo esc_attr( $link ); ?>" style="width:100%" placeholder="https://">
	</p>
	<?php
}

add_action( 'save_post_event', function ( $post_id ) {
	if ( ! isset( $_POST['icsa_event_details_nonce'] ) ||
		! wp_verify_nonce( $_POST['icsa_event_details_nonce'], 'icsa_event_details' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	update_post_meta( $post_id, 'icsa_event_start', sanitize_text_field( $_POST['icsa_event_start'] ?? '' ) );
	update_post_meta( $post_id, 'icsa_event_end', sanitize_text_field( $_POST['icsa_event_end'] ?? '' ) );
	update_post_meta( $post_id, 'icsa_event_location', sanitize_text_field( $_POST['icsa_event_location'] ?? '' ) );
	update_post_meta( $post_id, 'icsa_event_status', sanitize_text_field( $_POST['icsa_event_status'] ?? 'coming-soon' ) );
	update_post_meta( $post_id, 'icsa_event_link', esc_url_raw( $_POST['icsa_event_link'] ?? '' ) );
} );

/**
 * Every Event query sorts soonest-first by start date and shows only events
 * still upcoming or in progress (end date today or later) — used by both
 * the homepage's three-event preview and the full schedule on the Racing
 * page. Keyed on post type from the block's query context rather than a
 * className: this filter runs on the inner post-template block, not the
 * wp:query block the className attribute was actually set on.
 */
add_filter( 'query_loop_block_query_vars', function ( $query, $block, $page ) {
	if ( ( $block->context['query']['postType'] ?? '' ) !== 'event' ) {
		return $query;
	}

	$query['meta_key'] = 'icsa_event_start';
	$query['orderby']  = 'meta_value';
	$query['order']    = 'ASC';
	// Plain string compare, not 'type' => 'DATE' — ISO 8601 (Y-m-d) sorts
	// correctly as text already, and the CAST(... AS DATE) that 'type' adds
	// isn't understood by the SQLite shim this local dev setup runs on.
	$query['meta_query'] = [
		[
			'key'     => 'icsa_event_end',
			'value'   => current_time( 'Y-m-d' ),
			'compare' => '>=',
		],
	];

	return $query;
}, 10, 3 );

/**
 * "May 16–18" for a same-month range, "May 30 – Jun 1" across months.
 */
function icsa_format_event_dates( $start, $end ) {
	if ( ! $start ) {
		return [ 'month' => '', 'days' => '' ];
	}
	$start_ts = strtotime( $start );
	$end_ts   = $end ? strtotime( $end ) : $start_ts;

	if ( gmdate( 'Y-m', $start_ts ) === gmdate( 'Y-m', $end_ts ) ) {
		$days = gmdate( 'j', $start_ts );
		if ( $end_ts !== $start_ts ) {
			$days .= '&#8211;' . gmdate( 'j', $end_ts );
		}
		return [ 'month' => gmdate( 'M', $start_ts ), 'days' => $days ];
	}

	return [
		'month' => gmdate( 'M', $start_ts ),
		'days'  => gmdate( 'j', $start_ts ) . '&#8211;' . gmdate( 'M j', $end_ts ),
	];
}

/**
 * The date-badge + status-chip event card, shared by the schedule listing
 * and the homepage preview. $linked wraps the title in a permalink; the
 * single-event template passes false since the title's already the page's
 * own heading.
 */
function icsa_render_event_card( $post_id, $linked = true ) {
	$start    = get_post_meta( $post_id, 'icsa_event_start', true );
	$end      = get_post_meta( $post_id, 'icsa_event_end', true );
	$location = get_post_meta( $post_id, 'icsa_event_location', true );
	$status   = get_post_meta( $post_id, 'icsa_event_status', true ) ?: 'coming-soon';
	$dates    = icsa_format_event_dates( $start, $end );
	$status_label = ICSA_EVENT_STATUSES[ $status ] ?? ICSA_EVENT_STATUSES['coming-soon'];
	$status_class = [
		'open'        => 'is-open',
		'coming-soon' => 'is-upcoming',
		'completed'   => 'is-completed',
	][ $status ] ?? 'is-upcoming';

	$title = $linked
		? sprintf( '<a href="%s">%s</a>', esc_url( get_permalink( $post_id ) ), esc_html( get_the_title( $post_id ) ) )
		: esc_html( get_the_title( $post_id ) );

	ob_start();
	?>
	<div class="icsa-event">
		<div class="icsa-event-date"><span class="month"><?php echo esc_html( $dates['month'] ); ?></span><span class="days"><?php echo wp_kses( $dates['days'], [] ); ?></span></div>
		<div>
			<p class="icsa-status <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_label ); ?></p>
			<h3 class="wp-block-heading has-heading-font-size"><?php echo wp_kses_post( $title ); ?></h3>
			<?php if ( $location ) : ?>
				<p><?php echo esc_html( $location ); ?></p>
			<?php endif; ?>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Marker slot (same pattern as Resources' download button) for injecting
 * the event card into a Query Loop's post-template, or the detail header
 * on a single event page.
 */
add_filter( 'render_block', function ( $block_content, $block ) {
	if ( ( $block['blockName'] ?? '' ) !== 'core/paragraph' ) {
		return $block_content;
	}
	$class_name = $block['attrs']['className'] ?? '';
	if ( get_post_type() !== 'event' ) {
		return $block_content;
	}

	if ( str_contains( $class_name, 'icsa-event-card-slot' ) ) {
		return icsa_render_event_card( get_the_ID(), true );
	}
	if ( str_contains( $class_name, 'icsa-event-detail-slot' ) ) {
		return icsa_render_event_card( get_the_ID(), false );
	}
	if ( str_contains( $class_name, 'icsa-event-link-slot' ) ) {
		$link = get_post_meta( get_the_ID(), 'icsa_event_link', true );
		if ( ! $link ) {
			return '';
		}
		return sprintf(
			'<div class="wp-block-buttons"><div class="wp-block-button"><a class="wp-block-button__link has-signal-background-color has-background wp-element-button" href="%s" target="_blank" rel="noreferrer noopener">%s</a></div></div>',
			esc_url( $link ),
			esc_html__( 'Event info & registration', 'icsa' )
		);
	}

	return $block_content;
}, 10, 2 );
