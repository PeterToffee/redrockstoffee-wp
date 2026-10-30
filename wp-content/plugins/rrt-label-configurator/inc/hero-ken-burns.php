<?php
/**
 * Alternates the home hero's Elementor Background Slideshow Ken Burns
 * zoom so it swings toward alternating edges (right, left, right,
 * left...) instead of always zooming from dead center. Purely an
 * overlay on top of Elementor's own output -- no Elementor settings or
 * content are touched, and this does nothing on pages with no
 * Background Slideshow section.
 *
 * Why a CSS custom property + data-swiper-slide-index, not nth-child:
 * Elementor's Background Slideshow widget is a Swiper.js carousel built
 * entirely client-side. When its "Loop" option is on, Swiper physically
 * clones slide DOM nodes for seamless looping, so a plain nth-child
 * selector can't reliably tell odd photos from even ones. Every slide
 * (real or cloned) does reliably carry a stable data-swiper-slide-index
 * attribute matching its original photo's position, so that's what this
 * alternates on.
 *
 * Why a MutationObserver instead of Elementor's "frontend/element_ready/
 * global" hook: that hook fires once per element as soon as Elementor's
 * core has set it up, but the Background Slideshow widget's own JS is a
 * separate, asynchronously-loaded chunk -- confirmed by testing that the
 * hook does fire for the right section, but at that moment the
 * slideshow markup isn't built yet, so searching inside that scope
 * finds nothing. Worse, how long that chunk takes to load and build the
 * slides varies (observed anywhere from ~1s to 8s+ depending on system
 * load), so a single fixed polling budget for the whole thing is
 * unreliable. A MutationObserver reacts the instant the slideshow's
 * container element actually appears in the DOM, however long that
 * takes; a short poll after that only has to wait for Swiper's own
 * (comparatively fast) init to finish on it.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_enqueue_scripts',
	function () {
		// Attaches to Elementor's own 'elementor-frontend' handle. On pages
		// with no Elementor content that handle is registered but never
		// enqueued/printed, so this inline CSS/JS is a no-op there too.
		wp_add_inline_style(
			'elementor-frontend',
			'.elementor-ken-burns{transform-origin:var(--rrt-kb-origin,50% 50%);}'
		);

		wp_add_inline_script(
			'elementor-frontend',
			<<<'JS'
( function () {
	'use strict';

	function applyAlternatingOrigin( swiper ) {
		var slides = swiper.slides;
		for ( var i = 0; i < slides.length; i++ ) {
			var slideEl = slides[ i ].jquery ? slides[ i ][ 0 ] : slides[ i ];
			if ( ! slideEl || ! slideEl.getAttribute ) {
				continue;
			}
			var idx    = parseInt( slideEl.getAttribute( 'data-swiper-slide-index' ), 10 ) || 0;
			var origin = ( 0 === idx % 2 ) ? '85% 50%' : '15% 50%';
			var img    = slideEl.querySelector( '.elementor-background-slideshow__slide__image' );
			if ( img ) {
				img.style.setProperty( '--rrt-kb-origin', origin );
			}
		}
	}

	// Containers we've already started handling, so a slideshow found by
	// more than one MutationObserver callback doesn't get polled twice.
	var claimed = ( 'WeakSet' in window ) ? new WeakSet() : null;
	var claimedFallback = [];
	function isClaimed( el ) {
		return claimed ? claimed.has( el ) : -1 !== claimedFallback.indexOf( el );
	}
	function claim( el ) {
		if ( claimed ) {
			claimed.add( el );
		} else {
			claimedFallback.push( el );
		}
	}

	// The container element can exist before Swiper has finished
	// initializing on it, so once found, give it a short runway (~6s) to
	// become ready rather than requiring it to already be ready.
	function pollUntilReady( el, triesLeft ) {
		var swiper = jQuery( el ).data( 'swiper' );
		if ( swiper && swiper.slides && swiper.slides.length ) {
			applyAlternatingOrigin( swiper );
			return;
		}
		if ( triesLeft > 0 ) {
			setTimeout( function () {
				pollUntilReady( el, triesLeft - 1 );
			}, 150 );
		}
	}

	function scanExisting() {
		var containers = document.querySelectorAll( '.elementor-background-slideshow' );
		for ( var i = 0; i < containers.length; i++ ) {
			var el = containers[ i ];
			if ( isClaimed( el ) ) {
				continue;
			}
			claim( el );
			pollUntilReady( el, 40 );
		}
	}

	scanExisting();
	document.addEventListener( 'DOMContentLoaded', scanExisting );

	if ( 'MutationObserver' in window ) {
		new MutationObserver( scanExisting ).observe( document.documentElement, {
			childList: true,
			subtree: true,
		} );
	} else {
		// No MutationObserver support -- fall back to a long blind poll for
		// the container itself too (not just for Swiper readiness).
		var legacyTries = 0;
		var legacyTimer = setInterval( function () {
			scanExisting();
			legacyTries++;
			if ( legacyTries >= 100 ) {
				clearInterval( legacyTimer );
			}
		}, 300 );
	}
} )();
JS,
			'after'
		);
	},
	20
);
