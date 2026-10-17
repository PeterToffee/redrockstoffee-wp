<?php
/**
 * AJAX endpoints for the label configurator:
 *   - rrt_label_fresh_nonce   — issue a fresh nonce at page-view time.
 *     This page can be served from cache (GoDaddy/edge caching), which
 *     bakes a nonce into the cached HTML that goes stale after a while.
 *     The JS fetches a fresh one right before submit instead of trusting
 *     whatever nonce shipped with the (possibly cached) page — same
 *     pattern used on theunframer.com after a stale-nonce 403 bug there.
 *   - rrt_label_submit        — receives the design, composites the
 *     final label image server-side with GD, and emails it to Pete.
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
	$label_text = isset( $_POST['label_text'] ) ? sanitize_text_field( wp_unslash( $_POST['label_text'] ) ) : '';

	if ( empty( $name ) || empty( $email ) || ! is_email( $email ) ) {
		wp_send_json_error( array( 'message' => 'Please fill in your name and a valid email.' ) );
	}

	// Logo box: position/size as fractions (0-1) of the zone, so they
	// stay correct no matter what pixel size the zone ends up being.
	$logo_box = null;
	if ( isset( $_POST['logo_box'] ) ) {
		$decoded = json_decode( wp_unslash( $_POST['logo_box'] ), true );
		if ( is_array( $decoded ) ) {
			$logo_box = array(
				'x' => (float) ( $decoded['x'] ?? 0 ),
				'y' => (float) ( $decoded['y'] ?? 0 ),
				'w' => (float) ( $decoded['w'] ?? 0 ),
				'h' => (float) ( $decoded['h'] ?? 0 ),
			);
		}
	}
	$text_box = null;
	if ( isset( $_POST['text_box'] ) ) {
		$decoded = json_decode( wp_unslash( $_POST['text_box'] ), true );
		if ( is_array( $decoded ) ) {
			$text_box = array(
				'x' => (float) ( $decoded['x'] ?? 0 ),
				'y' => (float) ( $decoded['y'] ?? 0 ),
				'w' => (float) ( $decoded['w'] ?? 0 ),
				'h' => (float) ( $decoded['h'] ?? 0 ),
			);
		}
	}

	$logo_tmp_path = null;
	if ( ! empty( $_FILES['logo']['tmp_name'] ) && is_uploaded_file( $_FILES['logo']['tmp_name'] ) ) {
		$logo_tmp_path = $_FILES['logo']['tmp_name'];
	}

	if ( empty( $logo_tmp_path ) && empty( $label_text ) ) {
		wp_send_json_error( array( 'message' => 'Please add a logo and/or some text to your label.' ) );
	}

	$composite_path = rrt_label_composite(
		$size_key,
		$logo_tmp_path,
		$logo_box,
		$label_text,
		$text_box
	);

	if ( is_wp_error( $composite_path ) ) {
		wp_send_json_error( array( 'message' => $composite_path->get_error_message() ) );
	}

	rrt_label_email_submission(
		array(
			'size'       => $sizes[ $size_key ]['label'],
			'name'       => $name,
			'email'      => $email,
			'phone'      => $phone,
			'message'    => $message,
			'label_text' => $label_text,
		),
		$composite_path,
		$logo_tmp_path
	);

	wp_send_json_success( array( 'message' => "Thanks! We've got your design and will follow up by email." ) );
}

/**
 * Composites the uploaded logo (if any) and typed text (if any) onto the
 * real label template for the chosen size, and returns the path to the
 * generated flattened JPEG (in the uploads temp dir) so it can be
 * attached to Pete's review email.
 *
 * $logo_box / $text_box are fractions (0-1) of the zone's own box, e.g.
 * x=0,y=0,w=1,h=1 fills the whole zone.
 */
function rrt_label_composite( $size_key, $logo_tmp_path, $logo_box, $label_text, $text_box ) {
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

	// Logo, scaled/positioned inside the zone per the fractional box the
	// customer chose on screen.
	if ( $logo_tmp_path && $logo_box ) {
		$logo_info = getimagesize( $logo_tmp_path );
		$logo_img  = null;
		if ( $logo_info ) {
			switch ( $logo_info['mime'] ) {
				case 'image/png':
					$logo_img = imagecreatefrompng( $logo_tmp_path );
					break;
				case 'image/jpeg':
					$logo_img = imagecreatefromjpeg( $logo_tmp_path );
					break;
			}
		}
		if ( $logo_img ) {
			$dest_x = $px['zone_x'] + ( $logo_box['x'] * $px['zone_width'] );
			$dest_y = $px['zone_y'] + ( $logo_box['y'] * $px['zone_height'] );
			$dest_w = max( 1, round( $logo_box['w'] * $px['zone_width'] ) );
			$dest_h = max( 1, round( $logo_box['h'] * $px['zone_height'] ) );

			imagealphablending( $canvas, true );
			imagesavealpha( $canvas, true );
			imagecopyresampled(
				$canvas,
				$logo_img,
				(int) round( $dest_x ),
				(int) round( $dest_y ),
				0,
				0,
				(int) $dest_w,
				(int) $dest_h,
				imagesx( $logo_img ),
				imagesy( $logo_img )
			);
			imagedestroy( $logo_img );
		}
	}

	// Typed text, placed inside its own fractional box within the zone.
	// NOTE: uses GD's built-in bitmap font as a simple first pass (no TTF
	// bundled yet) — legible but plain; swap in a real .ttf + imagettftext
	// later if Pete wants nicer-looking preview text.
	if ( $label_text && $text_box ) {
		$text_x = $px['zone_x'] + ( $text_box['x'] * $px['zone_width'] );
		$text_y = $px['zone_y'] + ( $text_box['y'] * $px['zone_height'] );
		$text_w = max( 1, $text_box['w'] * $px['zone_width'] );

		$font        = 5; // largest built-in GD font
		$char_w      = imagefontwidth( $font );
		$max_chars_per_line = max( 1, floor( $text_w / $char_w ) );
		$wrapped     = wordwrap( $label_text, (int) $max_chars_per_line, "\n", true );
		$lines       = explode( "\n", $wrapped );
		$black       = imagecolorallocate( $canvas, 20, 20, 20 );
		$line_height = imagefontheight( $font ) + 4;

		foreach ( $lines as $i => $line ) {
			imagestring( $canvas, $font, (int) round( $text_x ), (int) round( $text_y + ( $i * $line_height ) ), $line, $black );
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

/**
 * Emails Pete the composited preview (what the customer actually
 * designed) plus the original raw logo upload, so he can redo the
 * composite by hand if he ever wants to.
 */
function rrt_label_email_submission( $fields, $composite_path, $logo_tmp_path ) {
	$to      = get_option( 'admin_email' );
	$subject = sprintf( '[Co-Branding Label] New design from %s', $fields['name'] );

	$body  = "A new co-branding label design was submitted:\n\n";
	$body .= 'Box size: ' . $fields['size'] . "\n";
	$body .= 'Name: ' . $fields['name'] . "\n";
	$body .= 'Email: ' . $fields['email'] . "\n";
	$body .= 'Phone: ' . $fields['phone'] . "\n";
	if ( $fields['label_text'] ) {
		$body .= 'Text on label: ' . $fields['label_text'] . "\n";
	}
	if ( $fields['message'] ) {
		$body .= "\nMessage:\n" . $fields['message'] . "\n";
	}
	$body .= "\nThe attached image shows the design as the customer built it. The original logo file (if any) is attached separately in case you want to redo the composite by hand.";

	$attachments = array( $composite_path );
	if ( $logo_tmp_path && file_exists( $logo_tmp_path ) ) {
		$attachments[] = $logo_tmp_path;
	}

	wp_mail( $to, $subject, $body, array(), $attachments );
}
