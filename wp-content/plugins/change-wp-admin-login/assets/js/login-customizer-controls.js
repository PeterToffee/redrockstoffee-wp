(function () {
	'use strict';
	if (!window.wp || !wp.customize) return;

	/**
	 * WordPress wraps preview messaging; wp-login.php iframe does not run customize-preview.js,
	 * so it never receives wp.customize.previewer.send() — we must always postMessage our own payload.
	 */
	function getPreviewIframe() {
		try {
			if (wp.customize && wp.customize.previewer && wp.customize.previewer.container) {
				var c = wp.customize.previewer.container;
				if (c && c.find) {
					var jq = c.find('iframe');
					if (jq && jq.length) {
						return jq[0];
					}
				}
				if (c && c.length && c[0] && c[0].querySelector) {
					var domIframe = c[0].querySelector('iframe');
					if (domIframe) {
						return domIframe;
					}
				}
			}
		} catch (e) {}
		return (
			document.querySelector('#customize-preview iframe') ||
			document.querySelector('#customize-preview-frame iframe') ||
			document.querySelector('.wp-full-overlay-main iframe') ||
			document.querySelector('iframe[name="customize-preview"]')
		);
	}

	function sendToPreview(id, value) {
		var iframe = getPreviewIframe();
		if (!iframe || !iframe.contentWindow) {
			return;
		}
		try {
			iframe.contentWindow.postMessage({ type: 'aioLoginSetting', id: id, value: value }, '*');
		} catch (e) {}
	}

	/**
	 * wp-login preview iframe does not always reload when the template setting changes; body can keep a dark
	 * template class while the sidebar shows a light template. Inject neutral CSS from the controls frame
	 * (same-origin) so the live preview updates immediately.
	 */
	function aioLoginInjectStaleDarkNeutralizerInPreview(slug) {
		var s = String(slug || '');
		var isDark = s === 'template-2' || s === 'template-4' || s === 'template-7';
		var iframe = getPreviewIframe();
		if (!iframe) {
			return;
		}
		var doc = null;
		try {
			doc = iframe.contentDocument || (iframe.contentWindow && iframe.contentWindow.document);
		} catch (err) {
			return;
		}
		if (!doc || !doc.head) {
			return;
		}
		var id = 'aio-login-customizer-neutralize-dark';
		var el = doc.getElementById(id);
		if (isDark) {
			if (el && el.parentNode) {
				el.parentNode.removeChild(el);
			}
			return;
		}
		if (!s) {
			return;
		}
		if (!el) {
			el = doc.createElement('style');
			el.id = id;
			doc.head.appendChild(el);
		}
		el.textContent =
			'body.login.aio-login__template-02,body.login.aio-login__template-05,body.login.aio-login__template-08{background:#f1f1f1 !important;}';
	}

	var AIO_LOGIN_MIDNIGHT_PREV_BG_LS = 'aio_login_midnight_prev_bg_v1';

	var aioLoginMidnightBgStash = { active: false, value: '' };
	var aioLoginPrevTemplateForBgSync = '';

	function aioLoginNormalizeBgColorForStash(v) {
		if (v === null || typeof v === 'undefined') {
			return '';
		}
		return String(v);
	}

	function aioLoginPersistMidnightPrevBg(value) {
		try {
			localStorage.setItem(AIO_LOGIN_MIDNIGHT_PREV_BG_LS, JSON.stringify(aioLoginNormalizeBgColorForStash(value)));
		} catch (eLs) {}
	}

	function aioLoginReadMidnightPrevBg() {
		try {
			var raw = localStorage.getItem(AIO_LOGIN_MIDNIGHT_PREV_BG_LS);
			if (raw === null) {
				return null;
			}
			return JSON.parse(raw);
		} catch (eRead) {
			return null;
		}
	}

	function aioLoginIsMidnightBlackHex(val) {
		var cur = String(val || '').replace(/\s/g, '').toLowerCase();
		return cur === '#000' || cur === '#000000';
	}

	/**
	 * Midnight Dark (template-2): force background color to black while selected; restore prior color when leaving.
	 * Persists pre-midnight color in localStorage so it still restores after publish + full reload.
	 */
	function aioLoginSyncBgColorWithMidnightTemplate(newSlug, oldSlug) {
		var fromMidnight = String(oldSlug || '') === 'template-2';
		var toMidnight = String(newSlug || '') === 'template-2';
		if (fromMidnight === toMidnight) {
			return;
		}
		try {
			wp.customize('aio_login_background_color', function (bg) {
				if (toMidnight && !fromMidnight) {
					var v = aioLoginNormalizeBgColorForStash(bg.get());
					aioLoginMidnightBgStash = { active: true, value: v };
					aioLoginPersistMidnightPrevBg(v);
					bg.set('#000000');
				} else if (!toMidnight && fromMidnight) {
					if (aioLoginMidnightBgStash.active) {
						bg.set(aioLoginMidnightBgStash.value);
						aioLoginMidnightBgStash.active = false;
					} else if (aioLoginIsMidnightBlackHex(bg.get())) {
						bg.set('#f1f1f1');
					}
				}
			});
		} catch (eBg) {}
	}

	var SETTING_IDS = [
		'aio_login_background_color',
		'aio_login_background_image',
		'aio_login_background_image_mobile',
		'aio_login__customization_templates',
		'aio_login_el_text_font_family',
		'aio_login_el_background_repeat',
		'aio_login_el_background_position',
		'aio_login_el_background_size',
		'aio_login_el_form_transparent',
		'aio_login_el_form_width',
		'aio_login_el_form_min_height',
		'aio_login_el_form_border_radius',
		'aio_login_el_form_shadow',
		'aio_login_el_form_shadow_opacity',
		'aio_login_el_form_padding',
		'aio_login_el_form_border',
		'aio_login_el_btn_bg_color',
		'aio_login_el_btn_hover_color',
		'aio_login_el_btn_text_color',
		'aio_login_el_btn_border_color',
		'aio_login_el_btn_size',
		'aio_login_el_btn_padding',
		'aio_login_el_btn_border_radius',
		'aio_login_el_btn_shadow',
		'aio_login_el_btn_shadow_opacity',
		'aio_login_el_btn_text_size',
		'aio_login_el_label_color',
		'aio_login_el_remember_label_color',
		'aio_login_el_label_font_size',
		'aio_login_el_remember_font_size',
		'aio_login_el_input_margin',
		'aio_login_el_input_bg_color',
		'aio_login_el_input_text_color',
		'aio_login_el_input_width',
		'aio_login_el_link_color',
		'aio_login_disable_logo',
		'aio_login_logo',
		'aio_login_logo_width',
		'aio_login_logo_height',
		'aio_login_margin_bottom',
		'aio_login_logo_url',
		'aio_login_logo_title',
		'aio_login_login_page_title',
		'aio_login_favicon',
		'aio_login_forgot_background_color',
		'aio_login_el_btn_padding_tb',
		'aio_login_custom-css'
	];

	function focusSection(sectionId) {
		if (!sectionId) return;
		var section = wp.customize.section(sectionId);
		if (section && section.focus) section.focus();
	}

	function bindSetting(id) {
		wp.customize(id, function (setting) {
			sendToPreview(id, setting.get());
			setting.bind(function (to) {
				sendToPreview(id, to);
			});
		});
	}

	var pushForgotBgImageUrl = function () {};

	function pushAllSettings() {
		SETTING_IDS.forEach(function (id) {
			try {
				wp.customize(id, function (setting) {
					sendToPreview(id, setting.get());
				});
			} catch (e) {}
		});
		if (typeof pushForgotBgImageUrl === 'function') {
			pushForgotBgImageUrl();
		}
	}

	function getCustomizerCfg() {
		return (
			window.aioLoginCustomizer || {
				hasPro: true,
				popupTitle: 'To access more features and options',
				popupButtonLabel: 'Get AIO Login Pro',
				popupUrl: 'https://aiologin.com/pricing/?utm_source=plugin&utm_medium=pro_pop_up&utm_campaign=plugin',
				pricingUrl: 'https://aiologin.com/pricing/?utm_source=plugin&utm_medium=pro_pop_up&utm_campaign=plugin',
				freeTemplateSlugs: ['default', 'template-8'],
				assetsUrl: '',
				resetDefaults: {},
				resetConfirm: '',
			}
		);
	}

	/**
	 * Apply default values to all AIO login Customizer settings (live preview + dirty state until Publish).
	 */
	function aioLoginResetAllToDefaults() {
		var cfg = getCustomizerCfg();
		var defs = cfg.resetDefaults || {};
		var msg = cfg.resetConfirm || '';
		if (msg && !window.confirm(msg)) {
			return;
		}
		Object.keys(defs).forEach(function (id) {
			try {
				wp.customize(id, function (setting) {
					setting.set(defs[id]);
				});
			} catch (e) {}
		});
		setTimeout(function () {
			try {
				wp.customize('aio_login_el_text_font_family', function (fam) {
					var v = fam.get();
					if (v) {
						aioLoginEnsureGoogleFontCss(v);
					}
				});
			} catch (e2) {}
			pushAllSettings();
		}, 100);
		setTimeout(pushAllSettings, 500);
	}

	function aioLoginEscAttr(str) {
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/"/g, '&quot;')
			.replace(/</g, '&lt;');
	}

	function aioLoginCssUrl(u) {
		return 'url("' + String(u).replace(/\\/g, '\\\\').replace(/"/g, '\\"') + '")';
	}

	function ensureProModal() {
		var el = document.getElementById('aio-login-customizer-pro-modal');
		if (el) {
			return el;
		}
		var cfg = getCustomizerCfg();
		var ctaUrl = cfg.popupUrl || cfg.pricingUrl || '';
		var modalTitle = cfg.popupTitle || 'To access more features and options';
		var modalBtn =
			cfg.popupButtonLabel ||
			(cfg.hasPro ? 'Get Business Plan Now' : 'Get AIO Login Pro');
		var assetsBase = (cfg.assetsUrl || '').replace(/\/?$/, '/');
		var isAppsumoVariant = String(cfg.upgradePopupVariant || '').toLowerCase() === 'appsumo';
		var logoSrc = isAppsumoVariant
			? assetsBase + 'images/logo.svg'
			: assetsBase + 'images/dashboard-logo-white.png';

		el = document.createElement('div');
		el.id = 'aio-login-customizer-pro-modal';
		el.className = 'aio-login__popup-wrapper aio-login-customizer-pro-modal';
		el.hidden = true;
		el.setAttribute('role', 'dialog');
		el.setAttribute('aria-modal', 'true');
		if (isAppsumoVariant) {
			var appsumoBase = assetsBase + 'images/';
			el.innerHTML =
				'<div class="aio-login__popup-container aio-login-customizer-pro-popup-box" style="width:550px;height:483px;border:0;background:transparent;box-shadow:none">' +
				'<div style="position:relative;width:100%;height:100%;border-radius:16px;background:linear-gradient(148deg,#f6f1ff 4%,#efe3ff 46%,#dcc3ff 100%);box-shadow:0 22px 38px rgba(34,22,74,.22);box-sizing:border-box;overflow:hidden;">' +
				'<span class="aio-login__popup-close" style="position:absolute;right:13px;top:19px;z-index:3;"><button type="button" style="width:26px;height:26px;padding:0;border-radius:50%;background:transparent;color:#6a35cf;border:none;font-size:27px;cursor:pointer;line-height:1" aria-label="Close">&times;</button></span>' +
				'<div style="position:absolute;left:50%;top:40px;transform:translateX(-50%);">' +
				'<img src="' + aioLoginEscAttr(logoSrc) + '" alt="AIO Login" style="display:block;width:196px;height:72px;object-fit:contain" />' +
				'</div>' +
				'<div style="position:absolute;left:50%;top:154px;transform:translateX(-50%);width:471px;">' +
				'<div style="display:flex;align-items:center;justify-content:center;gap:8px;">' +
				'<h2 style="margin:0;font-family:Poppins,Segoe UI,Arial,sans-serif;font-size:30px;font-weight:700;line-height:1;color:#6e16df;white-space:nowrap;">Go Unlimited with AIO Login</h2>' +
				'<img src="' + aioLoginEscAttr(appsumoBase + 'infinity.svg') + '" alt="" aria-hidden="true" style="width:40px;height:20px;flex:0 0 40px;" />' +
				'</div>' +
				'<p style="margin:14px 0 0;text-align:center;font-family:Poppins,Segoe UI,Arial,sans-serif;font-size:18px;font-weight:500;line-height:1.4;color:#3c0c79;">Unlimited Sites + Lifetime Access</p>' +
				'</div>' +
				'<div style="position:absolute;left:54px;top:267px;width:484px;display:grid;grid-template-columns:1fr 1fr;column-gap:26px;row-gap:13px;">' +
				'<div style="display:flex;align-items:center;gap:12px;font-family:Poppins,Segoe UI,Arial,sans-serif;font-size:14.246px;font-weight:600;line-height:1.4;color:#3c0c79;"><img src="' + aioLoginEscAttr(appsumoBase + 'feature-2fa.svg') + '" alt="" aria-hidden="true" style="width:20px;height:20px;flex:0 0 20px;display:block;"><span>2FA Authenticator</span></div>' +
				'<div style="display:flex;align-items:center;gap:12px;font-family:Poppins,Segoe UI,Arial,sans-serif;font-size:14.246px;font-weight:600;line-height:1.4;color:#3c0c79;"><img src="' + aioLoginEscAttr(appsumoBase + 'feature-ip.svg') + '" alt="" aria-hidden="true" style="width:20px;height:20px;flex:0 0 20px;display:block;"><span>IP Protection</span></div>' +
				'<div style="display:flex;align-items:center;gap:12px;font-family:Poppins,Segoe UI,Arial,sans-serif;font-size:14.246px;font-weight:600;line-height:1.4;color:#3c0c79;"><img src="' + aioLoginEscAttr(appsumoBase + 'feature-social.svg') + '" alt="" aria-hidden="true" style="width:20px;height:20px;flex:0 0 20px;display:block;"><span>Social Login</span><span style="display:inline-flex;align-items:center;gap:5px;margin-left:-2px;"><img src="' + aioLoginEscAttr(appsumoBase + 'social-discord.svg') + '" alt="" aria-hidden="true" style="width:20px;height:20px;display:block;"><img src="' + aioLoginEscAttr(appsumoBase + 'social-apple.svg') + '" alt="" aria-hidden="true" style="width:20px;height:20px;display:block;"></span></div>' +
				'<div style="display:flex;align-items:center;gap:12px;font-family:Poppins,Segoe UI,Arial,sans-serif;font-size:14.246px;font-weight:600;line-height:1.4;color:#3c0c79;"><img src="' + aioLoginEscAttr(appsumoBase + 'feature-cloudflare.svg') + '" alt="" aria-hidden="true" style="width:20px;height:20px;flex:0 0 20px;display:block;"><span>Cloudflare turnstile</span></div>' +
				'<div style="display:flex;align-items:center;gap:12px;font-family:Poppins,Segoe UI,Arial,sans-serif;font-size:14.246px;font-weight:600;line-height:1.4;color:#3c0c79;"><img src="' + aioLoginEscAttr(appsumoBase + 'feature-woo.svg') + '" alt="" aria-hidden="true" style="width:20px;height:20px;flex:0 0 20px;display:block;"><span>WooCommerce Integration</span></div>' +
				'<div style="display:flex;align-items:center;gap:12px;font-family:Poppins,Segoe UI,Arial,sans-serif;font-size:14.246px;font-weight:600;line-height:1.4;color:#3c0c79;"><img src="' + aioLoginEscAttr(appsumoBase + 'feature-customizer.svg') + '" alt="" aria-hidden="true" style="width:20px;height:20px;flex:0 0 20px;display:block;"><span>Login Page Customizer</span></div>' +
				'</div>' +
				'<a href="' + aioLoginEscAttr(ctaUrl) + '" target="_blank" rel="noopener noreferrer" style="position:absolute;left:193px;top:396px;width:164px;height:47px;display:inline-flex;align-items:center;justify-content:center;border-radius:10px;background:#6e16df;color:#fff;text-decoration:none;font-family:Poppins,Segoe UI,Arial,sans-serif;font-size:18px;font-weight:600;line-height:1.5;">Upgrade Now</a>' +
				'</div></div>';
		} else {
			var freemiusBase = assetsBase + 'images/';
			el.innerHTML =
				'<div class="aio-login__popup-container aio-login-customizer-pro-popup-box" style="width:550px;height:483px;border:0;background:transparent;box-shadow:none">' +
				'<div style="position:relative;width:100%;height:100%;border-radius:30px;background:linear-gradient(129.44deg,#FFFFFF 9.97%,#6E16DF 225.3%);box-sizing:border-box;overflow:hidden;">' +
				'<span class="aio-login__popup-close" style="position:absolute;right:13px;top:19px;z-index:3;"><button type="button" style="width:26px;height:26px;padding:0;border-radius:50%;background:transparent;color:#6a35cf;border:none;font-size:27px;cursor:pointer;line-height:1" aria-label="Close">&times;</button></span>' +
				'<div style="position:absolute;left:50%;top:36px;transform:translateX(-50%);">' +
				'<img src="' + aioLoginEscAttr(freemiusBase + 'logo.svg') + '" alt="AIO Login" style="display:block;width:196px;height:72px;object-fit:contain" />' +
				'</div>' +
				'<h2 style="position:absolute;top:156px;left:50%;transform:translateX(-50%);margin:0;width:460px;text-align:center;font-family:Poppins,Segoe UI,Arial,sans-serif;font-size:30px;font-weight:700;line-height:1.2;color:#6e16df;">To access more features and options</h2>' +
				'<div style="position:absolute;left:33px;top:264px;width:484px;display:grid;grid-template-columns:1fr 1fr;column-gap:26px;row-gap:13px;">' +
				'<div style="display:flex;align-items:center;gap:10px;font-family:Poppins,Segoe UI,Arial,sans-serif;font-size:14px;font-weight:500;line-height:1.35;color:#3c0c79;"><img src="' + aioLoginEscAttr(freemiusBase + 'feature-2fa.svg') + '" alt="" aria-hidden="true" style="width:20px;height:20px;flex:0 0 20px;display:block;"><span>2FA Authenticator</span></div>' +
				'<div style="display:flex;align-items:center;gap:10px;font-family:Poppins,Segoe UI,Arial,sans-serif;font-size:14px;font-weight:500;line-height:1.35;color:#3c0c79;"><img src="' + aioLoginEscAttr(freemiusBase + 'feature-ip.svg') + '" alt="" aria-hidden="true" style="width:20px;height:20px;flex:0 0 20px;display:block;"><span>IP Protection</span></div>' +
				'<div style="display:flex;align-items:center;gap:10px;font-family:Poppins,Segoe UI,Arial,sans-serif;font-size:14px;font-weight:500;line-height:1.35;color:#3c0c79;"><img src="' + aioLoginEscAttr(freemiusBase + 'feature-social.svg') + '" alt="" aria-hidden="true" style="width:20px;height:20px;flex:0 0 20px;display:block;"><span>Social Login</span><span style="display:inline-flex;align-items:center;gap:4px;"><img src="' + aioLoginEscAttr(freemiusBase + 'social-discord.svg') + '" alt="" aria-hidden="true" style="width:20px;height:20px;display:block;"><img src="' + aioLoginEscAttr(freemiusBase + 'social-apple.svg') + '" alt="" aria-hidden="true" style="width:20px;height:20px;display:block;"><img src="' + aioLoginEscAttr(freemiusBase + 'social-google.svg') + '" alt="" aria-hidden="true" style="width:20px;height:20px;display:block;"></span></div>' +
				'<div style="display:flex;align-items:center;gap:10px;font-family:Poppins,Segoe UI,Arial,sans-serif;font-size:14px;font-weight:500;line-height:1.35;color:#3c0c79;"><img src="' + aioLoginEscAttr(freemiusBase + 'feature-customizer.svg') + '" alt="" aria-hidden="true" style="width:20px;height:20px;flex:0 0 20px;display:block;"><span>Security Notification</span><span style="display:inline-flex;align-items:center;gap:4px;"><span style="width:20px;height:20px;display:inline-flex;align-items:center;justify-content:center;background:#fff;border-radius:50%;box-shadow:0 1px 2px rgba(17,24,39,.18);"><img src="' + aioLoginEscAttr(freemiusBase + 'icons/notification-slack.svg') + '" alt="" aria-hidden="true" style="width:12px;height:12px;display:block;"></span><span style="width:20px;height:20px;display:inline-flex;align-items:center;justify-content:center;background:#fff;border-radius:50%;box-shadow:0 1px 2px rgba(17,24,39,.18);"><img src="' + aioLoginEscAttr(freemiusBase + 'icons/notification-webhook.svg') + '" alt="" aria-hidden="true" style="width:12px;height:12px;display:block;"></span></span></div>' +
				'<div style="display:flex;align-items:center;gap:10px;font-family:Poppins,Segoe UI,Arial,sans-serif;font-size:14px;font-weight:500;line-height:1.35;color:#3c0c79;"><img src="' + aioLoginEscAttr(freemiusBase + 'feature-woo.svg') + '" alt="" aria-hidden="true" style="width:20px;height:20px;flex:0 0 20px;display:block;"><span>WooCommerce Integration</span></div>' +
				'<div style="display:flex;align-items:center;gap:10px;font-family:Poppins,Segoe UI,Arial,sans-serif;font-size:14px;font-weight:500;line-height:1.35;color:#3c0c79;"><img src="' + aioLoginEscAttr(freemiusBase + 'feature-cloudflare.svg') + '" alt="" aria-hidden="true" style="width:20px;height:20px;flex:0 0 20px;display:block;"><span>Cloudflare turnstile</span></div>' +
				'</div>' +
				'<a href="' + aioLoginEscAttr(ctaUrl) + '" target="_blank" rel="noopener noreferrer" style="position:absolute;left:50%;top:388px;transform:translateX(-50%);display:inline-flex;align-items:center;justify-content:center;padding:15px 20px;min-width:238px;border-radius:10px;background:#6e16df;color:#fff;text-decoration:none;font-family:Poppins,Segoe UI,Arial,sans-serif;font-size:18px;font-weight:600;line-height:1.5;">' + aioLoginEscAttr(modalBtn) + '</a>' +
				'</div></div>';
		}

		document.body.appendChild(el);

		var proBox = el.querySelector('.aio-login-pro__container');
		if (proBox && assetsBase && !isAppsumoVariant) {
			proBox.style.backgroundImage =
				aioLoginCssUrl(assetsBase + 'images/popup-left.png') + ', ' + aioLoginCssUrl(assetsBase + 'images/popup-right.png');
		}

		el.addEventListener('click', function (e) {
			if (e.target === el) {
				closeProModal();
			}
		});
		var closeBtn = el.querySelector('.aio-login__popup-close button');
		if (closeBtn) {
			closeBtn.addEventListener('click', function (e) {
				e.preventDefault();
				closeProModal();
			});
		}
		if (!window.__aioLoginCustomizerModalEsc) {
			window.__aioLoginCustomizerModalEsc = true;
			document.addEventListener('keydown', function (e) {
				if (e.key === 'Escape') {
					closeProModal();
				}
			});
		}
		return el;
	}

	function showProModal() {
		var modal = ensureProModal();
		modal.hidden = false;
	}

	function closeProModal() {
		var modal = document.getElementById('aio-login-customizer-pro-modal');
		if (modal) {
			modal.hidden = true;
		}
	}

	function aioLoginEnsureGoogleFontCss(family) {
		if (!family || family === 'Inherit') {
			return;
		}
		var id = 'aio-gf-' + String(family).replace(/[^a-z0-9]+/gi, '-');
		if (document.getElementById(id)) {
			return;
		}
		var link = document.createElement('link');
		link.id = id;
		link.rel = 'stylesheet';
		link.href =
			'https://fonts.googleapis.com/css2?family=' +
			encodeURIComponent(family).replace(/%20/g, '+') +
			':wght@400;600&display=swap';
		document.head.appendChild(link);
	}

	function aioLoginInitFontGridLazyLoad() {
		if (typeof IntersectionObserver === 'undefined') {
			document.querySelectorAll('.aio-login__customizer-font-item').forEach(function (btn) {
				aioLoginEnsureGoogleFontCss(btn.getAttribute('data-font'));
			});
			return;
		}
		var obs = new IntersectionObserver(
			function (entries) {
				entries.forEach(function (entry) {
					if (!entry.isIntersecting) {
						return;
					}
					var btn = entry.target;
					aioLoginEnsureGoogleFontCss(btn.getAttribute('data-font'));
					obs.unobserve(btn);
				});
			},
			{ root: null, rootMargin: '0px 0px 120px 0px', threshold: 0.01 }
		);
		document.querySelectorAll('.aio-login__customizer-font-item').forEach(function (btn) {
			obs.observe(btn);
		});
	}

	/**
	 * Move selected template + font tiles to the top row (after publish or on initial load of saved values).
	 */
	function aioLoginReorganizeAllGrids() {
		jQuery('.aio-login__customizer-template-grid-wrap').each(function () {
			aioLoginReorganizeTemplateGrid(jQuery(this));
		});
		jQuery('.aio-login__customizer-font-grid-wrap').each(function () {
			aioLoginReorganizeFontGrid(jQuery(this));
		});
	}

	/**
	 * Move tiles so the selected template is in the top full-width row (Customizer does not re-render PHP).
	 */
	function aioLoginReorganizeTemplateGrid($wrap) {
		var $selected = $wrap.find('.aio-login__customizer-template-item.is-selected').first();
		if (!$selected.length) {
			return;
		}

		var $selRow = $wrap.find('.aio-login__customizer-template-grid--selected');
		var cfg = getCustomizerCfg();
		var freeSlugs = cfg.freeTemplateSlugs || ['default', 'template-8'];
		var splitFree = $wrap.find('.aio-login__customizer-template-catalog-free').length > 0;

		var $all = $wrap.find('.aio-login__customizer-template-item').detach();

		$all.each(function () {
			var $btn = jQuery(this);
			var isSel = $btn.hasClass('is-selected');
			var slug = String($btn.data('value') || '');

			if (isSel) {
				$selRow.append($btn);
				return;
			}

			if (splitFree) {
				var isFree = freeSlugs.indexOf(slug) !== -1;
				if (isFree) {
					$wrap
						.find('.aio-login__customizer-template-catalog-free .aio-login__customizer-template-grid--catalog')
						.append($btn);
				} else {
					$wrap
						.find('.aio-login__customizer-pro-gate-area .aio-login__customizer-template-grid--catalog')
						.append($btn);
				}
			} else {
				var $catalogRow = $wrap.find('.aio-login__customizer-template-grid--catalog').first();
				if ($catalogRow.length) {
					$catalogRow.append($btn);
				}
			}
		});
	}

	/**
	 * Move tiles so the selected font is in the full-width row under search (Customizer does not re-render PHP).
	 */
	function aioLoginReorganizeFontGrid($wrap) {
		var $selected = $wrap.find('.aio-login__customizer-font-item.is-selected').first();
		if (!$selected.length) {
			return;
		}

		var $selRow = $wrap.find('.aio-login__customizer-font-grid--selected');
		var $catalogRow = $wrap.find('.aio-login__customizer-font-grid--catalog');

		var $all = $wrap.find('.aio-login__customizer-font-item').detach();

		$all.each(function () {
			var $btn = jQuery(this);
			var isSel = $btn.hasClass('is-selected');

			if (isSel) {
				$selRow.append($btn);
			} else if ($catalogRow.length) {
				$catalogRow.append($btn);
			}
		});
	}

	wp.customize.bind('ready', function () {
		SETTING_IDS.forEach(bindSetting);

		try {
			wp.customize('aio_login__customization_templates', function (setting) {
				aioLoginPrevTemplateForBgSync = String(setting.get() || '');
				if (aioLoginPrevTemplateForBgSync === 'template-2') {
					var persistedBg = aioLoginReadMidnightPrevBg();
					if (persistedBg !== null) {
						aioLoginMidnightBgStash = {
							active: true,
							value: aioLoginNormalizeBgColorForStash(persistedBg),
						};
					}
				}
				aioLoginInjectStaleDarkNeutralizerInPreview(setting.get());
				setting.bind(function (to) {
					var next = String(to || '');
					aioLoginSyncBgColorWithMidnightTemplate(next, aioLoginPrevTemplateForBgSync);
					aioLoginInjectStaleDarkNeutralizerInPreview(to);
					aioLoginPrevTemplateForBgSync = next;
				});
			});
		} catch (eTpl) {}

		wp.customize('aio_login_forgot_background_image', function (setting) {
			function push() {
				var id = parseInt(setting.get(), 10) || 0;
				function sendUrl(url) {
					sendToPreview('aio_login_forgot_background_image', { id: id, url: url || '' });
				}
				if (!id) {
					sendUrl('');
					return;
				}
				if (wp.media && wp.media.attachment) {
					var att = wp.media.attachment(id);
					if (att.get('url')) {
						sendUrl(att.get('url'));
						return;
					}
					att
						.fetch()
						.done(function () {
							sendUrl(att.get('url') || '');
						})
						.fail(function () {
							sendUrl('');
						});
					return;
				}
				sendUrl('');
			}
			pushForgotBgImageUrl = push;
			setting.bind(push);
			push();
		});

		function pushAllSettingsAndNeutralize() {
			pushAllSettings();
			try {
				wp.customize('aio_login__customization_templates', function (setting) {
					aioLoginInjectStaleDarkNeutralizerInPreview(setting.get());
				});
			} catch (eMo) {}
		}

		function attachIframeSync(ifr) {
			if (!ifr || ifr.getAttribute('data-aio-login-sync')) return;
			ifr.setAttribute('data-aio-login-sync', '1');
			ifr.addEventListener('load', function () {
				setTimeout(pushAllSettingsAndNeutralize, 50);
			});
		}

		var iframe = getPreviewIframe();
		attachIframeSync(iframe);

		var previewRoot = document.getElementById('customize-preview');
		if (previewRoot && typeof MutationObserver !== 'undefined') {
			var mo = new MutationObserver(function () {
				var next = getPreviewIframe();
				if (next) {
					attachIframeSync(next);
					setTimeout(pushAllSettingsAndNeutralize, 80);
				}
			});
			mo.observe(previewRoot, { childList: true, subtree: true });
		}

		setTimeout(pushAllSettingsAndNeutralize, 200);
		setTimeout(pushAllSettingsAndNeutralize, 800);
		setTimeout(pushAllSettingsAndNeutralize, 2000);

		// Last published selection at top on load (PHP keeps catalog order until we reorder once).
		setTimeout(aioLoginReorganizeAllGrids, 350);

		// Move selected template (and font) to the top row after any successful save — not on every thumbnail click.
		wp.customize.bind('saved', function () {
			setTimeout(aioLoginReorganizeAllGrids, 100);
		});

		window.addEventListener('message', function (evt) {
			var d = evt && evt.data ? evt.data : null;
			if (!d || d.type !== 'aioLoginFocusSection') return;
			focusSection(d.section);
		});

		// Themes section: template grid (replaces &lt;select&gt;).
		jQuery(document).on('click', '.aio-login__customizer-template-item', function (e) {
			var $btn = jQuery(this);
			if ($btn.hasClass('is-locked')) {
				e.preventDefault();
				e.stopPropagation();
				showProModal();
				return false;
			}
			e.preventDefault();
			var val = $btn.data('value');
			var $wrap = $btn.closest('.aio-login__customizer-template-grid-wrap');
			var $input = $wrap.find('input[type="hidden"][data-customize-setting-link]');
			if (!$input.length) {
				return;
			}
			var slug = val === null || typeof val === 'undefined' ? '' : String(val);
			$wrap.find('.aio-login__customizer-template-item').removeClass('is-selected').attr('aria-pressed', 'false');
			$btn.addClass('is-selected').attr('aria-pressed', 'true');
			try {
				wp.customize('aio_login__customization_templates', function (setting) {
					setting.set(slug);
				});
			} catch (err) {
				$input.val(slug).trigger('change');
			}
			aioLoginInjectStaleDarkNeutralizerInPreview(slug);
			setTimeout(function () {
				aioLoginInjectStaleDarkNeutralizerInPreview(slug);
			}, 120);
			setTimeout(function () {
				aioLoginInjectStaleDarkNeutralizerInPreview(slug);
			}, 450);
			// transport=refresh: some builds do not reload the login iframe from setting.set() alone (custom grid control).
			setTimeout(function () {
				try {
					if (wp.customize.previewer && typeof wp.customize.previewer.refresh === 'function') {
						wp.customize.previewer.refresh();
					}
				} catch (e2) {}
			}, 50);
		});

		// Google Fonts grid (replaces &lt;select&gt;).
		jQuery(document).on('click', '.aio-login__customizer-font-item', function (e) {
			var $btn = jQuery(this);
			if ($btn.hasClass('is-locked')) {
				e.preventDefault();
				e.stopPropagation();
				showProModal();
				return false;
			}
			e.preventDefault();
			var val = $btn.data('value');
			var $wrap = $btn.closest('.aio-login__customizer-font-grid-wrap');
			var $input = $wrap.find('input[type="hidden"][data-customize-setting-link]');
			if (!$input.length) {
				return;
			}
			aioLoginEnsureGoogleFontCss(val);
			$input.val(val).trigger('change');
			$wrap.find('.aio-login__customizer-font-item').removeClass('is-selected').attr('aria-pressed', 'false');
			$btn.addClass('is-selected').attr('aria-pressed', 'true');
			setTimeout(aioLoginInitFontGridLazyLoad, 60);
		});

		jQuery(document).on('input', '.aio-login__customizer-font-search', function () {
			var q = jQuery(this).val().toLowerCase().trim();
			var $wrap = jQuery(this).closest('.aio-login__customizer-font-grid-wrap');
			$wrap.find('.aio-login__customizer-font-grid--catalog .aio-login__customizer-font-item')
				.each(function () {
					var $btn = jQuery(this);
					var name = ($btn.find('.aio-login__customizer-font-name').text() || '').toLowerCase();
					var val = $btn.data('value');
					val = val ? String(val).toLowerCase() : '';
					$btn.toggle(!q || name.indexOf(q) !== -1 || val.indexOf(q) !== -1);
				});
		});

		try {
			wp.customize.section('aio_login_typography', function (section) {
				section.expanded.bind(function (isExpanded) {
					if (isExpanded) {
						setTimeout(aioLoginInitFontGridLazyLoad, 60);
					}
				});
			});
		} catch (err) {}

		try {
			wp.customize.section('aio_login_forgot', function (section) {
				section.expanded.bind(function (isExpanded) {
					if (!wp.customize.previewer || !wp.customize.previewer.previewUrl) {
						return;
					}
					var url = wp.customize.previewer.previewUrl.get();
					try {
						var u = new URL(url, window.location.origin);
						if (u.pathname.indexOf('wp-login.php') === -1) {
							return;
						}
						if (isExpanded) {
							u.searchParams.set('action', 'lostpassword');
							// Avoid carrying login/lost-password error query vars into the preview (shows bogus "empty username" etc.).
							u.searchParams.delete('error');
							u.searchParams.delete('loggedout');
							u.searchParams.delete('wp');
							u.searchParams.delete('checkemail');
						} else {
							u.searchParams.delete('action');
						}
						wp.customize.previewer.previewUrl.set(u.toString());
					} catch (e) {}
					setTimeout(pushAllSettings, 120);
					setTimeout(pushAllSettings, 500);
				});
			});
		} catch (err2) {}

		setTimeout(aioLoginInitFontGridLazyLoad, 400);
		setTimeout(aioLoginInitFontGridLazyLoad, 1500);

		jQuery(document).on('click', '.aio-login-customizer-reset-btn', function (e) {
			e.preventDefault();
			aioLoginResetAllToDefaults();
		});
	});
})();
