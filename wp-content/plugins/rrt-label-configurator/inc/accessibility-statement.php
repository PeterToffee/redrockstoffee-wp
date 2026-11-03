<?php
/**
 * Accessibility Statement — seeds a real, linkable page at
 * /accessibility-statement/. A code deploy alone is enough to stand this
 * up; nothing manual to redo in wp-admin. Never overwrites content once
 * the page exists, so Pete's own edits in wp-admin are safe.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function rrt_accessibility_statement_content() {
	return <<<'HTML'
<p><strong>Last updated:</strong> November 2026</p>

<p>Red Rocks Toffee Company is committed to making our site usable by as many people as possible, including people who use assistive technology such as screen readers, keyboard-only navigation, or screen magnification.</p>

<h2>Our Standard</h2>
<p>We aim to meet the Web Content Accessibility Guidelines (WCAG) 2.1, Level AA. This is an ongoing effort, not a one-time fix &mdash; we review and improve the site over time rather than treating accessibility as finished.</p>

<h2>What We're Working On</h2>
<p>Areas we're actively reviewing include image alt text, keyboard navigation through checkout and the co-branding label designer, and color contrast. If you encounter something that doesn't work well with the tools you use, we want to know &mdash; see below.</p>

<h2>Reporting an Issue</h2>
<p>If you have trouble accessing any part of this site, or have feedback on how we can improve, contact us at <a href="mailto:pete@redrockstoffee.com">pete@redrockstoffee.com</a>. Please include the page you were on and what assistive technology or browser you were using, if you can &mdash; it helps us track down the issue faster.</p>
HTML;
}

function rrt_seed_accessibility_statement_page() {
	$existing = get_page_by_path( 'accessibility-statement' );
	if ( $existing ) {
		return;
	}

	wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Accessibility Statement',
			'post_name'    => 'accessibility-statement',
			'post_content' => rrt_accessibility_statement_content(),
		)
	);
}
add_action( 'init', 'rrt_seed_accessibility_statement_page', 8 );
