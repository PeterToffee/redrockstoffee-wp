<?php
/**
 * Pete, 2026-10-29: starburst badge above "BORN IN STEAMBOAT SPRINGS..." on
 * the home page hero, linking to /co-branding/. Wording is editable from
 * wp-admin (a meta box on the home page's edit screen) rather than baked
 * into the graphic, so Pete can change it later without needing a new
 * image generated. Default text per Pete: "DESIGN YOUR LABEL HERE".
 *
 * Usage: drop a Shortcode widget in Elementor (on the home page, in the
 * column above the "BORN IN STEAMBOAT SPRINGS..." text) with:
 *     [rrt_home_starburst]
 * Then edit the wording any time from the home page's edit screen (the
 * "Starburst Badge" box below the content editor).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RRT_STARBURST_DEFAULT_TEXT', 'DESIGN YOUR LABEL HERE' );

add_action( 'add_meta_boxes', function () {
	$front_page_id = (int) get_option( 'page_on_front' );
	if ( ! $front_page_id ) {
		return;
	}
	add_meta_box(
		'rrt-home-starburst',
		'Starburst Badge (home page hero)',
		'rrt_home_starburst_meta_box',
		'page',
		'normal',
		'default'
	);
} );

function rrt_home_starburst_meta_box( $post ) {
	if ( (int) $post->ID !== (int) get_option( 'page_on_front' ) ) {
		return;
	}
	$text = get_post_meta( $post->ID, '_rrt_starburst_text', true );
	if ( '' === $text ) {
		$text = RRT_STARBURST_DEFAULT_TEXT;
	}
	wp_nonce_field( 'rrt_home_starburst_save', 'rrt_home_starburst_nonce' );
	?>
	<p>
		<label for="rrt-home-starburst-text"><strong>Starburst wording</strong> (shown on the badge above "Born in Steamboat Springs" — used by the <code>[rrt_home_starburst]</code> shortcode placed in Elementor)</label><br>
		<input type="text" id="rrt-home-starburst-text" name="rrt_starburst_text" value="<?php echo esc_attr( $text ); ?>" style="width:100%;max-width:480px;margin-top:6px;" maxlength="60">
	</p>
	<?php
}

add_action( 'save_post_page', function ( $post_id ) {
	if ( ! isset( $_POST['rrt_home_starburst_nonce'] ) || ! wp_verify_nonce( $_POST['rrt_home_starburst_nonce'], 'rrt_home_starburst_save' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_page', $post_id ) ) {
		return;
	}
	if ( isset( $_POST['rrt_starburst_text'] ) ) {
		update_post_meta( $post_id, '_rrt_starburst_text', sanitize_text_field( wp_unslash( $_POST['rrt_starburst_text'] ) ) );
	}
} );

add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_front_page() ) {
		return;
	}
	wp_enqueue_style( 'rrt-starburst-font', 'https://fonts.googleapis.com/css2?family=Luckiest+Guy&display=swap', array(), null );
	wp_enqueue_style( 'rrt-home-starburst', RRT_LABEL_URL . 'assets/home-starburst.css', array(), RRT_LABEL_VERSION );
} );

add_shortcode( 'rrt_home_starburst', function () {
	$front_page_id = (int) get_option( 'page_on_front' );
	$text = $front_page_id ? get_post_meta( $front_page_id, '_rrt_starburst_text', true ) : '';
	if ( '' === $text ) {
		$text = RRT_STARBURST_DEFAULT_TEXT;
	}

	ob_start();
	?>
	<a class="rrt-starburst" href="<?php echo esc_url( home_url( '/co-branding/' ) ); ?>">
		<img class="rrt-starburst-shape" src="<?php echo esc_url( RRT_LABEL_URL . 'assets/images/home-starburst-shape.png' ); ?>" alt="">
		<span class="rrt-starburst-text"><?php echo esc_html( $text ); ?></span>
	</a>
	<?php
	return ob_get_clean();
} );
