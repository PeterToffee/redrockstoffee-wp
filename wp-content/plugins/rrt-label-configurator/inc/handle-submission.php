<?php
/**
 * AJAX endpoints for the label configurator:
 *   - rrt_label_fresh_nonce   — issue a fresh nonce at page-view time.
 *     This page can be served from cache (GoDaddy/edge caching), which
 *     bakes a nonce into the cached HTML that goes stale after a while.
 *     The JS fetches a fresh one right before submit instead of trusting
 *     whatever nonce shipped with the (possibly cached) page — same
 *     pattern used on theunframer.com after a stale-nonce 403 bug there.
 *   - rrt_label_submit        — receives the design (a list of logo/text
 *     layers, same "add as many as you like" model as TheUnframer's own
 *     configurator), composites the final label image server-side with
 *     GD, and emails it to Pete.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_ajax_rrt_label_fresh_nonce', 'rrt_label_fresh_nonce' );
add_action( 'wp_ajax_nopriv_rrt_label_fresh_nonce', 'rrt_label_fresh_nonce' );
function rrt_label_fresh_nonce() {
	wp_send_json_success(
		array(
			'nonce' => wp_create_nonce( 'rrt_label_submit' ),
		)
	);
}

add_action( 'wp_ajax_rrt_label_submit', 'rrt_label_handle_submit' );
add_action( 'wp_ajax_nopriv_rrt_label_submit', 'rrt_label_handle_submit' );
function rrt_label_handle_submit() {
	check_ajax_referer( 'rrt_label_submit', 'nonce' );

	$size_key = isset( $_POST['size'] ) ? sanitize_text_field( wp_unslash( $_POST['size'] ) ) : '';
	$sizes    = rrt_label_sizes();
	if ( ! isset( $sizes[ $size_key ] ) ) {
		wp_send_json_error( array( 'message' => 'Please choose a box size.' ) );
	}

	$name    = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	$email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$phone   = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
	$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

	if ( empty( $name ) || empty( $email ) || ! is_email( $email ) ) {
		wp_send_json_error( array( 'message' => 'Please fill in your name and a valid email.' ) );
	}

	// One entry per layer the customer added, in the same order they were
	// added (= stacking/z-order). Logo layers carry pos+scale; text
	// layers carry pos+text+font+font_size. The actual uploaded logo
	// FILES travel separately as logo_files[] (multipart can't nest files
	// inside JSON), matched up to 'logo' entries in order below.
	$layers_raw = isset( $_POST['layers'] ) ? json_decode( wp_unslash( $_POST['layers'] ), true ) : null;
	if ( ! is_array( $layers_raw ) || empty( $layers_raw ) ) {
		wp_send_json_error( array( 'message' => 'Please add a logo and/or some text to your label.' ) );
	}

	$logo_tmp_paths = array();
	if ( ! empty( $_FILES['logo_files']['tmp_name'] ) && is_array( $_FILES['logo_files']['tmp_name'] ) ) {
		foreach ( $_FILES['logo_files']['tmp_name'] as $tmp_name ) {
			if ( is_uploaded_file( $tmp_name ) ) {
				$logo_tmp_paths[] = $tmp_name;
			}
		}
	}

	$art_library = rrt_label_art_library();

	$layers = array();
	$logo_i = 0;
	foreach ( $layers_raw as $raw ) {
		if ( ! is_array( $raw ) || empty( $raw['type'] ) ) {
			continue;
		}
		$pos = is_array( $raw['pos'] ?? null )
			? array(
				'x' => (float) ( $raw['pos']['x'] ?? 50 ),
				'y' => (float) ( $raw['pos']['y'] ?? 50 ),
			)
			: array( 'x' => 50, 'y' => 50 );

		if ( 'logo' === $raw['type'] ) {
			// Pete, 2026-10-17: "a library of holiday/birthday/celebration
			// art icons we can use to click like adding logos" — an ART
			// layer carries an art_key instead of an uploaded file, so it
			// resolves to this plugin's own bundled PNG and must NOT
			// consume a slot from logo_tmp_paths (nothing was uploaded for
			// it — doing so would shift the pairing for every real
			// uploaded logo after it).
			$art_key = isset( $raw['art_key'] ) ? sanitize_key( $raw['art_key'] ) : '';
			if ( $art_key && isset( $art_library[ $art_key ] ) ) {
				$layers[] = array(
					'type'     => 'logo',
					'pos'      => $pos,
					'scale'    => (float) ( $raw['scale'] ?? 0 ),
					'tmp_path' => RRT_LABEL_PATH . $art_library[ $art_key ]['file'],
					'art_key'  => $art_key,
				);
				continue;
			}
			if ( ! isset( $logo_tmp_paths[ $logo_i ] ) ) {
				++$logo_i;
				continue;
			}
			$layers[] = array(
				'type'       => 'logo',
				'pos'        => $pos,
				'scale'      => (float) ( $raw['scale'] ?? 0 ),
				'tmp_path'   => $logo_tmp_paths[ $logo_i ],
			);
			++$logo_i;
		} elseif ( 'text' === $raw['type'] ) {
			$text = isset( $raw['text'] ) ? sanitize_text_field( wp_unslash( $raw['text'] ) ) : '';
			if ( '' === $text ) {
				continue;
			}
			$layers[] = array(
				'type'      => 'text',
				'pos'       => $pos,
				'text'      => $text,
				'font'      => isset( $raw['font'] ) ? sanitize_text_field( wp_unslash( $raw['font'] ) ) : '',
				'font_size' => (float) ( $raw['font_size'] ?? 0 ),
				// Pete, 2026-10-17: 5-color palette + custom picker for text
				// color. Resolved client-side (resolveTextColor() in
				// label-configurator.js) to one hex color + one contrast
				// outline color, so the composited label Pete reviews
				// matches what the customer actually saw, not GD's old
				// hardcoded near-black text.
				'color'     => rrt_label_sanitize_hex_color( $raw['color'] ?? '', '#1a1a1a' ),
				'outline'   => rrt_label_sanitize_hex_color( $raw['outline'] ?? '', '#ffffff' ),
				// Pete, 2026-10-17: "checkbox to add shadow" — opt-in per
				// layer, off by default client-side too (see
				// group.shadowEnabled in label-configurator.js).
				'shadow'    => ! empty( $raw['shadow'] ),
				// "shadow intensity adjustment slider" — 0-100, see
				// group.shadowIntensity / outlineShadow() in
				// label-configurator.js.
				'shadow_intensity' => max( 0, min( 100, (float) ( $raw['shadow_intensity'] ?? 60 ) ) ),
			);
		}
	}

	if ( empty( $layers ) ) {
		wp_send_json_error( array( 'message' => 'Please add a logo and/or some text to your label.' ) );
	}

	$composite_path = rrt_label_composite( $size_key, $layers );

	if ( is_wp_error( $composite_path ) ) {
		wp_send_json_error( array( 'message' => $composite_path->get_error_message() ) );
	}

	rrt_label_email_submission(
		array(
			'size'    => $sizes[ $size_key ]['label'],
			'name'    => $name,
			'email'   => $email,
			'phone'   => $phone,
			'message' => $message,
			'layers'  => $layers,
		),
		$composite_path
	);

	wp_send_json_success( array( 'message' => "Thanks! We've got your design and will follow up by email." ) );
}

/**
 * Composites every logo/text layer the customer added onto the real
 * label template for the chosen size, in the order they were added
 * (= stacking order), and returns the path to the generated flattened
 * JPEG (in the uploads temp dir) so it can be attached to Pete's review
 * email.
 *
 * Each layer's `pos` is percent (0-100) within the zone, CENTER anchored
 * (matching the on-screen preview exactly — see
 * assets/label-configurator.js). A logo's `scale` is its rendered width
 * as a percent of the zone's own width. A text layer's `font_size` is
 * the font's pixel size as a percent of the zone's own width.
 */
function rrt_label_composite( $size_key, $layers ) {
	$px = rrt_label_zone_px( $size_key );
	if ( ! $px ) {
		return new WP_Error( 'rrt_bad_size', 'Unknown box size.' );
	}

	$sizes    = rrt_label_sizes();
	$template = RRT_LABEL_PATH . $sizes[ $size_key ]['template_file'];
	if ( ! file_exists( $template ) ) {
		return new WP_Error( 'rrt_missing_template', 'Label template is missing on the server.' );
	}

	$canvas = imagecreatefromjpeg( $template );
	if ( ! $canvas ) {
		return new WP_Error( 'rrt_bad_template', 'Could not open the label template.' );
	}

	foreach ( $layers as $layer ) {
		if ( 'logo' === $layer['type'] && $layer['scale'] > 0 ) {
			rrt_label_draw_logo( $canvas, $px, $layer['tmp_path'], $layer['pos'], $layer['scale'] );
		} elseif ( 'text' === $layer['type'] && $layer['font_size'] > 0 ) {
			rrt_label_draw_text( $canvas, $px, $layer['text'], $layer['pos'], $layer['font'], $layer['font_size'], $layer['color'], $layer['outline'], $layer['shadow'], $layer['shadow_intensity'] );
		}
	}

	$upload_dir = wp_upload_dir();
	$tmp_dir    = trailingslashit( $upload_dir['basedir'] ) . 'rrt-label-temp';
	if ( ! file_exists( $tmp_dir ) ) {
		wp_mkdir_p( $tmp_dir );
	}
	$out_path = $tmp_dir . '/label-' . $size_key . '-' . wp_generate_password( 8, false ) . '.jpg';
	imagejpeg( $canvas, $out_path, 90 );
	imagedestroy( $canvas );

	return $out_path;
}

function rrt_label_draw_logo( $canvas, $px, $logo_tmp_path, $logo_pos, $logo_scale ) {
	$logo_info = getimagesize( $logo_tmp_path );
	if ( ! $logo_info ) {
		return;
	}
	$logo_img = null;
	switch ( $logo_info['mime'] ) {
		case 'image/png':
			$logo_img = imagecreatefrompng( $logo_tmp_path );
			break;
		case 'image/jpeg':
			$logo_img = imagecreatefromjpeg( $logo_tmp_path );
			break;
	}
	if ( ! $logo_img ) {
		return;
	}

	$dest_w = max( 1, round( ( $logo_scale / 100 ) * $px['zone_width'] ) );
	$dest_h = max( 1, round( $dest_w * ( imagesy( $logo_img ) / imagesx( $logo_img ) ) ) );

	$center_x = $px['zone_x'] + ( $logo_pos['x'] / 100 ) * $px['zone_width'];
	$center_y = $px['zone_y'] + ( $logo_pos['y'] / 100 ) * $px['zone_height'];

	imagealphablending( $canvas, true );
	imagesavealpha( $canvas, true );
	imagecopyresampled(
		$canvas,
		$logo_img,
		(int) round( $center_x - $dest_w / 2 ),
		(int) round( $center_y - $dest_h / 2 ),
		0,
		0,
		(int) $dest_w,
		(int) $dest_h,
		imagesx( $logo_img ),
		imagesy( $logo_img )
	);
	imagedestroy( $logo_img );
}

/**
 * Parses a "#rrggbb" string into a GD color, falling back to $fallback_hex
 * (also "#rrggbb") for anything malformed — the client always sends real
 * hex (resolveTextColor() in label-configurator.js), but this is the AJAX
 * boundary, so never trust it blindly.
 */
function rrt_label_sanitize_hex_color( $value, $fallback_hex ) {
	$value = is_string( $value ) ? $value : '';
	return preg_match( '/^#[0-9a-fA-F]{6}$/', $value ) ? $value : $fallback_hex;
}

function rrt_label_gd_color( $canvas, $hex, $alpha = 0 ) {
	return imagecolorallocatealpha(
		$canvas,
		(int) hexdec( substr( $hex, 1, 2 ) ),
		(int) hexdec( substr( $hex, 3, 2 ) ),
		(int) hexdec( substr( $hex, 5, 2 ) ),
		$alpha
	);
}

function rrt_label_draw_text( $canvas, $px, $label_text, $text_pos, $text_font, $text_font_size, $text_color_hex = '#1a1a1a', $outline_hex = '#ffffff', $shadow_enabled = false, $shadow_intensity = 60 ) {
	$fonts    = rrt_label_fonts();
	$font_key = isset( $fonts[ $text_font ] ) ? $text_font : array_key_first( $fonts );
	$ttf_path = RRT_LABEL_PATH . $fonts[ $font_key ]['ttf_file'];
	if ( ! file_exists( $ttf_path ) ) {
		return;
	}

	$font_size_px     = ( $text_font_size / 100 ) * $px['zone_width'];
	$max_width        = $px['zone_width'] * 0.92;
	$text_color_clean = rrt_label_sanitize_hex_color( $text_color_hex, '#1a1a1a' );
	$text_color       = rrt_label_gd_color( $canvas, $text_color_clean );
	// "shadow intensity adjustment slider" — same continuous 0-100 dial
	// as outlineShadow() in label-configurator.js, replacing the old
	// fixed full/subtle auto-switch. GD alpha is 0 (opaque) to 127
	// (fully transparent), so 100% intensity -> alpha 0 (opaque),
	// 0% -> alpha 127 (invisible).
	$shadow_intensity = max( 0, min( 100, (float) $shadow_intensity ) );
	$outline_alpha    = (int) round( 127 * ( 1 - $shadow_intensity / 100 ) );
	$outline          = rrt_label_gd_color( $canvas, rrt_label_sanitize_hex_color( $outline_hex, '#000000' ), $outline_alpha );
	imagealphablending( $canvas, true );

	// Word-wrap using the REAL font's own glyph widths (imagettfbbox),
	// not a fixed-width guess, so long/short fonts wrap accurately.
	$words = preg_split( '/\s+/', trim( $label_text ) );
	$lines = array();
	$line  = '';
	foreach ( $words as $word ) {
		$test = $line ? $line . ' ' . $word : $word;
		$bbox = imagettfbbox( $font_size_px, 0, $ttf_path, $test );
		$test_width = $bbox[2] - $bbox[0];
		if ( $test_width > $max_width && $line ) {
			$lines[] = $line;
			$line    = $word;
		} else {
			$line = $test;
		}
	}
	if ( '' !== $line ) {
		$lines[] = $line;
	}

	$line_height  = $font_size_px * 1.25;
	$block_height = count( $lines ) * $line_height;

	$center_x = $px['zone_x'] + ( $text_pos['x'] / 100 ) * $px['zone_width'];
	$center_y = $px['zone_y'] + ( $text_pos['y'] / 100 ) * $px['zone_height'];
	$top      = $center_y - ( $block_height / 2 );

	// GD's imagettftext() has no native stroke/shadow — poor-man's outline
	// by drawing the line 8 times at a 1px ring of offsets in the outline
	// color first, then the real text on top in $text_color. The
	// intensity dial is expressed entirely through $outline_alpha above,
	// same as outlineShadow()'s continuous opacity scaling in
	// label-configurator.js — the ring itself stays constant.
	$outline_offsets = array(
		array( -1, -1 ), array( 0, -1 ), array( 1, -1 ),
		array( -1, 0 ), array( 1, 0 ),
		array( -1, 1 ), array( 0, 1 ), array( 1, 1 ),
	);

	foreach ( $lines as $i => $line_text ) {
		$bbox        = imagettfbbox( $font_size_px, 0, $ttf_path, $line_text );
		$line_width  = $bbox[2] - $bbox[0];
		$x           = (int) round( $center_x - $line_width / 2 );
		$baseline_y  = (int) round( $top + ( $i * $line_height ) + $font_size_px );

		if ( $shadow_enabled ) {
			foreach ( $outline_offsets as $offset ) {
				imagettftext( $canvas, $font_size_px, 0, $x + $offset[0], $baseline_y + $offset[1], $outline, $ttf_path, $line_text );
			}
		}
		imagettftext( $canvas, $font_size_px, 0, $x, $baseline_y, $text_color, $ttf_path, $line_text );
	}
}

/**
 * Emails Pete the composited preview (what the customer actually
 * designed) plus each original raw logo upload, so he can redo the
 * composite by hand if he ever wants to.
 */
function rrt_label_email_submission( $fields, $composite_path ) {
	$to      = get_option( 'admin_email' );
	$subject = sprintf( '[Co-Branding Label] New design from %s', $fields['name'] );

	$logo_count = 0;
	$text_lines = array();
	foreach ( $fields['layers'] as $layer ) {
		if ( 'logo' === $layer['type'] ) {
			++$logo_count;
		} else {
			$text_lines[] = $layer['text'];
		}
	}

	$body  = "A new co-branding label design was submitted:\n\n";
	$body .= 'Box size: ' . $fields['size'] . "\n";
	$body .= 'Name: ' . $fields['name'] . "\n";
	$body .= 'Email: ' . $fields['email'] . "\n";
	$body .= 'Phone: ' . $fields['phone'] . "\n";
	$body .= 'Logos: ' . $logo_count . "\n";
	foreach ( $text_lines as $i => $line ) {
		$body .= 'Text ' . ( $i + 1 ) . ': ' . $line . "\n";
	}
	if ( $fields['message'] ) {
		$body .= "\nMessage:\n" . $fields['message'] . "\n";
	}
	$body .= "\nThe attached image shows the design as the customer built it. The original logo file(s), if any, are attached separately in case you want to redo the composite by hand.";

	$attachments = array( $composite_path );
	foreach ( $fields['layers'] as $layer ) {
		// ART logos aren't a customer upload at all — nothing to "redo the
		// composite by hand" with, it's this plugin's own bundled asset,
		// already visible baked into the composite — so skip attaching it.
		if ( 'logo' === $layer['type'] && empty( $layer['art_key'] ) && ! empty( $layer['tmp_path'] ) && file_exists( $layer['tmp_path'] ) ) {
			$attachments[] = $layer['tmp_path'];
		}
	}

	wp_mail( $to, $subject, $body, array(), $attachments );
}
