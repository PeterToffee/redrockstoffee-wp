<?php
/**
 * Plugin Name: RRT Label Configurator
 * Description: Self-service co-branding label designer for the /co-branding/ page — customers upload a logo and/or type text into the blank area of a real 8oz/4oz label template, see a live preview, and submit it to Pete for review.
 * Version: 1.0.0
 * Author: Red Rocks Toffee Company
 *
 * Plain PHP/JS/CSS on purpose — no build step, no framework. Pete needs to
 * be able to open any file here and hand-edit it later.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RRT_LABEL_VERSION', '1.0.0' );
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

	$img_w = round( $cfg['image_width_in'] * $dpi );
	$img_h = round( $cfg['image_height_in'] * $dpi );
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
 * [rrt_label_configurator] — renders the size picker + canvas preview
 * + upload/text controls + the lead-contact fields, and enqueues this
 * plugin's own JS/CSS only on the page that actually uses it.
 */
function rrt_label_configurator_shortcode( $atts ) {
	wp_enqueue_style( 'rrt-label-configurator', RRT_LABEL_URL . 'assets/label-configurator.css', array(), RRT_LABEL_VERSION );
	wp_enqueue_script( 'rrt-label-configurator', RRT_LABEL_URL . 'assets/label-configurator.js', array(), RRT_LABEL_VERSION, true );

	$sizes    = rrt_label_sizes();
	$js_sizes = array();
	foreach ( $sizes as $key => $cfg ) {
		$px = rrt_label_zone_px( $key );
		$js_sizes[ $key ] = array(
			'label'        => $cfg['label'],
			'template_url' => RRT_LABEL_URL . $cfg['template_file'],
			'image_width'  => $px['image_width'],
			'image_height' => $px['image_height'],
			'zone_x'       => $px['zone_x'],
			'zone_y'       => $px['zone_y'],
			'zone_width'   => $px['zone_width'],
			'zone_height'  => $px['zone_height'],
		);
	}

	wp_add_inline_script(
		'rrt-label-configurator',
		'window.rrtLabelConfigurator = ' . wp_json_encode(
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'sizes'   => $js_sizes,
			)
		) . ';',
		'before'
	);

	ob_start();
	?>
	<div class="rrt-label-configurator" id="rrt-label-configurator">

		<div class="rrt-lc-field">
			<label for="rrt-lc-size">Box size</label>
			<select id="rrt-lc-size">
				<?php foreach ( $sizes as $key => $cfg ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $cfg['label'] ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="rrt-lc-stage">
			<canvas id="rrt-lc-canvas"></canvas>
		</div>

		<div class="rrt-lc-controls">
			<div class="rrt-lc-field">
				<label for="rrt-lc-logo">Upload your logo (optional)</label>
				<input type="file" id="rrt-lc-logo" accept="image/png,image/jpeg">
				<button type="button" id="rrt-lc-logo-remove" class="rrt-lc-btn-secondary" hidden>Remove logo</button>
			</div>

			<div class="rrt-lc-field">
				<label for="rrt-lc-text">Add text (optional)</label>
				<input type="text" id="rrt-lc-text" maxlength="80" placeholder="e.g. a company name or message">
			</div>

			<p class="rrt-lc-hint">Drag and resize your logo and/or text inside the blank area above.</p>

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
	<?php
	return ob_get_clean();
}
add_shortcode( 'rrt_label_configurator', 'rrt_label_configurator_shortcode' );
