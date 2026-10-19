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

	// Pete, 2026-10-19: "quantity plus 5% in the file name" — this is a
	// stand-in for a real WooCommerce order quantity (that integration is
	// explicitly deferred — "we'll coordinate with woocommerce, but not
	// right now"), just enough to get the print-ready sheet feature
	// working end to end today. Clamped up to the known per-size minimum
	// (never silently under-delivers) rather than rejecting the
	// submission over a quantity mistake.
	$quantity = isset( $_POST['quantity'] ) ? (int) $_POST['quantity'] : 1;
	$quantity = max( rrt_label_min_qty_for_size( $size_key ), $quantity );

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

	// Pete, 2026-10-18: "I need to get the file out of the configurator
	// flat and layered, so I can adjust if need to, with the full file
	// (11" tall label) with user art intact at 300 dpi ready to print" —
	// the flat composite above already IS the full 11" template (not
	// just the customer's small zone), now saved as lossless PNG with
	// real 300 DPI metadata instead of a lossy 90%-quality JPEG with no
	// DPI tag at all. This adds the "layered" half: one separate,
	// same-size, transparent PNG per layer (plus the bare template as
	// its own base layer) so Pete can stack them back up by hand in
	// Photoshop/GIMP and adjust any one piece without redoing the whole
	// composite. GD has no native concept of layers and this server has
	// no Imagick extension (confirmed — can't write a true PSD), so this
	// is the achievable version of "layered" without a new dependency.
	$layer_paths = rrt_label_composite_layers( $size_key, $layers );
	if ( is_wp_error( $layer_paths ) ) {
		$layer_paths = array(); // not fatal — the flat composite alone still gets emailed.
	}

	// Pete, 2026-10-19: "the label needs to be doubled, side by side on a
	// file 8.25in wide and 11in tall, with the order's name attached...
	// and quantity plus 5% in the file name... attached to any customer
	// emails and on the order confirmation page" — the actual print-ready
	// sheet. Pete reprints this SAME file as many times as it takes to
	// cover the order (the filename's qty already includes the 5% buffer,
	// so he doesn't have to do that math by hand each time).
	$labels_needed = (int) ceil( $quantity * 1.05 );
	$sheet_path    = rrt_label_composite_sheet( $size_key, $composite_path, $name, $labels_needed );
	if ( is_wp_error( $sheet_path ) ) {
		$sheet_path = null; // not fatal — the flat composite alone still gets emailed.
	}

	// Email is attempted (useful once this is live on GoDaddy, where real
	// mail delivery works), but Pete, 2026-10-18: "don't need mail, it can
	// go to a file, just give me the folder/filename" — on local XAMPP
	// there's no mail server behind `wp_mail()`, so it either silently
	// fails or just never gets tested at all. Either way, the files on
	// disk are the real, always-true answer, so the success message below
	// reports the exact folder + filenames directly instead of relying on
	// an email arriving.
	rrt_label_email_submission(
		array(
			'size'          => $sizes[ $size_key ]['label'],
			'name'          => $name,
			'email'         => $email,
			'phone'         => $phone,
			'message'       => $message,
			'layers'        => $layers,
			'quantity'      => $quantity,
			'labels_needed' => $labels_needed,
		),
		$composite_path,
		$layer_paths,
		$sheet_path
	);

	$all_paths = array_merge( array( $composite_path ), $layer_paths, $sheet_path ? array( $sheet_path ) : array() );
	$folder    = wp_normalize_path( dirname( $composite_path ) );
	$filenames = array_map( 'basename', $all_paths );

	wp_send_json_success(
		array(
			'message'   => "Saved! Folder: {$folder}\nFiles: " . implode( ', ', $filenames ),
			'folder'    => $folder,
			'filenames' => $filenames,
		)
	);
}

/**
 * Composites every logo/text layer the customer added onto the real
 * label template for the chosen size, in the order they were added
 * (= stacking order), and returns the path to the generated flattened
 * PNG (in the uploads temp dir) so it can be attached to Pete's review
 * email. This is the FULL template (the real 11" tall label, not just
 * the customer's small zone) — the zone pixel math only controls WHERE
 * within this full canvas each layer gets drawn.
 *
 * Pete, 2026-10-18: "the full file (11" tall label) with user art intact
 * at 300 dpi ready to print" — PNG (lossless) instead of the old 90%-
 * quality JPEG, plus a real embedded 300 DPI tag (see
 * rrt_label_set_png_dpi()) so any image editor/printer reads it as
 * actually 300 DPI at this pixel size, not an untagged file that merely
 * happens to have the right pixel count.
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
	$out_path = $tmp_dir . '/label-' . $size_key . '-' . wp_generate_password( 8, false ) . '-flat.png';
	imagepng( $canvas, $out_path, 6 ); // 6 = a reasonable PNG compression level; always lossless regardless of level.
	imagedestroy( $canvas );
	rrt_label_set_png_dpi( $out_path, 300 );

	return $out_path;
}

/**
 * The "layered" half of Pete's "flat and layered... so I can adjust if
 * need to" request. GD has no native concept of layers, and this server
 * has no Imagick extension (confirmed via `extension_loaded('imagick')`
 * — can't write a true multi-layer PSD without it), so this is the
 * achievable version: one separate PNG per layer, every one the SAME
 * full template size and position as the flat composite above, with
 * everything else on each file fully transparent. Opened together in
 * Photoshop/GIMP ("Place" each as its own layer, same stacking order as
 * the filenames) they reconstruct the exact same design as the flat
 * file, but each piece stays independently adjustable. Returns an
 * ordered array of file paths (index 0 = the bare template, the rest in
 * the same add-order as the customer's layers) or a WP_Error.
 */
function rrt_label_composite_layers( $size_key, $layers ) {
	$px = rrt_label_zone_px( $size_key );
	if ( ! $px ) {
		return new WP_Error( 'rrt_bad_size', 'Unknown box size.' );
	}

	$sizes    = rrt_label_sizes();
	$template = RRT_LABEL_PATH . $sizes[ $size_key ]['template_file'];
	if ( ! file_exists( $template ) ) {
		return new WP_Error( 'rrt_missing_template', 'Label template is missing on the server.' );
	}

	$template_img = imagecreatefromjpeg( $template );
	if ( ! $template_img ) {
		return new WP_Error( 'rrt_bad_template', 'Could not open the label template.' );
	}
	$width  = imagesx( $template_img );
	$height = imagesy( $template_img );

	$upload_dir = wp_upload_dir();
	$tmp_dir    = trailingslashit( $upload_dir['basedir'] ) . 'rrt-label-temp';
	if ( ! file_exists( $tmp_dir ) ) {
		wp_mkdir_p( $tmp_dir );
	}
	$batch_id = wp_generate_password( 8, false );
	$paths    = array();

	// Layer 0: the bare template itself, so the full stack is self-
	// contained (Pete doesn't have to separately dig up the master
	// template file to reconstruct the design).
	$base_path = $tmp_dir . "/label-{$size_key}-{$batch_id}-00-template.png";
	imagepng( $template_img, $base_path, 6 );
	imagedestroy( $template_img );
	rrt_label_set_png_dpi( $base_path, 300 );
	$paths[] = $base_path;

	$i = 1;
	foreach ( $layers as $layer ) {
		$is_logo = ( 'logo' === $layer['type'] && $layer['scale'] > 0 );
		$is_text = ( 'text' === $layer['type'] && $layer['font_size'] > 0 );
		if ( ! $is_logo && ! $is_text ) {
			continue;
		}

		// A blank, fully transparent canvas the same size as the
		// template — drawing just THIS ONE layer onto it, nothing else.
		$layer_canvas = imagecreatetruecolor( $width, $height );
		imagealphablending( $layer_canvas, false );
		imagesavealpha( $layer_canvas, true );
		$transparent = imagecolorallocatealpha( $layer_canvas, 0, 0, 0, 127 );
		imagefilledrectangle( $layer_canvas, 0, 0, $width - 1, $height - 1, $transparent );
		imagealphablending( $layer_canvas, true );

		if ( $is_logo ) {
			rrt_label_draw_logo( $layer_canvas, $px, $layer['tmp_path'], $layer['pos'], $layer['scale'] );
			$suffix = $layer['art_key'] ?? 'logo';
		} else {
			rrt_label_draw_text( $layer_canvas, $px, $layer['text'], $layer['pos'], $layer['font'], $layer['font_size'], $layer['color'], $layer['outline'], $layer['shadow'], $layer['shadow_intensity'] );
			$suffix = 'text';
		}

		$layer_path = sprintf( '%s/label-%s-%s-%02d-%s.png', $tmp_dir, $size_key, $batch_id, $i, sanitize_key( $suffix ) );
		imagepng( $layer_canvas, $layer_path, 6 );
		imagedestroy( $layer_canvas );
		rrt_label_set_png_dpi( $layer_path, 300 );
		$paths[] = $layer_path;
		++$i;
	}

	return $paths;
}

/**
 * Pete, 2026-10-19: "the label needs to be doubled, side by side on a
 * file 8.25in wide and 11in tall. with the order's name attached...
 * and quantity plus 5% in the file name." Builds the actual print-ready
 * sheet: N copies of the already-composited flat label (same file
 * rrt_label_composite() just produced) tiled side by side, per
 * rrt_label_sheet_config()'s labels-per-sheet/width for this size. The
 * flat composite has no transparency (drawn onto an opaque JPEG-sourced
 * canvas), so a plain imagecopy per slot is enough — no alpha blending
 * needed here.
 */
function rrt_label_composite_sheet( $size_key, $composite_path, $customer_name, $labels_needed ) {
	$sheet_cfg = rrt_label_sheet_config( $size_key );
	if ( ! $sheet_cfg ) {
		return new WP_Error( 'rrt_no_sheet_layout', 'No print-sheet layout defined for this box size.' );
	}

	$single = imagecreatefrompng( $composite_path );
	if ( ! $single ) {
		return new WP_Error( 'rrt_bad_composite', 'Could not open the composited label.' );
	}
	$single_w = imagesx( $single );
	$single_h = imagesy( $single );

	$sizes   = rrt_label_sizes();
	$dpi     = $sizes[ $size_key ]['dpi'];
	$sheet_w = max( (int) round( $sheet_cfg['width_in'] * $dpi ), $single_w * $sheet_cfg['labels_per_sheet'] );
	$sheet_h = $single_h;

	$sheet = imagecreatetruecolor( $sheet_w, $sheet_h );
	$white = imagecolorallocate( $sheet, 255, 255, 255 );
	imagefilledrectangle( $sheet, 0, 0, $sheet_w - 1, $sheet_h - 1, $white );

	for ( $i = 0; $i < $sheet_cfg['labels_per_sheet']; $i++ ) {
		imagecopy( $sheet, $single, $i * $single_w, 0, 0, 0, $single_w, $single_h );
	}
	imagedestroy( $single );

	$upload_dir = wp_upload_dir();
	$tmp_dir    = trailingslashit( $upload_dir['basedir'] ) . 'rrt-label-temp';
	if ( ! file_exists( $tmp_dir ) ) {
		wp_mkdir_p( $tmp_dir );
	}

	$name_part = sanitize_file_name( $customer_name ? $customer_name : 'customer' );
	$out_path  = sprintf(
		'%s/sheet-%s-%s-qty%d-%s.png',
		$tmp_dir,
		$size_key,
		$name_part,
		$labels_needed,
		wp_generate_password( 6, false )
	);

	imagepng( $sheet, $out_path, 6 );
	imagedestroy( $sheet );
	rrt_label_set_png_dpi( $out_path, 300 );

	return $out_path;
}

/**
 * Manually tags a PNG with a pHYs chunk so it's labeled $dpi dots per
 * inch — without this, a file can be built at exactly-300-DPI pixel
 * dimensions and still open in Photoshop/a printer reporting some other
 * resolution (or none): technically print-ready-sized, but not LABELED
 * that way. GD's own imagepng() turns out to already write SOME pHYs
 * chunk by default (confirmed empirically — blindly inserting a second
 * one produced an invalid PNG with two pHYs chunks, caught via
 * ImageMagick's `identify` throwing a "pHYs: duplicate" warning), so
 * this first strips any existing pHYs chunk(s) by walking the real chunk
 * structure, THEN inserts exactly one clean chunk immediately after
 * IHDR (always exactly 13 bytes of data in any valid PNG, so that offset
 * is fixed) — guaranteeing a single, spec-valid pHYs chunk every time.
 */
function rrt_label_set_png_dpi( $png_path, $dpi ) {
	$data = file_get_contents( $png_path );
	if ( false === $data || "\x89PNG\r\n\x1a\n" !== substr( $data, 0, 8 ) ) {
		return false;
	}

	// Walk every chunk and drop any existing pHYs — PNG chunk layout is
	// length(4) + type(4) + data(length) + crc(4).
	$clean  = substr( $data, 0, 8 );
	$offset = 8;
	$total  = strlen( $data );
	while ( $offset + 8 <= $total ) {
		$length    = unpack( 'N', substr( $data, $offset, 4 ) )[1];
		$type      = substr( $data, $offset + 4, 4 );
		$chunk_len = 4 + 4 + $length + 4;
		if ( 'pHYs' !== $type ) {
			$clean .= substr( $data, $offset, $chunk_len );
		}
		$offset += $chunk_len;
	}

	$pixels_per_meter = (int) round( $dpi / 0.0254 );
	$phys_data        = pack( 'NNC', $pixels_per_meter, $pixels_per_meter, 1 ); // X, Y, unit = meter
	$phys_chunk       = pack( 'N', strlen( $phys_data ) ) . 'pHYs' . $phys_data . pack( 'N', crc32( 'pHYs' . $phys_data ) );

	$ihdr_end = 8 + 4 + 4 + 13 + 4; // signature + length + type + 13 bytes of IHDR data + crc
	$new_data = substr( $clean, 0, $ihdr_end ) . $phys_chunk . substr( $clean, $ihdr_end );

	return (bool) file_put_contents( $png_path, $new_data );
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
 *
 * Pete, 2026-10-18: "flat and layered... with the full file at 300 dpi
 * ready to print" — $layer_paths (from rrt_label_composite_layers(), may
 * be an empty array if that step failed) are attached alongside the flat
 * composite: the bare template plus one transparent PNG per customer
 * layer, all print-resolution, same full 11" size/position as the flat
 * file, so the whole design can be reconstructed as real separate layers
 * in Photoshop/GIMP.
 *
 * Pete, 2026-10-19: $sheet_path (from rrt_label_composite_sheet(), may be
 * null if that step failed) is the actual print-ready sheet — the flat
 * label doubled/tripled side by side per rrt_label_sheet_config() — also
 * attached here since this is the only working delivery channel right
 * now ("this file will be attached to any customer emails" — the order
 * confirmation page version is explicitly deferred, pending WooCommerce).
 */
function rrt_label_email_submission( $fields, $composite_path, $layer_paths = array(), $sheet_path = null ) {
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
	$body .= 'Quantity requested: ' . $fields['quantity'] . "\n";
	$body .= 'Labels to print (qty + 5% buffer): ' . $fields['labels_needed'] . "\n";
	$body .= 'Logos: ' . $logo_count . "\n";
	foreach ( $text_lines as $i => $line ) {
		$body .= 'Text ' . ( $i + 1 ) . ': ' . $line . "\n";
	}
	if ( $fields['message'] ) {
		$body .= "\nMessage:\n" . $fields['message'] . "\n";
	}
	$body .= "\nAttached:\n";
	$body .= "- The flat, full 11\" label (PNG, real 300 DPI tag) — ready to print exactly as the customer designed it.\n";
	if ( $layer_paths ) {
		$body .= "- The same design as separate layers (bare template + one file per logo/text piece, numbered in stacking order) — open them all in Photoshop/GIMP as individual layers if you need to adjust any one piece by hand.\n";
	}
	$body .= "- The original uploaded logo file(s), if any, in case you want to work from the source image directly.\n";
	if ( $sheet_path ) {
		$body .= "- The print-ready sheet (multiple labels tiled side by side) — reprint this same file as many times as it takes to cover {$fields['labels_needed']} labels.";
	}

	$attachments = array( $composite_path );
	if ( $sheet_path && file_exists( $sheet_path ) ) {
		$attachments[] = $sheet_path;
	}
	foreach ( $layer_paths as $layer_path ) {
		if ( file_exists( $layer_path ) ) {
			$attachments[] = $layer_path;
		}
	}
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
