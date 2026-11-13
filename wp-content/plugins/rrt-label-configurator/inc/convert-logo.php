<?php
/**
 * Pete, 2026-11-13: "I have people throwing all sorts of files" (HEIC
 * iPhone photos, EPS/PDF/AI logo exports, TIFF/PSD, camera RAW) at the
 * logo upload step, and the configurator's own file picker's accept=""
 * already invited several of these (copied from TheUnframer's upload
 * configurator) without any of the logic to actually handle them —
 * browsers can't decode these formats into an <img> at all, so a
 * customer picking one got nothing: no preview, no error, just back to
 * what looks like a blank stage.
 *
 * This is the server half of the port of TheUnframer's own
 * inc/configurator-ajax.php conversion pipeline (same Imagick mechanics,
 * confirmed available on this GoDaddy host — EPS/PDF/HEIC/PSD/AI/TIFF
 * delegates all present). Unlike TheUnframer, this plugin has no
 * WordPress Media Library involvement anywhere else in its upload flow,
 * so this endpoint doesn't create attachments — it just converts
 * in-memory and hands back a data: URL the browser turns into a real
 * File, which then flows through every existing logo-layer code path
 * (drag, scale, held-design snapshot, final submit) completely
 * unchanged, same as any ordinary PNG/JPEG upload today.
 *
 * Also unlike TheUnframer (which flattens everything onto WHITE — right
 * for a full photo print), PDF/EPS/AI/PSD/TIFF convert to a TRANSPARENT
 * PNG here: these are far more likely to be actual logo/vector artwork
 * meant to sit on top of the label template, and GD's
 * imagecreatefrompng() is already this plugin's primary logo format
 * (see rrt_label_draw_logo() in handle-submission.php). HEIC and RAW
 * camera formats are genuine photos with no alpha expected, so those
 * still convert to JPEG exactly like TheUnframer's own pipeline.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_ajax_rrt_label_convert_logo', 'rrt_label_ajax_convert_logo' );
add_action( 'wp_ajax_nopriv_rrt_label_convert_logo', 'rrt_label_ajax_convert_logo' );

/**
 * Extensions this endpoint knows how to convert, split by which Imagick
 * branch (and which output format) they need. Kept as plain arrays
 * (not a single combined list) since the two branches below need to
 * tell them apart anyway.
 */
function rrt_label_convert_heic_extensions() {
	return array( 'heic', 'heif' );
}
function rrt_label_convert_vector_extensions() {
	return array( 'pdf', 'eps', 'ai', 'psd', 'tif', 'tiff' );
}
function rrt_label_convert_raw_extensions() {
	return array( 'cr2', 'cr3', 'nef', 'arw', 'dng', 'orf', 'raf', 'rw2' );
}

/**
 * Every MIME type and extension this endpoint will accept, for the
 * "does this file even qualify" gate. RAW files frequently arrive with
 * a generic application/octet-stream MIME (the browser doesn't
 * recognize the camera-specific type), so a file passes if EITHER its
 * reported MIME or its extension matches — same reasoning TheUnframer's
 * own allow-list uses.
 */
function rrt_label_convert_allowed_mimes() {
	return array(
		'image/heic',
		'image/heif',
		'application/pdf',
		'application/postscript',
		'application/eps',
		'image/x-eps',
		'application/illustrator',
		'image/vnd.adobe.photoshop',
		'image/x-photoshop',
		'application/photoshop',
		'application/psd',
		'image/tiff',
		'image/x-tiff',
		'image/x-canon-cr2',
		'image/x-canon-cr3',
		'image/x-nikon-nef',
		'image/x-sony-arw',
		'image/x-adobe-dng',
		'image/x-dng',
		'image/x-olympus-orf',
		'image/x-fuji-raf',
		'image/x-panasonic-rw2',
		'application/octet-stream',
	);
}
function rrt_label_convert_allowed_extensions() {
	return array_merge(
		rrt_label_convert_heic_extensions(),
		rrt_label_convert_vector_extensions(),
		rrt_label_convert_raw_extensions()
	);
}

function rrt_label_ajax_convert_logo() {
	check_ajax_referer( 'rrt_label_submit', 'nonce' );

	if ( empty( $_FILES['image'] ) || empty( $_FILES['image']['tmp_name'] ) || ! is_uploaded_file( $_FILES['image']['tmp_name'] ) ) {
		wp_send_json_error( array( 'message' => 'No file received.' ) );
	}

	$file = $_FILES['image'];

	// Same 32MB app-level cap the rest of this plugin's upload path
	// already assumes, independent of whatever the host's own
	// post_max_size/upload_max_filesize happen to allow.
	if ( $file['size'] > 32 * 1024 * 1024 ) {
		wp_send_json_error( array( 'message' => 'That file is too large (32MB max). Please use a smaller file.' ) );
	}

	$name = isset( $file['name'] ) ? wp_unslash( $file['name'] ) : '';
	$ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
	$mime = isset( $file['type'] ) ? $file['type'] : '';

	$is_allowed = in_array( $ext, rrt_label_convert_allowed_extensions(), true )
		|| in_array( $mime, rrt_label_convert_allowed_mimes(), true );

	if ( ! $is_allowed ) {
		wp_send_json_error( array( 'message' => 'That file type isn’t supported here.' ) );
	}

	if ( ! extension_loaded( 'imagick' ) ) {
		wp_send_json_error( array( 'message' => 'File conversion is unavailable on this server right now.' ) );
	}

	// Belt-and-braces re-detection: a RAW file's extension is the only
	// reliable signal (MIME is often generic/wrong), so route purely on
	// extension rather than trusting $mime for the branch decision.
	if ( in_array( $ext, rrt_label_convert_heic_extensions(), true ) ) {
		$result = rrt_label_convert_heic_to_jpeg( $file['tmp_name'] );
	} elseif ( in_array( $ext, rrt_label_convert_vector_extensions(), true ) ) {
		$result = rrt_label_convert_vector_to_png( $file['tmp_name'], $ext );
	} elseif ( in_array( $ext, rrt_label_convert_raw_extensions(), true ) ) {
		$result = rrt_label_convert_raw_to_jpeg( $file['tmp_name'] );
	} else {
		wp_send_json_error( array( 'message' => 'That file type isn’t supported here.' ) );
		return;
	}

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}

	wp_send_json_success( $result );
}

/**
 * Shared by all three conversion branches: writes the finished Imagick
 * object out and reads the bytes back into a data: URL, then returns
 * the plain array the AJAX handler sends back as JSON on success.
 *
 * Pete, 2026-11-13: a real customer EPS (Adobe Photoshop export) came
 * back from readImage()/mergeImageLayers() with the right dimensions
 * (confirmed: Ghostscript itself rasterizes this file fine, verified by
 * installing it locally and running the SAME conversion via the `magick`
 * CLI) but PHP-Imagick's getImageBlob() specifically threw "Failed to
 * get the image contents (empty or invalid image?)" on the live server
 * every time, regardless of colorspace/merge method/iterator position --
 * a known flakiness in that one call on some PHP-Imagick builds.
 * writeImage() to a real (short-lived, immediately deleted) temp file is
 * a completely different, far more battle-tested code path -- it's
 * exactly what the `magick` CLI itself does under the hood, which is why
 * the CLI never had this problem in the first place.
 */
function rrt_label_convert_respond( Imagick $im, $ext ) {
	$width  = $im->getImageWidth();
	$height = $im->getImageHeight();

	// Plain tempnam() (not wp_tempnam(), which needs wp-admin/includes/
	// file.php loaded -- not guaranteed in this plain admin-ajax.php
	// context) in the OS temp dir; nothing here is meant to persist or
	// live under wp-content, so there's no reason to involve WordPress's
	// own upload-dir machinery for a file that exists for microseconds.
	$tmp_out = tempnam( sys_get_temp_dir(), 'rrtcv' );
	$im->writeImage( $ext . ':' . $tmp_out );
	$blob = file_get_contents( $tmp_out );
	unlink( $tmp_out );
	$im->clear();

	if ( false === $blob || '' === $blob ) {
		throw new ImagickException( 'Conversion produced no image data.' );
	}

	$mime = ( 'png' === $ext ) ? 'image/png' : 'image/jpeg';

	return array(
		'data_url' => 'data:' . $mime . ';base64,' . base64_encode( $blob ),
		'ext'      => $ext,
		'width'    => $width,
		'height'   => $height,
	);
}

/**
 * Bakes EXIF orientation into pixels then rotates the Imagick result
 * when the current Imagick build has no autoOrient() — same fallback
 * switch TheUnframer's own converters use.
 */
function rrt_label_convert_auto_orient( Imagick $im, $background = '#000' ) {
	if ( is_callable( array( $im, 'autoOrient' ) ) ) {
		$im->autoOrient();
		return;
	}
	switch ( $im->getImageOrientation() ) {
		case Imagick::ORIENTATION_BOTTOMRIGHT:
			$im->rotateImage( $background, 180 );
			break;
		case Imagick::ORIENTATION_RIGHTTOP:
			$im->rotateImage( $background, 90 );
			break;
		case Imagick::ORIENTATION_LEFTBOTTOM:
			$im->rotateImage( $background, -90 );
			break;
	}
	$im->setImageOrientation( Imagick::ORIENTATION_TOPLEFT );
}

/**
 * HEIC/HEIF -> JPEG. Direct port of TheUnframer's
 * theunframer_convert_heic_to_jpeg_inplace(), minus the in-place
 * $_FILES mutation (this endpoint responds with a data: URL instead).
 */
function rrt_label_convert_heic_to_jpeg( $tmp_path ) {
	try {
		$im = new Imagick( $tmp_path );
		rrt_label_convert_auto_orient( $im );
		$im->setImageFormat( 'jpeg' );
		$im->setImageCompressionQuality( 98 );
		$im->setCompressionQuality( 98 );
		// 4:4:4 chroma, no stripImage() -- keeps the embedded ICC profile.
		$im->setSamplingFactors( array( '1x1', '1x1', '1x1' ) );
		return rrt_label_convert_respond( $im, 'jpg' );
	} catch ( Exception $e ) {
		return new WP_Error( 'heic_convert_failed', 'Could not convert this HEIC photo: ' . $e->getMessage() );
	}
}

/**
 * Camera RAW (CR2/CR3/NEF/ARW/DNG/ORF/RAF/RW2) -> JPEG. These are
 * genuine photos (no alpha expected), so the same JPEG pipeline as HEIC
 * applies -- no setResolution() (that's the vector-only step; calling
 * it on a raster/RAW source resamples pixels instead of just changing
 * rasterization density, same gotcha TheUnframer's own converter
 * documents).
 */
function rrt_label_convert_raw_to_jpeg( $tmp_path ) {
	try {
		$im = new Imagick( $tmp_path );
		rrt_label_convert_auto_orient( $im );
		$im->setImageFormat( 'jpeg' );
		$im->setImageCompressionQuality( 98 );
		$im->setCompressionQuality( 98 );
		$im->setSamplingFactors( array( '1x1', '1x1', '1x1' ) );
		return rrt_label_convert_respond( $im, 'jpg' );
	} catch ( Exception $e ) {
		return new WP_Error( 'raw_convert_failed', 'Could not convert this camera RAW file: ' . $e->getMessage() );
	}
}

/**
 * PDF/EPS/AI/PSD/TIFF -> TRANSPARENT PNG. Same Imagick mechanics as
 * TheUnframer's theunframer_convert_any_to_jpeg_inplace() (vector DPI
 * handling, PDF [0]-page-select, auto-orient) but deliberately keeps
 * alpha and targets PNG instead of flattening onto white -- these are
 * logo/artwork files meant to sit on top of the label template, not
 * full-frame photos (see this file's own top-of-file note on why).
 *
 * DPI is a fixed, generous value for vector formats rather than
 * TheUnframer's 8000px-long-side probe formula -- RRT's labels are a
 * few inches, not large-format prints, so that formula would produce a
 * needlessly huge canvas here. 600 DPI capped to a 4000px long side is
 * comfortably above what any of this plugin's label sizes need.
 */
function rrt_label_convert_vector_to_png( $tmp_path, $ext ) {
	$vector_exts = array( 'pdf', 'eps', 'ai' );
	$is_vector   = in_array( $ext, $vector_exts, true );
	$src         = $tmp_path . ( 'pdf' === $ext ? '[0]' : '' ); // first page only.

	try {
		$im = new Imagick();
		if ( $is_vector ) {
			$render_dpi = 600;
			$im->setResolution( $render_dpi, $render_dpi );
		}
		$im->readImage( $src );
		$im->setIteratorIndex( 0 ); // first layer/page only.

		// Cap runaway-large renders (e.g. a huge physical-page PDF at
		// 600 DPI) down to a sane ceiling, preserving aspect ratio.
		$max_dim    = 4000;
		$cur_width  = $im->getImageWidth();
		$cur_height = $im->getImageHeight();
		$long_side  = max( $cur_width, $cur_height );
		if ( $long_side > $max_dim ) {
			$scale = $max_dim / $long_side;
			$im->resizeImage( (int) round( $cur_width * $scale ), (int) round( $cur_height * $scale ), Imagick::FILTER_LANCZOS, 1 );
		}

		// Keep transparency -- do NOT flatten onto white. A PSD/TIFF
		// with no alpha channel at all just comes out fully opaque,
		// which is fine; this only matters for sources that DO have it.
		$im->setImageBackgroundColor( new ImagickPixel( 'transparent' ) );
		$flat = $im->mergeImageLayers( Imagick::LAYERMETHOD_MERGE );
		$im->clear();

		rrt_label_convert_auto_orient( $flat, 'transparent' );

		$flat->setImageFormat( 'png' );
		$flat->setImageAlphaChannel( Imagick::ALPHACHANNEL_ACTIVATE );

		return rrt_label_convert_respond( $flat, 'png' );
	} catch ( Exception $e ) {
		return new WP_Error(
			'vector_convert_failed',
			'Could not convert this ' . strtoupper( $ext ) . ' file: ' . $e->getMessage() . ' -- try exporting it as a JPG or PNG instead.'
		);
	}
}
