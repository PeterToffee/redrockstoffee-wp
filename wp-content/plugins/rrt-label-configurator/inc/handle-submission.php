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

/**
 * Pete, 2026-10-27: "have those saved in a manner so they are grouped with
 * all the parts, easily identifiable and side by side in a folder" — one
 * folder per order (timestamped + the customer's name, so it sorts and
 * reads at a glance in a file browser), holding every part of that one
 * order together: the flat label, the separate layers, the print sheet,
 * the customer's original upload(s), and a plain-text order-info.txt.
 * Lives under wp-content/uploads so it survives independent of any single
 * request, unlike PHP's own upload tmp files.
 */
function rrt_label_order_folder( $name ) {
	$upload_dir = wp_upload_dir();
	$orders_dir = trailingslashit( $upload_dir['basedir'] ) . 'rrt-label-orders';
	$name_part  = sanitize_file_name( $name ? $name : 'customer' );
	$folder     = trailingslashit( $orders_dir ) . gmdate( 'Y-m-d-His' ) . '-' . $name_part;
	wp_mkdir_p( $folder );

	// Same blank-index convention WordPress core itself uses throughout
	// wp-content/uploads — doesn't stop a direct, known URL from working
	// (nothing here is secret-by-URL), just stops a bare directory listing
	// from handing out every customer's name/order folder at once.
	$index_file = trailingslashit( $orders_dir ) . 'index.php';
	if ( ! file_exists( $index_file ) ) {
		file_put_contents( $index_file, "<?php\n// Silence is golden.\n" );
	}

	return $folder;
}

/**
 * One design's logo/text layers, resolved from the raw client JSON +
 * this design's own uploaded logo files down to the array
 * rrt_label_composite()/rrt_label_composite_layers() expect. Extracted
 * from rrt_label_handle_submit() (Pete, 2026-11-07: "put together an
 * order for each size... or more than one of one size") so the exact
 * same per-layer resolving logic (art_key lookup, low-quality DPI check,
 * sanitizing) runs once per DESIGN in the loop below, instead of being
 * duplicated inline for each one.
 *
 * Returns array( 'layers' => [...], 'dropped_logo_count' => int ).
 *
 * $design_i identifies this design within the submission purely so a
 * real-upload logo layer can look up its own OPTIONAL preserved original
 * (see label-configurator.js's placeLogoFile()/buildLayersMetaForDraft())
 * under $_FILES['logo_original_' . $design_i . '_' . $logo_i] — only
 * present when that logo was converted on the way in (HEIC/PDF/EPS/AI/
 * PSD/TIFF/RAW), never for a plain PNG/JPEG/WEBP upload.
 */
function rrt_label_resolve_design_layers( $layers_raw, $logo_tmp_paths, $sizes, $size_key, $design_i = 0 ) {
	$art_library = rrt_label_art_library();

	$layers = array();
	$logo_i = 0;
	// 2026-10-30 RED ALERT: a customer's logo layer could silently vanish
	// between the browser and this email with zero trace anywhere — no
	// warning on the order, no note in order-info.txt, nothing. The client
	// now rejects unsupported files with a clear message before submit (see
	// label-configurator.js), but this counter is a safety net for any
	// OTHER way a logo layer's file could fail to arrive (a future bug, a
	// request made outside the normal form) — if it ever happens again, Pete
	// sees it plainly instead of the order just looking complete.
	$dropped_logo_count = 0;
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
			// Pete, 2026-11-11: "the slider has to stop enlarging when the
			// file hits [the dotted line]" — Bill Bartels' order showed a
			// scale of 150 (% of the zone's own width) draws wider than the
			// whole label with no clipping here, eating the paper border
			// and chopping part of the image off at the canvas edge. The
			// client now hard-caps at 100 (clampLogoScale() in
			// label-configurator.js) so the dotted line is a real ceiling,
			// not just a visual one — this mirrors that cap server-side too,
			// so a stale cached JS bundle (or any other future caller) can't
			// resurrect the bug.
			if ( $art_key && isset( $art_library[ $art_key ] ) ) {
				$layers[] = array(
					'type'     => 'logo',
					'pos'      => $pos,
					'scale'    => min( 100, max( 0, (float) ( $raw['scale'] ?? 0 ) ) ),
					'tmp_path' => RRT_LABEL_PATH . $art_library[ $art_key ]['file'],
					'art_key'  => $art_key,
				);
				continue;
			}
			if ( ! isset( $logo_tmp_paths[ $logo_i ] ) ) {
				++$logo_i;
				++$dropped_logo_count;
				continue;
			}
			$logo_scale = min( 100, max( 0, (float) ( $raw['scale'] ?? 0 ) ) );
			// Pete, 2026-10-26: "I don't want to take someone's money and
			// print a really sh*t label from a garbage file" — warn, don't
			// block (confirmed via AskUserQuestion), so Pete sees it in his
			// own review email (rrt_label_email_submission()) even if the
			// customer ignored the on-page warning. Uses the CUSTOMER'S
			// ACTUAL chosen size, not just the raw file's resolution — a
			// huge photo dragged to a huge size can still look like garbage.
			// 200 DPI = a real-world "acceptable, not garbage" print floor,
			// looser than this plugin's own 300 DPI generation target.
			$low_quality = false;
			if ( $logo_scale > 0 ) {
				$logo_info = getimagesize( $logo_tmp_paths[ $logo_i ] );
				if ( $logo_info ) {
					$rendered_width_in = $sizes[ $size_key ]['zone']['width_in'] * ( $logo_scale / 100 );
					if ( $rendered_width_in > 0 ) {
						// A 90-degree EXIF rotation (see rrt_label_fix_jpeg_orientation())
						// swaps which raw dimension ends up as the actual printed
						// width, so this has to agree or the DPI check reads the
						// wrong axis for a sideways-shot phone photo.
						$logo_width = $logo_info[0];
						if ( 'image/jpeg' === $logo_info['mime'] && function_exists( 'exif_read_data' ) ) {
							$exif = @exif_read_data( $logo_tmp_paths[ $logo_i ] );
							if ( $exif && in_array( $exif['Orientation'] ?? 1, array( 6, 8 ), true ) ) {
								$logo_width = $logo_info[1];
							}
						}
						$effective_dpi = $logo_width / $rendered_width_in;
						$low_quality   = $effective_dpi < 200;
					}
				}
			}
			$layer = array(
				'type'        => 'logo',
				'pos'         => $pos,
				'scale'       => $logo_scale,
				'tmp_path'    => $logo_tmp_paths[ $logo_i ],
				// Pete, 2026-10-26: "donate their image for others to use...
				// immediately goes to our library" — only meaningful for a
				// real upload (never set for an art_key layer, handled in
				// the branch above). Actually copied into the Media Library
				// near the end of rrt_label_handle_submit(), after the real
				// order's composite/email already succeeded.
				'donate'      => ! empty( $raw['donate'] ),
				'low_quality' => $low_quality,
			);
			// Pete, 2026-11-13: "I have people throwing all sorts of files" —
			// when this logo was converted on the way in, the customer's real
			// original rides along under its own singular field (see
			// inc/convert-logo.php + label-configurator.js's submit handler);
			// rrt_label_save_originals() prefers this over $layer['tmp_path']
			// (the converted PNG/JPEG) so Pete still gets the authentic file.
			$original_field = 'logo_original_' . $design_i . '_' . $logo_i;
			if ( ! empty( $_FILES[ $original_field ]['tmp_name'] ) && is_uploaded_file( $_FILES[ $original_field ]['tmp_name'] ) ) {
				$layer['original_tmp_path'] = $_FILES[ $original_field ]['tmp_name'];
				$layer['original_name']     = isset( $_FILES[ $original_field ]['name'] ) ? sanitize_file_name( wp_unslash( $_FILES[ $original_field ]['name'] ) ) : '';
			}
			$layers[] = $layer;
			++$logo_i;
		} elseif ( 'text' === $raw['type'] ) {
			// Pete, 2026-11-11: "I had added a blank line with an extra
			// enter, it looked right on the preview but didn't give it to
			// me on the print" — sanitize_text_field() is WP core's
			// SINGLE-LINE sanitizer; it explicitly strips every line break
			// (blank lines included) before this even reaches
			// rrt_label_draw_text()'s own paragraph-splitting fix below, so
			// fixing that function alone was never going to be enough.
			// sanitize_textarea_field() is WP core's multi-line equivalent
			// (same one $message already correctly uses a few lines down) —
			// it still strips tags/invalid UTF-8 but leaves \n alone.
			$text = isset( $raw['text'] ) ? sanitize_textarea_field( wp_unslash( $raw['text'] ) ) : '';
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
				// Pete, 2026-10-25: "confirm all elements... have no
				// bugs" — was never read from $raw at all; the TEXT WIDTH
				// slider's value silently never reached the print file
				// (see rrt_label_draw_text()'s own fix for the other
				// half of this). Default 2 matches the client's own
				// defaultQuote() default (label-configurator.js).
				'side_margin' => max( 0, min( 50, (float) ( $raw['side_margin'] ?? 2 ) ) ),
				// Bold implemented below (rrt_label_draw_text()'s faux-bold
				// double-strike). Italic captured here too but NOT yet
				// applied server-side — see that function's own note on
				// why (imageaffine() needed for a real shear, availability
				// unconfirmed on the live host).
				'bold'   => ! empty( $raw['bold'] ),
				'italic' => ! empty( $raw['italic'] ),
			);
		}
	}

	return array(
		'layers'             => $layers,
		'dropped_logo_count' => $dropped_logo_count,
	);
}

add_action( 'wp_ajax_rrt_label_submit', 'rrt_label_handle_submit' );
add_action( 'wp_ajax_nopriv_rrt_label_submit', 'rrt_label_handle_submit' );
/**
 * Pete, 2026-11-07: "so far we have two files user stuff gets jammed
 * into. We need to make allowances so if user wishes to put together an
 * order for each size, file is saved(held)... until files sent by
 * 'submit my files design(s)'" — this now processes a LIST of designs
 * (each with its own flavor/size/quantity/layers/logo files) in one
 * submission instead of exactly one. Every per-design step (composite,
 * layers, print sheet, original uploads, proof) is completely unchanged
 * from the single-design version — it just runs once per design, into
 * its own subfolder of one shared $order_dir, so nothing from one design
 * can collide with or overwrite another's (e.g. two held 8oz designs
 * would otherwise both want "label-flat.png" in the same folder).
 */
function rrt_label_handle_submit() {
	check_ajax_referer( 'rrt_label_submit', 'nonce' );

	$name    = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	$email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$phone   = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
	$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

	if ( empty( $name ) || empty( $email ) || ! is_email( $email ) ) {
		wp_send_json_error( array( 'message' => 'Please fill in your name and a valid email.' ) );
	}

	// One entry per held/current design: { flavor, size, quantity, layers }.
	// 'layers' here is the SAME raw per-layer JSON the single-design
	// version used to send as the top-level 'layers' field — see
	// label-configurator.js's buildLayersMetaForDraft().
	$designs_raw = isset( $_POST['designs'] ) ? json_decode( wp_unslash( $_POST['designs'] ), true ) : null;
	if ( ! is_array( $designs_raw ) || empty( $designs_raw ) ) {
		wp_send_json_error( array( 'message' => 'Please add a logo and/or some text to your label.' ) );
	}

	$flavors         = rrt_label_flavors();
	$sizes_by_flavor = array(); // cache so rrt_label_sizes() isn't rebuilt per design sharing a flavor.
	$order_dir       = rrt_label_order_folder( $name );

	$design_results            = array();
	$dropped_logo_count_total  = 0;

	foreach ( $designs_raw as $design_i => $design_raw ) {
		if ( ! is_array( $design_raw ) ) {
			continue;
		}

		$flavor_key = isset( $design_raw['flavor'] ) ? sanitize_key( $design_raw['flavor'] ) : 'original-almond';
		if ( ! isset( $flavors[ $flavor_key ] ) || empty( $flavors[ $flavor_key ]['available'] ) ) {
			$flavor_key = 'original-almond';
		}
		if ( ! isset( $sizes_by_flavor[ $flavor_key ] ) ) {
			$sizes_by_flavor[ $flavor_key ] = rrt_label_sizes( $flavor_key );
		}
		$sizes = $sizes_by_flavor[ $flavor_key ];

		$size_key = isset( $design_raw['size'] ) ? sanitize_text_field( wp_unslash( $design_raw['size'] ) ) : '';
		if ( ! isset( $sizes[ $size_key ] ) ) {
			continue; // an unrecognized size in one design shouldn't sink the whole order.
		}

		// Pete, 2026-10-19: "quantity plus 5% in the file name" — this is a
		// stand-in for a real WooCommerce order quantity (that integration
		// is explicitly deferred — "we'll coordinate with woocommerce, but
		// not right now"), just enough to get the print-ready sheet feature
		// working end to end today. Clamped up to the known per-size
		// minimum (never silently under-delivers) rather than rejecting
		// the submission over a quantity mistake.
		$quantity = isset( $design_raw['quantity'] ) ? (int) $design_raw['quantity'] : 1;
		$quantity = max( rrt_label_min_qty_for_size( $size_key ), $quantity );

		// Pete, 2026-11-13: "what if I have notes for EACH label" — a
		// per-design note (captured in the box-preview popup, see
		// label-configurator.js), separate from $message's whole-order
		// note above.
		$design_note = isset( $design_raw['note'] ) ? sanitize_textarea_field( wp_unslash( $design_raw['note'] ) ) : '';

		$layers_raw = is_array( $design_raw['layers'] ?? null ) ? $design_raw['layers'] : array();
		if ( empty( $layers_raw ) ) {
			continue;
		}

		// The actual uploaded logo FILES for THIS design travel separately
		// as logo_files_{i}[] (multipart can't nest files inside JSON, and
		// each design needs its own slot so one design's uploads can't be
		// mismatched against another's 'logo' entries) — see
		// label-configurator.js's submit handler.
		$logo_tmp_paths = array();
		$files_field    = 'logo_files_' . $design_i;
		if ( ! empty( $_FILES[ $files_field ]['tmp_name'] ) && is_array( $_FILES[ $files_field ]['tmp_name'] ) ) {
			foreach ( $_FILES[ $files_field ]['tmp_name'] as $tmp_name ) {
				if ( is_uploaded_file( $tmp_name ) ) {
					$logo_tmp_paths[] = $tmp_name;
				}
			}
		}

		$resolved = rrt_label_resolve_design_layers( $layers_raw, $logo_tmp_paths, $sizes, $size_key, $design_i );
		$layers   = $resolved['layers'];
		if ( empty( $layers ) ) {
			continue;
		}

		// Pete, 2026-10-27: "have those saved in a manner so they are
		// grouped with all the parts, easily identifiable and side by
		// side in a folder" — still true per design, just one level
		// deeper now: each design gets its own subfolder of the shared
		// order folder so two designs' same-named output files (both
		// produce "label-flat.png" etc.) can never collide.
		$design_dir = trailingslashit( $order_dir ) . sprintf( 'design-%d-%s-%s', $design_i + 1, $flavor_key, $size_key );
		wp_mkdir_p( $design_dir );

		// Pete, 2026-11-08: "I get emails with the order, customer etc,
		// but the file carries NO info other than a count, we need more
		// identification in the filename itself" -> "filename, customer/
		// quant/size/flavor maybe?" — prepended to every file this design
		// produces (see $file_prefix in rrt_label_composite()'s own note),
		// so each file still identifies its order on its own once saved
		// out of the order folder, not just by sitting inside it.
		$file_prefix = sanitize_file_name( $name ) . '-qty' . $quantity . '-' . $size_key . '-' . $flavor_key . '-';

		// Pete, 2026-10-20: tiny sort mark baked into the label itself (see
		// rrt_label_draw_order_id()) — name + quantity, the two things
		// he'd actually look for while sorting a stack of freshly cut
		// labels.
		$order_id_text  = sprintf( "%s \xC2\xB7 Qty %d", $name, $quantity );
		$composite_path = rrt_label_composite( $size_key, $layers, $design_dir, $order_id_text, $flavor_key, $file_prefix );
		if ( is_wp_error( $composite_path ) ) {
			continue; // this one design failed to composite — don't let it sink every other held design in the order.
		}

		// Pete, 2026-10-18: "I need to get the file out of the
		// configurator flat and layered... with the full file at 300 dpi
		// ready to print" — see rrt_label_composite_layers()'s own note on
		// why this exists; not fatal if it fails.
		$layer_paths = rrt_label_composite_layers( $size_key, $layers, $design_dir, $flavor_key, $file_prefix );
		if ( is_wp_error( $layer_paths ) ) {
			$layer_paths = array();
		}

		// Pete, 2026-10-19: the print-ready sheet — see
		// rrt_label_composite_sheet()'s own note. 2026-11-08: "just give
		// me the number order, do not add more than what is in the
		// quantity the customer asks for" — no more +5% buffer; this is
		// exactly the customer's requested quantity.
		$labels_needed = $quantity;
		$sheet_path    = rrt_label_composite_sheet( $size_key, $composite_path, $design_dir, $labels_needed, $file_prefix );
		if ( is_wp_error( $sheet_path ) ) {
			$sheet_path = null;
		}

		$original_paths = rrt_label_save_originals( $design_dir, $layers, $file_prefix );

		$proof_path = rrt_label_composite_proof( $size_key, $composite_path, $design_dir, $file_prefix );
		if ( is_wp_error( $proof_path ) ) {
			$proof_path = null;
		}

		$design_results[] = array(
			'flavor_label'       => $flavors[ $flavor_key ]['label'],
			'size_label'         => $sizes[ $size_key ]['label'],
			'quantity'           => $quantity,
			'note'               => $design_note,
			'labels_needed'      => $labels_needed,
			'layers'             => $layers,
			'dropped_logo_count' => $resolved['dropped_logo_count'],
			'composite_path'     => $composite_path,
			'layer_paths'        => $layer_paths,
			'sheet_path'         => $sheet_path,
			'original_paths'     => $original_paths,
			'proof_path'         => $proof_path,
		);
		$dropped_logo_count_total += $resolved['dropped_logo_count'];
	}

	if ( empty( $design_results ) ) {
		wp_send_json_error( array( 'message' => 'Please add a logo and/or some text to your label.' ) );
	}

	$order_fields = array(
		'name'               => $name,
		'email'              => $email,
		'phone'              => $phone,
		'message'            => $message,
		'quantity'           => array_sum( wp_list_pluck( $design_results, 'quantity' ) ),
		'labels_needed'      => array_sum( wp_list_pluck( $design_results, 'labels_needed' ) ),
		'dropped_logo_count' => $dropped_logo_count_total,
	);

	// Email is attempted (useful once this is live on GoDaddy, where real
	// mail delivery works), but Pete, 2026-10-18: "don't need mail, it can
	// go to a file, just give me the folder/filename" — on local XAMPP
	// there's no mail server behind `wp_mail()`, so it either silently
	// fails or just never gets tested at all. Either way, the files on
	// disk (now in $order_dir, alongside order-info.txt below) are the
	// real, always-true answer regardless of whether mail works.
	rrt_label_email_submission( $order_fields, $design_results, $order_dir );

	// Same details as the email body, saved to disk too so the folder is
	// self-contained — every part of the order "side by side," per Pete's
	// own ask, without needing to go dig the matching email back out.
	rrt_label_write_order_info( $order_dir, $order_fields, $design_results );

	// Pete, 2026-10-26: "donate their image for others to use... immediately
	// goes to our library" — purely additive, runs AFTER the real order
	// already succeeded above, so any failure here (bad file, disk issue,
	// etc.) can never break the actual submission. $name/$email ARE passed
	// through (Pete's own follow-up: "we'll still need to know who it is...
	// to contact if there's something that's not allowable, or to flag them
	// as unwelcome donator") but rrt_label_donate_to_community_library()
	// (rrt-label-configurator.php) stores them as PRIVATE admin-only
	// postmeta only — never public, never returned by
	// rrt_label_community_art(). Itself quietly no-ops on any error.
	foreach ( $design_results as $design_result ) {
		foreach ( $design_result['layers'] as $layer ) {
			if ( 'logo' === $layer['type'] && ! empty( $layer['donate'] ) && empty( $layer['art_key'] ) ) {
				rrt_label_donate_to_community_library( $layer['tmp_path'], $name, $email );
			}
		}
	}

	$all_paths = array();
	foreach ( $design_results as $design_result ) {
		$all_paths = array_merge(
			$all_paths,
			array( $design_result['composite_path'] ),
			$design_result['layer_paths'],
			$design_result['original_paths'],
			$design_result['sheet_path'] ? array( $design_result['sheet_path'] ) : array(),
			$design_result['proof_path'] ? array( $design_result['proof_path'] ) : array()
		);
	}

	$design_count = count( $design_results );

	wp_send_json_success(
		array(
			// Pete, 2026-10-27: this used to echo the server's own folder
			// path straight into the customer-facing status message (a
			// leftover from local testing, before real mail delivery was
			// live) — fixed to a generic confirmation; the real folder/
			// filenames still ride along in the JSON for anyone (Pete,
			// browser devtools) who actually needs them.
			'message'   => $design_count > 1
				? "Thanks, {$name}! Your {$design_count} label designs have been submitted — we'll be in touch."
				: "Thanks, {$name}! Your label design has been submitted — we'll be in touch.",
			'folder'    => wp_normalize_path( $order_dir ),
			'filenames' => array_map( 'basename', $all_paths ),
		)
	);
}

/**
 * Copies each customer's ORIGINAL raw uploaded logo file into the order
 * folder too — PHP deletes its own upload tmp file at the end of the
 * request, so without this the source image would only ever exist as an
 * email attachment, never saved anywhere alongside the rest of the
 * order's parts. Skips art_key layers (this plugin's own bundled art, not
 * a customer upload — nothing original to save).
 *
 * $file_prefix — see rrt_label_composite()'s own note on this same param.
 */
function rrt_label_save_originals( $order_dir, $layers, $file_prefix = '' ) {
	$paths = array();
	$n     = 0;
	foreach ( $layers as $layer ) {
		if ( 'logo' !== $layer['type'] || ! empty( $layer['art_key'] ) ) {
			continue;
		}
		// Pete, 2026-11-13: "I have people throwing all sorts of files" —
		// a HEIC/PDF/EPS/AI/PSD/TIFF/RAW upload arrives here as its
		// already-converted PNG/JPEG ($layer['tmp_path']), with the
		// customer's authentic original (if any) riding along separately
		// as $layer['original_tmp_path'] (see
		// rrt_label_resolve_design_layers()'s own note on where that comes
		// from). Prefer the real original when present — Pete gets the
		// customer's actual file, not just what Imagick made of it.
		if ( ! empty( $layer['original_tmp_path'] ) && file_exists( $layer['original_tmp_path'] ) ) {
			$ext = strtolower( pathinfo( $layer['original_name'] ?? '', PATHINFO_EXTENSION ) );
			if ( ! $ext ) {
				$ext = 'dat'; // name didn't survive the upload intact — still save the bytes.
			}
			++$n;
			$dest = sprintf( '%s/%soriginal-logo-%d.%s', $order_dir, $file_prefix, $n, $ext );
			if ( copy( $layer['original_tmp_path'], $dest ) ) {
				$paths[] = $dest;
			}
			continue;
		}
		if ( empty( $layer['tmp_path'] ) || ! file_exists( $layer['tmp_path'] ) ) {
			continue;
		}
		// Same getimagesize()-based mime detection as
		// rrt_label_donate_to_community_library() — $tmp_path is PHP's raw
		// upload tmp file (e.g. "phpA1B2.tmp") with no real extension.
		$info = getimagesize( $layer['tmp_path'] );
		$ext  = isset( $info['mime'] ) ? array_search( $info['mime'], array( 'png' => 'image/png', 'jpg' => 'image/jpeg', 'webp' => 'image/webp' ), true ) : false;
		if ( ! $ext ) {
			continue;
		}
		++$n;
		$dest = sprintf( '%s/%soriginal-logo-%d.%s', $order_dir, $file_prefix, $n, $ext );
		if ( copy( $layer['tmp_path'], $dest ) ) {
			$paths[] = $dest;
		}
	}
	return $paths;
}

/**
 * Writes a plain-text summary of the order into the same per-order
 * folder as the image files — the same details as the review email
 * (rrt_label_email_submission()), but saved to disk too so the folder is
 * self-contained and "easily identifiable" without needing the matching
 * email alongside it.
 *
 * Pete, 2026-11-07: $order_fields now holds only the customer-level
 * fields (name/email/phone/message/totals) — per-design details
 * (size/flavor/quantity/layers) live in $design_results, one section per
 * held design, so an order with 3 designs reads as 3 sections instead of
 * one design's info silently standing in for the whole order.
 */
function rrt_label_write_order_info( $order_dir, $order_fields, $design_results ) {
	$body  = 'Name: ' . $order_fields['name'] . "\n";
	$body .= 'Email: ' . $order_fields['email'] . "\n";
	$body .= 'Phone: ' . $order_fields['phone'] . "\n";
	// Pete, 2026-11-08: "just give me the number order, do not add more
	// than what is in the quantity the customer asks for" — labels_needed
	// is now always exactly $quantity (no more +5% buffer), so the old
	// second "labels to print" line was just a confusing duplicate of
	// this one; dropped rather than reworded.
	$body .= 'Total quantity requested (all designs): ' . $order_fields['quantity'] . "\n";

	if ( ! empty( $order_fields['dropped_logo_count'] ) ) {
		$body .= "\n\xE2\x9A\xA0\xE2\x9A\xA0 " . $order_fields['dropped_logo_count'] . " image(s) the customer tried to add did NOT come through -- this order may be MISSING ARTWORK. Contact the customer before printing.\n\n";
	}

	foreach ( $design_results as $i => $design_result ) {
		$body .= "\n--- Design " . ( $i + 1 ) . ': ' . $design_result['flavor_label'] . ' / ' . $design_result['size_label'] . " ---\n";
		$body .= 'Quantity requested: ' . $design_result['quantity'] . "\n";
		if ( ! empty( $design_result['note'] ) ) {
			$body .= 'Note for this label: ' . $design_result['note'] . "\n";
		}

		$logo_n = 0;
		$text_n = 0;
		foreach ( $design_result['layers'] as $layer ) {
			if ( 'logo' === $layer['type'] ) {
				++$logo_n;
				if ( ! empty( $layer['low_quality'] ) ) {
					$body .= "\xE2\x9A\xA0 Logo {$logo_n} may be low-resolution for the size it was placed at — check before printing.\n";
				}
			} else {
				++$text_n;
				$body .= 'Text ' . $text_n . ': ' . $layer['text'] . "\n";
			}
		}
	}

	if ( $order_fields['message'] ) {
		$body .= "\nMessage:\n" . $order_fields['message'] . "\n";
	}

	file_put_contents( $order_dir . '/order-info.txt', $body );
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
 *
 * $order_id_text (optional) bakes a tiny sort mark into the label itself
 * — see rrt_label_draw_order_id() — BEFORE this file gets tiled into the
 * print sheet (rrt_label_composite_sheet() just copies this same saved
 * PNG N times), so every copy on the printed sheet already carries it.
 *
 * Saves into $order_dir (one folder per order — see
 * rrt_label_order_folder()) rather than a shared temp folder, so this
 * file sits "grouped with all the parts, side by side" alongside the
 * layers, print sheet, and original upload(s) from the same order.
 *
 * $flavor_key (Pete, 2026-11-07) picks which flavor's template image to
 * composite onto — see rrt_label_flavors() in rrt-label-configurator.php.
 *
 * $file_prefix (Pete, 2026-11-08: "I get emails with the order, customer
 * etc, but the file carries NO info other than a count, we need more
 * identification in the filename itself") — prepended to every file this
 * function (and its siblings below) writes, so a file still identifies
 * its order/customer/design even after being saved out of its own folder
 * (e.g. straight from the email's download link). Built once per design
 * in rrt_label_handle_submit() as "{customer-name}-{flavor}-{size}-".
 */
function rrt_label_composite( $size_key, $layers, $order_dir, $order_id_text = '', $flavor_key = 'original-almond', $file_prefix = '' ) {
	$px = rrt_label_zone_px( $size_key );
	if ( ! $px ) {
		return new WP_Error( 'rrt_bad_size', 'Unknown box size.' );
	}

	$sizes    = rrt_label_sizes( $flavor_key );
	// Pete, 2026-11-11: "it prints differently than if I go straight to
	// the printer" — template_print_file (not template_file) is what GD
	// gets here; see rrt_label_sizes()'s own note on why.
	$template = RRT_LABEL_PATH . $sizes[ $size_key ]['template_print_file'];
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
			rrt_label_draw_text( $canvas, $px, $layer['text'], $layer['pos'], $layer['font'], $layer['font_size'], $layer['color'], $layer['outline'], $layer['shadow'], $layer['shadow_intensity'], $layer['side_margin'] ?? 2, $layer['bold'] ?? false );
		}
	}

	rrt_label_draw_order_id( $canvas, $size_key, $order_id_text );

	$out_path = $order_dir . '/' . $file_prefix . 'label-flat.png';
	imagepng( $canvas, $out_path, 6 ); // 6 = a reasonable PNG compression level; always lossless regardless of level.
	imagedestroy( $canvas );
	rrt_label_set_png_dpi( $out_path, 300 );

	return $out_path;
}

/**
 * Pete, 2026-10-20: "we need to identify each single label with order
 * info/person/user/quantity near the top of each single label (a little
 * more than 1/4" down from the top), very tiny lettering... so when we
 * print and cut, we can sort out the labels by the end." A utilitarian
 * sort mark, not part of the customer's design — plain small text,
 * centered, placed in the wide blank margin above the Nutrition Facts
 * panel (confirmed empty from the top edge down to roughly 1in on both
 * box sizes, by directly cropping/inspecting both templates), so it
 * never collides with real label artwork.
 *
 * Font size 16 measured (imagettfbbox) at ~0.073in tall on this 300 DPI
 * canvas — comparable to real fine-print text, legible but out of the
 * way.
 */
function rrt_label_draw_order_id( $canvas, $size_key, $id_text ) {
	if ( '' === trim( $id_text ) ) {
		return;
	}
	$sizes = rrt_label_sizes();
	$dpi   = $sizes[ $size_key ]['dpi'];
	$width = imagesx( $canvas );

	$ttf_path = RRT_LABEL_PATH . 'assets/fonts/opensans-variable.ttf';
	if ( ! file_exists( $ttf_path ) ) {
		return;
	}

	$font_size_px = 16;
	$color        = imagecolorallocate( $canvas, 40, 40, 40 );
	$y            = (int) round( 0.3 * $dpi ); // "a little more than 1/4 inch down from the top"

	$bbox       = imagettfbbox( $font_size_px, 0, $ttf_path, $id_text );
	$text_width = $bbox[2] - $bbox[0];
	$x          = (int) round( ( $width - $text_width ) / 2 );

	imagettftext( $canvas, $font_size_px, 0, $x, $y, $color, $ttf_path, $id_text );
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
 *
 * Saves into $order_dir (see rrt_label_composite()'s own note on this).
 *
 * $flavor_key / $file_prefix — see rrt_label_composite()'s own notes on
 * these same params.
 */
function rrt_label_composite_layers( $size_key, $layers, $order_dir, $flavor_key = 'original-almond', $file_prefix = '' ) {
	$px = rrt_label_zone_px( $size_key );
	if ( ! $px ) {
		return new WP_Error( 'rrt_bad_size', 'Unknown box size.' );
	}

	$sizes    = rrt_label_sizes( $flavor_key );
	// See rrt_label_composite()'s own note just above on template_print_file.
	$template = RRT_LABEL_PATH . $sizes[ $size_key ]['template_print_file'];
	if ( ! file_exists( $template ) ) {
		return new WP_Error( 'rrt_missing_template', 'Label template is missing on the server.' );
	}

	$template_img = imagecreatefromjpeg( $template );
	if ( ! $template_img ) {
		return new WP_Error( 'rrt_bad_template', 'Could not open the label template.' );
	}
	$width  = imagesx( $template_img );
	$height = imagesy( $template_img );

	$paths = array();

	// Layer 0: the bare template itself, so the full stack is self-
	// contained (Pete doesn't have to separately dig up the master
	// template file to reconstruct the design).
	$base_path = $order_dir . '/' . $file_prefix . 'layer-00-template.png';
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
			rrt_label_draw_text( $layer_canvas, $px, $layer['text'], $layer['pos'], $layer['font'], $layer['font_size'], $layer['color'], $layer['outline'], $layer['shadow'], $layer['shadow_intensity'], $layer['side_margin'] ?? 2, $layer['bold'] ?? false );
			$suffix = 'text';
		}

		$layer_path = sprintf( '%s/%slayer-%02d-%s.png', $order_dir, $file_prefix, $i, sanitize_key( $suffix ) );
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
 *
 * Saves into $order_dir (see rrt_label_composite()'s own note on this).
 *
 * $file_prefix — see rrt_label_composite()'s own note on this same param;
 * $labels_needed used to be quantity+5% but Pete, 2026-11-08: "just give
 * me the number order, do not add more than what is in the quantity the
 * customer asks for" — it's exactly the customer's requested quantity now
 * (see rrt_label_handle_submit()), kept as its own param/name rather than
 * inlining $quantity so a future buffer policy (if ever wanted again) has
 * one place to change again.
 */
function rrt_label_composite_sheet( $size_key, $composite_path, $order_dir, $labels_needed, $file_prefix = '' ) {
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

	// 2026-10-19: 4oz's 3 labels (3 x 2.627in = 7.881in) don't exactly
	// fill an 8.25in sheet the way 8oz's 2 labels do (2 x 4.127in =
	// 8.254in, essentially edge to edge) — center the row instead of
	// pinning it to the left edge, so the leftover ~0.37in splits evenly
	// as a margin on both sides. No-op for 8oz (row already fills the
	// sheet, so the centering offset is 0).
	$row_width = $single_w * $sheet_cfg['labels_per_sheet'];
	$start_x   = (int) round( ( $sheet_w - $row_width ) / 2 );
	for ( $i = 0; $i < $sheet_cfg['labels_per_sheet']; $i++ ) {
		imagecopy( $sheet, $single, $start_x + $i * $single_w, 0, 0, 0, $single_w, $single_h );
	}
	imagedestroy( $single );

	$out_path = sprintf( '%s/%sprint-sheet-qty%d.png', $order_dir, $file_prefix, $labels_needed );

	imagepng( $sheet, $out_path, 6 );
	imagedestroy( $sheet );
	rrt_label_set_png_dpi( $out_path, 300 );

	return $out_path;
}

/**
 * Pete, 2026-10-27: "save a file off that includes the portion of the
 * label that is outside the canvas in the preview window and all inside
 * the scroller... so they know the outside of the label" — a customer-
 * facing proof, not a print file: crops the flat composite to exactly
 * the same window the on-screen preview's scroller shows (zone + the
 * mountain-logo/footer context below it — see rrt_label_crop_px(), the
 * same geometry the live configurator itself uses), so the customer sees
 * their logo/text in context on the real label artwork, not an isolated
 * rectangle.
 *
 * "outside border on either side with a torn look top and bottom... a
 * dotted line where our dotted line, centered on the label/box top" — a
 * decorative cream paper-strip trim on both sides (torn/jagged top and
 * bottom edges, like a strip hand-torn off a larger sheet) plus the same
 * dotted zone-boundary rectangle the live preview draws around the
 * customer's own draggable area, translated into this crop's coordinates
 * — so "here's the dotted line you placed things in" reads the same way
 * it did on screen. A caption band underneath states what the dotted
 * line means, since this image leaves the configurator's own on-screen
 * instructions behind.
 */
function rrt_label_composite_proof( $size_key, $composite_path, $order_dir, $file_prefix = '' ) {
	$px   = rrt_label_zone_px( $size_key );
	$crop = rrt_label_crop_px( $size_key );
	if ( ! $px || ! $crop ) {
		return new WP_Error( 'rrt_bad_size', 'Unknown box size.' );
	}

	$source = imagecreatefrompng( $composite_path );
	if ( ! $source ) {
		return new WP_Error( 'rrt_bad_composite', 'Could not open the composited label.' );
	}

	$border    = 60; // px either side - the torn-paper trim width.
	$caption_h = 110;
	$inner_w   = $crop['crop_width'];
	$inner_h   = $crop['crop_height'];
	$canvas_w  = $inner_w + ( $border * 2 );
	$canvas_h  = $inner_h + $caption_h;

	$canvas = imagecreatetruecolor( $canvas_w, $canvas_h );
	$white  = imagecolorallocate( $canvas, 255, 255, 255 );
	imagefilledrectangle( $canvas, 0, 0, $canvas_w - 1, $canvas_h - 1, $white );

	// The crop window itself, pasted flush against the torn trim.
	imagecopy( $canvas, $source, $border, 0, $crop['crop_x'], $crop['crop_y'], $inner_w, $inner_h );
	imagedestroy( $source );

	// Torn cream-paper strips on both sides, full crop height.
	$paper = imagecolorallocate( $canvas, 0xfd, 0xf3, 0xe7 ); // same cream used elsewhere in this plugin's UI
	$edge  = imagecolorallocate( $canvas, 0xd9, 0xc7, 0xa8 ); // a shade darker, for the torn edge's own line
	rrt_label_draw_torn_strip( $canvas, 0, $border, $inner_h, $paper, $edge );
	rrt_label_draw_torn_strip( $canvas, $border + $inner_w, $canvas_w, $inner_h, $paper, $edge );

	// The dotted zone-boundary rectangle, translated from full-template
	// pixel space into this crop's own coordinates (crop_x is always 0,
	// so only the y-offset and the added left border matter).
	$zone_x = $border + ( $px['zone_x'] - $crop['crop_x'] );
	$zone_y = $px['zone_y'] - $crop['crop_y'];
	$red    = imagecolorallocate( $canvas, 0x99, 0x1e, 0x21 );
	rrt_label_draw_dotted_rect( $canvas, $zone_x, $zone_y, $zone_x + $px['zone_width'], $zone_y + $px['zone_height'], $red, 3, 10 );

	// Caption band.
	$ttf_path = RRT_LABEL_PATH . 'assets/fonts/opensans-variable.ttf';
	if ( file_exists( $ttf_path ) ) {
		$dark  = imagecolorallocate( $canvas, 0x33, 0x33, 0x33 );
		$lines = array(
			'The dotted line is centered on the top of the box —',
			'it is for your confirmation of your logo/text placement.',
		);
		$font_size = 20;
		foreach ( $lines as $i => $line ) {
			$bbox       = imagettfbbox( $font_size, 0, $ttf_path, $line );
			$line_width = $bbox[2] - $bbox[0];
			$x          = (int) round( ( $canvas_w - $line_width ) / 2 );
			$y          = $inner_h + 45 + ( $i * 32 );
			imagettftext( $canvas, $font_size, 0, $x, $y, $dark, $ttf_path, $line );
		}
	}

	$out_path = $order_dir . '/' . $file_prefix . 'proof.png';
	imagepng( $canvas, $out_path, 6 );
	imagedestroy( $canvas );

	return $out_path;
}

/**
 * One vertical "torn paper strip" from x0 to x1, spanning 0..height, with
 * a jagged (not straight) top and bottom edge — a strip that reads as
 * hand-torn off a larger sheet rather than cleanly cut. The jagged edge
 * is drawn once and reused upside-down for the bottom so the two torn
 * edges don't look mechanically identical.
 */
function rrt_label_draw_torn_strip( $canvas, $x0, $x1, $height, $fill_color, $edge_color ) {
	$step  = 14;
	$jag   = 7;
	$top   = array();
	$x     = $x0;
	while ( $x <= $x1 ) {
		$top[] = array( $x, mt_rand( 0, $jag ) );
		$x    += $step;
	}
	$bottom = array();
	foreach ( $top as $p ) {
		$bottom[] = array( $p[0], $height - mt_rand( 0, $jag ) );
	}

	$poly = array();
	foreach ( $top as $p ) {
		$poly[] = $p[0];
		$poly[] = $p[1];
	}
	$poly[] = $x1;
	$poly[] = $height;
	for ( $i = count( $bottom ) - 1; $i >= 0; $i-- ) {
		$poly[] = $bottom[ $i ][0];
		$poly[] = $bottom[ $i ][1];
	}
	$poly[] = $x0;
	$poly[] = 0;

	imagefilledpolygon( $canvas, $poly, (int) ( count( $poly ) / 2 ), $fill_color );

	$top_line = array();
	foreach ( $top as $p ) {
		$top_line[] = $p[0]; $top_line[] = $p[1];
	}
	imagesetthickness( $canvas, 2 );
	for ( $i = 0; $i < count( $top ) - 1; $i++ ) {
		imageline( $canvas, $top[ $i ][0], $top[ $i ][1], $top[ $i + 1 ][0], $top[ $i + 1 ][1], $edge_color );
		imageline( $canvas, $bottom[ $i ][0], $bottom[ $i ][1], $bottom[ $i + 1 ][0], $bottom[ $i + 1 ][1], $edge_color );
	}
	imagesetthickness( $canvas, 1 );
}

/**
 * A dashed-line rectangle (GD has no native dashed-rect primitive) — used
 * to mirror the live configurator's own dotted zone-boundary outline onto
 * the exported proof image.
 */
function rrt_label_draw_dotted_rect( $canvas, $x0, $y0, $x1, $y1, $color, $thickness, $dash_len ) {
	imagesetthickness( $canvas, $thickness );
	$style = array_merge( array_fill( 0, $dash_len, $color ), array_fill( 0, $dash_len, IMG_COLOR_TRANSPARENT ) );
	imagesetstyle( $canvas, $style );
	imageline( $canvas, $x0, $y0, $x1, $y0, IMG_COLOR_STYLED );
	imageline( $canvas, $x0, $y1, $x1, $y1, IMG_COLOR_STYLED );
	imageline( $canvas, $x0, $y0, $x0, $y1, IMG_COLOR_STYLED );
	imageline( $canvas, $x1, $y0, $x1, $y1, IMG_COLOR_STYLED );
	imagesetthickness( $canvas, 1 );
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

/**
 * Phones (especially iPhones) often store a photo's real rotation as EXIF
 * metadata rather than baking it into the pixels. A browser auto-rotates
 * using that metadata when drawing the preview/proof, but GD reads raw
 * pixels and ignores EXIF entirely -- without this, a logo photo that
 * looked correctly upright in the live configurator and the proof email
 * can come out sideways in the actual printed label. Same pattern already
 * proven on theunframer.com (see inc/configurator-ajax.php there).
 */
function rrt_label_fix_jpeg_orientation( $image, $tmp_path ) {
	if ( ! function_exists( 'exif_read_data' ) ) {
		return $image;
	}
	$exif = @exif_read_data( $tmp_path );
	if ( ! $exif || empty( $exif['Orientation'] ) ) {
		return $image;
	}
	switch ( $exif['Orientation'] ) {
		case 3:
			$image = imagerotate( $image, 180, 0 );
			break;
		case 6:
			$image = imagerotate( $image, -90, 0 );
			break;
		case 8:
			$image = imagerotate( $image, 90, 0 );
			break;
	}
	return $image;
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
			if ( $logo_img ) {
				$logo_img = rrt_label_fix_jpeg_orientation( $logo_img, $logo_tmp_path );
			}
			break;
		case 'image/webp':
			if ( function_exists( 'imagecreatefromwebp' ) ) {
				$logo_img = imagecreatefromwebp( $logo_tmp_path );
			}
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

function rrt_label_draw_text( $canvas, $px, $label_text, $text_pos, $text_font, $text_font_size, $text_color_hex = '#1a1a1a', $outline_hex = '#ffffff', $shadow_enabled = false, $shadow_intensity = 60, $side_margin = 2, $bold = false ) {
	$fonts    = rrt_label_fonts();
	$font_key = isset( $fonts[ $text_font ] ) ? $text_font : array_key_first( $fonts );
	$ttf_path = RRT_LABEL_PATH . $fonts[ $font_key ]['ttf_file'];
	if ( ! file_exists( $ttf_path ) ) {
		return;
	}

	// Pete, 2026-11-11: "font size is out of whack with the preview" — see
	// rrt_label_fonts()'s own note on size_calibration: GD and a browser
	// don't render the same declared size at the same actual ink height,
	// and the gap is font-specific, so this scales font_size_px by the
	// measured correction for THIS font before it's used for anything
	// (word-wrap width test below included — a mis-sized font also wraps
	// at the wrong point).
	$font_size_px = ( $text_font_size / 100 ) * $px['zone_width'] * ( $fonts[ $font_key ]['size_calibration'] ?? 1.0 );
	/**
	 * Pete, 2026-10-25: "confirm all elements... have no bugs" — this was
	 * a hardcoded 0.92 (92%), completely ignoring the customer's TEXT
	 * WIDTH slider. Client-side this is `Math.max(20, 100 - 2*sideMargin)%`
	 * (renderTextGroup() in label-configurator.js) — matched here exactly,
	 * so the print file wraps the same words onto the same lines the
	 * customer actually approved, not a different fixed width (which, at
	 * the slider's own DEFAULT of 2, is 96%, not 92% — this diverged even
	 * when nobody touched the slider).
	 */
	$max_width_pct    = max( 20, 100 - 2 * (float) $side_margin );
	$max_width        = $px['zone_width'] * ( $max_width_pct / 100 );
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
	//
	// Pete, 2026-11-11: "I had added a blank line with an extra enter, it
	// looked right on the preview but didn't give it to me on the print
	// (removed the blank line)" — this used to run preg_split('/\s+/', ...)
	// over the ENTIRE text in one shot, and \s matches newlines too, so
	// every manual Enter (blank line or not) was just collapsed into
	// ordinary whitespace between words and re-flowed by width alone.
	// The browser's white-space:pre-wrap (renderTextGroup() in
	// label-configurator.js) treats every \n the customer actually typed
	// as a real break, blank lines included. Splitting into paragraphs on
	// \n FIRST, then width-wrapping each one independently (an empty
	// paragraph becomes exactly one empty output line, same as pre-wrap
	// renders it), matches that — only a run of leading/trailing blank
	// lines around the whole block is trimmed away, same as before.
	$lines = array();
	foreach ( explode( "\n", trim( $label_text, "\n" ) ) as $paragraph ) {
		$paragraph = trim( $paragraph );
		if ( '' === $paragraph ) {
			$lines[] = '';
			continue;
		}
		$words = preg_split( '/\s+/', $paragraph );
		$line  = '';
		foreach ( $words as $word ) {
			$test = $line ? $line . ' ' . $word : $word;
			$bbox = imagettfbbox( $font_size_px, 0, $ttf_path, $test );
			// Pete, 2026-11-11: "it spills out further than desired... font
			// coming out... a little larger than previewed" — measured
			// directly (GD's imagettfbbox vs a real browser Range, same 3
			// test strings, across every font): GD and the browser don't
			// just disagree on HEIGHT (already corrected above via
			// size_calibration) — they disagree on WIDTH too, by a
			// DIFFERENT amount, so no single font_size_px can make both
			// match at once. wrap_width_calibration corrects ONLY this
			// wrap-fit test (so the same words land on the same lines the
			// customer saw) without touching the actual glyph size drawn
			// below — see rrt_label_fonts()'s own note for the full
			// methodology and the per-font numbers.
			$test_width = ( $bbox[2] - $bbox[0] ) * ( $fonts[ $font_key ]['wrap_width_calibration'] ?? 1.0 );
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
	}

	// Pete, 2026-11-11: 1.25 here vs .preview-text-layer's real
	// line-height:1.35 (configurator-components.css) was a second,
	// separate contributor to "font size is out of whack with the
	// preview" — compounds with every extra line, so a short caption was
	// close enough to pass unnoticed while a multi-line one visibly drifted.
	$line_height  = $font_size_px * 1.35;
	$block_height = count( $lines ) * $line_height;

	/**
	 * Pete, 2026-10-25: "lettering placement... in the preview, box
	 * seems good, BUT when it gets to the file, a shift of the art up,
	 * or the lettering down." Confirmed real bug: renderTextGroup() in
	 * label-configurator.js anchors text at its BOTTOM edge (`bottom:
	 * (100-pos.y)%`, growing upward as it wraps — see that function's
	 * own comment, "Y is anchored by the box's BOTTOM edge, not
	 * centered"), but this previously used the SAME center-anchor
	 * formula as the logo (center_y, then top = center_y -
	 * block_height/2) for text too. That silently pushed every printed
	 * text block DOWN by half its own height versus what the customer
	 * approved on screen — worse the more lines/the bigger the font.
	 * The logo itself was never wrong (it genuinely is center-anchored
	 * on both sides, client and server agree) — the growing gap just
	 * read as "the art moved up." Fixed to match: pos.y is the block's
	 * BOTTOM edge, not its center.
	 */
	$center_x = $px['zone_x'] + ( $text_pos['x'] / 100 ) * $px['zone_width'];
	$bottom_y = $px['zone_y'] + ( $text_pos['y'] / 100 ) * $px['zone_height'];
	$top      = $bottom_y - $block_height;

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

	// Pete, 2026-11-11: "it spills out further than desired... WYSIWYG is
	// the order of the day" — wrap_width_calibration (above) only fixes
	// WHICH words land on which line; it can't fix the actual on-canvas
	// footprint, because font_size_px is one number and GD draws both
	// axes from it — correcting height (so the glyphs LOOK the size the
	// customer picked) necessarily leaves width wrong by a different
	// amount (measured per-font in rrt_label_fonts()), and vice versa.
	// Real fix: draw each line to its own transparent, uncropped
	// intermediate canvas at the height-calibrated size (so vertical
	// stays correct), then imagecopyresampled() it horizontally by
	// wrap_width_calibration (height unchanged) before compositing onto
	// the real canvas — an actual pixel stretch/squeeze of the glyphs
	// themselves, not just a word-wrap heuristic, so the real printed
	// footprint matches what the browser showed on both axes at once.
	$wrap_width_calibration = $fonts[ $font_key ]['wrap_width_calibration'] ?? 1.0;
	$pad                    = 4; // clears the 1px outline ring + 1px bold offset with room to spare.

	foreach ( $lines as $i => $line_text ) {
		if ( '' === $line_text ) {
			continue; // a blank line only ever needed its own line_height worth of vertical space, already accounted for in $block_height.
		}

		$bbox       = imagettfbbox( $font_size_px, 0, $ttf_path, $line_text );
		$line_width = $bbox[2] - $bbox[0];
		$ascent     = (int) ceil( max( 0, -$bbox[7] ) );
		$descent    = (int) ceil( max( 0, $bbox[1] ) );

		$strip_w = (int) ceil( $line_width ) + 2 * $pad;
		$strip_h = $ascent + $descent + 2 * $pad;
		$strip   = imagecreatetruecolor( $strip_w, $strip_h );
		imagealphablending( $strip, false );
		imagesavealpha( $strip, true );
		$strip_transparent = imagecolorallocatealpha( $strip, 0, 0, 0, 127 );
		imagefill( $strip, 0, 0, $strip_transparent );
		imagealphablending( $strip, true );

		// Local origin within the strip: left-ink-edge at $pad, baseline at
		// $pad + $ascent down — matches $strip_h's own ascent+descent+2*pad
		// layout above.
		$local_x       = $pad - $bbox[0];
		$local_base_y  = $pad + $ascent;

		if ( $shadow_enabled ) {
			foreach ( $outline_offsets as $offset ) {
				imagettftext( $strip, $font_size_px, 0, $local_x + $offset[0], $local_base_y + $offset[1], $outline, $ttf_path, $line_text );
			}
		}
		/**
		 * Pete, 2026-10-25: "confirm all elements... have no bugs" — Bold
		 * was fully live client-side (fontWeight:700, a real different
		 * glyph weight from the variable/weighted web font) but never
		 * sent to or applied by the server at all. None of these fonts
		 * have a separate bold .ttf (see rrt_label_fonts() — one file
		 * each), so a true weight-matched bold isn't available here;
		 * faux-bold (stamping the same glyphs a second time, 1px to the
		 * right) is the standard approximation GD itself has no built-in
		 * alternative to — same "stamp it more than once" idea already
		 * used for the outline/shadow effect just above, just a single
		 * extra pass instead of a full ring.
		 */
		imagettftext( $strip, $font_size_px, 0, $local_x, $local_base_y, $text_color, $ttf_path, $line_text );
		if ( $bold ) {
			imagettftext( $strip, $font_size_px, 0, $local_x + 1, $local_base_y, $text_color, $ttf_path, $line_text );
		}

		$scaled_w = max( 1, (int) round( $strip_w * $wrap_width_calibration ) );
		$scaled   = imagecreatetruecolor( $scaled_w, $strip_h );
		imagealphablending( $scaled, false );
		imagesavealpha( $scaled, true );
		$scaled_transparent = imagecolorallocatealpha( $scaled, 0, 0, 0, 127 );
		imagefill( $scaled, 0, 0, $scaled_transparent );
		imagecopyresampled( $scaled, $strip, 0, 0, 0, 0, $scaled_w, $strip_h, $strip_w, $strip_h );
		imagedestroy( $strip );

		$dest_x     = (int) round( $center_x - $scaled_w / 2 );
		$baseline_y = (int) round( $top + ( $i * $line_height ) + $font_size_px );
		$dest_y     = $baseline_y - $pad - $ascent;

		imagecopy( $canvas, $scaled, $dest_x, $dest_y, 0, 0, $scaled_w, $strip_h );
		imagedestroy( $scaled );
	}
}

/**
 * Emails Pete the composited preview (what the customer actually
 * designed) plus each original raw logo upload, so he can redo the
 * composite by hand if he ever wants to.
 *
 * Pete, 2026-10-18: "flat and layered... with the full file at 300 dpi
 * ready to print" — $layer_paths (from rrt_label_composite_layers(), may
 * be an empty array if that step failed): the bare template plus one
 * transparent PNG per customer layer, all print-resolution, same full
 * 11" size/position as the flat file, so the whole design can be
 * reconstructed as real separate layers in Photoshop/GIMP.
 *
 * Pete, 2026-10-19: $sheet_path (from rrt_label_composite_sheet(), may be
 * null if that step failed) is the actual print-ready sheet — the flat
 * label doubled/tripled side by side per rrt_label_sheet_config().
 *
 * Pete, 2026-10-28: all of the above used to go out as real MIME
 * attachments. A real order (flat + sheet + 4 layers + original upload,
 * ~8.8MB raw) hit GoDaddy's mail relay's size cap and got a hard HTTP 413
 * back — silently, since the result of wp_mail() was never checked.
 * Cassie's earlier orders were smaller and happened to sneak under the
 * cap, which is why they "came through like a dream" while this one
 * vanished. Pete: "what if we send links to the files instead" / "that's
 * what we have on theunframer.com" / "full files, everything" — so every
 * file still goes out (nothing trimmed), just as a link into the order's
 * own uploads folder (already public, like any other WP upload) instead
 * of as a MIME attachment. No size ceiling either way now.
 */
function rrt_label_email_submission( $order_fields, $design_results, $order_dir ) {
	// Pete, 2026-10-28: admin_email (pete@redrockstoffee.com) wasn't getting
	// checked — submissions were landing with no one seeing them. Send to
	// both so a missed inbox on one side doesn't mean a missed order.
	$to            = array_unique( array_filter( array( get_option( 'admin_email' ), 'pete@roadrex.com' ) ) );
	$missing_image = ! empty( $order_fields['dropped_logo_count'] );
	$design_count  = count( $design_results );
	$subject       = ( $missing_image ? '[MISSING IMAGE] ' : '' ) . sprintf(
		'[Co-Branding Label] New %s from %s',
		$design_count > 1 ? ( $design_count . ' designs' ) : 'design',
		$order_fields['name']
	);

	$upload_dir = wp_upload_dir();
	$order_url  = trailingslashit( $upload_dir['baseurl'] ) . 'rrt-label-orders/' . rawurlencode( basename( $order_dir ) );

	$body  = '<html><body style="font-family: Arial, sans-serif; line-height: 1.6;">';
	if ( $missing_image ) {
		$body .= '<div style="background:#b00020; color:#fff; font-weight:bold; padding:14px 18px; border-radius:6px; margin-bottom:16px;">';
		$body .= '&#9888; ' . esc_html( $order_fields['dropped_logo_count'] ) . ' image(s) this customer tried to add did NOT come through. This order is likely MISSING ARTWORK — contact them before printing.';
		$body .= '</div>';
	}
	$body .= '<h2 style="margin-bottom:4px;">New co-branding label ' . ( $design_count > 1 ? 'order (' . $design_count . ' designs)' : 'design' ) . '</h2>';
	$body .= '<table style="border-collapse: collapse; width: 100%; max-width: 600px;">';
	$body .= '<tr><td style="padding:6px 8px; border-bottom:1px solid #ddd;"><strong>Name</strong></td><td style="padding:6px 8px; border-bottom:1px solid #ddd;">' . esc_html( $order_fields['name'] ) . '</td></tr>';
	$body .= '<tr><td style="padding:6px 8px; border-bottom:1px solid #ddd;"><strong>Email</strong></td><td style="padding:6px 8px; border-bottom:1px solid #ddd;"><a href="mailto:' . esc_attr( $order_fields['email'] ) . '">' . esc_html( $order_fields['email'] ) . '</a></td></tr>';
	$body .= '<tr><td style="padding:6px 8px; border-bottom:1px solid #ddd;"><strong>Phone</strong></td><td style="padding:6px 8px; border-bottom:1px solid #ddd;">' . esc_html( $order_fields['phone'] ) . '</td></tr>';
	$body .= '<tr><td style="padding:6px 8px; border-bottom:1px solid #ddd;"><strong>Total quantity requested</strong></td><td style="padding:6px 8px; border-bottom:1px solid #ddd;">' . esc_html( $order_fields['quantity'] ) . '</td></tr>';
	$body .= '</table>';

	if ( $order_fields['message'] ) {
		$body .= '<h3 style="margin-bottom:4px;">Message</h3><p>' . nl2br( esc_html( $order_fields['message'] ) ) . '</p>';
	}

	// Pete, 2026-11-07: one section per held design (flavor/size/qty, its
	// own text/logo layers, its own files) — previously this whole email
	// only ever described ONE design because only one could exist per
	// submission.
	foreach ( $design_results as $i => $design_result ) {
		$logo_count = 0;
		$text_lines = array();
		foreach ( $design_result['layers'] as $layer ) {
			if ( 'logo' === $layer['type'] ) {
				++$logo_count;
			} else {
				$text_lines[] = $layer['text'];
			}
		}

		$body .= '<hr style="margin:20px 0; border:none; border-top:2px solid #ddd;">';
		$body .= '<h3 style="margin-bottom:4px;">Design ' . ( $i + 1 ) . ': ' . esc_html( $design_result['flavor_label'] ) . ' — ' . esc_html( $design_result['size_label'] ) . '</h3>';
		$body .= '<table style="border-collapse: collapse; width: 100%; max-width: 600px;">';
		$body .= '<tr><td style="padding:6px 8px; border-bottom:1px solid #eee;"><strong>Quantity requested</strong></td><td style="padding:6px 8px; border-bottom:1px solid #eee;">' . esc_html( $design_result['quantity'] ) . '</td></tr>';
		$body .= '<tr><td style="padding:6px 8px; border-bottom:1px solid #eee;"><strong>Logos</strong></td><td style="padding:6px 8px; border-bottom:1px solid #eee;">' . esc_html( $logo_count ) . '</td></tr>';
		if ( ! empty( $design_result['note'] ) ) {
			$body .= '<tr><td style="padding:6px 8px; border-bottom:1px solid #eee;"><strong>Note for this label</strong></td><td style="padding:6px 8px; border-bottom:1px solid #eee;">' . nl2br( esc_html( $design_result['note'] ) ) . '</td></tr>';
		}
		$body .= '</table>';

		// Pete, 2026-10-26: "I don't want to take someone's money and print
		// a really sh*t label from a garbage file" — warn-not-block
		// (confirmed), so this is the backstop: even if the customer
		// ignored the on-page warning, Pete sees it here before he
		// actually prints anything. Only ever set on a real-upload logo
		// layer, never an art_key one.
		$logo_n = 0;
		foreach ( $design_result['layers'] as $layer ) {
			if ( 'logo' !== $layer['type'] ) {
				continue;
			}
			++$logo_n;
			if ( ! empty( $layer['low_quality'] ) ) {
				$body .= '<p style="color:#b00020; font-weight:bold;">&#9888; Logo ' . esc_html( $logo_n ) . ' may be low-resolution for the size it was placed at — check before printing.</p>';
			}
		}

		if ( $text_lines ) {
			$body .= '<h4 style="margin-bottom:4px;">Text</h4><ul>';
			foreach ( $text_lines as $j => $line ) {
				$body .= '<li>Text ' . ( $j + 1 ) . ': ' . esc_html( $line ) . '</li>';
			}
			$body .= '</ul>';
		}

		$files = array();
		if ( $design_result['composite_path'] && file_exists( $design_result['composite_path'] ) ) {
			$files['Flat label (300 DPI, ready to print)'] = $design_result['composite_path'];
		}
		if ( $design_result['sheet_path'] && file_exists( $design_result['sheet_path'] ) ) {
			$files[ sprintf( 'Print-ready sheet (%d labels tiled)', $design_result['labels_needed'] ) ] = $design_result['sheet_path'];
		}
		if ( $design_result['proof_path'] && file_exists( $design_result['proof_path'] ) ) {
			$files['Customer-facing proof (logo/text in context)'] = $design_result['proof_path'];
		}
		foreach ( $design_result['layer_paths'] as $layer_path ) {
			if ( file_exists( $layer_path ) ) {
				$files[ 'Layer: ' . basename( $layer_path ) ] = $layer_path;
			}
		}
		// Pete, 2026-10-27: "here are the files" — one came through named
		// literally "phpgTLcxA", no extension at all. $layer['tmp_path']
		// for a real upload is PHP's own raw upload tmp file, which has no
		// real filename of its own. $original_paths
		// (rrt_label_save_originals(), called before this function)
		// already copied the same files into the order folder under
		// proper names (original-logo-1.jpg, etc.) — link those instead
		// so the filename actually means something.
		foreach ( $design_result['original_paths'] as $original_path ) {
			if ( file_exists( $original_path ) ) {
				$files[ 'Original upload: ' . basename( $original_path ) ] = $original_path;
			}
		}

		$body .= '<h4 style="margin-bottom:4px;">Files</h4>';
		if ( $files ) {
			$body .= '<table style="border-collapse: collapse; width: 100%; max-width: 600px;">';
			foreach ( $files as $label => $path ) {
				$file_url = trailingslashit( $order_url ) . rawurlencode( basename( dirname( $path ) ) ) . '/' . rawurlencode( basename( $path ) );
				$body    .= '<tr><td style="padding:6px 8px; border-bottom:1px solid #eee;">' . esc_html( $label ) . '</td><td style="padding:6px 8px; border-bottom:1px solid #eee;"><a href="' . esc_url( $file_url ) . '" style="display:inline-block; padding:6px 14px; background:#3d4348; color:#fff; text-decoration:none; border-radius:4px;">Download</a></td></tr>';
			}
			$body .= '</table>';
		} else {
			$body .= '<p>No files were generated for this design — check the order folder on the server.</p>';
		}
	}

	$body .= '</body></html>';

	$headers = array( 'Content-Type: text/html; charset=UTF-8' );

	wp_mail( $to, $subject, $body, $headers );
}
