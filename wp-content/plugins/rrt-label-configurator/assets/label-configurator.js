/**
 * RRT Label Configurator — plain vanilla JS, no build step, no framework.
 *
 * Draws the chosen size's real label template on a <canvas>, lets the
 * customer drag/resize an uploaded logo and/or typed text inside the
 * label's blank zone, and submits the raw inputs (not a screenshot) to
 * the server, which composites the authoritative final image with GD.
 */
( function () {
	'use strict';

	var config = window.rrtLabelConfigurator || { sizes: {}, ajaxUrl: '' };

	var root = document.getElementById( 'rrt-label-configurator' );
	if ( ! root ) {
		return;
	}

	var els = {
		size: document.getElementById( 'rrt-lc-size' ),
		canvas: document.getElementById( 'rrt-lc-canvas' ),
		logoInput: document.getElementById( 'rrt-lc-logo' ),
		logoRemove: document.getElementById( 'rrt-lc-logo-remove' ),
		textInput: document.getElementById( 'rrt-lc-text' ),
		name: document.getElementById( 'rrt-lc-name' ),
		email: document.getElementById( 'rrt-lc-email' ),
		phone: document.getElementById( 'rrt-lc-phone' ),
		message: document.getElementById( 'rrt-lc-message' ),
		submit: document.getElementById( 'rrt-lc-submit' ),
		status: document.getElementById( 'rrt-lc-status' ),
	};

	var ctx = els.canvas.getContext( '2d' );

	// Current state, always in the TEMPLATE IMAGE'S OWN pixel space
	// (not the on-screen display size, which only scales for viewing).
	var state = {
		sizeKey: null,
		templateImg: null,
		logoImg: null,
		logoFile: null,
		// box = fraction (0-1) of the zone's own width/height
		logoBox: null,
		textBox: { x: 0.05, y: 0.1, w: 0.9, h: 0.8 },
		dragging: null, // { what: 'logo'|'text', mode: 'move'|'resize', startX, startY, startBox }
	};

	function activeSize() {
		return config.sizes[ state.sizeKey ];
	}

	function loadTemplate( sizeKey ) {
		var size = config.sizes[ sizeKey ];
		if ( ! size ) {
			return;
		}
		state.sizeKey = sizeKey;
		state.logoImg = null;
		state.logoFile = null;
		state.logoBox = null;
		els.logoInput.value = '';
		els.logoRemove.hidden = true;

		var img = new Image();
		img.onload = function () {
			state.templateImg = img;
			els.canvas.width = size.image_width;
			els.canvas.height = size.image_height;
			draw();
		};
		img.src = size.template_url;
	}

	function draw() {
		if ( ! state.templateImg ) {
			return;
		}
		var size = activeSize();
		ctx.clearRect( 0, 0, els.canvas.width, els.canvas.height );
		ctx.drawImage( state.templateImg, 0, 0 );

		// Dashed outline showing the customer where the drop zone is.
		ctx.save();
		ctx.strokeStyle = 'rgba(142,36,52,0.6)';
		ctx.lineWidth = Math.max( 2, size.image_width * 0.003 );
		ctx.setLineDash( [ 14, 10 ] );
		ctx.strokeRect( size.zone_x, size.zone_y, size.zone_width, size.zone_height );
		ctx.restore();

		if ( state.logoImg && state.logoBox ) {
			drawBox( state.logoBox, function ( px ) {
				ctx.drawImage( state.logoImg, px.x, px.y, px.w, px.h );
			} );
		}

		if ( els.textInput.value.trim() ) {
			drawBox( state.textBox, function ( px ) {
				drawWrappedText( els.textInput.value.trim(), px );
			} );
		}
	}

	// Converts a fractional box (relative to the zone) into absolute
	// template-image pixels, draws the content via the callback, then
	// draws a light selection outline + resize handle on top.
	function drawBox( box, drawContent ) {
		var size = activeSize();
		var px = {
			x: size.zone_x + box.x * size.zone_width,
			y: size.zone_y + box.y * size.zone_height,
			w: box.w * size.zone_width,
			h: box.h * size.zone_height,
		};
		drawContent( px );

		ctx.save();
		ctx.strokeStyle = 'rgba(0,0,0,0.35)';
		ctx.lineWidth = 2;
		ctx.setLineDash( [] );
		ctx.strokeRect( px.x, px.y, px.w, px.h );
		var handleSize = Math.max( 16, size.image_width * 0.02 );
		ctx.fillStyle = 'rgba(142,36,52,0.85)';
		ctx.fillRect( px.x + px.w - handleSize / 2, px.y + px.h - handleSize / 2, handleSize, handleSize );
		ctx.restore();
	}

	function drawWrappedText( text, px ) {
		var size = activeSize();
		var fontSize = Math.max( 14, px.h * 0.18 );
		ctx.save();
		ctx.fillStyle = '#141414';
		ctx.font = fontSize + 'px sans-serif';
		ctx.textBaseline = 'top';

		var words = text.split( ' ' );
		var lines = [];
		var line = '';
		words.forEach( function ( word ) {
			var test = line ? line + ' ' + word : word;
			if ( ctx.measureText( test ).width > px.w && line ) {
				lines.push( line );
				line = word;
			} else {
				line = test;
			}
		} );
		if ( line ) {
			lines.push( line );
		}

		lines.forEach( function ( l, i ) {
			ctx.fillText( l, px.x, px.y + i * fontSize * 1.25 );
		} );
		ctx.restore();
	}

	// --- pointer interaction: drag to move, drag the bottom-right handle to resize ---

	function canvasToImagePoint( evt ) {
		var rect = els.canvas.getBoundingClientRect();
		var scaleX = els.canvas.width / rect.width;
		var scaleY = els.canvas.height / rect.height;
		return {
			x: ( evt.clientX - rect.left ) * scaleX,
			y: ( evt.clientY - rect.top ) * scaleY,
		};
	}

	function boxPixels( box ) {
		var size = activeSize();
		return {
			x: size.zone_x + box.x * size.zone_width,
			y: size.zone_y + box.y * size.zone_height,
			w: box.w * size.zone_width,
			h: box.h * size.zone_height,
		};
	}

	function hitTest( point ) {
		var size = activeSize();
		var handleSize = Math.max( 16, size.image_width * 0.02 );
		var candidates = [];
		if ( state.logoImg && state.logoBox ) {
			candidates.push( { what: 'logo', box: state.logoBox } );
		}
		if ( els.textInput.value.trim() ) {
			candidates.push( { what: 'text', box: state.textBox } );
		}
		// Check topmost (last drawn) first.
		for ( var i = candidates.length - 1; i >= 0; i-- ) {
			var px = boxPixels( candidates[ i ].box );
			var onHandle =
				point.x >= px.x + px.w - handleSize &&
				point.x <= px.x + px.w + handleSize / 2 &&
				point.y >= px.y + px.h - handleSize &&
				point.y <= px.y + px.h + handleSize / 2;
			if ( onHandle ) {
				return { what: candidates[ i ].what, mode: 'resize' };
			}
			if ( point.x >= px.x && point.x <= px.x + px.w && point.y >= px.y && point.y <= px.y + px.h ) {
				return { what: candidates[ i ].what, mode: 'move' };
			}
		}
		return null;
	}

	els.canvas.addEventListener( 'pointerdown', function ( evt ) {
		var point = canvasToImagePoint( evt );
		var hit = hitTest( point );
		if ( ! hit ) {
			return;
		}
		els.canvas.setPointerCapture( evt.pointerId );
		els.canvas.classList.add( 'rrt-dragging' );
		var box = hit.what === 'logo' ? state.logoBox : state.textBox;
		state.dragging = {
			what: hit.what,
			mode: hit.mode,
			startPoint: point,
			startBox: { x: box.x, y: box.y, w: box.w, h: box.h },
		};
	} );

	els.canvas.addEventListener( 'pointermove', function ( evt ) {
		if ( ! state.dragging ) {
			return;
		}
		var size = activeSize();
		var point = canvasToImagePoint( evt );
		var dx = ( point.x - state.dragging.startPoint.x ) / size.zone_width;
		var dy = ( point.y - state.dragging.startPoint.y ) / size.zone_height;
		var box = state.dragging.what === 'logo' ? state.logoBox : state.textBox;
		var start = state.dragging.startBox;

		if ( state.dragging.mode === 'move' ) {
			box.x = clamp( start.x + dx, 0, 1 - start.w );
			box.y = clamp( start.y + dy, 0, 1 - start.h );
		} else {
			box.w = clamp( start.w + dx, 0.05, 1 - start.x );
			box.h = clamp( start.h + dy, 0.05, 1 - start.y );
		}
		draw();
	} );

	[ 'pointerup', 'pointercancel' ].forEach( function ( evtName ) {
		els.canvas.addEventListener( evtName, function () {
			state.dragging = null;
			els.canvas.classList.remove( 'rrt-dragging' );
		} );
	} );

	function clamp( value, min, max ) {
		return Math.max( min, Math.min( max, value ) );
	}

	// --- controls ---

	els.size.addEventListener( 'change', function () {
		loadTemplate( els.size.value );
	} );

	els.logoInput.addEventListener( 'change', function () {
		var file = els.logoInput.files[ 0 ];
		if ( ! file ) {
			return;
		}
		state.logoFile = file;
		var reader = new FileReader();
		reader.onload = function ( evt ) {
			var img = new Image();
			img.onload = function () {
				state.logoImg = img;
				// Default: a reasonably sized box in the upper-left of
				// the zone, keeping the logo's own aspect ratio.
				var aspect = img.width / img.height;
				var w = 0.5;
				var h = clamp( w / aspect * ( activeSize().zone_width / activeSize().zone_height ), 0.05, 1 );
				state.logoBox = { x: 0.05, y: 0.05, w: w, h: h };
				els.logoRemove.hidden = false;
				draw();
			};
			img.src = evt.target.result;
		};
		reader.readAsDataURL( file );
	} );

	els.logoRemove.addEventListener( 'click', function () {
		state.logoImg = null;
		state.logoFile = null;
		state.logoBox = null;
		els.logoInput.value = '';
		els.logoRemove.hidden = true;
		draw();
	} );

	els.textInput.addEventListener( 'input', draw );

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
		if ( ! els.name.value.trim() || ! els.email.value.trim() ) {
			setStatus( 'Please fill in your name and email.', 'error' );
			return;
		}
		if ( ! state.logoFile && ! els.textInput.value.trim() ) {
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
			body.append( 'message', els.message.value.trim() );
			body.append( 'label_text', els.textInput.value.trim() );
			if ( state.logoBox ) {
				body.append( 'logo_box', JSON.stringify( state.logoBox ) );
			}
			if ( els.textInput.value.trim() ) {
				body.append( 'text_box', JSON.stringify( state.textBox ) );
			}
			if ( state.logoFile ) {
				body.append( 'logo', state.logoFile );
			}

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
	var firstSize = Object.keys( config.sizes )[ 0 ];
	if ( firstSize ) {
		loadTemplate( firstSize );
	}
} )();
