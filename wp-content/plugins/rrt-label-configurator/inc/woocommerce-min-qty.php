<?php
/**
 * Per-product minimum order quantity for WooCommerce — built for the
 * "build your own custom label toffee" products (Pete, 2026-10-18: "min
 * of 2 for the 8 oz, and min of 6 for the 4 oz"), but written generically
 * so it works for ANY product, not hardcoded to specific product IDs.
 *
 * NOT ACTIVE YET. This file is not require_once'd anywhere — it has zero
 * effect on the site until it's wired in (see rrt-label-configurator.php)
 * AND the two WooCommerce products actually exist. Pete, 2026-10-18:
 * "can you build it and still not put it into local yet?" — this is that:
 * real, working code, deliberately left dormant.
 *
 * How to activate when ready:
 *   1. Create the product(s) in wp-admin (e.g. "Custom Label Order —
 *      8oz", "Custom Label Order — 4oz").
 *   2. On each product's edit screen, add a Custom Field (Screen Options
 *      -> Custom Fields, if not already visible) named `_rrt_min_qty`
 *      with the value `2` or `6` — that's the only setup this code reads.
 *   3. Add `require_once RRT_LABEL_PATH . 'inc/woocommerce-min-qty.php';`
 *      to rrt-label-configurator.php (only if WooCommerce is active —
 *      see the class_exists() guard below).
 *
 * See the plan file for the full context and the decision history behind
 * why this is the only piece built for v1 (everything else about
 * checkout stays the existing design -> email-Pete flow, unchanged):
 * the plan lives outside this repo, in Claude's saved plans.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// WooCommerce might not be active on every site this plugin could run on
// (it's also used standalone, email-only, with no WooCommerce dependency
// at all) — never assume it's there.
if ( ! class_exists( 'WooCommerce' ) ) {
	return;
}

/**
 * Reads a product's minimum quantity from its `_rrt_min_qty` custom
 * field. Returns 0 (no minimum) if the field is missing, non-numeric, or
 * less than 1 — this code is a no-op for every product that doesn't
 * explicitly opt in, never a surprise restriction on existing products.
 */
function rrt_label_min_qty_for_product( $product_id ) {
	$raw = get_post_meta( $product_id, '_rrt_min_qty', true );
	$min = (int) $raw;
	return $min > 1 ? $min : 0;
}

// Sets the quantity input's own minimum/default on the product page, so
// the stepper/box never even lets the shopper land below the minimum in
// the first place — the validation hook below is the real enforcement,
// this is just making the UI match it instead of looking inconsistent.
add_filter(
	'woocommerce_quantity_input_args',
	function ( $args, $product ) {
		$min = rrt_label_min_qty_for_product( $product->get_id() );
		if ( $min ) {
			$args['min_value']   = $min;
			$args['input_value'] = max( (int) $args['input_value'], $min );
		}
		return $args;
	},
	10,
	2
);

// The real, hard, server-side enforcement — blocks the add-to-cart
// request outright (with a clear on-screen notice) if the submitted
// quantity is under that product's minimum, regardless of what the
// quantity input on the page tried to pre-fill.
add_filter(
	'woocommerce_add_to_cart_validation',
	function ( $passed, $product_id, $quantity ) {
		$min = rrt_label_min_qty_for_product( $product_id );
		if ( $min && $quantity < $min ) {
			wc_add_notice(
				sprintf(
					/* translators: %d: minimum order quantity */
					__( 'This product requires a minimum order quantity of %d.', 'rrt-label-configurator' ),
					$min
				),
				'error'
			);
			return false;
		}
		return $passed;
	},
	10,
	3
);
