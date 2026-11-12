<?php
/**
 * Plugin Name: RRT Label Configurator
 * Description: Self-service co-branding label designer for the /co-branding/ page — customers upload a logo and/or type text into the blank area of a real 8oz/4oz label template, see a live preview, and submit it to Pete for review.
 * Version: 3.10.1
 * Author: Red Rocks Toffee Company
 *
 * Plain PHP/JS/CSS on purpose — no build step, no framework. Pete needs to
 * be able to open any file here and hand-edit it later.
 *
 * The look/feel (grid layout, dropzone/card/slider/font-picker styling)
 * is copied as directly as possible from TheUnframer.com's own production
 * upload configurator (wp-content/themes/TheUnframer/assets/css/
 * upload-configurator.css + custom.css, class names/values reused, not
 * reinvented) — Pete's explicit call: his customers are the same people
 * who use TheUnframer, so this should look and feel almost identical,
 * not just "inspired by." The customer still starts from a BLANK zone
 * and adds logo/text layers one at a time (closer to quote-module.js's
 * multi-layer model than the single-photo-upload flow), since there's no
 * "main photo" here — the label template itself is always present.
 *
 * IMPORTANT: bump RRT_LABEL_VERSION on every CSS/JS edit. It's the
 * cache-busting `?ver=` query string WordPress/the browser uses to know
 * a file changed — forgetting this means edits can look like they never
 * took effect, because the browser just keeps serving its cached copy.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RRT_LABEL_VERSION', '3.88.0' );
define( 'RRT_LABEL_PATH', plugin_dir_path( __FILE__ ) );
define( 'RRT_LABEL_URL', plugin_dir_url( __FILE__ ) );

require_once RRT_LABEL_PATH . 'inc/handle-submission.php';
require_once RRT_LABEL_PATH . 'inc/home-starburst.php';
require_once RRT_LABEL_PATH . 'inc/harry-path.php';
require_once RRT_LABEL_PATH . 'inc/hero-ken-burns.php';
require_once RRT_LABEL_PATH . 'inc/accessibility-statement.php';

/**
 * Pete, 2026-10-25: "where are we putting this... how do we coordinate
 * with the products page?" — activating the min-qty enforcement built
 * 2026-10-18, and adding a CTA link on the two matching Original Almond
 * product pages (not every product), pointing to the real /co-branding/
 * page where the configurator now lives. Product IDs are the ONLY two
 * confirmed by Pete this session — `Original Almond Toffee 8 oz. Box`
 * (ID 12) and `4 oz. Original Almond Toffee x 2` (ID 5161). Priority 25
 * on woocommerce_single_product_summary lands it between the excerpt
 * (20) and WooCommerce's own add-to-cart button (30) — visible, not
 * buried below the purchase action.
 *
 * Both this block and inc/woocommerce-min-qty.php's own internal guard
 * check class_exists('WooCommerce') — that check is only reliable once
 * every plugin has had a chance to load, so both are deferred to the
 * plugins_loaded hook rather than running at this file's top level
 * (where WooCommerce's class isn't guaranteed to exist yet, depending
 * on plugin load order — confirmed this session: without this, the
 * check silently failed and neither feature ever registered).
 */
add_action(
	'plugins_loaded',
	function () {
		require_once RRT_LABEL_PATH . 'inc/woocommerce-min-qty.php';

		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		add_action(
			'woocommerce_single_product_summary',
			function () {
				global $product;
				if ( ! $product ) {
					return;
				}
				$rrt_cta_product_ids = array( 12, 5161 );
				if ( ! in_array( $product->get_id(), $rrt_cta_product_ids, true ) ) {
					return;
				}
				printf(
					'<p style="margin: 1em 0; padding: 0.75em 1em; background: #fdf3e7; border: 1px solid #991e21; border-radius: 8px; font-weight: 600;"><a href="%1$s" style="color: #991e21; text-decoration: none;">Want this with your own custom label? Design one &rarr;</a></p>',
					esc_url( home_url( '/co-branding/' ) )
				);
			},
			25
		);
	}
);

/**
 * Pete, 2026-11-10: Koffie-Almond's 4 template files (plain + tinted,
 * 8oz + 4oz) are now in assets/templates/koffie-almond/ — the 2 plain
 * files Pete supplied, plus tinted versions for the live preview/3D box
 * mockup. Flavor flipped to 'available' => true, live.
 *
 * 2026-11-11: "the coffee label seems too heavy [saturated]... go back to
 * the original Koffie files... figure out a workaround without touching
 * the saturation/white" — every earlier attempt at the tinted files
 * (hard-threshold white replace, Linear Burn, a whiteness-masked blend)
 * assumed the problem was in how hard each one repainted the near-white
 * background. It wasn't. The source JPEGs are CMYK with a real embedded
 * "U.S. Web Coated (SWOP) v2" print profile, and the very first/every
 * subsequent build of these tinted files converted them to sRGB with a
 * plain `-colorspace sRGB` reassignment — which ignores that embedded
 * profile entirely. Measured directly against a real ICC-aware
 * conversion (`-profile` with Windows' own sRGB.icm, which DOES use the
 * source profile): a bean pixel came out srgb(113,0,1) the naive way —
 * fully saturated maroon, green/blue crushed to 0 — versus the correct
 * srgb(128,25,28), a muted roast-brown. That's the real "too heavy":
 * every one of my recolor attempts was correctly leaving the ink alone,
 * but the ink itself was wrong from the very first conversion, before
 * any of that recoloring ever touched it.
 *
 * Current tinted files: Pete's original plain JPEGs, converted ONLY
 * through that ICC-aware profile conversion (no white-replacement, no
 * masking, no blending of any kind — literally zero pixels recolored
 * beyond the colorspace conversion itself, per Pete's own ask). The
 * white background is therefore plain white again, same as the
 * untouched source — it won't match the butter-yellow paper stock the
 * way Original Almond's tinted files do; that's a separate, deliberately
 * deferred cosmetic question, not a bug.
 *
 * 'template_dir' is relative to assets/templates/ and is where
 * rrt_label_sizes() below looks for that flavor's {size}-template.jpg —
 * empty string for Original Almond keeps today's existing files (which
 * live directly in assets/templates/) working untouched.
 */
function rrt_label_flavors() {
	return array(
		'original-almond' => array(
			'label'        => 'Original Almond',
			'template_dir' => '',
			'available'    => true,
		),
		'koffie-almond'   => array(
			'label'        => 'Koffie-Almond™',
			'template_dir' => 'koffie-almond/',
			'available'    => true,
		),
	);
}

/**
 * One place to adjust each label size's template image and its blank
 * customer-content zone. Everything is in inches, not pixels, so it reads
 * the way the real label artwork is measured and is easy to hand-tune.
 *
 * zone width/height are centered on the label; zone "top_in" is measured
 * from the end of the label FARTHEST from the mountain logo (confirmed
 * with Pete — that's the Nutrition Facts panel end on both templates).
 *
 * $flavor_key only changes WHICH template image 'template_file' points at
 * (rrt_label_flavors() above) — every other number here (dpi, zone
 * geometry, box dimensions) is the same real physical box/label
 * regardless of flavor, so it's still one array, not duplicated per
 * flavor.
 *
 * Pete, 2026-11-11: "it prints differently than if I go straight to the
 * printer without going through the configurator" — the real bug.
 * 'template_file' is the TRUE, untouched source (CMYK, a real embedded
 * "U.S. Web Coated SWOP v2" print profile) — GD (inc/handle-submission.php,
 * rrt_label_composite()/rrt_label_composite_layers()) used to load this
 * directly via imagecreatefromjpeg(), but GD has no color management at
 * all: it decodes CMYK with a naive formula, ignoring the embedded
 * profile entirely. Measured directly: a bean pixel came out
 * rgb(112,0,1) through GD (green/blue crushed to 0 — oversaturated
 * maroon) vs. the correct rgb(128,25,28) with a real ICC-aware
 * conversion — identical bug, identical source-file setup, for BOTH
 * flavors, so this has likely been true of every printed order since
 * launch. 'template_print_file' is a ONE-TIME, ICC-aware conversion of
 * the exact same file (ImageMagick -profile against Windows' own
 * sRGB.icm, which DOES use the embedded profile — nothing else touched,
 * same as koffie-almond's tinted-file fix) — GD loads THIS instead, so
 * it's just handed already-correct RGB and never has to decode CMYK
 * itself. 'template_file' stays the real original for anything that
 * might need it later (an actual CMYK print-shop handoff, say).
 */
function rrt_label_sizes( $flavor_key = 'original-almond' ) {
	$flavors      = rrt_label_flavors();
	$flavor       = $flavors[ $flavor_key ] ?? $flavors['original-almond'];
	$template_dir = $flavor['template_dir'];

	return array(
		'8oz' => array(
			'label'           => '8oz Box',
			// Pete, 2026-10-21: exact wording for the size dropdown's extra
			// note (see the <select> markup below) — kept as a separate
			// field from 'label' since that one's also used in the
			// submission email and the JS "minimum order" error message,
			// where this phrasing wouldn't read right.
			'dropdown_note'   => 'Min. 2 Boxes',
			'template_file'       => 'assets/templates/' . $template_dir . '8oz-template.jpg',
			'template_print_file' => 'assets/templates/' . $template_dir . '8oz-template-print.jpg',
			'dpi'             => 300,
			'image_width_in'  => 4.127,
			'image_height_in' => 11,
			// Real glossy-white box dimensions, confirmed with Pete
			// 2026-10-25 — used only by the 3D box-mockup preview below
			// (rrt_label_box_mockup_geometry()), never by the GD print
			// compositing. The label wraps the box vertically: front (=
			// box_height_in) -> top (= box_depth_in) -> back (=
			// box_height_in) -> bottom (= box_depth_in) -> 1in hidden
			// overlap, which is exactly 2+3+2+3+1 = 11in = image_height_in
			// above — not a coincidence, confirmed against the template
			// image itself (mountain logo near the "front" end, Nutrition
			// Facts near the "back/bottom" end).
			'box_width_in'    => 6,
			'box_depth_in'    => 3,
			'box_height_in'   => 2,
			'zone'            => array(
				'width_in'  => 3.75,
				'height_in' => 2.5,
				// Pete, 2026-10-19: "move the dotted outline box up the
				// label by 1/16 of an inch, keep it the same from side to
				// side" — same width/height, top_in moved from 5.25 to
				// 5.1875 (5.25 - 1/16).
				'top_in'    => 5.1875,
			),
		),
		'4oz' => array(
			'label'           => '4oz Box',
			'dropdown_note'   => 'Min. 6, sold in 2s',
			'template_file'       => 'assets/templates/' . $template_dir . '4oz-template.jpg',
			'template_print_file' => 'assets/templates/' . $template_dir . '4oz-template-print.jpg',
			'dpi'             => 300,
			'image_width_in'  => 2.627,
			'image_height_in' => 11,
			// See the 8oz entry above for why these three exist — same
			// depth/height (the wrap math is identical), narrower box.
			'box_width_in'    => 3,
			'box_depth_in'    => 3,
			'box_height_in'   => 2,
			'zone'            => array(
				'width_in'  => 2.375,
				'height_in' => 2.5,
				'top_in'    => 5.1875, // see note above — same 1/16" shift
			),
		),
	);
}

/**
 * Pete, 2026-10-19: "the label needs to be doubled, side by side on a
 * file 8.25in wide and 11in tall" — the print-ready sheet that gets
 * built from the flat composite (rrt_label_composite_sheet() in
 * inc/handle-submission.php). One place to tune labels-per-sheet/sheet
 * width per box size, same spirit as rrt_label_sizes() above.
 *
 * 2026-10-19: Pete confirmed 4oz as "three across on an 8.25in x 11in
 * file" — same sheet width as 8oz (not the narrower 7.881in = 3 x
 * 2.627in this used as a placeholder before being confirmed), so one
 * sheet size serves both box sizes. The 3 labels don't exactly fill
 * 8.25in (3 x 2.627in = 7.881in, leaving ~0.37in left over), so
 * rrt_label_composite_sheet() centers the row instead of pinning it to
 * the left edge.
 */
function rrt_label_sheet_config( $size_key ) {
	$sheets = array(
		'8oz' => array(
			'labels_per_sheet' => 2,
			'width_in'         => 8.25,
		),
		'4oz' => array(
			'labels_per_sheet' => 3,
			'width_in'         => 8.25,
		),
	);
	return $sheets[ $size_key ] ?? null;
}

/**
 * The minimum order quantity per box size — Pete, 2026-10-18: "min of 2
 * for the 8 oz, and min of 6 for the 4 oz." Already built once as dormant
 * WooCommerce-product-meta logic (inc/woocommerce-min-qty.php, not yet
 * activated); this is the same two numbers, used right now to validate
 * the configurator's own temporary quantity field (see rrt-lc-quantity)
 * since real WooCommerce order quantities aren't wired in yet.
 */
function rrt_label_min_qty_for_size( $size_key ) {
	$mins = array(
		'8oz' => 2,
		'4oz' => 6,
	);
	return $mins[ $size_key ] ?? 1;
}

/**
 * The fonts offered for the typed text. inc/quote-module-card.part.php's
 * font-drum shows all 15 of TheUnframer's original fonts (copied
 * verbatim, same values/labels/preview styling) — this array is the
 * server-side half: one real local .ttf per key (assets/fonts/) for the
 * GD compositing (GD/FreeType can't use a webfont's .woff/.woff2), so
 * the PRINTED label actually comes out in whatever font the customer
 * picked and saw on screen, not a silent fallback.
 *
 * 2026-10-21: until now only 3 of these 15 keys had a real .ttf
 * registered (opensans/oswald/dancingscript) — every other font choice
 * silently composited as "Clean" (Open Sans) server-side with no
 * warning anywhere, a real WYSIWYG break between the live preview (pure
 * CSS, already supported all 15 fine) and the final printed file. Fixed
 * by sourcing the other 12 as real TTFs (all standard, freely-licensed
 * Google Fonts, same place the original 3 came from) instead of trimming
 * the picker down to the 3 that already worked.
 *
 * Pete, 2026-11-11: "font size is out of whack with the preview... lines
 * goofed up... placement a little because of the larger font size," then
 * after an initial height-only fix: "it spills out further than desired...
 * WYSIWYG is the order of the day." GD's imagettfbbox()/imagettftext() and
 * a browser's own text renderer do NOT agree on how a given font's glyphs
 * measure for the same declared pixel size — and, measured properly, they
 * disagree on WIDTH and HEIGHT by DIFFERENT amounts, so no single number
 * can make both match. Measured rigorously this time: 3 different test
 * strings (so a font whose ratio shifts with content gets caught, not
 * averaged away), real ink from a browser Range (matching
 * textGlyphGapPct()'s technique in label-configurator.js) against GD's
 * imagettfbbox() on each font's actual .ttf file, at 200px for precision.
 *
 * Two numbers per font come out of that:
 * - 'size_calibration' (browser_h / gd_h, averaged) — multiplies
 *   font_size_px itself in rrt_label_draw_text(), so the actual drawn
 *   glyph size matches the browser's.
 * - 'wrap_width_calibration' (browser_w / gd_w at the SAME font_size_px,
 *   divided by size_calibration since the width this corrects is measured
 *   AFTER font_size_px is already height-calibrated) — multiplies ONLY
 *   the word-wrap fit-test's measured width, so the same words land on
 *   the same lines the customer saw, without distorting the actual glyphs
 *   drawn (which still use the height-calibrated size above). Two
 *   different engines simply don't render the same font identically; this
 *   is the closest a single flat number per axis can get.
 *
 * Three fonts (Bold Script/Satisfy, Bangers Bold, Great Vibes) measured
 * much less reliable — their ratio swung 5-7% just from CHANGING THE
 * TEST STRING (every other font held under ~3.5%), meaning no fixed
 * multiplier reliably predicts them for arbitrary customer text. Pete,
 * 2026-11-11: "if we reduce the number of fonts, that's ok" — these
 * three are pulled from the font-drum (inc/quote-module-card.part.php)
 * so no NEW design can pick them. They stay HERE and in TEXT_FONTS
 * (label-configurator.js) anyway, with their (less consistent, but still
 * far better than uncalibrated) measured numbers — Pete, same day: "Bold
 * Script is the one used for the Happy Holidays/Mythical Designs one,
 * it's still outstanding with the customer" — pulling a font's DATA, not
 * just its drum card, would silently fall back to a different font (see
 * $font_key below) the next time that already-placed order's design is
 * opened, edited, or reprinted. Dropping a font from the picker and
 * erasing its ability to render/print a design someone already approved
 * are two different things; only the picker changes here.
 *
 * If a font's .ttf/TEXT_FONTS entry is ever ACTUALLY deleted (not just
 * pulled from the drum) — e.g. once Pete confirms no outstanding order
 * still uses it — remove it from here, TEXT_FONTS, and the font-drum
 * together; all three must stay in sync or a customer could pick a font
 * this array doesn't know (falls back to the array's first font — see
 * $font_key below — which would re-break WYSIWYG silently).
 *
 * Re-measure and update this whole list (all 16 original fonts, not just
 * survivors — a previously-inconsistent font might calibrate fine with a
 * different .ttf) if any font's .ttf file ever gets swapped for a
 * different version/foundry.
 *
 * Two key names below differ from their on-screen label for a reason:
 * - 'scripty' (label "Scripty") was 'dancingscript' here — same file,
 *   renamed to match the actual `value="scripty"` the font-drum radio
 *   card posts, which is what `rrt_label_draw_text()` looks up by.
 * - 'arial' (label "Clean & Simple") composites with Arimo, not real
 *   Arial — Arial itself isn't a freely redistributable font file.
 *   Arimo is Google's own metric-compatible open-source substitute
 *   (what Google Docs/LibreOffice use for the same reason), so spacing/
 *   line-wrapping still matches what the on-screen preview (which uses
 *   the system's real Arial/Helvetica, see .font-preview.arial in
 *   configurator-components.css) shows almost exactly, even though the
 *   exact glyph shapes differ slightly. No google_font to enqueue for
 *   it — the live preview never uses Arimo, only this print path does.
 */
function rrt_label_fonts() {
	return array(
		'opensans'      => array(
			'label'                   => 'Clean',
			'css_family'              => "'Open Sans', sans-serif",
			'ttf_file'                => 'assets/fonts/opensans-variable.ttf',
			'google_font'             => 'Open+Sans:wght@400;700',
			'size_calibration'        => 1.019,
			'wrap_width_calibration'  => 0.740,
		),
		'oswald'        => array(
			'label'                   => 'Bold Modern',
			'css_family'              => "'Oswald', sans-serif",
			'ttf_file'                => 'assets/fonts/oswald-variable.ttf',
			'google_font'             => 'Oswald:wght@600',
			'size_calibration'        => 1.115,
			'wrap_width_calibration'  => 0.750,
		),
		'luckiest'      => array(
			'label'                   => 'Luckiest Guy',
			'css_family'              => "'Luckiest Guy', cursive",
			'ttf_file'                => 'assets/fonts/luckiest.ttf',
			'google_font'             => 'Luckiest+Guy',
			'size_calibration'        => 1.248,
			'wrap_width_calibration'  => 0.591,
		),
		'comic'         => array(
			'label'                   => 'Comic Balloon',
			'css_family'              => "'Boogaloo', cursive",
			'ttf_file'                => 'assets/fonts/comic.ttf',
			'google_font'             => 'Boogaloo',
			'size_calibration'        => 0.911,
			'wrap_width_calibration'  => 0.828,
		),
		// Pete, 2026-11-11: "bold script not there on live... along with
		// recalibration please" — back in the font-drum (Mythical
		// Designs' Bold Script needed to actually be pickable to rebuild
		// it), re-measured with 8 strings instead of 3 for a steadier
		// number. One wrinkle specific to this font: GD's own bbox height
		// genuinely shrinks for a string with NO descenders at all (e.g.
		// "Thank You So Much"), while the browser's measurement is a fixed
		// font-metric box that doesn't — not a GD/browser disagreement,
		// two different definitions of "height" that only converge when
		// the text has a natural mix of ascenders/descenders, which real
		// customer text overwhelmingly does. Excluded that one
		// no-descender outlier from the average below; still the least
		// consistent font kept anywhere in this file (~4-9% spread vs
		// under 3.5% for the fonts still fully trusted), so give it a bit
		// more margin than the rest.
		'satisfy'       => array(
			'label'                   => 'Bold Script',
			'css_family'              => "'Satisfy', cursive",
			'ttf_file'                => 'assets/fonts/satisfy.ttf',
			'google_font'             => 'Satisfy',
			'size_calibration'        => 0.890,
			'wrap_width_calibration'  => 0.835,
		),
		'lobster'       => array(
			'label'                   => 'Lobster',
			'css_family'              => "'Lobster', cursive",
			'ttf_file'                => 'assets/fonts/lobster.ttf',
			'google_font'             => 'Lobster',
			'size_calibration'        => 0.934,
			'wrap_width_calibration'  => 0.786,
		),
		// Pete, 2026-11-11: dropped from the font-drum, not deleted — see
		// 'satisfy' just above for why.
		'bangers'       => array(
			'label'                   => 'Bangers Bold',
			'css_family'              => "'Bangers', cursive",
			'ttf_file'                => 'assets/fonts/bangers.ttf',
			'google_font'             => 'Bangers',
			'size_calibration'        => 1.031,
			'wrap_width_calibration'  => 0.710,
		),
		'fredoka'       => array(
			'label'                   => 'Rounded & Fun',
			'css_family'              => "'Fredoka One', cursive",
			'ttf_file'                => 'assets/fonts/fredoka.ttf',
			'google_font'             => 'Fredoka+One',
			'size_calibration'        => 0.896,
			'wrap_width_calibration'  => 0.831,
		),
		'montserrat'    => array(
			'label'                   => 'Clean Modern',
			'css_family'              => "'Montserrat', sans-serif",
			'ttf_file'                => 'assets/fonts/montserrat-bold.ttf',
			'google_font'             => 'Montserrat:wght@700',
			'size_calibration'        => 0.921,
			'wrap_width_calibration'  => 0.814,
		),
		'abril'         => array(
			'label'                   => 'Abril Fatface',
			'css_family'              => "'Abril Fatface', cursive",
			'ttf_file'                => 'assets/fonts/abril.ttf',
			'google_font'             => 'Abril+Fatface',
			'size_calibration'        => 1.008,
			'wrap_width_calibration'  => 0.736,
		),
		// Pete, 2026-11-11: dropped from the font-drum, not deleted — see
		// 'satisfy' above for why.
		'greatvibes'    => array(
			'label'                   => 'Great Vibes',
			'css_family'              => "'Great Vibes', cursive",
			'ttf_file'                => 'assets/fonts/greatvibes.ttf',
			'google_font'             => 'Great+Vibes',
			'size_calibration'        => 0.811,
			'wrap_width_calibration'  => 0.915,
		),
		'sniglet'       => array(
			'label'                   => 'Sniglet',
			'css_family'              => "'Sniglet', cursive",
			'ttf_file'                => 'assets/fonts/sniglet.ttf',
			'google_font'             => 'Sniglet',
			'size_calibration'        => 0.930,
			'wrap_width_calibration'  => 0.807,
		),
		'arial'         => array(
			'label'                   => 'Clean & Simple',
			'css_family'              => 'Arial, Helvetica, sans-serif',
			'ttf_file'                => 'assets/fonts/arial.ttf',
			'google_font'             => '',
			'size_calibration'        => 0.892,
			'wrap_width_calibration'  => 0.850,
		),
		'playfair'      => array(
			'label'                   => 'Classic Serif',
			'css_family'              => "'Playfair Display', serif",
			'ttf_file'                => 'assets/fonts/playfair-italic.ttf',
			'google_font'             => 'Playfair+Display:ital@1',
			'size_calibration'        => 1.009,
			'wrap_width_calibration'  => 0.741,
		),
		'scripty'       => array(
			'label'                   => 'Scripty',
			'css_family'              => "'Dancing Script', cursive",
			'ttf_file'                => 'assets/fonts/dancingscript-variable.ttf',
			'google_font'             => 'Dancing+Script:wght@600',
			'size_calibration'        => 0.899,
			'wrap_width_calibration'  => 0.843,
		),
		'pacifico'      => array(
			'label'                   => 'Casual Script',
			'css_family'              => "'Pacifico', cursive",
			'ttf_file'                => 'assets/fonts/pacifico.ttf',
			'google_font'             => 'Pacifico',
			'size_calibration'        => 0.943,
			'wrap_width_calibration'  => 0.777,
		),
	);
}

/**
 * Pete, 2026-10-17: "we need a library of holiday/birthday/celebration
 * art 'icons' we can use to click like adding logos" — pre-made PNGs
 * (not customer uploads) a customer can drop onto the label the exact
 * same way they drop a logo: same rendering/drag/resize/submit path as a
 * real uploaded logo (see "ART" handling in label-configurator.js and
 * the art_key branch in inc/handle-submission.php), just sourced from a
 * file this plugin already ships instead of a file the customer sent.
 * "fill the art dropdown with that good stuff" — most of these (assets/
 * art/noto-*.png) are Google's own Noto Emoji graphics (fonts.google.com/
 * noto/specimen/Noto+Color+Emoji, source: github.com/googlefonts/noto-
 * emoji), Apache 2.0 licensed — free for any use, no attribution
 * required. The 3 flags (assets/art/twemoji-*-flag.png) are NOT from
 * Noto — that build deliberately drops country flags (drop_flags.py in
 * the repo) — they're from Twitter's Twemoji instead (github.com/
 * twitter/twemoji), which IS properly licensed for this use but under
 * CC-BY 4.0, NOT Apache — that license requires attribution (a visible
 * credit somewhere, e.g. "Flag icons by Twemoji"), unlike everything
 * else in this folder.
 *
 * Pete, 2026-10-18: "I want the best quality... true 300 DPI on the
 * label when we're printing" — all 41 pieces are rendered at 1800x1800px
 * from each source's TRUE VECTOR SVG (github's .../2D/svg/ for Noto,
 * .../assets/svg/ for Twemoji — originally downloaded from the lower-
 * res pre-rendered 512px PNGs instead, which blurred visibly once placed
 * large on the canvas). 1800px covers 600 DPI even at the biggest
 * possible placement (80% of the 8oz zone's 3.75" width = 3", the
 * largest the logo-size slider allows) — double the 300 DPI floor Pete
 * asked for, with real margin. The original .svg sources are kept
 * alongside the .png files (assets/art/svg-src/ for Noto, the matching
 * .svg next to each twemoji-*.png) so any one piece can be re-rendered
 * at an even higher resolution later with zero quality loss, same
 * reasoning as keeping the hand-drawn SVGs for the templates.
 *
 * All of it downloaded once (not loaded live at request time) so this
 * works offline and isn't a 3rd-party runtime dependency. Swap in
 * different art later the same "just replace the file" way as the label
 * templates, if Pete ever wants a different style.
 */
function rrt_label_art_library() {
	return array(
		// Birthday
		'balloons'      => array(
			'label' => 'Birthday Balloons',
			'file'  => 'assets/art/noto-balloons.png',
		),
		'cake'          => array(
			'label' => 'Birthday Cake',
			'file'  => 'assets/art/noto-birthday-cake.png',
		),
		'cupcake'       => array(
			'label' => 'Cupcake',
			'file'  => 'assets/art/noto-cupcake.png',
		),
		'confettiball'  => array(
			'label' => 'Confetti Ball',
			'file'  => 'assets/art/noto-confettiball.png',
		),
		'ribbon'        => array(
			'label' => 'Ribbon',
			'file'  => 'assets/art/noto-ribbon.png',
		),
		'gift'          => array(
			'label' => 'Gift',
			'file'  => 'assets/art/noto-gift.png',
		),
		'trophy'        => array(
			'label' => 'Trophy',
			'file'  => 'assets/art/noto-trophy.png',
		),
		// Christmas / winter
		'christmastree' => array(
			'label' => 'Christmas Tree',
			'file'  => 'assets/art/noto-christmas-tree.png',
		),
		'santa'         => array(
			'label' => 'Santa',
			'file'  => 'assets/art/noto-santa.png',
		),
		'snowman'       => array(
			'label' => 'Snowman',
			'file'  => 'assets/art/noto-snowman.png',
		),
		'snowflake'     => array(
			'label' => 'Snowflake',
			'file'  => 'assets/art/noto-snowflake.png',
		),
		'bell'          => array(
			'label' => 'Bell',
			'file'  => 'assets/art/noto-bell.png',
		),
		'menorah'       => array(
			'label' => 'Menorah',
			'file'  => 'assets/art/noto-menorah.png',
		),
		// Halloween / Thanksgiving
		'jackolantern'  => array(
			'label' => 'Jack-o-Lantern',
			'file'  => 'assets/art/noto-jackolantern.png',
		),
		'ghost'         => array(
			'label' => 'Ghost',
			'file'  => 'assets/art/noto-ghost.png',
		),
		'turkey'        => array(
			'label' => 'Turkey',
			'file'  => 'assets/art/noto-turkey.png',
		),
		// Easter / spring
		'rabbit'        => array(
			'label' => 'Bunny',
			'file'  => 'assets/art/noto-rabbit.png',
		),
		'egg'           => array(
			'label' => 'Egg',
			'file'  => 'assets/art/noto-egg.png',
		),
		'rose'          => array(
			'label' => 'Rose',
			'file'  => 'assets/art/noto-rose.png',
		),
		// Love
		'heart'         => array(
			'label' => 'Heart',
			'file'  => 'assets/art/noto-heart.png',
		),
		'twohearts'     => array(
			'label' => 'Two Hearts',
			'file'  => 'assets/art/noto-twohearts.png',
		),
		'bouquet'       => array(
			'label' => 'Bouquet',
			'file'  => 'assets/art/noto-bouquet.png',
		),
		// General celebration
		'partypopper'   => array(
			'label' => 'Party Popper',
			'file'  => 'assets/art/noto-party-popper.png',
		),
		'partyface'     => array(
			'label' => 'Party Face',
			'file'  => 'assets/art/noto-partyface.png',
		),
		'fireworks'     => array(
			'label' => 'Fireworks',
			'file'  => 'assets/art/noto-fireworks.png',
		),
		'sparkler'      => array(
			'label' => 'Sparkler',
			'file'  => 'assets/art/noto-sparkler.png',
		),
		'sparkles'      => array(
			'label' => 'Sparkles',
			'file'  => 'assets/art/noto-sparkles.png',
		),
		'star'          => array(
			'label' => 'Star',
			'file'  => 'assets/art/noto-star.png',
		),
		// Western / Red Rocks brand flavor
		'cowboy'        => array(
			'label' => 'Cowboy',
			'file'  => 'assets/art/noto-cowboy.png',
		),
		'mountain'      => array(
			'label' => 'Mountain',
			'file'  => 'assets/art/noto-mountain-snowcap.png',
		),
		'horse'         => array(
			'label' => 'Horse',
			'file'  => 'assets/art/noto-horse.png',
		),
		'donkey'        => array(
			'label' => 'Donkey',
			'file'  => 'assets/art/noto-donkey.png',
		),
		'dog'           => array(
			'label' => 'Dog',
			'file'  => 'assets/art/noto-dog.png',
		),
		'eagle'         => array(
			'label' => 'Eagle',
			'file'  => 'assets/art/noto-eagle.png',
		),
		// Flags — Twemoji, not Noto (CC-BY 4.0, attribution required — see
		// the doc comment above this function).
		'usflag'        => array(
			'label' => 'US Flag',
			'file'  => 'assets/art/twemoji-us-flag.png',
		),
		'caflag'        => array(
			'label' => 'Canadian Flag',
			'file'  => 'assets/art/twemoji-ca-flag.png',
		),
		'mxflag'        => array(
			'label' => 'Mexican Flag',
			'file'  => 'assets/art/twemoji-mx-flag.png',
		),
		// Colorado's state flag has no Unicode/Twemoji glyph (only national
		// flags do), so this one is an original rendering, not a Twemoji
		// download — matched to the same 1800x1800 canvas/margins/corner
		// radius as the three flags above so it sits in the picker
		// consistently, but it carries no CC-BY attribution requirement
		// (a US state flag's design isn't copyrightable; this specific
		// PNG is our own artwork).
		'coflag'        => array(
			'label' => 'Colorado Flag',
			'file'  => 'assets/art/twemoji-co-flag.png',
		),
		'football'      => array(
			'label' => 'Football',
			'file'  => 'assets/art/noto-football.png',
		),
		// No "trout" emoji exists in Unicode — this is the generic Fish
		// emoji (a blue tropical-style fish, not especially trout-like),
		// labeled honestly as "Fish" rather than "Trout".
		'fish'          => array(
			'label' => 'Fish',
			'file'  => 'assets/art/noto-fish.png',
		),
		// No "fly rod" emoji exists either — generic fishing pole instead,
		// labeled accordingly.
		'fishingpole'   => array(
			'label' => 'Fishing Pole',
			'file'  => 'assets/art/noto-fishingpole.png',
		),
		'hikingboot'    => array(
			'label' => 'Hiking Boot',
			'file'  => 'assets/art/noto-hikingboot.png',
		),
	);
}

/**
 * Pete, 2026-10-26: "we'll need to build our library... donate their image
 * for others to use... so it immediately goes to our library" — customer-
 * donated art, merged into the same ART picker as rrt_label_art_library()
 * above but sourced from real WP Media Library attachments instead of a
 * hand-curated PHP array. Storage = real attachments (not a new CPT/table/
 * JSON file) on purpose: Pete can already browse and delete anything in
 * wp-admin -> Media with zero new admin UI to build or code to touch if a
 * submission needs removing. Tagged via rrt_label_donate_to_community_library()
 * below. Capped at 60 (newest first) so the picker can't grow unbounded.
 */
function rrt_label_community_art() {
	$attachments = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 60,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_key'       => '_rrt_community_art',
			'meta_value'     => '1',
		)
	);

	$items = array();
	foreach ( $attachments as $attachment ) {
		$url = wp_get_attachment_url( $attachment->ID );
		if ( ! $url ) {
			continue;
		}
		$items[ 'community:' . $attachment->ID ] = array(
			'label' => $attachment->post_title ? $attachment->post_title : 'Community art',
			'file'  => $url,
		);
	}
	return $items;
}

/**
 * Copies a just-uploaded logo file into the real Media Library and tags it
 * as donated community art. Called from rrt_label_handle_submit() (inc/
 * handle-submission.php) AFTER the real order's composite/email already
 * succeeded — this is purely additive and must never block or fail the
 * actual order submission, so every failure path here just returns quietly
 * (no exceptions, no wp_send_json_error).
 *
 * Copies (not moves) the tmp file — $tmp_path is still PHP's own upload tmp
 * file at this point and may be read again elsewhere in the same request;
 * letting PHP clean it up itself at request end is simpler than coordinating
 * who deletes it.
 *
 * Pete, 2026-10-26: "keep art donators as anonymous as possible, unless
 * the user wants to be known" — anonymous to OTHER CUSTOMERS, never shown
 * anywhere public (rrt_label_community_art() never returns these fields).
 * Pete's own follow-up correction: "seeing that no user will get the
 * backend info of the donator, we'll still need to know who it is and to
 * contact if there's something that's not allowable, or to flag them as
 * unwelcome donator" — so name/email ARE kept, strictly as private,
 * admin-only postmeta (visible only in wp-admin -> Media), for exactly
 * that moderation purpose. Anyone who wants real public credit instead is
 * pointed at TheUnframer's own KindShare flow (/share/) rather than this
 * plugin half-building a second identity/attribution system of its own.
 */
function rrt_label_donate_to_community_library( $tmp_path, $donor_name, $donor_email ) {
	if ( ! $tmp_path || ! file_exists( $tmp_path ) ) {
		return;
	}

	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	// wp_check_filetype() goes by the PATH'S OWN extension, but $tmp_path is
	// PHP's raw upload tmp file (e.g. "phpA1B2.tmp") with no real extension
	// at all — it would silently fail here for every genuine image.
	// getimagesize() reads the actual file content instead, same technique
	// rrt_label_draw_logo() already uses for these exact same tmp files.
	$logo_info = getimagesize( $tmp_path );
	$ext       = isset( $logo_info['mime'] ) ? array_search( $logo_info['mime'], array( 'png' => 'image/png', 'jpg' => 'image/jpeg', 'webp' => 'image/webp' ), true ) : false;
	if ( ! $ext ) {
		return;
	}
	$mime = $logo_info['mime'];

	// Pete, 2026-10-26: "set some sort of threshold for added files, if
	// they are too small, we can't use them, they'll look like crap" —
	// "discard if we want" for the donation library specifically (a
	// separate, stricter warn-not-block rule applies to a customer's own
	// upload on a real order — see rrt_label_handle_submit()). Reuses the
	// exact 1800px standard already established for the curated clip-art
	// library (rrt_label_art_library()'s own header comment: "covers 600
	// DPI even at the biggest possible placement... double the 300 DPI
	// floor, with real margin") — one consistent quality bar, not a second
	// invented number. Silent no-op, same as every other failure path
	// here — no UI, no explanation, it just never becomes a library item.
	if ( min( $logo_info[0], $logo_info[1] ) < 1800 ) {
		return;
	}

	$upload_dir = wp_upload_dir();
	if ( ! empty( $upload_dir['error'] ) ) {
		return;
	}

	$filename = wp_unique_filename( $upload_dir['path'], 'community-art.' . $ext );
	$dest     = trailingslashit( $upload_dir['path'] ) . $filename;

	if ( ! @copy( $tmp_path, $dest ) ) {
		return;
	}

	$attachment_id = wp_insert_attachment(
		array(
			'post_mime_type' => $mime,
			'post_title'     => sanitize_file_name( pathinfo( $filename, PATHINFO_FILENAME ) ),
			'post_status'    => 'inherit',
		),
		$dest
	);

	if ( ! $attachment_id || is_wp_error( $attachment_id ) ) {
		return;
	}

	$metadata = wp_generate_attachment_metadata( $attachment_id, $dest );
	wp_update_attachment_metadata( $attachment_id, $metadata );

	update_post_meta( $attachment_id, '_rrt_community_art', '1' );
	// Private moderation contact only — never returned by
	// rrt_label_community_art() or shown to any other customer.
	update_post_meta( $attachment_id, '_rrt_donor_name', sanitize_text_field( $donor_name ) );
	update_post_meta( $attachment_id, '_rrt_donor_email', sanitize_email( $donor_email ) );
}

/**
 * Pixel geometry for one size's zone, derived from the inches above.
 * Returns the template image's own pixel dimensions plus the zone's
 * pixel box (x/y/width/height), horizontally centered automatically.
 */
function rrt_label_zone_px( $size_key ) {
	$sizes = rrt_label_sizes();
	if ( ! isset( $sizes[ $size_key ] ) ) {
		return null;
	}
	$cfg = $sizes[ $size_key ];
	$dpi = $cfg['dpi'];

	$img_w  = round( $cfg['image_width_in'] * $dpi );
	$img_h  = round( $cfg['image_height_in'] * $dpi );
	$zone_w = round( $cfg['zone']['width_in'] * $dpi );
	$zone_h = round( $cfg['zone']['height_in'] * $dpi );
	$zone_y = round( $cfg['zone']['top_in'] * $dpi );
	$zone_x = round( ( $img_w - $zone_w ) / 2 );

	return array(
		'image_width'  => $img_w,
		'image_height' => $img_h,
		'zone_x'       => $zone_x,
		'zone_y'       => $zone_y,
		'zone_width'   => $zone_w,
		'zone_height'  => $zone_h,
	);
}

/**
 * How much of the label (in inches) to show above/below the blank zone in
 * the on-screen preview — just enough to give a peek of the surrounding
 * label (logo block one side, Net Weight/ingredients the other) without
 * showing the whole 11"-long strip, which is too tall to use on screen.
 * Purely a display crop — never changes the zone geometry used for the
 * actual GD compositing in inc/handle-submission.php.
 *
 * Pete, 2026-10-19: "show more of the label... more of the logo... at
 * least half of the logo, the tops of the lettering with the mountain.
 * Need to exude user confidence." The mountain-logo lockup
 * (redrockstoffee.com / "RED ROCKS TOFFEE COMPANY" arched lettering /
 * mountain graphic) sits well below the zone, starting ~0.82in past
 * zone_bottom — measured directly off 8oz-template.jpg (zone_bottom=
 * 2306px; the lockup's top text starts ~2461px).
 *
 * 2026-10-21: "it would be great to see the top portion of the logo
 * first [on load], and the user can slide up to reveal the rest of the
 * canvas dotted line area" — the bottom side now extends all the way to
 * the image's actual bottom edge (see rrt_label_crop_px()) instead of a
 * fixed margin, maximizing how much of the logo/footer can be scrolled
 * into view. Superseded 2026-10-25 (see rrt_label_display_geometry() for
 * the current visible-height/initial-scroll logic), but crop_height
 * itself still reaches the image's bottom edge so there's still more to
 * scroll into if wanted.
 *
 * 2026-10-25: "tighten up the window by the amount of the lettering at
 * the top of the label, (not showing... make window that much smaller,
 * so we see the dottedline area and the top half of the logo" — the
 * 0.75in margin toward the Nutrition Facts end (previously kept because
 * "wasn't part of either ask") showed a sliver of that panel's own
 * (upside-down, out-of-context) text above the zone; dropped to 0 so the
 * visible window starts right at the zone's own top edge instead.
 */
function rrt_label_crop_margins_in() {
	return array(
		// Pete, 2026-10-25: "need a px or two at the top between the red
		// banner lettering and the dotted line" — dropping the margin to
		// 0 (above) put the zone flush against the sticky instructions
		// banner with no breathing room at all. A small reintroduced gap
		// (not the old 0.75in — just enough to visually separate them).
		'top' => 0.05, // toward the Nutrition Facts end
	);
}

/**
 * The on-screen preview's crop window, in the template's own full-image
 * pixel space: from a bit above the zone (see rrt_label_crop_margins_in())
 * all the way down to the image's actual bottom edge — i.e. the zone
 * plus EVERYTHING below it (mountain logo, tagline, footer), so the full
 * remainder is available to scroll into view. Full image width is
 * always shown (the zone already spans nearly the whole width on both
 * sizes, so there's nothing useful to crop horizontally).
 */
function rrt_label_crop_px( $size_key ) {
	$px = rrt_label_zone_px( $size_key );
	if ( ! $px ) {
		return null;
	}
	$sizes      = rrt_label_sizes();
	$dpi        = $sizes[ $size_key ]['dpi'];
	$margins_in = rrt_label_crop_margins_in();
	$top_px     = round( $margins_in['top'] * $dpi );

	$crop_y      = max( 0, $px['zone_y'] - $top_px );
	$crop_bottom = $px['image_height'];

	return array(
		'crop_x'      => 0,
		'crop_y'      => $crop_y,
		'crop_width'  => $px['image_width'],
		'crop_height' => $crop_bottom - $crop_y,
	);
}

/**
 * Everything the front-end display needs for one size, as plain
 * percentages — no canvas, no per-frame JS math. The preview is built
 * from three CSS-positioned layers inside a single overflow:hidden
 * "stage" box:
 *   1. the full template <img>, shifted up with a CSS transform so only
 *      the crop window (zone + a little context) shows through;
 *   2. a "zone" <div>, positioned/sized as a percentage of the stage —
 *      this is the bounded area the logo/text layers drag within;
 *   3. the logo <img> and text <div>, positioned as percentages *within*
 *      the zone, same technique TheUnframer's quote-module.js uses for
 *      its draggable logo/text layers.
 */
function rrt_label_display_geometry( $size_key ) {
	$px   = rrt_label_zone_px( $size_key );
	$crop = rrt_label_crop_px( $size_key );
	if ( ! $px || ! $crop ) {
		return null;
	}

	$sizes = rrt_label_sizes();
	$dpi   = $sizes[ $size_key ]['dpi'];

	// Pete, 2026-10-25: "make the configurator window less tall and only
	// show half the logo, so we see more of the canvas area" — supersedes
	// the 2026-10-19/21 tuning below (kept scrolled deep toward the logo
	// by default, which pushed the zone itself out of the initial view —
	// the opposite of what's wanted here). The mountain-logo lockup starts
	// ~0.52in past zone_bottom (measured directly off 8oz-template.jpg:
	// zone_bottom=2306px=7.6875in; the lockup's top text starts
	// ~2461px=8.203in) — showing roughly another 1in past that lands
	// partway through the lockup (it's a multi-line stack: site URL /
	// "RED ROCKS" / "TOFFEE COMPANY" / tagline, so ~1in reaches about
	// through the mountain graphic and first text line) — "half the
	// logo," not the whole thing. The VISIBLE box now = zone + this,
	// shorter than the old reach-for-the-logo height it's replacing.
	$visible_bottom_in   = 1.5;
	$visible_crop_height = ( $px['zone_y'] - $crop['crop_y'] ) + $px['zone_height'] + round( $visible_bottom_in * $dpi );

	// Pete, 2026-10-25: "when we open the window, can we have it be at
	// the bottom, seeing the logo and auto slide up to show the dotted
	// box" — this value is now where the stage SETTLES after that
	// animated slide-up (0 = all the way up, zone fully visible), not
	// literally where it starts. The actual "start at the bottom" jump +
	// smooth animated scroll back up to this value live in loadSize()
	// in label-configurator.js.
	$initial_scroll_pct = 0;

	return array(
		// The full scrollable content (background image + zone), sized
		// to reach all the way down to the mountain logo and the rest of
		// the label below the zone.
		'crop_width'          => $crop['crop_width'],
		'crop_height'         => $crop['crop_height'],
		// The VISIBLE stage box — shorter than the full content above,
		// which is what makes the box scrollable (see rrt-lc-stage-inner
		// in label-configurator.js).
		'visible_crop_height' => $visible_crop_height,
		'initial_scroll_pct'  => $initial_scroll_pct,
		// Full template <img>: rendered width as a % of the stage, and
		// how far to shift it up/left (in %, of the IMAGE'S OWN
		// rendered size — matches CSS `transform: translate()` percent
		// semantics) so the crop window lines up in the stage window.
		'bg_width_pct'    => 100 * $px['image_width'] / $crop['crop_width'],
		'bg_translate_x'  => 100 * $crop['crop_x'] / $px['image_width'],
		'bg_translate_y'  => 100 * $crop['crop_y'] / $px['image_height'],
		// The zone box, positioned/sized as a % of the stage.
		'zone_left_pct'   => 100 * ( $px['zone_x'] - $crop['crop_x'] ) / $crop['crop_width'],
		'zone_top_pct'    => 100 * ( $px['zone_y'] - $crop['crop_y'] ) / $crop['crop_height'],
		'zone_width_pct'  => 100 * $px['zone_width'] / $crop['crop_width'],
		'zone_height_pct' => 100 * $px['zone_height'] / $crop['crop_height'],
	);
}

/**
 * [rrt_label_configurator] — renders the two-column configurator (logo
 * upload + text/font controls + contact info in a sidebar, live preview
 * on the right, collapsing to one column on mobile) and enqueues this
 * plugin's own JS/CSS only on the page that actually uses it.
 */
function rrt_label_configurator_shortcode( $atts ) {
	// configurator-base.css / configurator-components.css are VERBATIM
	// copies of TheUnframer.com's own upload-configurator.css + the
	// relevant chunk of custom.css (Pete: "copy configurator and we'll
	// strip OUT parts") — enqueued first so our own small file can
	// override/add to them, not replace them.
	wp_enqueue_style( 'rrt-configurator-base', RRT_LABEL_URL . 'assets/configurator-base.css', array(), RRT_LABEL_VERSION );
	wp_enqueue_style( 'rrt-configurator-components', RRT_LABEL_URL . 'assets/configurator-components.css', array( 'rrt-configurator-base' ), RRT_LABEL_VERSION );
	wp_enqueue_style( 'rrt-label-configurator', RRT_LABEL_URL . 'assets/label-configurator.css', array( 'rrt-configurator-components' ), RRT_LABEL_VERSION );
	wp_enqueue_script( 'rrt-label-configurator', RRT_LABEL_URL . 'assets/label-configurator.js', array(), RRT_LABEL_VERSION, true );

	// Same Google Fonts list TheUnframer's own configurator loads (its
	// font-drum's .font-preview.* classes reference these families by
	// name) — copied verbatim from inc/enqueue-scripts.php there, plus
	// this plugin's own label-compositing fonts.
	$fonts         = rrt_label_fonts();
	$font_families = array(
		'Poppins:wght@400;700;800;900',
		'Luckiest+Guy',
		'Satisfy',
		'Lobster',
		'Bangers',
		'Fredoka+One',
		'Montserrat:wght@400;700',
		'Abril+Fatface',
		'Great+Vibes',
		'Sniglet',
		'Pacifico',
		'Oswald:wght@400;600',
		'Dancing+Script:wght@600',
		'Playfair+Display:ital,wght@0,400;1,400',
		'Boogaloo',
		'Kaushan+Script',
	);
	foreach ( $fonts as $f ) {
		if ( ! empty( $f['google_font'] ) ) { // 'arial' composites with Arimo but never loads it for the (system-font) preview — see rrt_label_fonts().
			$font_families[] = $f['google_font'];
		}
	}
	wp_enqueue_style(
		'rrt-label-fonts',
		'https://fonts.googleapis.com/css2?family=' . implode( '&family=', $font_families ) . '&display=swap',
		array(),
		null
	);

	// Pete, 2026-11-07: flavor is now a real second axis alongside size
	// (see rrt_label_flavors() above) — $sizes itself is flavor-independent
	// geometry, used only to enumerate the two size keys and build $geo
	// (same regardless of flavor); $js_sizes nests PER FLAVOR because
	// that's the only thing that actually changes per flavor
	// (template_url/template_tinted_url).
	$sizes    = rrt_label_sizes();
	$flavors  = rrt_label_flavors();
	$js_sizes = array();
	foreach ( $flavors as $flavor_key => $flavor_cfg ) {
		$flavor_sizes             = rrt_label_sizes( $flavor_key );
		$js_sizes[ $flavor_key ]  = array();
		foreach ( $flavor_sizes as $key => $cfg ) {
			$geo = rrt_label_display_geometry( $key );
			$js_sizes[ $flavor_key ][ $key ] = array_merge(
				array(
					'label'           => $cfg['label'],
					'template_url'    => RRT_LABEL_URL . $cfg['template_file'],
					// Pete, 2026-10-25: "start from scratch... for that part
					// of the image" — every CSS-only attempt (multiply blend,
					// flat alpha wash, sepia filter) either faded the ink or
					// got the color wrong, because none of them can actually
					// tell paper from ink. Real fix: a build-time script
					// (scratchpad's tint-template.php, GD-based) recolors
					// only the near-white background pixels of the actual
					// template JPG, leaving true ink/logo colors alone —
					// used ONLY by the 3D box mockup, never the flat 2D
					// preview or the real print compositing in
					// inc/handle-submission.php, which still use the
					// original untouched template.
					'template_tinted_url' => RRT_LABEL_URL . str_replace( '.jpg', '-tinted.jpg', $cfg['template_file'] ),
					// Raw inches for the 3D box-mockup preview (see
					// assets/label-configurator.js's computeBoxFaces()) — the
					// mockup computes its own crop windows directly from these
					// rather than reusing $geo above, which is tuned for the
					// flat scrollable preview's different framing.
					'image_width_in'  => $cfg['image_width_in'],
					'image_height_in' => $cfg['image_height_in'],
					'box_width_in'    => $cfg['box_width_in'],
					'box_depth_in'    => $cfg['box_depth_in'],
					'box_height_in'   => $cfg['box_height_in'],
					'zone_width_in'   => $cfg['zone']['width_in'],
					'zone_height_in'  => $cfg['zone']['height_in'],
					'zone_top_in'     => $cfg['zone']['top_in'],
				),
				$geo
			);
		}
	}

	$js_flavors = array();
	foreach ( $flavors as $flavor_key => $flavor_cfg ) {
		$js_flavors[ $flavor_key ] = array(
			'label'     => $flavor_cfg['label'],
			'available' => ! empty( $flavor_cfg['available'] ),
		);
	}

	$js_fonts = array();
	foreach ( $fonts as $key => $f ) {
		$js_fonts[ $key ] = array(
			'label'      => $f['label'],
			'css_family' => $f['css_family'],
		);
	}

	$js_art = array();
	foreach ( rrt_label_art_library() as $key => $a ) {
		$js_art[ $key ] = array(
			'label' => $a['label'],
			'url'   => RRT_LABEL_URL . $a['file'],
		);
	}
	// Pete, 2026-10-26: "Community Library Art items aren't being brought
	// into the canvas... when selected" — root cause: addArtLogo() in
	// label-configurator.js looks up the clicked data-art-key in
	// config.art, but this object was only ever built from the curated
	// library above, never from rrt_label_community_art(). Clicking a
	// community thumbnail (data-art-key="community:{id}", see the
	// #art-picker-panel loop below) found nothing and silently did
	// nothing. Community items already use a full Media Library URL in
	// 'file' (no RRT_LABEL_URL prefix needed, unlike the curated ones
	// above).
	foreach ( rrt_label_community_art() as $key => $a ) {
		$js_art[ $key ] = array(
			'label' => $a['label'],
			'url'   => $a['file'],
		);
	}

	wp_add_inline_script(
		'rrt-label-configurator',
		'window.rrtLabelConfigurator = ' . wp_json_encode(
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'flavors' => $js_flavors,
				'sizes'   => $js_sizes,
				'fonts'   => $js_fonts,
				'art'     => $js_art,
			)
		) . ';',
		'before'
	);

	ob_start();
	?>
	<div class="rrt-label-configurator" id="rrt-label-configurator">
		<div class="configurator-grid text-active">

			<div class="configurator-sidebar">

				<div class="config-card rrt-lc-card--size">
					<div class="rrt-lc-field">
						<label class="rrt-lc-field-label">Flavor</label>
						<?php
						// Pete, 2026-11-10: Koffie-Almond is now available
						// (see rrt_label_flavors() in this file), so this
						// markup renders both flavors as plain pickable
						// radios — the --disabled class/"Coming soon" suffix
						// below only kick in again if a flavor's 'available'
						// is ever set back to false.
						$is_first_flavor = true;
						?>
						<div class="rrt-lc-size-options rrt-lc-flavor-options" role="radiogroup" aria-label="Flavor">
							<?php foreach ( $flavors as $flavor_key => $flavor_cfg ) : ?>
								<label class="rrt-lc-size-option<?php echo empty( $flavor_cfg['available'] ) ? ' rrt-lc-size-option--disabled' : ''; ?>">
									<input type="radio" name="rrt-lc-flavor" value="<?php echo esc_attr( $flavor_key ); ?>"<?php echo $is_first_flavor ? ' checked' : ''; ?><?php echo empty( $flavor_cfg['available'] ) ? ' disabled' : ''; ?>>
									<span><?php echo esc_html( $flavor_cfg['label'] ); ?><?php echo empty( $flavor_cfg['available'] ) ? ' — Coming soon' : ''; ?></span>
								</label>
								<?php $is_first_flavor = false; ?>
							<?php endforeach; ?>
						</div>
					</div>
					<div class="rrt-lc-field">
						<?php
						// Pete, 2026-11-07: "change the dropdown that says box
						// size, to radio buttons for the two current, both
						// showing" -- was a <select>, now both sizes sit
						// right on the page (only two options, no real need
						// to hide one behind a dropdown). Same id="rrt-lc-size"
						// is gone (radios share one name instead); JS below
						// reads whichever one is :checked.
						//
						// Pete, 2026-11-08: "remove the 'BOX SIZE' title
						// lettering" — the visible <label> is gone; the
						// radiogroup's aria-label below keeps this
						// identifiable for screen readers even with no
						// visible heading.
						$is_first_size = true;
						?>
						<div class="rrt-lc-size-options" role="radiogroup" aria-label="Box size">
							<?php foreach ( $sizes as $key => $cfg ) : ?>
								<?php
								// Pete, 2026-10-21: "need the appropriate Minimum number" shown
								// right in the dropdown — exact wording per size in
								// rrt_label_sizes()'s 'dropdown_note'. $cfg['label'] itself
								// (e.g. "8oz Box") is left untouched — it's also used in the
								// submission email and the JS "minimum order" error message,
								// where this longer phrasing wouldn't read right.
								$option_text = $cfg['label'] . ' — ' . $cfg['dropdown_note'];
								?>
								<label class="rrt-lc-size-option">
									<input type="radio" name="rrt-lc-size" value="<?php echo esc_attr( $key ); ?>"<?php echo $is_first_size ? ' checked' : ''; ?>>
									<span><?php echo esc_html( $option_text ); ?></span>
								</label>
								<?php $is_first_size = false; ?>
							<?php endforeach; ?>
						</div>
					</div>
				</div>

				<?php
				/**
				 * Byte-for-byte copies of TheUnframer.com template-upload.php
				 * lines 49-193 (the font-drum/quote-toggle inline <style>)
				 * and lines 194-551 (the real quote-module-card markup) --
				 * Pete: "copy means copy" -- included from plain files
				 * instead of retyped inline, so there is no drift between
				 * this and the real source. Nothing here is wired to this
				 * plugin's JS yet; strip/adapt together next, in place,
				 * not by retyping.
				 */
				include RRT_LABEL_PATH . 'inc/quote-module-style.part.php';
				include RRT_LABEL_PATH . 'inc/quote-module-card.part.php';
				?>

				<div class="config-card rrt-lc-card--contact">
					<h3 class="rrt-lc-heading">Your info</h3>

					<div class="rrt-lc-field">
						<label for="rrt-lc-name">Your name</label>
						<input type="text" id="rrt-lc-name" required>
					</div>
					<div class="rrt-lc-field">
						<label for="rrt-lc-email">Email</label>
						<input type="email" id="rrt-lc-email" required>
					</div>
					<div class="rrt-lc-field">
						<label for="rrt-lc-phone">Phone</label>
						<input type="tel" id="rrt-lc-phone">
					</div>
					<div class="rrt-lc-field">
						<label for="rrt-lc-quantity">Quantity (boxes)</label>
						<input type="number" id="rrt-lc-quantity" min="1" step="1" value="2">
					</div>
					<div class="rrt-lc-field">
						<label for="rrt-lc-message">Anything else we should know?</label>
						<textarea id="rrt-lc-message" rows="3"></textarea>
					</div>

					<?php
					/**
					 * Pete, 2026-11-07: "so far we have two files user stuff
					 * gets jammed into. We need to make allowances so if
					 * user wishes to put together an order for each size,
					 * file is saved(held), fires off the Harry I love it
					 * button with a descriptor/tally showing until files
					 * sent by 'submit my files design(s)'" — clicking
					 * "Harry, I love it!" on the box-preview popup now holds
					 * a snapshot of the design in progress instead of just
					 * closing the popup (see the boxLoveBtn handler in
					 * label-configurator.js). This tray lists every held
					 * design with a quick remove, and stays hidden until at
					 * least one exists. Submit always bundles whatever's
					 * held here PLUS whatever's still on the stage, so a
					 * single-design order needs no extra click, same as
					 * before this feature existed.
					 */
					?>
					<?php
					/**
					 * Pete, 2026-11-08: "doesn't make sense yet... a) no way
					 * to tell what it is, b) can I look again?, c) I need a
					 * 'make another label?' button" — each held <li> now
					 * carries a live thumbnail + a one-line content summary
					 * (built in label-configurator.js) so two held designs
					 * never look identical, a "View" button reopens a bigger
					 * read-only version of that same thumbnail, and this
					 * explicit button scrolls back up to the flavor/size
					 * card instead of expecting the customer to notice the
					 * stage already reset on its own.
					 */
					?>
					<div class="rrt-lc-held-designs" id="rrt-lc-held-designs" hidden>
						<h3 class="rrt-lc-heading">Saved designs</h3>
						<ul class="rrt-lc-held-list" id="rrt-lc-held-list"></ul>
						<button type="button" id="rrt-lc-add-another-btn" class="rrt-lc-btn-secondary rrt-lc-add-another-btn">+ Make another label</button>
					</div>

					<button type="button" id="rrt-lc-submit" class="rrt-lc-btn-primary">Submit my label design</button>
					<img id="rrt-lc-spinner" class="rrt-lc-spinner" src="<?php echo esc_url( RRT_LABEL_URL . 'assets/images/harry-logo.png' ); ?>" alt="" aria-hidden="true">
					<p id="rrt-lc-status" class="rrt-lc-status" role="status"></p>
				</div>

			</div>

			<div class="configurator-main">
				<div class="rrt-lc-stage" id="rrt-lc-stage">
					<!-- Pete, 2026-10-21: "red strip box/white lettering inside the
					     top edge of the window" — a sibling of rrt-lc-stage-inner
					     (not a child of it), so it stays pinned to the top of the
					     visible window regardless of how far the inner content is
					     scrolled; pointer-events:none so it never blocks grab-to-pan
					     or dragging a logo/text layer underneath it. -->
					<div class="rrt-lc-instructions" id="rrt-lc-instructions">Logo/Message Inside Dotted Line</div>
					<div class="rrt-lc-stage-inner" id="rrt-lc-stage-inner">
						<img class="rrt-lc-bg" id="rrt-lc-bg" alt="">
						<div class="rrt-lc-zone" id="rrt-lc-zone">
							<!-- logo/text layers are added here by JS as the customer clicks
							     "Add logo" / "Add text" — the zone starts empty on purpose. -->
						</div>
					</div>
				</div>

				<?php
				/**
				 * Pete, 2026-10-25: "visual helper... put the single label
				 * on the box of each size... looking at it from the front
				 * on a 30 degree up angle and 30 degree angle to the left."
				 * Pure CSS 3D (rotateX/rotateY/translateZ on plain divs) —
				 * same "no canvas, no framework" rule as the rest of this
				 * plugin. Only the 3 faces visible from this one FIXED
				 * angle exist at all (top, front, left end cap) — this
				 * isn't a rotatable viewer, so back/bottom/right end cap
				 * are skipped outright, never built. Sizes/positions are
				 * set by assets/label-configurator.js's computeBoxFaces(),
				 * from the raw box_, image_, and zone_*_in numbers above.
				 *
				 * Pete, 2026-10-25 (same day): "show up when we hit a
				 * button... 'let me see what we have' button and a 'Love
				 * it!' button under the popup image and a 'back to the
				 * drawing board' button" — moved from always-visible inline
				 * to a popup, opened by the button below. Both popup
				 * buttons just close it (editing already happens live
				 * behind it, so "drawing board" needs no extra state to
				 * restore) — see openBoxModal()/closeBoxModal() in
				 * label-configurator.js.
				 */
				?>
				<button type="button" id="rrt-lc-box-preview-btn" class="rrt-lc-btn-primary rrt-lc-box-preview-btn">See your Label on the box</button>

			</div>

		</div>

		<div class="rrt-lc-box-modal" id="rrt-lc-box-modal" hidden>
					<div class="rrt-lc-box-modal-backdrop" id="rrt-lc-box-modal-backdrop"></div>
					<div class="rrt-lc-box-modal-panel" role="dialog" aria-modal="true" aria-labelledby="rrt-lc-box-modal-heading">
						<h3 class="rrt-lc-heading" id="rrt-lc-box-modal-heading">How it'll look on the box</h3>
						<?php
						/**
						 * Pete, 2026-10-25: "remove the - and + on either
						 * end and make it easier to work on the swing/arc,
						 * a grab/smoosh to slide it" — the slider's gone;
						 * swing is now a direct drag right on the box
						 * itself (see the pointerdown/pointermove handlers
						 * on #rrt-lc-box-perspective in
						 * label-configurator.js), same grab-to-pan pattern
						 * already used on the flat preview's #rrt-lc-stage.
						 */
						?>
						<p class="rrt-lc-box-drag-hint">Drag to turn and tilt</p>
						<div class="rrt-lc-box-perspective" id="rrt-lc-box-perspective">
							<div class="rrt-lc-box-scene" id="rrt-lc-box-scene">
								<div class="rrt-lc-box-face rrt-lc-box-top" id="rrt-lc-box-top">
									<div class="rrt-lc-box-label-strip" id="rrt-lc-box-top-strip">
										<img class="rrt-lc-box-label-img" id="rrt-lc-box-top-img" alt="">
										<div class="rrt-lc-zone-mirror" id="rrt-lc-zone-mirror">
											<div class="rrt-lc-zone-mirror-inner" id="rrt-lc-zone-mirror-inner"></div>
										</div>
									</div>
								</div>
								<div class="rrt-lc-box-face rrt-lc-box-front" id="rrt-lc-box-front">
									<div class="rrt-lc-box-label-strip" id="rrt-lc-box-front-strip">
										<img class="rrt-lc-box-label-img" id="rrt-lc-box-front-img" alt="">
									</div>
								</div>
								<?php
							/**
							 * Pete, 2026-10-25: "we will need a white end on
							 * the right end" — the swing slider (added
							 * earlier today) can now turn the box far enough
							 * to expose the RIGHT end cap, which never
							 * existed (this started as a fixed-angle, left-
							 * side-only mockup). Same plain glossy-white
							 * panel as the left one, mirrored.
							 */
							?>
							<div class="rrt-lc-box-face rrt-lc-box-endcap-left" id="rrt-lc-box-endcap-left"></div>
							<div class="rrt-lc-box-face rrt-lc-box-endcap-right" id="rrt-lc-box-endcap-right"></div>
							</div>
						</div>
						<div class="rrt-lc-box-modal-actions">
							<button type="button" id="rrt-lc-box-love-btn" class="rrt-lc-btn-primary"><span class="rrt-lc-love-icon-wrap"><img class="rrt-lc-love-icon" src="<?php echo esc_url( RRT_LABEL_URL . 'assets/images/harry-logo.png' ); ?>" width="36" height="21" decoding="sync" alt=""></span>Harry, I love it!</button>
							<?php
							/**
							 * Pete, 2026-11-10: "when we open/view a saved
							 * file, then look at it on the box, we need to
							 * have an 'All Good - Keep' Button as well, for
							 * someone who just wants to check and it pops us
							 * back to see the saved stuff" — same visibility
							 * condition as #rrt-lc-box-edit-btn below (only
							 * while viewing a SAVED design): a plain, labeled
							 * way to say "this is fine as-is" instead of
							 * relying on backdrop-click/Escape, which did the
							 * same thing but wasn't discoverable.
							 */
							?>
							<button type="button" id="rrt-lc-box-keep-btn" class="rrt-lc-btn-primary" hidden>All Good - Keep</button>
							<?php
							/**
							 * Pete, 2026-11-09: "can we throw it back to the
							 * preview/edit field?" — only shown while this
							 * popup is viewing a SAVED design (see
							 * openBoxModalForHeldDesign() in
							 * label-configurator.js), hidden the rest of the
							 * time same as the love-it button is in reverse.
							 */
							?>
							<button type="button" id="rrt-lc-box-edit-btn" class="rrt-lc-btn-primary" hidden>Edit This Design</button>
							<button type="button" id="rrt-lc-box-back-btn" class="rrt-lc-btn-secondary">Back to the drawing board</button>
							<?php
							/**
							 * Pete, 2026-11-09: "I think we can safely remove
							 * the 'back to the drawing board,' let's put a
							 * 'delete/start again' in its place that removes
							 * and gets us back to the beginning" — ONLY while
							 * viewing a SAVED design (same condition as
							 * #rrt-lc-box-edit-btn above): "back to the
							 * drawing board" doesn't apply there — there's no
							 * live draft to go back to — and REAL closing
							 * (no change, keep the saved design) is still
							 * available via the backdrop click or Escape, so
							 * nothing is lost by dropping it as a labeled
							 * button here.
							 */
							?>
							<button type="button" id="rrt-lc-box-delete-btn" class="rrt-lc-btn-secondary" hidden>Delete / Start Again</button>
						</div>
					</div>
				</div>

				<?php
				/**
				 * Pete, 2026-11-08: "can I look again?" — a small read-only
				 * popup that re-shows a held design's thumbnail (the same
				 * cloned-zone snapshot used in the tray <li>, just bigger)
				 * so a customer can check what they saved without it being
				 * loaded back onto the live editing stage. Reuses the same
				 * .rrt-lc-box-modal* classes as the box-mockup popup above —
				 * same backdrop/centered-panel look, nothing box-specific
				 * about those classes.
				 */
				?>
				<div class="rrt-lc-box-modal" id="rrt-lc-held-preview-modal" hidden>
					<div class="rrt-lc-box-modal-backdrop" id="rrt-lc-held-preview-backdrop"></div>
					<div class="rrt-lc-box-modal-panel" role="dialog" aria-modal="true" aria-labelledby="rrt-lc-held-preview-heading">
						<h3 class="rrt-lc-heading" id="rrt-lc-held-preview-heading">Saved design</h3>
						<div class="rrt-lc-held-preview-thumb" id="rrt-lc-held-preview-thumb"></div>
						<p class="rrt-lc-held-preview-details" id="rrt-lc-held-preview-details"></p>
						<?php
						/**
						 * Pete, 2026-11-08: "possible to look at the file
						 * (view) on the box before committing/submitting?
						 * To save any number of labels and then go back
						 * through them to see them on the box?" — hands off
						 * to the SAME 3D box-mockup popup used while live-
						 * editing (openBoxModalForHeldDesign() in
						 * label-configurator.js), just fed this held
						 * design's own frozen snapshot instead of the live
						 * stage.
						 */
						?>
						<div class="rrt-lc-box-modal-actions">
							<button type="button" id="rrt-lc-held-preview-box-btn" class="rrt-lc-btn-primary">See on the Box</button>
							<?php
							/**
							 * Pete, 2026-11-09: "can we throw it back to the
							 * preview/edit field?" — sends this saved design
							 * back onto the live stage for more editing (see
							 * editHeldDesign() in label-configurator.js).
							 */
							?>
							<button type="button" id="rrt-lc-held-preview-edit-btn" class="rrt-lc-btn-primary">Edit This Design</button>
							<button type="button" id="rrt-lc-held-preview-close" class="rrt-lc-btn-secondary">Close</button>
						</div>
					</div>
				</div>

				<?php
				/**
				 * Pete, 2026-11-09: "what if there's a user that wants
				 * nothing?? when they hit harry I love it, we need a
				 * popup, you didn't add anything, is this what you want?
				 * A yes, and a go back and redo" — previously, clicking
				 * "Harry, I love it!" with no text/logo just silently
				 * closed the box popup (see draftHasContent() check in
				 * label-configurator.js). Now it opens this confirmation
				 * instead, so a customer who genuinely wants a blank
				 * label can say so on purpose rather than it happening by
				 * accident. Same .rrt-lc-box-modal* classes as the other
				 * popups.
				 */
				?>
				<div class="rrt-lc-box-modal" id="rrt-lc-empty-confirm-modal" hidden>
					<div class="rrt-lc-box-modal-backdrop" id="rrt-lc-empty-confirm-backdrop"></div>
					<div class="rrt-lc-box-modal-panel" role="dialog" aria-modal="true" aria-labelledby="rrt-lc-empty-confirm-heading">
						<h3 class="rrt-lc-heading" id="rrt-lc-empty-confirm-heading">You didn't add anything</h3>
						<p class="rrt-lc-empty-confirm-text">No text or image is on this label yet. Is that what you want?</p>
						<div class="rrt-lc-box-modal-actions">
							<button type="button" id="rrt-lc-empty-confirm-yes-btn" class="rrt-lc-btn-primary">Yes, save it blank</button>
							<button type="button" id="rrt-lc-empty-confirm-back-btn" class="rrt-lc-btn-secondary">Go back and redo</button>
						</div>
					</div>
				</div>
				<?php
				/**
				 * Pete, 2026-11-09: "if we X the saved file out... maybe we
				 * need a 'this will delete your saved file' and a way back
				 * if that's a mistake" — the × on a Saved Designs tray row
				 * used to delete immediately with no confirmation and no
				 * undo. Same confirm-modal pattern as the empty-label
				 * popup above, just for this action instead.
				 */
				?>
				<div class="rrt-lc-box-modal" id="rrt-lc-delete-confirm-modal" hidden>
					<div class="rrt-lc-box-modal-backdrop" id="rrt-lc-delete-confirm-backdrop"></div>
					<div class="rrt-lc-box-modal-panel" role="dialog" aria-modal="true" aria-labelledby="rrt-lc-delete-confirm-heading">
						<h3 class="rrt-lc-heading" id="rrt-lc-delete-confirm-heading">Delete this saved design?</h3>
						<p class="rrt-lc-empty-confirm-text">This will delete your saved design. This can't be undone.</p>
						<div class="rrt-lc-box-modal-actions">
							<button type="button" id="rrt-lc-delete-confirm-yes-btn" class="rrt-lc-btn-secondary">Yes, delete it</button>
							<button type="button" id="rrt-lc-delete-confirm-back-btn" class="rrt-lc-btn-primary">No, keep it</button>
						</div>
					</div>
				</div>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'rrt_label_configurator', 'rrt_label_configurator_shortcode' );
























