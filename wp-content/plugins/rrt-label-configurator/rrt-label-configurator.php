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

define( 'RRT_LABEL_VERSION', '3.16.1' );
define( 'RRT_LABEL_PATH', plugin_dir_path( __FILE__ ) );
define( 'RRT_LABEL_URL', plugin_dir_url( __FILE__ ) );

require_once RRT_LABEL_PATH . 'inc/handle-submission.php';

/**
 * One place to adjust each label size's template image and its blank
 * customer-content zone. Everything is in inches, not pixels, so it reads
 * the way the real label artwork is measured and is easy to hand-tune.
 *
 * zone width/height are centered on the label; zone "top_in" is measured
 * from the end of the label FARTHEST from the mountain logo (confirmed
 * with Pete — that's the Nutrition Facts panel end on both templates).
 */
function rrt_label_sizes() {
	return array(
		'8oz' => array(
			'label'           => '8oz Box',
			'template_file'   => 'assets/templates/8oz-template.jpg',
			'dpi'             => 300,
			'image_width_in'  => 4.127,
			'image_height_in' => 11,
			'zone'            => array(
				'width_in'  => 3.75,
				'height_in' => 2.5,
				'top_in'    => 5.25,
			),
		),
		'4oz' => array(
			'label'           => '4oz Box',
			'template_file'   => 'assets/templates/4oz-template.jpg',
			'dpi'             => 300,
			'image_width_in'  => 2.627,
			'image_height_in' => 11,
			'zone'            => array(
				'width_in'  => 2.375,
				'height_in' => 2.5,
				'top_in'    => 5.25,
			),
		),
	);
}

/**
 * The fonts offered for the typed text, trimmed down from TheUnframer's
 * 15-font "font drum" to a small, distinct set. Each needs a real local
 * .ttf (assets/fonts/) for the server-side GD compositing (GD/FreeType
 * can't use a webfont's .woff), plus a matching Google Fonts family for
 * the on-screen DOM preview so what the customer sees matches what gets
 * composited.
 */
function rrt_label_fonts() {
	return array(
		'opensans'      => array(
			'label'       => 'Clean',
			'css_family'  => "'Open Sans', sans-serif",
			'ttf_file'    => 'assets/fonts/opensans-variable.ttf',
			'google_font' => 'Open+Sans:wght@400;700',
		),
		'oswald'        => array(
			'label'       => 'Bold Modern',
			'css_family'  => "'Oswald', sans-serif",
			'ttf_file'    => 'assets/fonts/oswald-variable.ttf',
			'google_font' => 'Oswald:wght@600',
		),
		'dancingscript' => array(
			'label'       => 'Handwritten',
			'css_family'  => "'Dancing Script', cursive",
			'ttf_file'    => 'assets/fonts/dancingscript-variable.ttf',
			'google_font' => 'Dancing+Script:wght@600',
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
 * emoji, 2D/png/512/), Apache 2.0 licensed — free for any use, no
 * attribution required. The 3 flags (assets/art/twemoji-*-flag.png) are
 * NOT from Noto — that build deliberately drops country flags
 * (drop_flags.py in the repo) — they're from Twitter's Twemoji instead
 * (github.com/twitter/twemoji, assets/svg/), which IS properly licensed
 * for this use but under CC-BY 4.0, NOT Apache — that license requires
 * attribution (a visible credit somewhere, e.g. "Flag icons by Twemoji"),
 * unlike everything else in this folder. All of it downloaded once (not
 * loaded live at request time) so this works offline and isn't a 3rd-
 * party runtime dependency. Swap in different art later the same "just
 * replace the file" way as the label templates, if Pete ever wants a
 * different style.
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
 */
function rrt_label_crop_margin_in() {
	return 0.75;
}

/**
 * The on-screen preview's crop window, in the template's own full-image
 * pixel space: the zone plus a vertical margin, clamped to the image's
 * actual bounds. Full image width is always shown (the zone already
 * spans nearly the whole width on both sizes, so there's nothing useful
 * to crop horizontally).
 */
function rrt_label_crop_px( $size_key ) {
	$px = rrt_label_zone_px( $size_key );
	if ( ! $px ) {
		return null;
	}
	$sizes     = rrt_label_sizes();
	$dpi       = $sizes[ $size_key ]['dpi'];
	$margin_px = round( rrt_label_crop_margin_in() * $dpi );

	$crop_y      = max( 0, $px['zone_y'] - $margin_px );
	$crop_bottom = min( $px['image_height'], $px['zone_y'] + $px['zone_height'] + $margin_px );

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

	return array(
		// Stage aspect ratio (CSS `aspect-ratio`).
		'crop_width'      => $crop['crop_width'],
		'crop_height'     => $crop['crop_height'],
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
		$font_families[] = $f['google_font'];
	}
	wp_enqueue_style(
		'rrt-label-fonts',
		'https://fonts.googleapis.com/css2?family=' . implode( '&family=', $font_families ) . '&display=swap',
		array(),
		null
	);

	$sizes    = rrt_label_sizes();
	$js_sizes = array();
	foreach ( $sizes as $key => $cfg ) {
		$geo = rrt_label_display_geometry( $key );
		$js_sizes[ $key ] = array_merge(
			array(
				'label'        => $cfg['label'],
				'template_url' => RRT_LABEL_URL . $cfg['template_file'],
			),
			$geo
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

	wp_add_inline_script(
		'rrt-label-configurator',
		'window.rrtLabelConfigurator = ' . wp_json_encode(
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
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
						<label for="rrt-lc-size">Box size</label>
						<select id="rrt-lc-size">
							<?php foreach ( $sizes as $key => $cfg ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $cfg['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
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
						<label for="rrt-lc-message">Anything else we should know?</label>
						<textarea id="rrt-lc-message" rows="3"></textarea>
					</div>

					<button type="button" id="rrt-lc-submit" class="rrt-lc-btn-primary">Submit my label design</button>
					<p id="rrt-lc-status" class="rrt-lc-status" role="status"></p>
				</div>

			</div>

			<div class="configurator-main">
				<div class="rrt-lc-stage" id="rrt-lc-stage">
					<img class="rrt-lc-bg" id="rrt-lc-bg" alt="">
					<div class="rrt-lc-zone" id="rrt-lc-zone">
						<!-- logo/text layers are added here by JS as the customer clicks
						     "Add logo" / "Add text" — the zone starts empty on purpose. -->
					</div>
				</div>
			</div>

		</div>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'rrt_label_configurator', 'rrt_label_configurator_shortcode' );























