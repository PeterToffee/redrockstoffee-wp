/**
 * RRT Label Configurator — plain vanilla JS, no build step, no framework.
 *
 * Wires up the REAL copied TheUnframer quote-module-card markup
 * (inc/quote-module-card.part.php) to this plugin's own fixed-zone
 * preview (assets/label-configurator.css's .rrt-lc-stage/.rrt-lc-zone).
 * 2026-10-21: Cartoon Balloon's markup (never wired here — Pete:
 * "we won't need the cartoon balloons") has been removed outright, after
 * briefly considering building it out and learning GD (the server-side
 * print renderer) can't match TheUnframer's smooth SVG balloon shapes
 * pixel-for-pixel — Pete chose to drop the feature instead of shipping
 * an approximation.
 *
 * Text groups and the logo are each positioned as absolutely-positioned
 * elements inside .rrt-lc-zone, left/top % + `transform:
 * translate(-50%,-50%)`, same technique TheUnframer's own quote-module.js
 * uses. Font-size/logo-scale are stored as "percent of the zone's own
 * width" (matches quote-module.js's `fsPx = fw * fontSize / 100`), so
 * they scale correctly if the zone's rendered size ever changes.
 *
 * The server never trusts this preview directly — on submit, every text
 * group + the logo are sent as a `layers` JSON array (same contract
 * inc/handle-submission.php already expects) and GD re-composites the
 * authoritative final image from those numbers.
 */
( function () {
	'use strict';

	var config = window.rrtLabelConfigurator || { sizes: {}, fonts: {}, art: {}, ajaxUrl: '' };

	var root = document.getElementById( 'rrt-label-configurator' );
	if ( ! root ) {
		return;
	}

	// Font key -> CSS family, matching the radio-card `value`s in the
	// copied font-drum exactly (also matches the Google Fonts families
	// this plugin already enqueues).
	var TEXT_FONTS = {
		oswald: "'Oswald', sans-serif",
		luckiest: "'Luckiest Guy', cursive",
		comic: "'Boogaloo', cursive",
		satisfy: "'Satisfy', cursive",
		lobster: "'Lobster', cursive",
		bangers: "'Bangers', cursive",
		fredoka: "'Fredoka One', cursive",
		montserrat: "'Montserrat', sans-serif",
		abril: "'Abril Fatface', cursive",
		greatvibes: "'Great Vibes', cursive",
		sniglet: "'Sniglet', cursive",
		arial: "Arial, Helvetica, sans-serif",
		playfair: "'Playfair Display', serif",
		scripty: "'Dancing Script', cursive",
		pacifico: "'Pacifico', cursive",
	};

	// The 5 fixed text-color swatches (toffee-appropriate: brand red, gold,
	// brown, cream, slate) — see inc/quote-module-card.part.php for why
	// .color-row/.color-swatch themselves are the real site's own
	// component, just a new palette.
	var TEXT_COLOR_SWATCHES = {
		red: '#991e21',
		gold: '#c89b3c',
		brown: '#5c3a21',
		cream: '#fdf6e3',
		slate: '#708090',
	};

	var els = {
		size: document.getElementById( 'rrt-lc-size' ),
		stage: document.getElementById( 'rrt-lc-stage' ),
		stageInner: document.getElementById( 'rrt-lc-stage-inner' ),
		bg: document.getElementById( 'rrt-lc-bg' ),
		zone: document.getElementById( 'rrt-lc-zone' ),

		// 3D box mockup (see computeBoxFaces()/mirrorZoneToBox() below).
		boxScene: document.getElementById( 'rrt-lc-box-scene' ),
		boxTopStrip: document.getElementById( 'rrt-lc-box-top-strip' ),
		boxTopImg: document.getElementById( 'rrt-lc-box-top-img' ),
		boxFrontStrip: document.getElementById( 'rrt-lc-box-front-strip' ),
		boxFrontImg: document.getElementById( 'rrt-lc-box-front-img' ),
		zoneMirror: document.getElementById( 'rrt-lc-zone-mirror' ),
		zoneMirrorInner: document.getElementById( 'rrt-lc-zone-mirror-inner' ),

		tabBtnQuote: document.getElementById( 'tab-btn-quote' ),
		tabBtnLogo: document.getElementById( 'tab-btn-logo' ),
		panelQuote: document.getElementById( 'tab-panel-quote' ),
		panelLogo: document.getElementById( 'tab-panel-logo' ),

		quoteSelectorBtns: document.getElementById( 'quote-selector-btns' ),
		quoteAddBtn: document.getElementById( 'quote-add-btn' ),
		quoteRemoveBtn: document.getElementById( 'quote-remove-btn' ),
		quoteText: document.getElementById( 'quote-text' ),
		btnBold: document.getElementById( 'btn-bold' ),
		btnItalic: document.getElementById( 'btn-italic' ),
		fsSlider: document.getElementById( 'fs-slider' ),
		boxSideMargin: document.getElementById( 'box-side-margin' ),
		shadowColorRow: document.getElementById( 'shadow-color-row' ),
		textColorSwatches: document.getElementById( 'text-color-swatches' ),
		textColorCustom: document.getElementById( 'text-color-custom' ),
		shadowIntensitySlider: document.getElementById( 'shadow-intensity-slider' ),
		centerTextBtn: document.getElementById( 'center-text-btn' ),
		fontRadios: document.querySelectorAll( 'input[name="quote-font"]' ),

		logoFileInput: document.getElementById( 'logo-file-input' ),
		artPicker: document.getElementById( 'art-picker' ),
		artPickerBtn: document.getElementById( 'art-picker-btn' ),
		artPickerPanel: document.getElementById( 'art-picker-panel' ),
		logoUploadBtn: document.getElementById( 'logo-upload-btn' ),
		logoAddBtn: document.getElementById( 'logo-add-btn' ),
		logoRemoveBtn: document.getElementById( 'logo-remove-btn' ),
		logoSizeSlider: document.getElementById( 'logo-size-slider' ),
		logoSelectorBtns: document.getElementById( 'logo-selector-btns' ),
		logoFilename: document.getElementById( 'logo-filename' ),

		name: document.getElementById( 'rrt-lc-name' ),
		email: document.getElementById( 'rrt-lc-email' ),
		phone: document.getElementById( 'rrt-lc-phone' ),
		quantity: document.getElementById( 'rrt-lc-quantity' ),
		message: document.getElementById( 'rrt-lc-message' ),
		submit: document.getElementById( 'rrt-lc-submit' ),
		status: document.getElementById( 'rrt-lc-status' ),
	};

	var state = {
		sizeKey: null,
		textGroups: [], // { id, text, font, fontSize, bold, italic, shadowColor, pos, el }
		activeGroupId: null,
		nextGroupId: 1,
		logos: [], // { id, file, scale, pos, el }
		activeLogoId: null,
		nextLogoId: 1,
		addingNewLogo: false, // true while waiting on the file picker for a brand-new logo (vs. replacing the active one)
		dragging: null,
	};

	function activeSize() {
		return config.sizes[ state.sizeKey ];
	}

	// --- size switching (unchanged from before, still the "right" part) ---

	els.size.addEventListener( 'change', function () {
		loadSize( els.size.value );
	} );

	// Pete, 2026-10-18: "min of 2 for the 8 oz, and min of 6 for the 4 oz."
	// Same numbers as rrt_label_min_qty_for_size() server-side (inc/
	// handle-submission.php) — this just keeps the quantity field's own
	// min/default in sync with that server-side floor, matching instead of
	// contradicting it.
	var MIN_QTY = { '8oz': 2, '4oz': 6 };

	function loadSize( sizeKey ) {
		var size = config.sizes[ sizeKey ];
		if ( ! size ) {
			return;
		}
		state.sizeKey = sizeKey;

		var minQty = MIN_QTY[ sizeKey ] || 1;
		els.quantity.min = minQty;
		if ( ! els.quantity.value || parseInt( els.quantity.value, 10 ) < minQty ) {
			els.quantity.value = minQty;
		}

		// Pete, 2026-10-19: "the previous window... loads with the same
		// size as before" — .rrt-lc-stage (the VISIBLE box) sizes to
		// visible_crop_height, the original mobile-safe height.
		// .rrt-lc-stage-inner (the actual scrollable content, reaching the
		// mountain logo) sizes to the taller crop_height, which is what
		// makes the stage scroll at all — see rrt_label_display_geometry()
		// in rrt-label-configurator.php.
		els.stage.style.aspectRatio = size.crop_width + ' / ' + size.visible_crop_height;
		els.stageInner.style.aspectRatio = size.crop_width + ' / ' + size.crop_height;
		els.bg.src = size.template_url;
		els.bg.style.width = size.bg_width_pct + '%';
		els.bg.style.transform = 'translate(-' + size.bg_translate_x + '%, -' + size.bg_translate_y + '%)';

		els.zone.style.left = size.zone_left_pct + '%';
		els.zone.style.top = size.zone_top_pct + '%';
		els.zone.style.width = size.zone_width_pct + '%';
		els.zone.style.height = size.zone_height_pct + '%';

		computeBoxFaces( size );

		state.textGroups.forEach( renderTextGroup );
		state.logos.forEach( renderLogo );

		// Pete, 2026-10-21: "see the top portion of the logo first [on
		// load], and the user can slide up to reveal the rest of the
		// canvas dotted line area" — start scrolled most of the way down
		// the full crop (rrt_label_crop_px() now extends that all the way
		// to the image's bottom edge), showing the mountain logo. Then,
		// same day: "have it start/load less toward the bottom of the
		// label" — landing at the absolute max scroll felt too far down;
		// size.initial_scroll_pct (a % of the real max scroll distance,
		// not total content height) pulls it back up slightly. Read against
		// the actual rendered scrollHeight/clientHeight here (not a
		// PHP-predicted pixel value), so it lines up regardless of which
		// viewport height this device ends up rendering (desktop
		// aspect-ratio or the mobile 50vh cap — see label-configurator.css).
		requestAnimationFrame( function () {
			var maxScroll = els.stage.scrollHeight - els.stage.clientHeight;
			els.stage.scrollTop = ( size.initial_scroll_pct / 100 ) * maxScroll;
		} );
	}

	function positionEl( el, pos ) {
		el.style.left = pos.x + '%';
		el.style.top = pos.y + '%';
	}

	// --- 3D box mockup ---
	//
	// Pete, 2026-10-25: a visual helper showing the live design wrapped
	// onto the actual glossy-white box, viewed from one fixed angle (30°
	// up, 30° left — see --box-tilt/--box-turn in label-configurator.css).
	// Not a canvas/WebGL thing — plain CSS 3D transforms on 3 flat divs
	// (top/front/left end cap; see the CSS file for why those 3 and not
	// all 6 faces of a cube).
	//
	// The label is one continuous 11in strip wrapping the box vertically:
	// front -> top -> back -> bottom -> 1in hidden overlap (2+3+2+3+1=11,
	// confirmed against size.image_height_in and the template image
	// itself — the mountain logo sits near the "front" end, Nutrition
	// Facts near the "back/bottom" end). Only "top" (the customer's own
	// zone) and "front" (the fixed mountain-logo artwork) are ever
	// visible from this angle, so those are the only two crop windows
	// computed here.
	var BOX_PX_PER_INCH = 34; // on-screen scale for the mockup only — tune freely, doesn't touch print sizing anywhere

	function computeBoxFaces( size ) {
		if ( ! els.boxScene || ! size.box_width_in ) {
			return;
		}
		var scale = BOX_PX_PER_INCH;
		var boxW  = size.box_width_in * scale;
		var boxD  = size.box_depth_in * scale;
		var boxH  = size.box_height_in * scale;

		els.boxScene.style.setProperty( '--box-w', boxW + 'px' );
		els.boxScene.style.setProperty( '--box-d', boxD + 'px' );
		els.boxScene.style.setProperty( '--box-h', boxH + 'px' );

		// Where the wrap crosses from one panel to the next, in inches
		// from the Nutrition-Facts end of the template (the SAME end
		// size.zone_top_in is measured from) — see the big comment above
		// and rrt_label_sizes() in the PHP file for how this partition
		// (bottom/back/top/front/overlap) was confirmed.
		var topCropYIn   = size.box_depth_in + size.box_height_in;
		var frontCropYIn = 2 * size.box_depth_in + size.box_height_in;

		// The printed label strip is narrower than the box, centered —
		// bare box shows on both sides (confirmed with Pete).
		var stripW    = size.image_width_in * scale;
		var stripLeft = ( boxW - stripW ) / 2;
		var imgH      = size.image_height_in * scale;

		[ { strip: els.boxTopStrip, img: els.boxTopImg, h: boxD, cropYIn: topCropYIn },
		  { strip: els.boxFrontStrip, img: els.boxFrontImg, h: boxH, cropYIn: frontCropYIn }
		].forEach( function ( f ) {
			if ( ! f.strip || ! f.img ) {
				return;
			}
			f.strip.style.left = stripLeft + 'px';
			f.strip.style.width = stripW + 'px';
			f.strip.style.height = f.h + 'px';
			f.img.src = size.template_url;
			f.img.style.width = stripW + 'px';
			f.img.style.height = imgH + 'px';
			f.img.style.top = ( -f.cropYIn * scale ) + 'px';
		} );

		// The zone-mirror sits at the zone's real position within the
		// "top" slice — same math as PHP's zone_left/top_pct, just against
		// this crop window instead of the flat preview's.
		if ( els.zoneMirror ) {
			var zoneW = size.zone_width_in * scale;
			var zoneH = size.zone_height_in * scale;
			var zoneLeft = ( stripW - zoneW ) / 2; // zone is horizontally centered in the printed strip, same as the flat preview
			var zoneTop  = ( size.zone_top_in - topCropYIn ) * scale;
			els.zoneMirror.style.left = zoneLeft + 'px';
			els.zoneMirror.style.top = zoneTop + 'px';
			els.zoneMirror.style.width = zoneW + 'px';
			els.zoneMirror.style.height = zoneH + 'px';
		}

		syncBoxMirror();
	}

	// The real zone's text font-size is stored in absolute px (sized off
	// #rrt-lc-zone's own rendered width — see updateTextGroupFontSize()),
	// so a bare innerHTML clone would render oversized/undersized in the
	// mirror's smaller box. Fix: size the clone's wrapper to match the
	// REAL zone's current px dimensions exactly (so every % and px style
	// inside resolves identically), then CSS-scale the whole wrapper down
	// to fit the mirror's actual allotted space.
	var mirrorSyncScheduled = false;
	function syncBoxMirror() {
		if ( ! els.zoneMirrorInner || ! els.zoneMirror || ! els.zone ) {
			return;
		}
		var real = els.zone.getBoundingClientRect();
		if ( real.width <= 0 || real.height <= 0 ) {
			return;
		}
		els.zoneMirrorInner.style.width = real.width + 'px';
		els.zoneMirrorInner.style.height = real.height + 'px';
		var mirrorW = els.zoneMirror.getBoundingClientRect().width;
		var scale = mirrorW > 0 ? mirrorW / real.width : 1;
		els.zoneMirrorInner.style.transform = 'scale(' + scale + ')';
		els.zoneMirrorInner.innerHTML = els.zone.innerHTML;
	}
	function scheduleMirrorSync() {
		if ( mirrorSyncScheduled ) {
			return;
		}
		mirrorSyncScheduled = true;
		requestAnimationFrame( function () {
			mirrorSyncScheduled = false;
			syncBoxMirror();
		} );
	}
	// Catches every way the zone's contents can change — render calls,
	// drag (which moves layers via direct style writes, not a render
	// function), add/remove — without needing to hook each one by hand.
	if ( els.zone && window.MutationObserver ) {
		new MutationObserver( scheduleMirrorSync ).observe( els.zone, {
			childList: true,
			subtree: true,
			attributes: true,
			attributeFilter: [ 'style', 'class' ],
		} );
	}

	// --- tabs ---

	function selectTab( tab ) {
		var map = {
			quote: { btn: els.tabBtnQuote, panel: els.panelQuote },
			logo: { btn: els.tabBtnLogo, panel: els.panelLogo },
		};
		Object.keys( map ).forEach( function ( key ) {
			var active = key === tab;
			map[ key ].btn.classList.toggle( 'active', active );
			map[ key ].panel.classList.toggle( 'active', active );
		} );
	}

	els.tabBtnQuote.addEventListener( 'click', function () {
		selectTab( 'quote' );
	} );
	els.tabBtnLogo.addEventListener( 'click', function () {
		selectTab( 'logo' );
	} );

	// --- text groups ---

	function activeGroup() {
		return state.textGroups.filter( function ( g ) {
			return g.id === state.activeGroupId;
		} )[ 0 ] || null;
	}

	function addTextGroup() {
		var group = {
			id: state.nextGroupId++,
			text: '',
			font: 'oswald',
			fontSize: 5,
			bold: false,
			italic: false,
			shadowColor: 'white', // WHITE TYPE (default): white text, black outline. BLACK TYPE: inverted — see renderTextGroup().
			textColor: null, // null = use shadowColor's white/black preset. A hex string (palette swatch or custom picker) overrides it — see renderTextGroup().
			shadowIntensity: 60, // Pete, 2026-10-17: "shadow intensity adjustment slider" — 0-100, replaces the old fixed full/subtle auto-switch with a continuous dial the customer controls directly — see outlineShadow().
			sideMargin: 2, // matches the real site's defaultQuote() — maxWidth = 100-2*this
			pos: defaultPos( state.textGroups.length ),
		};

		var div = document.createElement( 'div' );
		div.className = 'preview-text-layer style-dropshadow';
		els.zone.appendChild( div );
		group.el = div;

		state.textGroups.push( group );
		state.activeGroupId = group.id;
		attachDrag( div, group, 'text' );
		renderTextGroup( group );
		renderSelectorPills();
		loadGroupIntoControls( group );
	}

	function removeActiveGroup() {
		var group = activeGroup();
		if ( ! group ) {
			return;
		}
		group.el.remove();
		state.textGroups = state.textGroups.filter( function ( g ) {
			return g.id !== group.id;
		} );
		state.activeGroupId = state.textGroups.length ? state.textGroups[ state.textGroups.length - 1 ].id : null;
		renderSelectorPills();
		var next = activeGroup();
		if ( next ) {
			loadGroupIntoControls( next );
		} else {
			els.quoteText.value = '';
			updateCenterBtn();
		}
	}

	// The very first logo/text added starts dead center — only the 2nd,
	// 3rd, etc. get nudged apart so they don't land exactly on top of
	// each other (and, for text, so it doesn't land pre-flagged as
	// "off center" by updateCenterBtn() the moment it's created).
	function defaultPos( existingCount ) {
		if ( 0 === existingCount ) {
			return { x: 50, y: 50 };
		}
		var nudge = ( existingCount % 4 ) * 6 - 9;
		return { x: 50 + nudge, y: 50 + nudge };
	}

	function renderSelectorPills() {
		els.quoteSelectorBtns.innerHTML = '';
		state.textGroups.forEach( function ( group, i ) {
			var pill = document.createElement( 'button' );
			pill.type = 'button';
			pill.className = 'toggle-btn' + ( group.id === state.activeGroupId ? ' selected' : '' );
			pill.style.width = '1.6rem';
			pill.style.fontSize = '0.65rem';
			pill.textContent = String( i + 1 );
			pill.addEventListener( 'click', function () {
				state.activeGroupId = group.id;
				renderSelectorPills();
				loadGroupIntoControls( group );
			} );
			els.quoteSelectorBtns.appendChild( pill );
		} );
	}

	function loadGroupIntoControls( group ) {
		els.quoteText.value = group.text;
		els.btnBold.classList.toggle( 'selected', group.bold );
		els.btnItalic.classList.toggle( 'selected', group.italic );
		els.fsSlider.value = group.fontSize;
		els.boxSideMargin.value = group.sideMargin;
		els.fontRadios.forEach( function ( r ) {
			r.checked = r.value === group.font;
		} );
		// WHITE TYPE/BLACK TYPE and the palette/custom picker are mutually
		// exclusive — only one highlighted "selected" state at a time,
		// matching whichever one actually controls group.el's color
		// right now (see the textColor branch in renderTextGroup()).
		els.shadowColorRow.querySelectorAll( '.toggle-btn' ).forEach( function ( btn ) {
			btn.classList.toggle( 'selected', ! group.textColor && btn.dataset.shadowColor === group.shadowColor );
		} );
		els.textColorSwatches.querySelectorAll( '.color-swatch' ).forEach( function ( swatch ) {
			swatch.classList.toggle( 'selected', TEXT_COLOR_SWATCHES[ swatch.dataset.color ] === group.textColor );
		} );
		els.textColorCustom.classList.toggle( 'selected', !! group.textColor && ! isPaletteColor( group.textColor ) );
		if ( group.textColor ) {
			els.textColorCustom.value = group.textColor;
		}
		els.shadowIntensitySlider.value = group.shadowIntensity;
		updateCenterBtn();
	}

	function isPaletteColor( hex ) {
		return Object.keys( TEXT_COLOR_SWATCHES ).some( function ( key ) {
			return TEXT_COLOR_SWATCHES[ key ] === hex;
		} );
	}

	// Ported from the real site's updateCenterBtn()/"Center text button"
	// handler — shows a "TEXT OFF CENTER — TAP TO RECENTER" alarm button
	// whenever the active text group has drifted off horizontal center,
	// clicking it snaps x back to 50%. Hidden whenever nothing is
	// off-center (or there's no active group at all).
	function updateCenterBtn() {
		var group = activeGroup();
		if ( ! group || Math.abs( group.pos.x - 50 ) <= 0.1 ) {
			els.centerTextBtn.style.display = 'none';
			return;
		}
		els.centerTextBtn.style.display = '';
	}

	els.centerTextBtn.addEventListener( 'click', function () {
		var group = activeGroup();
		if ( ! group ) {
			return;
		}
		group.pos.x = 50;
		renderTextGroup( group );
		updateCenterBtn();
	} );

	// Ported from TheUnframer's own renderLayerOnEl() (quote-module.js) —
	// text layers are positioned/sized differently from the logo:
	// `width: max-content` (deterministic shrink-to-fit, which is also
	// what fixed the box seeming to "change shape" — it had no explicit
	// width before) and `left` + `bottom` + `transform: translateX(-50%)`
	// only (no translateY — Y is anchored by the box's BOTTOM edge, not
	// centered), so the box grows upward from a fixed point as text wraps
	// instead of growing from a shifting center. max-width is driven by
	// the TEXT WIDTH slider (sideMargin), not a fixed CSS percentage —
	// `100 - 2*sideMargin`, clamped to a 20% floor, same formula as the
	// real site.
	function renderTextGroup( group ) {
		group.el.textContent = group.text;
		// Always the plain "dropshadow" style (text + outline, no
		// background box) — WHITE TYPE/BLACK TYPE is NOT a different
		// placement style, it's the real site's shadowColor toggle WITHIN
		// dropshadow: white (default CSS) = white text/black outline,
		// black = inline-overridden to near-black text/white outline. The
		// 'blackbox' class is a literal solid-black background rectangle,
		// a completely different thing — wiring BLACK TYPE to it was the
		// bug ("white letters in a black box" instead of black lettering).
		group.el.className = 'preview-text-layer style-dropshadow';
		// resolveTextColor() picks whichever is in control — the custom
		// palette/picker (group.textColor) if set, else the WHITE
		// TYPE/BLACK TYPE preset — and the matching outline for contrast.
		var resolvedColor = resolveTextColor( group );
		group.el.style.color = resolvedColor.color;
		// "Shadow" checkbox + intensity slider — on by default at a
		// middle intensity, unchecks to turn it off entirely.
		// Pete, 2026-10-21: "make the line look exactly like the line on
		// theunframer upload configurator" — dropped the separate Shadow
		// on/off checkbox (matches Unframer's current version of this row,
		// which never had one); shadowIntensity 0 already reads as fully
		// invisible per outlineShadow()'s own math, so it doubles as "off".
		group.el.style.textShadow = group.shadowIntensity > 0 ? outlineShadow( group.shadowIntensity ) : 'none';
		group.el.style.display = group.text ? 'block' : 'none';
		group.el.style.fontFamily = TEXT_FONTS[ group.font ] || 'sans-serif';
		group.el.style.fontWeight = group.bold ? '700' : '400';
		group.el.style.fontStyle = group.italic ? 'italic' : 'normal';
		group.el.style.whiteSpace = group.text.indexOf( '\n' ) !== -1 ? 'pre-wrap' : 'normal';
		group.el.style.width = 'max-content';
		group.el.style.maxWidth = Math.max( 20, 100 - 2 * group.sideMargin ) + '%';
		updateTextGroupFontSize( group ); // sets font-size — affects the box's rendered width/height, measured below
		clampGroupToZone( group );

		group.el.style.left = group.pos.x + '%';
		group.el.style.bottom = ( 100 - group.pos.y ) + '%';
		group.el.style.top = 'auto';
		group.el.style.transform = 'translateX(-50%)';
	}

	// Hard wall, same as the drag clamp, but triggered by size changes
	// that aren't a drag at all — typing more text, a bigger font, or a
	// wider TEXT WIDTH setting all grow the box, and since it's
	// bottom-anchored (see renderTextGroup()'s comment), a taller box
	// pushes its TOP edge upward. Without this, going from one line to
	// two could push the box above the zone entirely while its pos.y
	// never changed. MUST be called after ANY style change that could
	// resize the box (content, font, width) — not just from
	// renderTextGroup() itself, since the font-size slider used to call
	// updateTextGroupFontSize() directly and skip this entirely.
	function clampGroupToZone( group ) {
		if ( group.text ) {
			var zoneRect = els.zone.getBoundingClientRect();
			// Width is correct already (max-content respects maxWidth), but
			// height only reflects the real wrapped line count once the
			// element is actually in final layout — safe to measure now
			// since font/width/content are already applied above.
			var elRect = group.el.getBoundingClientRect();
			var halfWPct = ( elRect.width / zoneRect.width ) * 50;
			var heightPct = ( elRect.height / zoneRect.height ) * 100;
			// When the box itself is wider/taller than the zone (e.g. a
			// big font wrapped to 2 lines), halfWPct can exceed 50 and
			// heightPct can exceed 100 — there's no position left that
			// fully contains the box. clamp(value, min, max) with
			// min > max returns min unconditionally (Math.max(min, ...)
			// always wins), which previously sent pos.y to a wild
			// out-of-range value like 202%. Capping the bounds themselves
			// to a sane [0,100] window first pins the box to the nearest
			// sensible edge (centered / flush-bottom) instead.
			var minX = Math.min( halfWPct, 50 );
			var maxX = Math.max( 100 - halfWPct, 50 );
			var minY = Math.min( heightPct, 100 );
			group.pos.x = clamp( group.pos.x, minX, maxX );
			group.pos.y = clamp( group.pos.y, minY, 100 );
		}

		group.el.style.left = group.pos.x + '%';
		group.el.style.bottom = ( 100 - group.pos.y ) + '%';
		group.el.style.top = 'auto';
		group.el.style.transform = 'translateX(-50%)';
	}

	function updateTextGroupFontSize( group ) {
		var zoneRect = els.zone.getBoundingClientRect();
		var px = Math.max( 8, zoneRect.width * ( group.fontSize / 100 ) );
		group.el.style.fontSize = px + 'px';
	}

	els.quoteAddBtn.addEventListener( 'click', addTextGroup );
	els.quoteRemoveBtn.addEventListener( 'click', removeActiveGroup );

	els.quoteText.addEventListener( 'input', function () {
		// The textarea's value at the moment this fires already includes
		// the character just typed — but if this is the FIRST character
		// (no group exists yet), addTextGroup() -> loadGroupIntoControls()
		// resets els.quoteText.value back to the new (empty) group's text,
		// wiping that character out from under the user. Capture it first.
		var typedValue = els.quoteText.value;
		var group = activeGroup();
		if ( ! group ) {
			addTextGroup();
			group = activeGroup();
		}
		group.text = typedValue;
		els.quoteText.value = typedValue;
		renderTextGroup( group );
	} );

	els.btnBold.addEventListener( 'click', function () {
		var group = activeGroup();
		if ( ! group ) {
			return;
		}
		group.bold = ! group.bold;
		els.btnBold.classList.toggle( 'selected', group.bold );
		renderTextGroup( group );
	} );

	els.btnItalic.addEventListener( 'click', function () {
		var group = activeGroup();
		if ( ! group ) {
			return;
		}
		group.italic = ! group.italic;
		els.btnItalic.classList.toggle( 'selected', group.italic );
		renderTextGroup( group );
	} );

	els.fsSlider.addEventListener( 'input', function () {
		var group = activeGroup();
		if ( ! group ) {
			return;
		}
		group.fontSize = parseFloat( els.fsSlider.value );
		// updateTextGroupFontSize() only sets font-size — it never
		// touches position, so the hard-wall clamp (and the resulting
		// left/bottom re-apply) has to be driven explicitly here, same
		// as renderTextGroup() does for every other style change. This
		// is the fix for text popping outside the canvas when enlarged
		// to the point of wrapping to a second line.
		updateTextGroupFontSize( group );
		clampGroupToZone( group );
	} );

	els.boxSideMargin.addEventListener( 'input', function () {
		var group = activeGroup();
		if ( ! group ) {
			return;
		}
		group.sideMargin = parseFloat( els.boxSideMargin.value );
		renderTextGroup( group );
	} );

	els.fontRadios.forEach( function ( radio ) {
		radio.addEventListener( 'change', function () {
			var group = activeGroup();
			if ( ! group || ! radio.checked ) {
				return;
			}
			group.font = radio.value;
			renderTextGroup( group );
		} );
	} );

	els.shadowColorRow.querySelectorAll( '.toggle-btn' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var group = activeGroup();
			if ( ! group ) {
				return;
			}
			group.shadowColor = btn.dataset.shadowColor;
			group.textColor = null; // WHITE/BLACK TYPE and the custom palette are mutually exclusive.
			loadGroupIntoControls( group );
			renderTextGroup( group );
		} );
	} );

	// Pete, 2026-10-17: "there's room next to black type... let's add a 5
	// color pallette for text color" + "make it adjustable, slide into
	// your color" — the 5 fixed swatches and the native color picker both
	// just set group.textColor to a hex string; renderTextGroup() picks a
	// readable outline for whatever color lands there.
	els.textColorSwatches.querySelectorAll( '.color-swatch' ).forEach( function ( swatch ) {
		swatch.addEventListener( 'click', function () {
			var group = activeGroup();
			if ( ! group ) {
				return;
			}
			group.textColor = TEXT_COLOR_SWATCHES[ swatch.dataset.color ] || null;
			loadGroupIntoControls( group );
			renderTextGroup( group );
		} );
	} );

	els.textColorCustom.addEventListener( 'input', function () {
		var group = activeGroup();
		if ( ! group ) {
			return;
		}
		group.textColor = els.textColorCustom.value;
		loadGroupIntoControls( group );
		renderTextGroup( group );
	} );

	els.shadowIntensitySlider.addEventListener( 'input', function () {
		var group = activeGroup();
		if ( ! group ) {
			return;
		}
		group.shadowIntensity = parseFloat( els.shadowIntensitySlider.value );
		renderTextGroup( group );
	} );

	// Keep font sizes correct as the stage's rendered size changes.
	if ( window.ResizeObserver ) {
		new ResizeObserver( function () {
			state.textGroups.forEach( updateTextGroupFontSize );
		} ).observe( els.zone );
	}

	// --- slider step buttons (the small − / + next to every slider in the
	// copied markup — logo size, font size, text width, etc.) — generic,
	// not tied to any one slider: reads data-for/data-dir, nudges that
	// slider's value by its own step, and fires a real 'input' event so
	// whichever listener already owns that slider (e.g. logoSizeSlider's
	// or fsSlider's above) runs exactly as if the user had dragged it. */
	document.querySelectorAll( '.slider-step-btn' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var slider = document.getElementById( btn.dataset.for );
			if ( ! slider ) {
				return;
			}
			var step = parseFloat( slider.step ) || 1;
			var dir = parseFloat( btn.dataset.dir ) || 1;
			var min = parseFloat( slider.min );
			var max = parseFloat( slider.max );
			var value = parseFloat( slider.value ) + dir * step;
			slider.value = Math.max( min, Math.min( max, value ) );
			slider.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		} );
	} );

	// --- slider fill indicator (.style-slider's colored track is driven
	// by a --fill CSS custom property, not the native thumb position —
	// the copied CSS defaults it to a fixed 20% and nothing ever updated
	// it, so the red fill looked frozen no matter where the thumb/value
	// actually was. Generic: applies to every .style-slider, updates on
	// both real drags and the synthetic 'input' events the step buttons
	// above dispatch. */
	function updateSliderFill( slider ) {
		var min = parseFloat( slider.min ) || 0;
		var max = parseFloat( slider.max ) || 100;
		var pct = ( ( parseFloat( slider.value ) - min ) / ( max - min ) ) * 100;
		slider.style.setProperty( '--fill', pct + '%' );
	}

	document.querySelectorAll( '.style-slider' ).forEach( function ( slider ) {
		updateSliderFill( slider );
		slider.addEventListener( 'input', function () {
			updateSliderFill( slider );
		} );
	} );

	// --- font drum (ported from the real site's quote-module.js — the
	// markup was copied over with the down-arrow ".font-drum-rotator"
	// button already in it, but nothing ever wired it or the drum's own
	// wheel/swipe scroll, so the arrow did nothing ("broken"). Pure
	// scroll — never changes which font is selected, just moves the
	// visible list by one row and wraps at the end. ---
	document.querySelectorAll( '.font-drum' ).forEach( function ( drum ) {
		drum.addEventListener( 'wheel', function ( evt ) {
			evt.preventDefault();
			var card = drum.querySelector( '.radio-card' );
			var itemH = card ? card.offsetHeight : 40;
			var down = evt.deltaY > 0;
			var atBot = drum.scrollTop + drum.clientHeight >= drum.scrollHeight - 4;
			var atTop = drum.scrollTop <= 4;
			if ( down && atBot ) {
				drum.scrollTo( { top: 0, behavior: 'smooth' } );
			} else if ( ! down && atTop ) {
				drum.scrollTo( { top: drum.scrollHeight, behavior: 'smooth' } );
			} else {
				drum.scrollBy( { top: down ? itemH : -itemH, behavior: 'smooth' } );
			}
		}, { passive: false } );

		var touchStartX = 0;
		drum.addEventListener( 'touchstart', function ( evt ) {
			touchStartX = evt.touches[ 0 ].clientX;
		}, { passive: true } );
		drum.addEventListener( 'touchend', function ( evt ) {
			var dx = touchStartX - evt.changedTouches[ 0 ].clientX;
			if ( Math.abs( dx ) < 10 ) {
				return;
			}
			setTimeout( function () {
				var maxScroll = drum.scrollWidth - drum.clientWidth;
				if ( dx > 0 && drum.scrollLeft >= maxScroll - 4 ) {
					drum.scrollTo( { left: 0, behavior: 'smooth' } );
				} else if ( dx < 0 && drum.scrollLeft <= 4 ) {
					drum.scrollTo( { left: maxScroll, behavior: 'smooth' } );
				}
			}, 300 );
		}, { passive: true } );
	} );

	document.querySelectorAll( '.font-drum-rotator' ).forEach( function ( btn ) {
		var row = btn.closest( '.font-drum-row' );
		var drum = row ? row.querySelector( '.font-drum' ) : null;
		if ( ! drum ) {
			return;
		}
		btn.addEventListener( 'click', function () {
			var card = drum.querySelector( '.radio-card' );
			var itemH = card ? card.offsetHeight : 40;
			var atBot = drum.scrollTop + drum.clientHeight >= drum.scrollHeight - 4;
			if ( atBot ) {
				drum.scrollTo( { top: 0, behavior: 'smooth' } );
			} else {
				drum.scrollBy( { top: itemH, behavior: 'smooth' } );
			}
		} );
	} );

	// --- logo (same "multiple instances, pick which one you're editing"
	// pattern as text groups — CHOOSE FILE replaces the ACTIVE logo's
	// image, +Add starts a brand-new one, matching #logo-add-btn/
	// #logo-selector-btns already in the copied markup, which weren't
	// wired on the first pass — that's why "choose file" clobbered the
	// one logo you already had instead of adding another.) ---

	function activeLogo() {
		return state.logos.filter( function ( l ) {
			return l.id === state.activeLogoId;
		} )[ 0 ] || null;
	}

	// A logo is either a real customer upload (logo.file, a File object) or
	// one of the bundled ART pieces (logo.artKey/artLabel, no File at all)
	// — this reads whichever applies instead of assuming .file exists.
	function logoDisplayName( logo ) {
		return logo.artKey ? logo.artLabel : ( logo.file ? logo.file.name : '' );
	}

	// Pete, 2026-10-17: "a library of holiday/birthday/celebration art
	// icons we can use to click like adding logos... a drop down box on
	// the add logo line simply a button that says ART" — picking one adds
	// it as a brand-new logo-type layer, same drag/resize/stacking as a
	// real uploaded logo, just sourced from this plugin's own bundled PNG
	// (config.art) instead of an uploaded file. Always adds a new one
	// (never replaces the active logo) — simplest, unambiguous behavior,
	// matching "+ Add" rather than "CHOOSE FILE".
	function addArtLogo( key ) {
		var art = config.art && config.art[ key ];
		if ( ! art ) {
			return;
		}
		var img = document.createElement( 'img' );
		img.className = 'rrt-lc-logo-layer';
		img.alt = '';
		img.src = art.url;
		els.zone.appendChild( img );
		var logo = {
			id: state.nextLogoId++,
			file: null,
			artKey: key,
			artLabel: art.label,
			scale: parseFloat( els.logoSizeSlider.value ),
			pos: defaultPos( state.logos.length ),
			el: img,
		};
		state.logos.push( logo );
		state.activeLogoId = logo.id;
		attachDrag( img, logo, 'logo' );
		renderLogoSelectorPills();
		els.logoFilename.textContent = logoDisplayName( logo );
		renderLogo( logo );
	}

	// A native <select>'s <option> can only ever show plain text — no
	// browser lets you put an image in one — so "see the art, not a
	// description" rules that out entirely. This is a small custom
	// popover instead: the ART button toggles a grid of real thumbnails
	// (rendered server-side in quote-module-card.part.php), clicking one
	// adds it and closes the panel.
	// Pete, 2026-10-17: "can't look at art" / "circle with arrows... page
	// goes up and down" — #logo-selector-row has overflow-y:hidden (a
	// fix for an earlier, unrelated stray-scrollbar bug), which CLIPS any
	// absolutely-positioned child that pops outside the row's own height
	// — exactly what this panel needs to do, making it invisible, and
	// its phantom off-screen footprint was dragging the row's own
	// scrollable area around under the cursor. Fixed the standard way a
	// popover escapes a clipped/scrolling ancestor: move it to be a
	// direct child of <body> (position:fixed in its own CSS), positioned
	// under the button fresh every time it opens.
	document.body.appendChild( els.artPickerPanel );

	function positionArtPanel() {
		var rect = els.artPickerBtn.getBoundingClientRect();
		els.artPickerPanel.style.left = rect.left + 'px';
		els.artPickerPanel.style.top = ( rect.bottom + 4 ) + 'px';
	}

	els.artPickerBtn.addEventListener( 'click', function ( evt ) {
		evt.stopPropagation();
		var isOpen = els.artPickerPanel.style.display !== 'none';
		if ( isOpen ) {
			els.artPickerPanel.style.display = 'none';
		} else {
			positionArtPanel();
			els.artPickerPanel.style.display = 'grid';
		}
	} );

	els.artPickerPanel.querySelectorAll( '.art-picker-item' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function ( evt ) {
			evt.stopPropagation();
			addArtLogo( btn.dataset.artKey );
			els.artPickerPanel.style.display = 'none';
		} );
	} );

	// Click anywhere outside the panel (or its own toggle button) closes it.
	document.addEventListener( 'click', function ( evt ) {
		if ( els.artPickerPanel.style.display === 'none' ) {
			return;
		}
		if ( ! els.artPicker.contains( evt.target ) && ! els.artPickerPanel.contains( evt.target ) ) {
			els.artPickerPanel.style.display = 'none';
		}
	} );

	els.logoUploadBtn.addEventListener( 'click', function () {
		state.addingNewLogo = false;
		els.logoFileInput.value = '';
		els.logoFileInput.click();
	} );

	els.logoAddBtn.addEventListener( 'click', function () {
		state.addingNewLogo = true;
		els.logoFileInput.value = '';
		els.logoFileInput.click();
	} );

	els.logoFileInput.addEventListener( 'change', function () {
		var file = els.logoFileInput.files[ 0 ];
		if ( ! file || ! /^image\/(png|jpe?g)$/.test( file.type ) ) {
			return;
		}
		var reader = new FileReader();
		reader.onload = function ( evt ) {
			// Pete, 2026-10-19: "I can bring in multiple art, from library,
			// but no more of my own... it'll swap my file for the new file."
			// "YOUR FILE" was reusing the active logo whenever ANY logo was
			// active, which was only ever meant to let an ART placeholder
			// get swapped for a real upload (see the artKey check below) —
			// but it also caught a second real upload, replacing the first
			// instead of adding it. Now it only reuses the active slot when
			// that slot is currently an ART piece; an active real upload (or
			// no active logo at all) always adds a new layer, matching how
			// the ART picker already behaves.
			var active = state.addingNewLogo ? null : activeLogo();
			var logo   = ( active && active.artKey ) ? active : null;
			if ( ! logo ) {
				var img = document.createElement( 'img' );
				img.className = 'rrt-lc-logo-layer';
				img.alt = '';
				els.zone.appendChild( img );
				logo = {
					id: state.nextLogoId++,
					file: file,
					scale: parseFloat( els.logoSizeSlider.value ),
					pos: defaultPos( state.logos.length ),
					el: img,
				};
				state.logos.push( logo );
				state.activeLogoId = logo.id;
				attachDrag( img, logo, 'logo' );
				renderLogoSelectorPills();
			} else {
				logo.file = file;
				logo.artKey = null; // CHOOSE FILE on an ART logo replaces it with a real upload.
				logo.artLabel = null;
			}
			state.addingNewLogo = false;
			logo.el.src = evt.target.result;
			els.logoFilename.textContent = file.name;
			renderLogo( logo );
		};
		reader.readAsDataURL( file );
	} );

	els.logoRemoveBtn.addEventListener( 'click', function () {
		var logo = activeLogo();
		if ( ! logo ) {
			return;
		}
		logo.el.remove();
		state.logos = state.logos.filter( function ( l ) {
			return l.id !== logo.id;
		} );
		state.activeLogoId = state.logos.length ? state.logos[ state.logos.length - 1 ].id : null;
		renderLogoSelectorPills();
		var next = activeLogo();
		els.logoFilename.textContent = next ? logoDisplayName( next ) : '';
		if ( next ) {
			els.logoSizeSlider.value = next.scale;
		}
	} );

	els.logoSizeSlider.addEventListener( 'input', function () {
		var logo = activeLogo();
		if ( ! logo ) {
			return;
		}
		logo.scale = parseFloat( els.logoSizeSlider.value );
		renderLogo( logo );
	} );

	function renderLogoSelectorPills() {
		els.logoSelectorBtns.innerHTML = '';
		state.logos.forEach( function ( logo, i ) {
			var pill = document.createElement( 'button' );
			pill.type = 'button';
			pill.className = 'toggle-btn' + ( logo.id === state.activeLogoId ? ' selected' : '' );
			pill.style.width = '1.6rem';
			pill.style.fontSize = '0.65rem';
			pill.textContent = String( i + 1 );
			pill.addEventListener( 'click', function () {
				state.activeLogoId = logo.id;
				renderLogoSelectorPills();
				els.logoFilename.textContent = logoDisplayName( logo );
				els.logoSizeSlider.value = logo.scale;
			} );
			els.logoSelectorBtns.appendChild( pill );
		} );
	}

	function renderLogo( logo ) {
		logo.el.style.width = logo.scale + '%';
		positionEl( logo.el, logo.pos );
	}

	// --- drag to reposition (same x/y % storage for text groups and logo,
	// but applied to the element differently per kind — see
	// renderTextGroup()'s comment for why text uses left+bottom while the
	// logo uses left+top+translate(-50%,-50%), matching the real site) ---

	function attachDrag( el, item, kind ) {
		el.addEventListener( 'pointerdown', function ( evt ) {
			evt.preventDefault();
			el.setPointerCapture( evt.pointerId );
			el.classList.add( 'rrt-dragging' );
			var zoneRect = els.zone.getBoundingClientRect();
			var elRect = el.getBoundingClientRect();
			state.dragging = {
				item: item,
				el: el,
				kind: kind,
				startPoint: { x: evt.clientX, y: evt.clientY },
				startPos: { x: item.pos.x, y: item.pos.y },
				// Element's own size as a % of the zone, captured once at
				// drag-start (size doesn't change mid-drag — only the
				// slider changes it, not the pointer) — this is what makes
				// the dashed zone edge a hard wall for the element's
				// actual edges, not just its anchor point.
				halfWPct: ( elRect.width / zoneRect.width ) * 50,
				halfHPct: ( elRect.height / zoneRect.height ) * 50,
			};
		} );
		el.addEventListener( 'pointermove', onDragMove );
		el.addEventListener( 'pointerup', endDrag );
		el.addEventListener( 'pointercancel', endDrag );
	}

	function onDragMove( evt ) {
		if ( ! state.dragging ) {
			return;
		}
		var zoneRect = els.zone.getBoundingClientRect();
		var d = state.dragging;
		var dxPct = ( ( evt.clientX - d.startPoint.x ) / zoneRect.width ) * 100;
		var dyPct = ( ( evt.clientY - d.startPoint.y ) / zoneRect.height ) * 100;
		var rawX = d.startPos.x + dxPct;
		var rawY = d.startPos.y + dyPct;

		// Hard wall: clamp so the element's own rendered edges never cross
		// the zone boundary, not just its anchor point — EXCEPT for ART,
		// where Pete confirmed the dashed zone is the right boundary but
		// "we still need more room to move art closer to the dashed
		// lines": even dragged to the absolute limit, a fully-contained
		// box can only ever get flush with the line (verified — 0px gap
		// at every size tested), never past it, so "closer" has to mean
		// letting the box actually CROSS the line. ART's anchor (center)
		// is allowed the full [0,100] zone range instead of being pulled
		// in by half its own size, so up to half the art can overlap past
		// the dashed line — logos/text are unaffected, still fully
		// contained. Logo is center-anchored on both axes; text is
		// center-anchored on X but BOTTOM-anchored on Y (see
		// renderTextGroup()'s comment), so its vertical room to move is
		// measured from the bottom edge upward. Same min>max guard as
		// clampGroupToZone() — if the box is wider/taller than the zone
		// itself, cap the bounds to a sane [0,100] window first so
		// clamp() can't return an out-of-range value.
		var isArt = !! d.item.artKey;
		var dragMinX = isArt ? 0 : Math.min( d.halfWPct, 50 );
		var dragMaxX = isArt ? 100 : Math.max( 100 - d.halfWPct, 50 );
		d.item.pos.x = clamp( rawX, dragMinX, dragMaxX );
		if ( 'text' === d.kind ) {
			var dragMinY = Math.min( d.halfHPct * 2, 100 );
			d.item.pos.y = clamp( rawY, dragMinY, 100 );
			d.el.style.left = d.item.pos.x + '%';
			d.el.style.bottom = ( 100 - d.item.pos.y ) + '%';
			updateCenterBtn();
		} else {
			var dragMinYLogo = isArt ? 0 : Math.min( d.halfHPct, 100 - d.halfHPct );
			var dragMaxYLogo = isArt ? 100 : Math.max( 100 - d.halfHPct, d.halfHPct );
			d.item.pos.y = clamp( rawY, dragMinYLogo, dragMaxYLogo );
			positionEl( d.el, d.item.pos );
		}
	}

	function endDrag() {
		if ( ! state.dragging ) {
			return;
		}
		state.dragging.el.classList.remove( 'rrt-dragging' );
		state.dragging = null;
	}

	function clamp( value, min, max ) {
		return Math.max( min, Math.min( max, value ) );
	}

	// Pete, 2026-10-20: "need to be able to slide the label in the window
	// by grabbing, not just rely on the side slider... important for
	// mobile." .rrt-lc-zone has touch-action:none (needed so dragging a
	// logo/text item doesn't fight the browser's own touch-scroll), which
	// also blocks native touch-scrolling of the stage anywhere inside the
	// zone — leaving only the thin native scrollbar, which is a poor touch
	// target. This adds an explicit grab-to-pan gesture directly on the
	// stage: dragging on any empty background (NOT on a logo/text layer,
	// which keeps its own existing attachDrag() behavior — checked via
	// state.dragging, which an item's own pointerdown sets first since it
	// fires before this bubbles up) scrolls the preview.
	var panState = null;

	els.stage.addEventListener( 'pointerdown', function ( evt ) {
		if ( state.dragging ) {
			return; // a logo/text item is already handling this pointer
		}
		evt.preventDefault();
		panState = {
			pointerId: evt.pointerId,
			startY: evt.clientY,
			startScrollTop: els.stage.scrollTop,
		};
		els.stage.setPointerCapture( evt.pointerId );
		els.stage.classList.add( 'rrt-lc-panning' );
	} );

	els.stage.addEventListener( 'pointermove', function ( evt ) {
		if ( ! panState || evt.pointerId !== panState.pointerId ) {
			return;
		}
		evt.preventDefault();
		els.stage.scrollTop = panState.startScrollTop - ( evt.clientY - panState.startY );
	} );

	function endPan( evt ) {
		if ( ! panState || ( evt && evt.pointerId !== panState.pointerId ) ) {
			return;
		}
		els.stage.classList.remove( 'rrt-lc-panning' );
		panState = null;
	}
	els.stage.addEventListener( 'pointerup', endPan );
	els.stage.addEventListener( 'pointercancel', endPan );

	// Resolves whatever is actually controlling a text group's color right
	// now — the custom palette/picker (group.textColor) if set, otherwise
	// the WHITE TYPE/BLACK TYPE preset — down to one { color, outline }
	// pair. Shared by renderTextGroup() (the live preview) AND the submit
	// handler below (so the emailed/composited label Pete reviews uses
	// the exact same color the customer saw, not GD's old hardcoded
	// near-black default).
	// Pete, 2026-10-17: "shadow check box does nothing, except remove from
	// white type" — root cause: the outline color used to be picked by
	// the TEXT's OWN luminance (light text -> dark outline, dark text ->
	// white outline), which makes sense for outline-vs-TEXT contrast, but
	// ignores what the outline needs to contrast against: the label
	// template itself, which is a plain light/white print background, not
	// a photo. A white outline is invisible there regardless of the
	// text's color, so BLACK TYPE / dark custom colors effectively never
	// showed one — the checkbox looked like it "did nothing" except for
	// WHITE TYPE. Fix: always use the dark outline recipe — it's visible
	// against this light backdrop in every case, including a harmless,
	// subtle self-outline when the text itself is already dark.
	function resolveTextColor( group ) {
		if ( group.textColor ) {
			return { color: group.textColor, outline: '#000000' };
		}
		if ( 'black' === group.shadowColor ) {
			return { color: '#1a1a1a', outline: '#000000' };
		}
		return { color: '#ffffff', outline: '#000000' };
	}

	// Pete, 2026-10-17: "black type shadow really needs to be backed way
	// off" -> "move shadow checkbox up to the add/remove line with a
	// shadow intensity adjustment slider" — the fixed full/subtle
	// auto-switch (based on how dark the text color was) is now a plain
	// continuous 0-100 dial (group.shadowIntensity) the customer drags
	// themselves: 0 is invisible, 100 matches the original full-strength
	// outline, everything between scales the same ring+blur recipe
	// proportionally.
	function outlineShadow( intensity ) {
		var amount = Math.max( 0, Math.min( 100, intensity ) ) / 100;
		var ring = ( 0.95 * amount ).toFixed( 2 );
		var nearBlur = ( 1 * amount ).toFixed( 2 );
		var farBlur = ( 0.5 * amount ).toFixed( 2 );
		return '-1px -1px 0 rgba(0,0,0,' + ring + '), 1px -1px 0 rgba(0,0,0,' + ring + '), -1px 1px 0 rgba(0,0,0,' + ring + '), 1px 1px 0 rgba(0,0,0,' + ring + '), 2px 2px 5px rgba(0,0,0,' + nearBlur + '), 0 0 12px rgba(0,0,0,' + farBlur + ')';
	}

	// --- submit ---

	function setStatus( message, type ) {
		els.status.textContent = message;
		els.status.className = 'rrt-lc-status' + ( type ? ' is-' + type : '' );
	}

	function fetchFreshNonce() {
		var body = new FormData();
		body.append( 'action', 'rrt_label_fresh_nonce' );
		return fetch( config.ajaxUrl, { method: 'POST', body: body } )
			.then( function ( r ) {
				return r.json();
			} )
			.then( function ( json ) {
				return json.success ? json.data.nonce : null;
			} );
	}

	els.submit.addEventListener( 'click', function () {
		var hasText = state.textGroups.some( function ( g ) {
			return g.text.trim();
		} );

		if ( ! els.name.value.trim() || ! els.email.value.trim() ) {
			setStatus( 'Please fill in your name and email.', 'error' );
			return;
		}
		var minQty = MIN_QTY[ state.sizeKey ] || 1;
		if ( parseInt( els.quantity.value, 10 ) < minQty ) {
			setStatus( 'Minimum order for ' + config.sizes[ state.sizeKey ].label + ' is ' + minQty + '.', 'error' );
			return;
		}
		if ( ! state.logos.length && ! hasText ) {
			setStatus( 'Please add a logo and/or some text to your label.', 'error' );
			return;
		}

		els.submit.disabled = true;
		setStatus( 'Submitting your design…' );

		fetchFreshNonce().then( function ( nonce ) {
			if ( ! nonce ) {
				setStatus( 'Something went wrong refreshing the form. Please reload the page and try again.', 'error' );
				els.submit.disabled = false;
				return;
			}

			var body = new FormData();
			body.append( 'action', 'rrt_label_submit' );
			body.append( 'nonce', nonce );
			body.append( 'size', state.sizeKey );
			body.append( 'name', els.name.value.trim() );
			body.append( 'email', els.email.value.trim() );
			body.append( 'phone', els.phone.value.trim() );
			body.append( 'quantity', els.quantity.value );
			body.append( 'message', els.message.value.trim() );

			var layersMeta = [];
			state.logos.forEach( function ( logo ) {
				// ART logos have no uploaded File at all — nothing to send
				// in logo_files[], just the art_key so the server can pull
				// its own bundled asset (see rrt_label_art_library() in
				// inc/handle-submission.php). Real uploads still go through
				// logo_files[] exactly as before.
				if ( ! logo.artKey ) {
					body.append( 'logo_files[]', logo.file );
				}
				layersMeta.push( { type: 'logo', pos: logo.pos, scale: logo.scale, art_key: logo.artKey || '' } );
			} );
			state.textGroups.forEach( function ( group ) {
				if ( group.text.trim() ) {
					var resolvedColor = resolveTextColor( group );
					layersMeta.push( {
						type: 'text',
						pos: group.pos,
						text: group.text,
						font: group.font,
						font_size: group.fontSize,
						// So the server-side composite (what Pete actually
						// reviews/approves) matches the color the customer
						// saw, instead of GD's old hardcoded near-black text.
						color: resolvedColor.color,
						outline: resolvedColor.outline,
						// No more separate on/off checkbox (see renderTextGroup()) —
						// derive it from intensity so the PHP side (still expecting
						// both fields) gets a consistent value either way.
						shadow: group.shadowIntensity > 0,
						shadow_intensity: group.shadowIntensity,
					} );
				}
			} );
			body.append( 'layers', JSON.stringify( layersMeta ) );

			fetch( config.ajaxUrl, { method: 'POST', body: body } )
				.then( function ( r ) {
					return r.json();
				} )
				.then( function ( json ) {
					els.submit.disabled = false;
					if ( json.success ) {
						setStatus( json.data.message, 'success' );
					} else {
						setStatus( json.data && json.data.message ? json.data.message : 'Something went wrong. Please try again.', 'error' );
					}
				} )
				.catch( function () {
					els.submit.disabled = false;
					setStatus( 'Something went wrong. Please try again.', 'error' );
				} );
		} );
	} );

	// --- init ---
	// No default tab/text group — Pete: "let's start with no default,
	// buttons ready to get you where you ask." Clicking a pill opens its
	// panel; typing in the text box (or the font-drum's own fallback in
	// the text-input handler above) is what actually creates the first
	// text group, same moment a real customer would expect one to exist.
	var firstSize = Object.keys( config.sizes )[ 0 ];
	if ( firstSize ) {
		loadSize( firstSize );
	}
} )();
