/**
 * Harry Transition — flight engine (production).
 *
 *   playHarryTransition( layoutKey, opts )
 *
 *   layoutKey  string|null   key of a row in Harry -> Layouts. null = built-in demo path.
 *   opts.force   bool        ignore the row's Active toggle / date window
 *   opts.target  Element     land the pop on this element's centre instead of the X marker
 *   opts.onDone  function    called when the overlay has cleared
 *
 * Data comes from window.HARRY_PATHS (localised by inc/harry-path.php):
 *   { sprites:{ key:url }, layouts:{ key:{ sprite, active, start, end, path } } }
 *
 * Path JSON (what the Harry Path Editor exports) — coords are viewport fractions:
 *   { v:1,
 *     layout:{ OS:[fx,fy], S:[fx,fy], X:[fx,fy], dots:[[fx,fy,linger], ...] },
 *     opt:{ fspd, spin, spinT, grow, fade, startMode:'os'|'fade', orient:'bank'|'level' } }
 */
(function () {
	'use strict';

	var FALLBACK_HARRY =
		'data:image/svg+xml;utf8,' +
		encodeURIComponent(
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">' +
			'<circle cx="50" cy="50" r="44" fill="#fff" stroke="#111" stroke-width="4"/>' +
			'<text x="50" y="66" font-family="Georgia,serif" font-size="52" text-anchor="middle" fill="#111">H</text></svg>'
		);

	var DEMO = {
		layout: {
			OS: [-0.03, 0.88],
			S: [0.14, 0.78],
			X: [0.74, 0.34],
			dots: [
				[0.34, 0.62, 0.6],
				[0.52, 0.42, 0.6]
			]
		},
		opt: { fspd: 550, spin: 1, spinT: 0.6, grow: 1, fade: 0.75, startMode: 'os', orient: 'bank' }
	};

	var FLY = 0.34;                 // constant flight scale of the sprite box
	var busy = false;
	var dom = null;                 // { fx, shaker, wad, img }
	var rafId = null, timers = [];
	var entryScroll = null;         // page scroll to restore after a scroll-followed flight
	var activeResize = null;        // mid-flight resize guard, cleared on reset

	/* ---------- helpers ---------- */
	function reduced() {
		return window.matchMedia && window.matchMedia('(prefers-reduced-motion:reduce)').matches;
	}
	function later(fn, ms) { var id = setTimeout(fn, ms); timers.push(id); return id; }
	function clearTimers() { timers.forEach(clearTimeout); timers = []; }
	function killRaf() { if (rafId) cancelAnimationFrame(rafId); rafId = null; }
	function cancelAnims(el) { (el.getAnimations ? el.getAnimations() : []).forEach(function (a) { a.cancel(); }); }

	function docHeightRaw() {
		var se = document.scrollingElement || document.documentElement;
		return Math.max(se.scrollHeight, document.body ? document.body.scrollHeight : 0, window.innerHeight);
	}

	/* ---------- waypoint model (v2 = element-anchored, clock + proximity) --------
	 * A waypoint is one of:
	 *   { anchor:"slug", clock:0..12, prox:"tight|medium|loose"|px, nudge:[dx,dy] }
	 *        a point at the given o'clock position around [data-harry-anchor="slug"]
	 *        (12=up, 3=right, 6=down, 9=left; 0/"center" = its dead centre),
	 *        pushed `prox` px past the box edge, then `nudge` px.
	 *   { between:["slugA","slugB"], t:0..1, clockA, clockB, proxA, proxB, nudge }
	 *        a point `t` of the way along the line joining two anchor points.
	 *   { edge:"left|top|right|bottom", along:0..1 }   off-screen entry (screen-rel)
	 *   { selector:"css", text:"…", clock, prox, nudge }   loose fallback + drift check
	 *   [fx,fy] / [fx,fy,ling] / {fx,fy} / {anchor,ax,ay}  legacy (v1 / interim)
	 * resolveWaypoint() -> { x, y } in DOCUMENT px, or null if it can't be found.
	 */
	var authVW = 0, authVH = 0;
	function setAuthorGeom(path) {
		authVW = +(path && path.vw) || window.innerWidth;
		authVH = +(path && path.vh) || window.innerHeight;
	}
	function warn(m) { if (window.console && console.warn) console.warn(m); }
	function frac(v, dflt) { return (v === undefined || v === null) ? dflt : +v; }

	var PROX = { tight: 12, medium: 40, loose: 90 };
	function proxPx(p) {
		if (p === undefined || p === null) return PROX.medium;
		if (typeof p === 'number') return p;
		return PROX[p] != null ? PROX[p] : (parseFloat(p) || PROX.medium);
	}
	// unit direction for a clock hour (12=up, 3=right, 6=down, 9=left); 0/'center' -> null
	function clockDir(clock) {
		var c = (clock === 'center' || clock === undefined || clock === null) ? 0 : (+clock % 12);
		if (!c) return null;
		var ang = c * 30 * Math.PI / 180;
		return { x: Math.sin(ang), y: -Math.cos(ang) };
	}
	// a document-space point at `clock`/`prox` around a viewport rect
	function pointOnBox(rect, clock, prox, nudge) {
		var cx = rect.left + rect.width / 2, cy = rect.top + rect.height / 2;
		var dir = clockDir(clock), x = cx, y = cy;
		if (dir) {
			var tx = Math.abs(dir.x) > 1e-6 ? (rect.width / 2) / Math.abs(dir.x) : Infinity;
			var ty = Math.abs(dir.y) > 1e-6 ? (rect.height / 2) / Math.abs(dir.y) : Infinity;
			var reach = Math.min(tx, ty) + proxPx(prox);
			x = cx + dir.x * reach; y = cy + dir.y * reach;
		}
		var n = nudge || [0, 0];
		return { x: x + (n[0] || 0) + window.scrollX, y: y + (n[1] || 0) + window.scrollY };
	}
	function findAnchorEl(wp) {
		if (wp.anchor) return { el: document.querySelector('[data-harry-anchor="' + String(wp.anchor).replace(/"/g, '') + '"]'), why: 'anchor "' + wp.anchor + '"' };
		if (wp.selector) { var el = null; try { el = document.querySelector(wp.selector); } catch (e) { } return { el: el, why: 'selector "' + wp.selector + '"' }; }
		return { el: null, why: 'waypoint' };
	}
	function checkDrift(el, wp, why) {
		if (!wp.text) return;
		var t = (el.textContent || '').trim().replace(/\s+/g, ' ');
		if (t && t.indexOf(String(wp.text).slice(0, 40)) < 0) {
			warn('Harry FruitFly: ' + why + ' resolved but its text changed ("' + String(wp.text).slice(0, 30) + '" -> "' + t.slice(0, 30) + '") — the anchor may now point at the wrong thing.');
		}
	}
	function resolveWaypoint(wp) {
		if (!wp) return null;
		// --- legacy v1 (document fractions of the author window) ---
		if (Array.isArray(wp)) return { x: (wp[0] || 0) * authVW, y: (wp[1] || 0) * authVH };
		if (wp.fx !== undefined || wp.fy !== undefined) return { x: (wp.fx || 0) * authVW, y: (wp.fy || 0) * authVH };
		// --- edge (off-screen entry) ---
		if (wp.edge) {
			var vw = window.innerWidth, vh = window.innerHeight, sx = window.scrollX, sy = window.scrollY, a = frac(wp.along, 0.5);
			if (wp.edge === 'left') return { x: sx - vw * 0.04, y: sy + vh * a };
			if (wp.edge === 'right') return { x: sx + vw * 1.04, y: sy + vh * a };
			if (wp.edge === 'top') return { x: sx + vw * a, y: sy - vh * 0.06 };
			return { x: sx + vw * a, y: sy + vh * 1.06 };
		}
		// --- between two anchors ---
		if (wp.between && wp.between.length === 2) {
			var A = findAnchorEl({ anchor: wp.between[0] }), B = findAnchorEl({ anchor: wp.between[1] });
			if (!A.el || !B.el) { warn('Harry FruitFly: between ' + (A.el ? '' : A.why) + (B.el ? '' : ' & ' + B.why) + ' not found — waypoint dropped.'); return null; }
			var pa = pointOnBox(A.el.getBoundingClientRect(), wp.clockA != null ? wp.clockA : 0, wp.proxA, null);
			var pb = pointOnBox(B.el.getBoundingClientRect(), wp.clockB != null ? wp.clockB : 0, wp.proxB, null);
			var tt = frac(wp.t, 0.5), nn = wp.nudge || [0, 0];
			return { x: pa.x + (pb.x - pa.x) * tt + (nn[0] || 0), y: pa.y + (pb.y - pa.y) * tt + (nn[1] || 0) };
		}
		// --- anchored / loose (an element + clock/prox) ---
		var f = findAnchorEl(wp);
		if (!f.el) { warn('Harry FruitFly: ' + f.why + ' not found on this page — waypoint dropped.'); return null; }
		checkDrift(f.el, wp, f.why);
		var r = f.el.getBoundingClientRect();
		if (wp.ax !== undefined || wp.ay !== undefined) {   // interim {ax,ay} still honoured
			return { x: r.left + frac(wp.ax, 0.5) * r.width + window.scrollX, y: r.top + frac(wp.ay, 0.5) * r.height + window.scrollY };
		}
		return pointOnBox(r, wp.clock != null ? wp.clock : 'center', wp.prox, wp.nudge);
	}
	// keep Harry pinned ~42% down the viewport — INSTANT every frame, exactly like
	// the editor's Play preview. Uses the LIVE document height (grows with the
	// flight spacer) so it can follow the sprite to the deepest waypoint.
	function follow(docY) {
		var maxY = Math.max(0, docHeightRaw() - window.innerHeight);
		window.scrollTo(0, Math.max(0, Math.min(maxY, docY - window.innerHeight * 0.42)));
	}
	function ling(p) { return Array.isArray(p) ? (p[2] || 0) : ((p && p.ling) || 0); }

	function todayYmd() {
		var d = new Date();
		return '' + d.getFullYear() +
			('0' + (d.getMonth() + 1)).slice(-2) +
			('0' + d.getDate()).slice(-2);
	}

	function spriteUrl(name) {
		var s = (window.HARRY_PATHS && window.HARRY_PATHS.sprites) || {};
		return s[name] || s.harry || FALLBACK_HARRY;
	}

	var MOBILE_MAX = 768;   // screens this wide or narrower get the mobile path if one exists

	function resolve(key, force) {
		var L = window.HARRY_PATHS && window.HARRY_PATHS.layouts && window.HARRY_PATHS.layouts[key];
		var hasAny = L && ((L.path && L.path.layout) || (L.pathMobile && L.pathMobile.layout));
		if (!key || !hasAny) {
			return { path: DEMO, sprite: 'harry' };
		}
		if (!force) {
			if (!L.active) return null;
			var t = todayYmd();
			if (L.start && t < L.start) return null;
			if (L.end && t > L.end) return null;
		}
		// pick mobile vs desktop by screen width, fall back to whichever exists
		var wantMobile = window.innerWidth <= MOBILE_MAX;
		var mob = L.pathMobile && L.pathMobile.layout ? L.pathMobile : null;
		var desk = L.path && L.path.layout ? L.path : null;
		var p = (wantMobile && mob) || desk || mob;

		return {
			path: {
				v: p.v || 1,
				layout: p.layout,
				vw: p.vw, vh: p.vh,   // only used by the v1 compat shim
				opt: Object.assign(
					{ fspd: 550, spin: 1, spinT: 0.6, grow: 1, fade: 0.75, startMode: 'os', orient: 'bank' },
					p.opt || {}
				)
			},
			sprite: L.sprite || 'harry'
		};
	}

	function build() {
		if (dom) return dom;
		var fx = document.createElement('div');
		fx.className = 'harry-fx';
		fx.id = 'harry-fx';
		fx.innerHTML =
			'<div class="harry-fx__shaker"><div class="harry-fx__wad"><img alt=""></div></div>';
		document.body.appendChild(fx);
		dom = {
			fx: fx,
			shaker: fx.querySelector('.harry-fx__shaker'),
			wad: fx.querySelector('.harry-fx__wad'),
			img: fx.querySelector('img')
		};
		return dom;
	}

	function removeSpacer() {
		var sp = document.getElementById('harry-fx-spacer');
		if (sp && sp.parentNode) sp.parentNode.removeChild(sp);
	}

	function hardReset(onDone) {
		busy = false;
		killRaf(); clearTimers();
		removeSpacer();
		if (activeResize) { window.removeEventListener('resize', activeResize); activeResize = null; }
		if (dom) {
			[dom.fx, dom.shaker, dom.wad, dom.img].forEach(cancelAnims);
			[].slice.call(dom.shaker.querySelectorAll('.harry-fx__spec')).forEach(function (s) { s.remove(); });
			dom.wad.style.cssText = ''; dom.img.style.cssText = '';
			dom.fx.style.cssText = ''; dom.shaker.style.cssText = '';
			dom.fx.classList.remove('is-on');
		}
		if (entryScroll != null) { window.scrollTo(0, entryScroll); entryScroll = null; }
		if (typeof onDone === 'function') onDone();
	}

	function softDust(x, y, mult) {
		mult = mult || 1;
		var n = Math.round(6 * mult);
		for (var i = 0; i < n; i++) {
			var sz = (6 + Math.random() * 6) * Math.sqrt(mult);
			var s = document.createElement('div');
			s.className = 'harry-fx__spec';
			s.style.background = 'rgba(120,110,100,' + (0.42 + Math.random() * 0.18) + ')';
			s.style.width = sz + 'px'; s.style.height = sz + 'px';
			s.style.left = x + 'px'; s.style.top = y + 'px';
			dom.shaker.appendChild(s);
			var a = Math.random() * Math.PI * 2, d = (18 + Math.random() * 44) * mult;
			(function (sp, ang, dist) {
				sp.animate([
					{ transform: 'translate(-50%,-50%) scale(.6)', opacity: .75 },
					{ transform: 'translate(calc(-50% + ' + Math.cos(ang) * dist + 'px),calc(-50% + ' + Math.sin(ang) * dist + 'px)) scale(1.4)', opacity: 0 }
				], { duration: 600 + Math.random() * 300, easing: 'ease-out', fill: 'forwards' })
					.onfinish = function () { sp.remove(); };
			})(s, a, d);
		}
	}

	/* ---------- the flight ---------- */
	function fly(cfg, opts) {
		var d = build();
		var layout = cfg.path.layout, o = cfg.path.opt;
		setAuthorGeom(cfg.path);                      // freeze author vw/vh/docH + this page's height
		entryScroll = window.scrollY;                 // put the page back here when the overlay clears

		// the path is measured against the viewport size at kickoff; if that changes
		// mid-flight (opening/closing devtools, rotating a phone) the math is stale —
		// bail cleanly rather than fling Harry around.
		var vh0 = window.innerHeight, vw0 = window.innerWidth;
		function onResize() {
			// ignore the small height wobble from a mobile URL/tool bar hiding on scroll;
			// only bail on a real layout change — orientation flip or a desktop window resize
			if (Math.abs(window.innerWidth - vw0) > 80 || Math.abs(window.innerHeight - vh0) > 260) {
				hardReset(opts && opts.onDone);
			}
		}
		activeResize = onResize;
		window.addEventListener('resize', onResize);

		d.img.setAttribute('src', spriteUrl(cfg.sprite));
		d.fx.classList.add('is-on');

		// resolve every waypoint to DOCUMENT px NOW (anchors locked for the flight)
		var S = resolveWaypoint(layout.S), X = resolveWaypoint(layout.X), OS = resolveWaypoint(layout.OS);
		if (opts && opts.target && opts.target.getBoundingClientRect) {
			var r = opts.target.getBoundingClientRect();
			X = { x: r.left + r.width / 2 + window.scrollX, y: r.top + r.height / 2 + window.scrollY };
		}
		if (!S || !X) { warn('Harry FruitFly: start or end waypoint could not be resolved — flight cancelled.'); hardReset(opts && opts.onDone); return; }
		if (!OS) OS = { x: S.x, y: S.y - window.innerHeight };   // no OS anchor -> come straight down from above S
		var dots = (layout.dots || []).map(function (p) {
			var q = resolveWaypoint(p); if (q) q.ling = ling(p); return q;
		}).filter(Boolean);
		var P = (o.startMode === 'fade' ? [S] : [OS, S]).concat(dots).concat([X]);
		var lead = o.startMode === 'fade' ? 1 : 2;

		// The fixed overlay can't lengthen the document, so on a path taller than
		// the page the scroll-follow would hit a ceiling and the sprite would drift
		// off the bottom (worse with every deeper waypoint). Add a temporary bottom
		// spacer so follow() can keep Harry pinned the whole way, like the editor.
		var deepest = 0;
		for (var pi = 0; pi < P.length; pi++) { if (P[pi].y > deepest) deepest = P[pi].y; }
		var need = Math.ceil(deepest + window.innerHeight * 0.6 - docHeightRaw());
		if (need > 0) {
			var sp = document.createElement('div');
			sp.id = 'harry-fx-spacer';
			sp.setAttribute('aria-hidden', 'true');
			sp.style.cssText = 'height:' + need + 'px;width:1px;margin:0;padding:0;pointer-events:none;visibility:hidden';
			document.body.appendChild(sp);
		}

		function cr(i, u) {
			var p0 = P[i - 1] || P[i], p1 = P[i], p2 = P[i + 1], p3 = P[i + 2] || P[i + 1];
			var u2 = u * u, u3 = u2 * u;
			return {
				x: 0.5 * ((2 * p1.x) + (-p0.x + p2.x) * u + (2 * p0.x - 5 * p1.x + 4 * p2.x - p3.x) * u2 + (-p0.x + 3 * p1.x - 3 * p2.x + p3.x) * u3),
				y: 0.5 * ((2 * p1.y) + (-p0.y + p2.y) * u + (2 * p0.y - 5 * p1.y + 4 * p2.y - p3.y) * u2 + (-p0.y + 3 * p1.y - 3 * p2.y + p3.y) * u3)
			};
		}
		var poly = [], acc = 0, prev = null, SEG = 44, dotStops = [];
		for (var i = 0; i < P.length - 1; i++) {
			for (var s = (i === 0 ? 0 : 1); s <= SEG; s++) {
				var pt = cr(i, s / SEG);
				if (prev) acc += Math.hypot(pt.x - prev.x, pt.y - prev.y);
				poly.push({ x: pt.x, y: pt.y, len: acc }); prev = pt;
				if (s === SEG && (i + 1) >= lead && i < P.length - 2) {
					dotStops.push({ len: acc, ling: dots[i + 1 - lead].ling });
				}
			}
		}
		var totalLen = poly[poly.length - 1].len;
		function sampleAt(L) {
			L = Math.max(0, Math.min(totalLen, L));
			var lo = 0, hi = poly.length - 1;
			while (lo < hi - 1) { var mid = (lo + hi) >> 1; if (poly[mid].len < L) lo = mid; else hi = mid; }
			var a = poly[lo], b = poly[hi], seg = (b.len - a.len) || 1, k = (L - a.len) / seg;
			return { x: a.x + (b.x - a.x) * k, y: a.y + (b.y - a.y) * k, tx: (b.x - a.x) / seg, ty: (b.y - a.y) / seg };
		}

		var dist = 0, spd = o.fspd, last = performance.now();
		var pauseUntil = 0, nextStop = 0, curRot = 0, started = false, holdUntil = 0;

		// place the wad at a DOCUMENT point (x, docy); scroll the page to keep it
		// on screen, then convert to viewport px for the fixed overlay
		function put(x, docy, rot, bob) {
			follow(docy);
			var vy = docy - window.scrollY + (bob || 0);
			d.wad.style.transform = 'translate(' + x + 'px,' + vy + 'px) translate(-50%,-50%) scale(' + FLY + ') rotate(' + (rot || 0) + 'deg)';
		}

		if (o.startMode === 'fade') {
			var fadeMs = Math.max(1, o.fade * 1000);
			var s00 = sampleAt(0);
			d.wad.style.opacity = '0';
			put(s00.x, s00.y, 0);
			d.wad.animate([{ opacity: 0 }, { opacity: 1 }], { duration: fadeMs, fill: 'forwards' });
			holdUntil = performance.now() + fadeMs;
		}

		function frame(now) {
			var dt = Math.min(0.05, (now - last) / 1000); last = now;
			var sp;
			if (now < holdUntil) {
				sp = sampleAt(0); put(sp.x, sp.y, 0);
				rafId = requestAnimationFrame(frame); return;
			}
			if (now < pauseUntil) {
				sp = sampleAt(dist);
				var ld = ((0 - curRot + 540) % 360) - 180; curRot += ld * 0.2;
				put(sp.x, sp.y, curRot, Math.sin(now * 0.012) * 3);
				rafId = requestAnimationFrame(frame); return;
			}
			dist += spd * dt;
			if (nextStop < dotStops.length && dist >= dotStops[nextStop].len) {
				dist = dotStops[nextStop].len;
				pauseUntil = now + dotStops[nextStop].ling * 1000;
				nextStop++;
			}
			if (dist >= totalLen) { endSpinPop(sampleAt(totalLen)); return; }
			sp = sampleAt(dist);
			var target = o.orient === 'bank' ? Math.atan2(sp.ty, sp.tx) * 180 / Math.PI : 0;
			var diff = ((target - curRot + 540) % 360) - 180;
			curRot += started ? diff * 0.18 : (started = true, diff);
			put(sp.x, sp.y, curRot);
			rafId = requestAnimationFrame(frame);
		}

		var s0 = sampleAt(0);
		put(s0.x, s0.y, 0);
		d.wad.style.opacity = o.startMode === 'fade' ? d.wad.style.opacity : '1';
		last = performance.now();
		rafId = requestAnimationFrame(frame);

		function endSpinPop(sp) {
			killRaf();
			follow(sp.y);                                  // settle the page on the landing spot
			var vy = sp.y - window.scrollY;
			var base = 'translate(' + sp.x + 'px,' + vy + 'px) translate(-50%,-50%) ';
			var r0 = curRot, r1 = curRot + 360 * o.spin;
			var spinMs = Math.max(1, o.spinT * 1000);
			var gEnd = FLY * o.grow;
			d.wad.animate([
				{ transform: base + 'scale(' + FLY + ') rotate(' + r0 + 'deg)' },
				{ transform: base + 'scale(' + gEnd + ') rotate(' + r1 + 'deg)' }
			], { duration: spinMs, easing: 'cubic-bezier(.3,0,.3,1)', fill: 'forwards' });
			later(function () {
				d.wad.animate([
					{ transform: base + 'scale(' + gEnd + ') rotate(' + r1 + 'deg)', opacity: 1 },
					{ transform: base + 'scale(' + (gEnd * 1.28) + ',' + (gEnd * 0.8) + ') rotate(' + r1 + 'deg)', opacity: 1, offset: 0.4, easing: 'cubic-bezier(.3,-0.5,.5,1)' },
					{ transform: base + 'scale(' + (gEnd * 0.1) + ') rotate(' + r1 + 'deg)', opacity: 0, offset: 1, easing: 'cubic-bezier(.6,0,.9,.4)' }
				], { duration: 240, fill: 'forwards' });
				softDust(sp.x, vy, 0.7);
				later(function () {
					d.fx.animate([{ opacity: 1 }, { opacity: 0 }], { duration: 140, fill: 'forwards' })
						.onfinish = function () { hardReset(opts && opts.onDone); };
				}, 240);
			}, spinMs);
		}
	}

	// ?harrymarks=1 (or =<key>) — draw the resolved waypoints as numbered dots at
	// their computed document positions, no flight. For calibrating a path.
	function drawMarks(cfg) {
		var d = build();
		setAuthorGeom(cfg.path);
		var L = cfg.path.layout;
		var raw = [L.OS, L.S].concat(L.dots || []).concat([L.X]);
		var pts = raw.map(resolveWaypoint);
		d.fx.classList.add('is-on');
		d.wad.style.display = 'none';
		var host = d.shaker;
		[].slice.call(host.querySelectorAll('.harry-mark')).forEach(function (n) { n.remove(); });
		pts.forEach(function (p, i) {
			if (!p) return;   // unresolved anchor — warn already logged
			var m = document.createElement('div');
			m.className = 'harry-mark';
			var label = i === 0 ? 'OS' : i === 1 ? 'S' : i === pts.length - 1 ? 'X' : (i - 1);
			m.textContent = label;
			m.style.cssText = 'position:fixed;left:' + p.x + 'px;top:' + (p.y - window.scrollY) +
				'px;transform:translate(-50%,-50%);width:24px;height:24px;border-radius:50%;' +
				'background:#e6007a;color:#fff;font:800 11px/24px system-ui;text-align:center;' +
				'box-shadow:0 0 0 3px #fff,0 2px 8px rgba(0,0,0,.5);z-index:100000';
			host.appendChild(m);
		});
		var info = document.createElement('div');
		info.className = 'harry-mark';
		info.style.cssText = 'position:fixed;left:8px;top:8px;background:#111;color:#fff;font:12px/1.4 monospace;padding:6px 9px;border-radius:6px;z-index:100001;white-space:pre';
		info.textContent = 'v=' + (cfg.path.v || 1) + '  playVH=' + window.innerHeight + '  playDocH=' + docHeightRaw() +
			'\nresolved ' + pts.filter(Boolean).length + ' / ' + pts.length + ' waypoints' +
			'\ndeepest docY=' + Math.round(Math.max.apply(null, pts.filter(Boolean).map(function (p) { return p.y; })));
		host.appendChild(info);
	}

	/* ---------- public entry ---------- */
	window.playHarryTransition = function (key, opts) {
		opts = opts || {};
		if (busy) return;

		// ?harrymarks=<key>  — draw the resolved waypoints of THAT layout as static
		// numbered dots (calibration/debug), no flight. ?harrymarks=1 uses `key`.
		var marks = new URLSearchParams(location.search).get('harrymarks');
		if (marks !== null) {
			var mkKey = (marks && marks !== '1') ? marks : key;
			var mkCfg = resolve(mkKey, true);
			if (mkCfg) drawMarks(mkCfg);
			return;
		}

		// respect reduced-motion for real transitions, but let an explicit force (test buttons) through
		if (reduced() && !(opts && opts.force)) { if (typeof opts.onDone === 'function') opts.onDone(); return; }
		var cfg = resolve(key, !!(opts && opts.force));
		if (!cfg) return;            // inactive / outside its date window
		busy = true;
		build();
		hardReset();                // clear any stale state, keep busy
		busy = true;
		requestAnimationFrame(function () {
			requestAnimationFrame(function () { fly(cfg, opts); });
		});
	};

	// let a page know the engine is ready
	window.dispatchEvent(new Event('harry-path-ready'));
})();
