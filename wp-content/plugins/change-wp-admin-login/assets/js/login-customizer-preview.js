(function () {
	'use strict';

	function ensureFontLink(fontFamily) {
		var id = 'aio-login-pro-customizer-font';
		var existing = document.getElementById(id);
		if (!fontFamily || fontFamily === 'Inherit') {
			if (existing) existing.parentNode.removeChild(existing);
			return;
		}
		var family = String(fontFamily).trim().replace(/\s+/g, '+');
		var href = 'https://fonts.googleapis.com/css2?family=' + family + ':wght@300;400;500;600;700&display=swap';
		if (!existing) {
			existing = document.createElement('link');
			existing.id = id;
			existing.rel = 'stylesheet';
			document.head.appendChild(existing);
		}
		existing.href = href;
	}

	function ensureStyleEl() {
		var el = document.getElementById('aio-login-pro-customizer-live');
		if (!el) {
			el = document.createElement('style');
			el.id = 'aio-login-pro-customizer-live';
			document.head.appendChild(el);
		} else if (el.parentNode) {
			el.parentNode.appendChild(el);
		}
		return el;
	}

	function cssEscape(v) {
		return String(v || '').replace(/<\/style/gi, '<\\/style');
	}

	function cssBgUrl(u) {
		if (!u) {
			return '';
		}
		return 'url("' + String(u).replace(/\\/g, '\\\\').replace(/"/g, '\\"') + '")';
	}

	function px(v) {
		if (v === '' || v === null || typeof v === 'undefined') return '';
		var n = parseFloat(v);
		return isFinite(n) ? (n + 'px') : '';
	}

	/** Only emit px when value is > 0. Customizer often sends 0 / "0" for "unset" — those must not override template layout (0 width/min-height collapses the login form). */
	function pxPositive(v) {
		if (v === '' || v === null || typeof v === 'undefined') return '';
		var n = parseFloat(v);
		return isFinite(n) && n > 0 ? n + 'px' : '';
	}

	function inputWidthValue(v) {
		if (v === '' || v === null || typeof v === 'undefined') return '';
		var s = String(v).trim();
		if (s === '0' || s === '0px') return '';
		if (/[a-z%]/i.test(s)) return s;
		var n = parseFloat(s);
		return isFinite(n) && n > 0 ? n + 'px' : '';
	}

	/** Comma-separated selectors: each branch gets the pseudo (e.g. :hover), required for valid CSS. */
	function cssEachSelectorPseudo(commaSel, pseudo) {
		if (!commaSel || !pseudo) {
			return '';
		}
		return commaSel
			.split(',')
			.map(function (s) {
				return s.trim() + pseudo;
			})
			.join(', ');
	}

	/**
	 * Match PHP output specificity in class-login-customization-output.php:
	 * $specific = body.login.{template}, $strong_specific = body.login.{template}.{template}
	 * so live rules can override saved !important CSS.
	 */
	function getTemplateSelectors() {
		var b = document.body;
		if (!b || !b.className) {
			return { s: 'body.login', strong: 'body.login' };
		}
		var parts = String(b.className).split(/\s+/);
		var tpl = '';
		for (var i = 0; i < parts.length; i++) {
			if (parts[i].indexOf('aio-login__template-') === 0) {
				tpl = parts[i];
				break;
			}
		}
		if (!tpl) {
			return { s: 'body.login', strong: 'body.login' };
		}
		return {
			s: 'body.login.' + tpl,
			strong: 'body.login.' + tpl + '.' + tpl
		};
	}

	function isDefaultLoginBgColor(hex) {
		if (hex === '' || hex === null || typeof hex === 'undefined') return true;
		var t = String(hex).replace(/\s/g, '');
		return t.toLowerCase() === '#f1f1f1';
	}

	/** Body.dark templates (Midnight Dark, etc.) — default gray must not cover their CSS background. */
	function isDarkBodyTemplateClass() {
		var b = document.body;
		if (!b || !b.className) return false;
		var c = ' ' + String(b.className).split(/\s+/).join(' ') + ' ';
		return (
			c.indexOf(' aio-login__template-02 ') !== -1 ||
			c.indexOf(' aio-login__template-05 ') !== -1 ||
			c.indexOf(' aio-login__template-08 ') !== -1
		);
	}

	/** Option slugs for templates whose bundled CSS uses a dark page background (matches PHP). */
	function isDarkTemplateOptionSlug(slug) {
		var s = String(slug || '');
		return s === 'template-2' || s === 'template-4' || s === 'template-7';
	}

	/**
	 * Skip injecting default #f1f1f1 only for dark themes. When Customizer has already switched slug but the
	 * iframe body class still lags (before refresh), trust state.templateSlug — not body class alone.
	 */
	function shouldSkipDefaultPreviewBg(state) {
		if (!state.bgColor || !isDefaultLoginBgColor(state.bgColor)) {
			return false;
		}
		if (state.templateSlug && String(state.templateSlug).length) {
			return isDarkTemplateOptionSlug(state.templateSlug);
		}
		return isDarkBodyTemplateClass();
	}

	function buildCSS(state) {
		var css = '';
		var sel = getTemplateSelectors();
		var body = sel.s;
		var bodyStrong = sel.strong;

		// Non-dark template selected in Customizer but iframe still has old dark body class (before refresh):
		// strip Midnight/Glass/Frost black bg immediately. Does not run when slug is a dark theme.
		if (state.templateSlug && String(state.templateSlug).length && !isDarkTemplateOptionSlug(state.templateSlug)) {
			css +=
				'body.login.aio-login__template-02,body.login.aio-login__template-05,body.login.aio-login__template-08{background:#f1f1f1 !important;}';
		}

		// Default/custom bg from setting (needs state.bgColor; postMessage may lag template by a frame).
		if (state.bgColor && !shouldSkipDefaultPreviewBg(state)) {
			css += bodyStrong + '{background-color:' + cssEscape(state.bgColor) + ' !important;}';
		}
		if (state.bgRepeat || state.bgPos || state.bgSize) {
			css += bodyStrong + '{';
			if (state.bgRepeat) css += 'background-repeat:' + cssEscape(state.bgRepeat) + ' !important;';
			if (state.bgPos) css += 'background-position:' + cssEscape(state.bgPos) + ' !important;';
			if (state.bgSize) css += 'background-size:' + cssEscape(state.bgSize) + ' !important;';
			css += '}';
		}

		// Typography (apply widely; match PHP $strong_specific)
		if (state.fontFamily && state.fontFamily !== 'Inherit') {
			css += bodyStrong + ', ' + bodyStrong + ' * {font-family:"' + cssEscape(state.fontFamily) + '", sans-serif !important;}';
		}

		// Logo (disable rule is appended at end of buildCSS so it wins over template-09 #login h1.wp-login-logo)
		var logoW = pxPositive(state.logoW);
		var logoH = pxPositive(state.logoH);
		if (logoW) {
			css += body + ' #login h1 a, ' + body + ' .wp-login-logo a{width:' + logoW + ' !important;}';
			if (body.indexOf('template-09') !== -1) {
				css +=
					body +
					' #login h1.wp-login-logo,' +
					body +
					' #login h1{min-width:0!important;width:' +
					logoW +
					' !important;max-width:none!important;}';
			}
		}
		if (logoH) {
			css += body + ' #login h1 a, ' + body + ' .wp-login-logo a{height:' + logoH + ' !important;}';
			if (body.indexOf('template-09') !== -1) {
				css += body + ' #login h1.wp-login-logo,' + body + ' #login h1{height:' + logoH + ' !important;}';
			}
		}
		if (state.logoMB !== '' && state.logoMB !== null && typeof state.logoMB !== 'undefined') {
			var logoMB = px(state.logoMB);
			if (logoMB) {
				css += body + ' #login h1, ' + body + ' .wp-login-logo{margin-bottom:' + logoMB + ' !important;}';
			}
		}

		// Future Tech: logo lives inside #loginform / #lostpasswordform (footer script); center in flex column
		if (!state.disableLogo && body.indexOf('template-09') !== -1) {
			css +=
				body +
				' #loginform > h1.wp-login-logo,' +
				body +
				' #loginform > h1,' +
				body +
				' #lostpasswordform > h1.wp-login-logo,' +
				body +
				' #lostpasswordform > h1{position:relative!important;align-self:center!important;margin-left:auto!important;margin-right:auto!important;transform:none!important;left:auto!important;right:auto!important;top:auto!important;}';
		}

		// Form (match PHP $strong_specific + #login form so rules win over template stylesheets)
		var formSel = bodyStrong + ' #loginform, ' + bodyStrong + ' form';
		if (state.formTransparent) {
			css += formSel + '{background-color: rgba(255,255,255,0.0) !important; background: transparent !important;}';
		}
		var formW = pxPositive(state.formW);
		var formMinH = pxPositive(state.formMinH);
		if (formW) css += formSel + '{width:' + formW + ' !important; max-width:none !important;}';
		if (formMinH) css += formSel + '{min-height:' + formMinH + ' !important;}';
		if (state.formRadius !== '' && state.formRadius !== null && typeof state.formRadius !== 'undefined') {
			var fr = px(state.formRadius);
			if (fr) css += formSel + '{border-radius:' + fr + ' !important;}';
		}
		if (state.formPadding) css += formSel + '{padding:' + cssEscape(state.formPadding) + ' !important;}';
		if (state.formBorder) css += formSel + '{border:' + cssEscape(state.formBorder) + ' !important;}';
		if (state.formShadow) {
			var op = (state.formShadowOpacity === '' || state.formShadowOpacity == null) ? 0.15 : parseFloat(state.formShadowOpacity);
			if (!isFinite(op)) op = 0.15;
			css += formSel + '{box-shadow:' + cssEscape(state.formShadow) + ' rgba(0,0,0,' + op + ') !important;}';
		}

		// Inputs
		var inputSel = body + ' form input:not([type="submit"]):not([type="checkbox"]):not([type="radio"])';
		if (state.inputMargin) css += inputSel + '{margin:' + cssEscape(state.inputMargin) + ' !important;}';
		if (state.inputBg) css += inputSel + '{background-color:' + cssEscape(state.inputBg) + ' !important;}';
		if (state.inputText) css += inputSel + '{color:' + cssEscape(state.inputText) + ' !important;}';
		var inputW = inputWidthValue(state.inputW);
		if (inputW) css += inputSel + '{width:' + cssEscape(inputW) + ' !important;}';

		// Labels — exclude Remember Me so .forgetmenot / Additional CSS can style it (bodyStrong label beats .forgetmenot otherwise).
		if (state.labelColor) {
			css +=
				bodyStrong +
				' #login label:not([for="rememberme"]){color:' +
				cssEscape(state.labelColor) +
				' !important;}';
		}
		var labelSz = pxPositive(state.labelSize);
		if (labelSz) {
			css +=
				bodyStrong + ' #login label:not([for="rememberme"]){font-size:' + labelSz + ' !important;}';
		}
		if (state.rememberColor) {
			css +=
				bodyStrong +
				' #loginform .forgetmenot label[for="rememberme"],' +
				bodyStrong +
				' #lostpasswordform .forgetmenot label[for="rememberme"]{color:' +
				cssEscape(state.rememberColor) +
				' !important;}';
		}
		var rememberSz = pxPositive(state.rememberSize);
		if (rememberSz) {
			css +=
				bodyStrong +
				' #loginform .forgetmenot label[for="rememberme"],' +
				bodyStrong +
				' #lostpasswordform .forgetmenot label[for="rememberme"]{font-size:' +
				rememberSz +
				' !important;}';
		}

		// Footer links (Lost password, Back to site, privacy, template-09 footer).
		// Lost-password link is both #nav a and .wp-login-lost-password — one high-specificity rule would block Additional CSS color on .wp-login-lost-password.
		if (state.linkColor) {
			var footSel =
				bodyStrong +
				' #nav a:not(.wp-login-lost-password), ' +
				bodyStrong +
				' #backtoblog a, ' +
				bodyStrong +
				' .privacy-policy-page-link a, ' +
				bodyStrong +
				' .aio-login__template-09-footer-links a';
			css += footSel + '{color:' + cssEscape(state.linkColor) + ' !important;}';
			css += '.wp-login-lost-password{color:' + cssEscape(state.linkColor) + ' !important;}';
		}

		// Buttons
		var btnSel = bodyStrong + ' #login form input[type="submit"], ' + bodyStrong + ' #loginform input[type="submit"], ' + bodyStrong + ' #login .button-primary';
		if (state.btnBg) css += btnSel + '{background-color:' + cssEscape(state.btnBg) + ' !important;}';
		if (state.btnText) css += btnSel + '{color:' + cssEscape(state.btnText) + ' !important;}';
		if (state.btnBorder) css += btnSel + '{border-color:' + cssEscape(state.btnBorder) + ' !important;}';
		if (state.btnRadius !== '' && state.btnRadius !== null && typeof state.btnRadius !== 'undefined') {
			var br = px(state.btnRadius);
			if (br) css += btnSel + '{border-radius:' + br + ' !important;}';
		}
		if (state.btnPad && String(state.btnPad).trim()) {
			css += btnSel + '{padding:' + cssEscape(String(state.btnPad).trim()) + ' !important;}';
		}
		var btnSz = pxPositive(state.btnSize);
		if (btnSz) {
			css += btnSel + '{min-height:' + btnSz + ' !important; box-sizing:border-box !important;}';
		}
		var btnTs = pxPositive(state.btnTextSize);
		if (btnTs) css += btnSel + '{font-size:' + btnTs + ' !important;}';
		if (state.btnHover) {
			css +=
				cssEachSelectorPseudo(btnSel, ':hover') +
				'{background-color:' +
				cssEscape(state.btnHover) +
				' !important; border-color:' +
				cssEscape(state.btnHover) +
				' !important;}';
		}
		if (state.btnShadow) {
			var bop = (state.btnShadowOpacity === '' || state.btnShadowOpacity == null) ? 0.2 : parseFloat(state.btnShadowOpacity);
			if (!isFinite(bop)) bop = 0.2;
			css += btnSel + '{box-shadow:' + cssEscape(state.btnShadow) + ' rgba(0,0,0,' + bop + ') !important;}';
		}

		// Forgot screen background (body gets .login-action-lostpassword when preview navigates to ?action=lostpassword)
		if (state.forgotBgColor) {
			css += bodyStrong + '.login-action-lostpassword{background-color:' + cssEscape(state.forgotBgColor) + ' !important;}';
		}
		if (state.forgotBgImageUrl) {
			css +=
				bodyStrong +
				'.login-action-lostpassword{background-image:' +
				cssBgUrl(state.forgotBgImageUrl) +
				' !important;background-size:cover !important;background-position:center !important;background-repeat:no-repeat !important;}';
		}

		// Custom CSS
		if (state.customCss) css += '\n' + state.customCss;

		// Future Tech: core #nav / #backtoblog after Additional CSS so preview cannot re-show them
		if (body.indexOf('template-09') !== -1) {
			css +=
				'\n' +
				body +
				' #login > #nav,' +
				body +
				' #login > #backtoblog{display:none!important;visibility:hidden!important;height:0!important;overflow:hidden!important;margin:0!important;padding:0!important;clip:rect(0,0,0,0)!important;position:absolute!important;width:0!important;pointer-events:none!important;}';
		}

		if (state.disableLogo) {
			css +=
				body +
				' #login h1.wp-login-logo,' +
				body +
				' #login h1,' +
				body +
				' #login .wp-login-logo,' +
				body +
				' .wp-login-logo{display:none!important;visibility:hidden!important;height:0!important;width:0!important;margin:0!important;padding:0!important;overflow:hidden!important;clip:rect(0,0,0,0)!important;position:absolute!important;pointer-events:none!important;}';
		}

		return css;
	}

	var aioPinListenersBound = false;
	var aioPinRaf = 0;

	function ensureEditPinsHostStyle() {
		var hostId = 'aio-login-pro-customizer-pins';
		if (document.getElementById('aio-login-pro-customizer-pins-style')) return;
		var s = document.createElement('style');
		s.id = 'aio-login-pro-customizer-pins-style';
		s.textContent = ''
			+ '#' + hostId + '{position:fixed;inset:0;pointer-events:none;z-index:999999;}'
			+ '#' + hostId + ' .aio-pin{position:fixed;pointer-events:auto;display:inline-flex;align-items:center;justify-content:center;'
			+ 'width:28px;height:28px;border-radius:999px;margin:0;padding:0;border:none;'
			+ 'background:#fff;border:1px solid rgba(0,0,0,.15)!important;box-shadow:0 6px 20px rgba(0,0,0,.12);cursor:pointer;'
			+ 'transform:translate(0,-50%);box-sizing:border-box;}'
			+ '#' + hostId + ' .aio-pin:hover{box-shadow:0 10px 26px rgba(0,0,0,.18);} '
			+ '#' + hostId + ' .aio-pin svg.aio-pin-ico{width:15px;height:15px;color:#111827;flex-shrink:0;}'
			+ '#' + hostId + ' .aio-pin--templates svg.aio-pin-ico{width:17px;height:17px;}'
			+ '#' + hostId + ' .aio-pin-ico path,' + '#' + hostId + ' .aio-pin-ico rect{fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}'
			/* Hide Core Customizer edit shortcuts in this iframe — they target the wrong nodes on wp-login and overlap our pins. */
			+ 'body.login .customize-partial-edit-shortcut,body.login .customize-selective-edit-shortcut-button,'
			+ 'body.login button.customize-partial-edit-shortcut-button{display:none!important;}';
		document.head.appendChild(s);
	}

	function bindEditPinRelayout() {
		if (aioPinListenersBound) return;
		aioPinListenersBound = true;
		var schedule = function () {
			if (aioPinRaf) return;
			aioPinRaf = requestAnimationFrame(function () {
				aioPinRaf = 0;
				updateEditPins();
			});
		};
		window.addEventListener('scroll', schedule, true);
		window.addEventListener('resize', schedule);
		if (window.visualViewport) {
			window.visualViewport.addEventListener('resize', schedule);
			window.visualViewport.addEventListener('scroll', schedule);
		}
		window.addEventListener('load', schedule);
	}

	function updateEditPins() {
		var hostId = 'aio-login-pro-customizer-pins';
		var host = document.getElementById(hostId);
		if (!host) return;

		function getPin(id) {
			return host.querySelector('[data-pin="' + id + '"]');
		}

		function ensurePin(id, section, ariaLabel) {
			var existing = getPin(id);
			if (!existing) {
				existing = document.createElement('button');
				existing.type = 'button';
				existing.className = id === 'templates' ? 'aio-pin aio-pin--templates' : 'aio-pin';
				existing.setAttribute('data-pin', id);
				existing.setAttribute('aria-label', ariaLabel || 'Edit section');
				existing.innerHTML =
					id === 'templates'
						? '<svg class="aio-pin-ico" viewBox="0 0 24 24" aria-hidden="true"><rect width="7" height="7" x="3" y="3" rx="1" ry="1"/><rect width="7" height="7" x="14" y="3" rx="1" ry="1"/><rect width="7" height="7" x="14" y="14" rx="1" ry="1"/><rect width="7" height="7" x="3" y="14" rx="1" ry="1"/></svg>'
						: '<svg class="aio-pin-ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/><path d="m15 5 4 4"/></svg>';
				existing.addEventListener('click', function () {
					try {
						if (window.parent) {
							window.parent.postMessage({ type: 'aioLoginFocusSection', section: section }, '*');
						}
					} catch (e) { }
				});
				host.appendChild(existing);
			}
			return existing;
		}

		var PIN = 28;
		var PIN_GAP = 8;
		var PIN_PAD = 8;

		/** Pin just outside target rect (left preferred; right if not enough space) — vertical center. */
		function placePinBesideRect(el, rect) {
			if (!rect || rect.width < 1 || rect.height < 1) return;
			var vw = window.innerWidth || 0;
			var vh = window.innerHeight || 0;
			var cy = rect.top + rect.height / 2;
			var xLeft = rect.left - PIN - PIN_GAP;
			var xRight = rect.right + PIN_GAP;
			var x;
			if (xLeft >= PIN_PAD) {
				x = xLeft;
			} else if (xRight + PIN <= vw - PIN_PAD) {
				x = xRight;
			} else {
				x = Math.max(PIN_PAD, Math.min(rect.left - PIN - PIN_GAP, vw - PIN - PIN_PAD));
			}
			cy = Math.max(PIN_PAD, Math.min(cy, vh - PIN_PAD));
			el.style.left = Math.round(x) + 'px';
			el.style.top = Math.round(cy) + 'px';
			el.style.transform = 'translate(0,-50%)';
		}

		/** Background pin: top-right of #login so it does not stack on the logo (was top-left). */
		function placePinTopRightInRect(el, rect) {
			if (!rect || rect.width < 1 || rect.height < 1) return;
			var vw = window.innerWidth || 0;
			var vh = window.innerHeight || 0;
			var x = rect.right - PIN_GAP - PIN;
			var y = rect.top + PIN_GAP;
			x = Math.max(PIN_PAD, Math.min(x, vw - PIN - PIN_PAD));
			y = Math.max(PIN_PAD, Math.min(y, vh - PIN - PIN_PAD));
			el.style.left = Math.round(x) + 'px';
			el.style.top = Math.round(y) + 'px';
			el.style.transform = 'translate(0,0)';
		}

		/** Templates pin: top-left inside #login column (2×2 layout grid = templates). */
		function placeTemplatesPinTopLeft(el, loginRect) {
			if (!loginRect || loginRect.width < 1) return;
			var vw = window.innerWidth || 0;
			var vh = window.innerHeight || 0;
			var x = loginRect.left + PIN_GAP;
			var y = loginRect.top + PIN_GAP;
			x = Math.max(PIN_PAD, Math.min(x, vw - PIN - PIN_PAD));
			y = Math.max(PIN_PAD, Math.min(y, vh - PIN - PIN_PAD));
			el.style.left = Math.round(x) + 'px';
			el.style.top = Math.round(y) + 'px';
			el.style.transform = 'translate(0,0)';
		}

		function removePin(id) {
			var p = getPin(id);
			if (p && p.parentNode) p.parentNode.removeChild(p);
		}

		// Logo — prefer the <a> box; if it has no layout size (bg-only logo), use the heading wrapper
		var logoEl = document.querySelector('#login h1.wp-login-logo a, #login .wp-login-logo a, #login h1 a');
		var lr = logoEl ? logoEl.getBoundingClientRect() : null;
		if (!logoEl || !lr || lr.width < 2 || lr.height < 2) {
			logoEl = document.querySelector('#login h1.wp-login-logo, #login h1, .wp-login-logo');
			lr = logoEl ? logoEl.getBoundingClientRect() : null;
		}
		if (logoEl && lr && lr.width > 1 && lr.height > 1) {
			var pl = ensurePin('logo', 'aio_login_logo');
			placePinBesideRect(pl, lr);
		} else removePin('logo');

		// Form — beside first username/email field (not whole form) so pin stays off the logo when h1 is inside #loginform
		var formEl = document.querySelector('#loginform') || document.querySelector('#lostpasswordform') || document.querySelector('#registerform');
		if (formEl) {
			var formAnchor =
				formEl.querySelector('#user_login, #user_email, input[name="log"], input[name="user_login"]') ||
				formEl.querySelector('input[type="text"], input[type="email"]') ||
				formEl;
			var fr = formAnchor.getBoundingClientRect();
			if (fr.width > 1 && fr.height > 1) {
				var pf = ensurePin('form', 'aio_login_form');
				placePinBesideRect(pf, fr);
			} else removePin('form');
		} else removePin('form');

		// Button — outside submit (was overlapping label inside the button)
		var btnEl = document.querySelector('#loginform input[type="submit"].button-primary, #loginform input[type="submit"]');
		if (!btnEl) btnEl = document.querySelector('#lostpasswordform input[type="submit"], #registerform input[type="submit"]');
		if (!btnEl) btnEl = document.querySelector('#loginform .button-primary');
		if (btnEl) {
			var br = btnEl.getBoundingClientRect();
			if (br.width > 1 && br.height > 1) {
				var pb = ensurePin('button', 'aio_login_button');
				placePinBesideRect(pb, br);
			} else removePin('button');
		} else removePin('button');

		// Lost your password? (main login only) — Customize Forgot Password section
		var loginFormForNav = document.querySelector('#loginform');
		var forgotNavA = null;
		if (loginFormForNav) {
			forgotNavA =
				document.querySelector('#login #nav a[href*="lostpassword"], #login #nav a[href*="action%3Dlostpassword"]') ||
				document.querySelector('#login a[href*="lostpassword"]');
		}
		if (forgotNavA) {
			var nvr = forgotNavA.getBoundingClientRect();
			if (nvr.width > 1 && nvr.height > 1) {
				var pForgot = ensurePin('forgot', 'aio_login_forgot', 'Customize forgot password');
				placePinBesideRect(pForgot, nvr);
			} else removePin('forgot');
		} else removePin('forgot');

		// Background + Template — same #login column (template pin opens Themes / template grid)
		var loginBox = document.querySelector('#login');
		if (loginBox) {
			var gr = loginBox.getBoundingClientRect();
			if (gr.width > 1 && gr.height > 1) {
				var pbg = ensurePin('background', 'aio_login_background');
				placePinTopRightInRect(pbg, gr);
				var pTpl = ensurePin('templates', 'aio_login_themes', 'Edit template');
				placeTemplatesPinTopLeft(pTpl, gr);
			} else {
				removePin('background');
				removePin('templates');
			}
		} else {
			removePin('background');
			removePin('templates');
			removePin('forgot');
		}
	}

	function ensureEditPins() {
		var hostId = 'aio-login-pro-customizer-pins';
		var host = document.getElementById(hostId);
		if (!host) {
			host = document.createElement('div');
			host.id = hostId;
			document.body.appendChild(host);
		}
		ensureEditPinsHostStyle();
		bindEditPinRelayout();
		updateEditPins();
		requestAnimationFrame(function () {
			updateEditPins();
			setTimeout(updateEditPins, 120);
			setTimeout(updateEditPins, 400);
		});
	}

	var state = {
		templateSlug: '',
		bgColor: '',
		bgRepeat: '',
		bgPos: '',
		bgSize: '',
		fontFamily: 'Inherit',
		disableLogo: false,
		logoW: '',
		logoH: '',
		logoMB: '',
		logoTitle: '',
		logoUrl: '',
		_logoTitleTouched: false,
		_logoUrlTouched: false,
		formTransparent: false,
		formW: '',
		formMinH: '',
		formRadius: '',
		formShadow: '',
		formShadowOpacity: '0.15',
		formPadding: '',
		formBorder: '',
		inputMargin: '',
		inputBg: '',
		inputText: '',
		inputW: '',
		labelColor: '',
		rememberColor: '',
		labelSize: '',
		rememberSize: '',
		linkColor: '',
		btnBg: '',
		btnHover: '',
		btnText: '',
		btnBorder: '',
		btnPad: '',
		btnSize: '',
		btnRadius: '',
		btnShadow: '',
		btnShadowOpacity: '0.2',
		btnTextSize: '',
		forgotBgColor: '',
		forgotBgImageUrl: '',
		customCss: ''
	};

	function applyLogoLinkState() {
		var a =
			document.querySelector('#login h1.wp-login-logo a') ||
			document.querySelector('#login h1 a') ||
			document.querySelector('.wp-login-logo a');
		if (!a) {
			return;
		}
		if (state._logoTitleTouched) {
			var t = state.logoTitle == null ? '' : String(state.logoTitle);
			a.textContent = t;
			if (t) {
				a.setAttribute('title', t);
				a.setAttribute('aria-label', t);
			} else {
				a.removeAttribute('title');
				a.removeAttribute('aria-label');
			}
		}
		if (state._logoUrlTouched && state.logoUrl) {
			try {
				a.setAttribute('href', state.logoUrl);
			} catch (e) {}
		}
	}

	function render() {
		var oldBadge = document.getElementById('aio-login-pro-customizer-connected');
		if (oldBadge && oldBadge.parentNode) {
			oldBadge.parentNode.removeChild(oldBadge);
		}
		ensureFontLink(state.fontFamily);
		var css = buildCSS(state);
		// In Customizer preview, login notices/errors are not actionable and often appear due to auth/session state.
		// Hide them to keep the design preview clean.
		try {
			var qs = String(window.location.search || '');
			if (qs.indexOf('customize_changeset_uuid=') !== -1 || qs.indexOf('aio_login_customizer_preview=') !== -1) {
				// Always hide #login_error in preview (validation errors are not actionable here).
				// On lost password, keep p.message (instruction) visible — it is not #login_error.
				css += '\nbody.login #login_error { display: none !important; }';
				css +=
					'\nbody.login:not(.login-action-lostpassword) .message, body.login:not(.login-action-lostpassword) .success { display: none !important; }';
			}
		} catch (e) { }
		ensureStyleEl().textContent = css;
		ensureEditPins();
		applyLogoLinkState();
	}

	function bind(settingId, handler) {
		// No-op: wp-login.php preview does not load customize-preview runtime.
		// Settings are pushed from controls via postMessage, see message listener below.
	}

	// Background
	// initial render
	render();

	// Listen for setting updates pushed from Customizer controls.
	window.addEventListener('message', function (evt) {
		var d = evt && evt.data ? evt.data : null;
		if (!d || d.type !== 'aioLoginSetting') return;
		var id = String(d.id || '');
		var v = d.value;

		switch (id) {
			case 'aio_login__customization_templates':
				state.templateSlug = v === null || typeof v === 'undefined' ? '' : String(v);
				break;
			case 'aio_login_background_color': state.bgColor = v; break;
			case 'aio_login_el_background_repeat': state.bgRepeat = v; break;
			case 'aio_login_el_background_position': state.bgPos = v; break;
			case 'aio_login_el_background_size': state.bgSize = v; break;
			case 'aio_login_el_text_font_family': state.fontFamily = v; break;
			case 'aio_login_disable_logo': state.disableLogo = !!v; break;
			case 'aio_login_logo_width': state.logoW = v; break;
			case 'aio_login_logo_height': state.logoH = v; break;
			case 'aio_login_margin_bottom': state.logoMB = v; break;
			case 'aio_login_logo_title':
				state.logoTitle = v === null || typeof v === 'undefined' ? '' : String(v);
				// Skip first empty sync so we do not wipe core default link text rendered by PHP.
				if (state._logoTitleTouched || state.logoTitle !== '') {
					state._logoTitleTouched = true;
				}
				break;
			case 'aio_login_logo_url':
				state.logoUrl = v === null || typeof v === 'undefined' ? '' : String(v).trim();
				if (state._logoUrlTouched || state.logoUrl !== '') {
					state._logoUrlTouched = true;
				}
				break;
			case 'aio_login_el_form_transparent': state.formTransparent = !!v; break;
			case 'aio_login_el_form_width': state.formW = v; break;
			case 'aio_login_el_form_min_height': state.formMinH = v; break;
			case 'aio_login_el_form_border_radius': state.formRadius = v; break;
			case 'aio_login_el_form_shadow': state.formShadow = v; break;
			case 'aio_login_el_form_shadow_opacity': state.formShadowOpacity = v; break;
			case 'aio_login_el_form_padding': state.formPadding = v; break;
			case 'aio_login_el_form_border': state.formBorder = v; break;
			case 'aio_login_el_input_margin': state.inputMargin = v; break;
			case 'aio_login_el_input_bg_color': state.inputBg = v; break;
			case 'aio_login_el_input_text_color': state.inputText = v; break;
			case 'aio_login_el_input_width': state.inputW = v; break;
			case 'aio_login_el_label_color': state.labelColor = v; break;
			case 'aio_login_el_remember_label_color': state.rememberColor = v; break;
			case 'aio_login_el_label_font_size': state.labelSize = v; break;
			case 'aio_login_el_remember_font_size': state.rememberSize = v; break;
			case 'aio_login_el_link_color': state.linkColor = v; break;
			case 'aio_login_el_btn_bg_color': state.btnBg = v; break;
			case 'aio_login_el_btn_hover_color': state.btnHover = v; break;
			case 'aio_login_el_btn_text_color': state.btnText = v; break;
			case 'aio_login_el_btn_border_color': state.btnBorder = v; break;
			case 'aio_login_el_btn_padding': state.btnPad = v; break;
			case 'aio_login_el_btn_size': state.btnSize = v; break;
			case 'aio_login_el_btn_border_radius': state.btnRadius = v; break;
			case 'aio_login_el_btn_shadow': state.btnShadow = v; break;
			case 'aio_login_el_btn_shadow_opacity': state.btnShadowOpacity = v; break;
			case 'aio_login_el_btn_text_size': state.btnTextSize = v; break;
			case 'aio_login_forgot_background_color': state.forgotBgColor = v; break;
			case 'aio_login_forgot_background_image':
				state.forgotBgImageUrl = v && typeof v === 'object' && v.url ? String(v.url) : '';
				break;
			case 'aio_login_custom-css': state.customCss = v; break;
			default:
				// ignore
				break;
		}

		render();
	});
})();

