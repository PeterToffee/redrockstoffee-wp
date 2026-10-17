(function () {
	'use strict';

	var rendered = new WeakMap();
	var renderTimer = null;
	var rendering = false;

	function getConfig() {
		return window.aioLoginWooCaptcha || {};
	}

	function normalizeSize(size) {
		if (!size || 'flexible' === size) {
			return 'normal';
		}
		return size;
	}

	function normalizeTheme(theme) {
		if (!theme || 'auto' === theme) {
			return 'light';
		}
		return theme;
	}

	function widgetLooksRendered(el) {
		if (!el) {
			return false;
		}
		if (el.querySelector('iframe')) {
			return true;
		}
		if (el.shadowRoot) {
			return !!(el.shadowRoot.querySelector('iframe') || el.shadowRoot.querySelector('input[name="cf-turnstile-response"]'));
		}
		return false;
	}

	function purgeDuplicateTurnstileFields(form) {
		if (!form) {
			return;
		}

		var keeper = form.querySelector('input.aio-login-cf-turnstile-response[name="cf-turnstile-response"]');
		form.querySelectorAll('input[name="cf-turnstile-response"]').forEach(function (input) {
			if (input === keeper) {
				return;
			}
			if (input.closest('.cf-turnstile') || input.classList.contains('aio-login-cf-turnstile-response')) {
				return;
			}
			input.parentNode.removeChild(input);
		});
	}

	function ensureTurnstileResponseField(form) {
		if (!form) {
			return null;
		}

		purgeDuplicateTurnstileFields(form);

		var field = form.querySelector('input.aio-login-cf-turnstile-response[name="cf-turnstile-response"]');
		if (!field) {
			field = document.createElement('input');
			field.type = 'hidden';
			field.name = 'cf-turnstile-response';
			field.className = 'aio-login-cf-turnstile-response';
			field.value = '';
			field.setAttribute('autocomplete', 'off');
			form.insertBefore(field, form.firstChild);
		}

		return field;
	}

	function syncFormTurnstileTokens(form) {
		if (!form) {
			return;
		}

		var field = ensureTurnstileResponseField(form);
		if (!field) {
			return;
		}

		form.querySelectorAll('.cf-turnstile[data-aio-turnstile-rendered="1"]').forEach(function (widgetEl) {
			var widgetId = rendered.get(widgetEl);
			if (widgetId === undefined || !window.turnstile || typeof window.turnstile.getResponse !== 'function') {
				return;
			}

			var response = window.turnstile.getResponse(widgetId);
			if (response) {
				field.value = response;
			}
		});
	}

	function syncTurnstileToken(widgetEl, token) {
		if (!widgetEl) {
			return;
		}

		var form = widgetEl.closest('form');
		var field = ensureTurnstileResponseField(form);
		if (!field) {
			return;
		}

		if (token) {
			field.value = token;
			return;
		}

		var widgetId = rendered.get(widgetEl);
		if (widgetId !== undefined && window.turnstile && typeof window.turnstile.getResponse === 'function') {
			var response = window.turnstile.getResponse(widgetId);
			if (response) {
				field.value = response;
			}
		}
	}

	function bindTurnstileFormSync() {
		document.addEventListener(
			'submit',
			function (event) {
				var form = event.target;
				if (!form || !form.querySelectorAll) {
					return;
				}

				if (!form.matches('form.woocommerce-form-login, form.login, form.woocommerce-form-register, form.woocommerce-form--login')) {
					return;
				}

				syncFormTurnstileTokens(form);
			},
			true
		);

		document.addEventListener(
			'click',
			function (event) {
				var target = event.target;
				if (!target || !target.closest) {
					return;
				}

				var btn = target.closest('button[name="login"], .woocommerce-form-login__submit, button.woocommerce-form-register__submit');
				if (!btn) {
					return;
				}

				var form = btn.closest('form');
				if (!form) {
					return;
				}

				syncFormTurnstileTokens(form);
			},
			true
		);
	}

	function isCheckoutAjaxLoginUrl(url) {
		if (!url || typeof url !== 'string') {
			return false;
		}

		return /(?:wc-ajax=|[?&])fc_checkout_login(?:[/?&]|$)/.test(url)
			|| /(?:wc-ajax=|[?&])checkout_login(?:[/?&]|$)/.test(url);
	}

	function getWooLoginForm() {
		return document.querySelector('form.woocommerce-form-login, form.login, form.woocommerce-form--login');
	}

	function getTurnstileTokenForAjax() {
		var form = getWooLoginForm();
		if (!form) {
			return '';
		}

		syncFormTurnstileTokens(form);
		var field = form.querySelector('input.aio-login-cf-turnstile-response[name="cf-turnstile-response"]');
		return field && field.value ? field.value : '';
	}

	function appendTurnstileTokenToPayload(data, token) {
		if (!token) {
			return data;
		}

		if (!data) {
			return 'cf-turnstile-response=' + encodeURIComponent(token);
		}

		if (typeof data === 'string') {
			if (data.indexOf('cf-turnstile-response=') !== -1) {
				return data.replace(/cf-turnstile-response=[^&]*/g, 'cf-turnstile-response=' + encodeURIComponent(token));
			}
			return data + (data.length ? '&' : '') + 'cf-turnstile-response=' + encodeURIComponent(token);
		}

		if (typeof data === 'object') {
			if (typeof FormData !== 'undefined' && data instanceof FormData) {
				data.set('cf-turnstile-response', token);
				return data;
			}
			data['cf-turnstile-response'] = token;
		}

		return data;
	}

	function resetTurnstileWidgets() {
		document.querySelectorAll('form.woocommerce-form-login, form.login, form.woocommerce-form--login').forEach(function (form) {
			var field = form.querySelector('input.aio-login-cf-turnstile-response[name="cf-turnstile-response"]');
			if (field) {
				field.value = '';
			}
		});

		document.querySelectorAll('.cf-turnstile[data-aio-turnstile-rendered="1"]').forEach(function (widgetEl) {
			var widgetId = rendered.get(widgetEl);
			if (widgetId !== undefined && window.turnstile && typeof window.turnstile.reset === 'function') {
				try {
					window.turnstile.reset(widgetId);
				} catch (e) {}
			}
			syncTurnstileToken(widgetEl, '');
		});
	}

	function loginErrorNoticeHasContent(notice) {
		if (!notice) {
			return false;
		}

		var text = (notice.textContent || '').replace(/\s+/g, ' ').trim();
		return text.length > 0;
	}

	function bindCheckoutAjaxCaptchaSync() {
		if (typeof jQuery !== 'undefined') {
			jQuery(document).ajaxSend(function (event, jqXHR, settings) {
				if (!settings || !isCheckoutAjaxLoginUrl(settings.url)) {
					return;
				}

				var token = getTurnstileTokenForAjax();
				if (!token) {
					return;
				}

				settings.data = appendTurnstileTokenToPayload(settings.data, token);
			});

			jQuery(document).ajaxComplete(function (event, jqXHR, settings) {
				if (!settings || !isCheckoutAjaxLoginUrl(settings.url)) {
					return;
				}

				var response = jqXHR.responseJSON;
				if (!response && jqXHR.responseText) {
					try {
						response = JSON.parse(jqXHR.responseText);
					} catch (e) {
						response = null;
					}
				}

				if (!response || response.result !== 'error') {
					return;
				}

				// Turnstile tokens are one-time use; any failed login requires a fresh challenge.
				resetTurnstileWidgets();
			});
		}

		if (typeof window.fetch === 'function' && !window.aioLoginWooCaptchaFetchPatched) {
			window.aioLoginWooCaptchaFetchPatched = true;
			var nativeFetch = window.fetch.bind(window);

			window.fetch = function (input, init) {
				var url = typeof input === 'string' ? input : (input && input.url ? input.url : '');
				if (!isCheckoutAjaxLoginUrl(url)) {
					return nativeFetch(input, init);
				}

				var token = getTurnstileTokenForAjax();
				if (!token) {
					return nativeFetch(input, init);
				}

				init = init || {};
				var headers = init.headers || {};

				if (init.body instanceof FormData) {
					init.body.set('cf-turnstile-response', token);
				} else if (typeof init.body === 'string') {
					init.body = appendTurnstileTokenToPayload(init.body, token);
				} else if (init.body && typeof init.body === 'object') {
					init.body = appendTurnstileTokenToPayload(Object.assign({}, init.body), token);
					init.headers = Object.assign({}, headers, { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' });
					init.body = new URLSearchParams(init.body).toString();
				} else {
					init.body = appendTurnstileTokenToPayload('', token);
					init.headers = Object.assign({}, headers, { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' });
				}

				return nativeFetch(input, init);
			};
		}
	}

	function resetTurnstileAfterLoginError() {
		var hasLoginError = false;
		var loginFormSelector = 'form.woocommerce-form-login, form.login, form.woocommerce-form--login';

		document.querySelectorAll('.fc-login-messages').forEach(function (notice) {
			if (loginErrorNoticeHasContent(notice)) {
				hasLoginError = true;
			}
		});

		if (!hasLoginError && document.querySelector(loginFormSelector)) {
			document.querySelectorAll('.woocommerce-error, .woocommerce-notices .woocommerce-error').forEach(function (notice) {
				if (loginErrorNoticeHasContent(notice)) {
					hasLoginError = true;
				}
			});
		}

		if (hasLoginError) {
			resetTurnstileWidgets();
		}
	}

	function renderTurnstile(el) {
		if (!el || widgetLooksRendered(el) || rendered.has(el)) {
			return;
		}

		var sitekey = el.getAttribute('data-sitekey') || '';
		if (!sitekey || !window.turnstile || typeof window.turnstile.render !== 'function') {
			return;
		}

		var form = el.closest('form');
		ensureTurnstileResponseField(form);

		var renderOptions = {
			sitekey: sitekey,
			theme: normalizeTheme(el.getAttribute('data-theme')),
			size: normalizeSize(el.getAttribute('data-size')),
			'response-field': false,
			callback: function (token) {
				syncTurnstileToken(el, token);
			},
			'error-callback': function () {
				el.classList.add('aio-login-woo-captcha--error');
				syncTurnstileToken(el, '');
			},
			'expired-callback': function () {
				syncTurnstileToken(el, '');
				rendered.delete(el);
				el.removeAttribute('data-aio-turnstile-rendered');
				el.innerHTML = '';
				renderTurnstile(el);
			},
		};

		var language = el.getAttribute('data-language');
		if (language && 'auto' !== language) {
			renderOptions.language = language;
		}

		var runRender = function () {
			try {
				var widgetId = window.turnstile.render(el, renderOptions);
				rendered.set(el, widgetId);
				el.setAttribute('data-aio-turnstile-rendered', '1');
				el.classList.remove('aio-login-woo-captcha--error');
				syncTurnstileToken(el);
			} catch (e) {
				el.classList.add('aio-login-woo-captcha--error');
			}
		};

		if (typeof window.turnstile.ready === 'function') {
			window.turnstile.ready(runRender);
		} else {
			runRender();
		}
	}

	function renderHcaptcha(el) {
		if (!el || widgetLooksRendered(el) || rendered.has(el)) {
			return;
		}

		var sitekey = el.getAttribute('data-sitekey') || '';
		if (!sitekey || !window.hcaptcha || typeof window.hcaptcha.render !== 'function') {
			return;
		}

		try {
			var widgetId = window.hcaptcha.render(el, {
				sitekey: sitekey,
				theme: el.getAttribute('data-theme') || 'light',
				size: el.getAttribute('data-size') || 'normal',
			});
			rendered.set(el, widgetId);
			el.setAttribute('data-aio-hcaptcha-rendered', '1');
		} catch (e) {}
	}

	function renderAll(root) {
		var scope = root && root.querySelectorAll ? root : document;

		scope.querySelectorAll('.aio-login-woo-captcha .cf-turnstile[data-sitekey]').forEach(renderTurnstile);
		scope.querySelectorAll('.aio-login-woo-captcha .h-captcha[data-sitekey]').forEach(renderHcaptcha);
	}

	function shouldReactToMutation(mutations) {
		for (var i = 0; i < mutations.length; i++) {
			var added = mutations[i].addedNodes;
			if (!added || !added.length) {
				continue;
			}
			for (var j = 0; j < added.length; j++) {
				var node = added[j];
				if (node.nodeType !== 1) {
					continue;
				}
				if (
					node.matches &&
					(
						node.matches('.wc-block-components-modal, .woocommerce-form-login, form.login, .aio-login-woo-captcha, .cf-turnstile') ||
						node.querySelector('.wc-block-components-modal, .woocommerce-form-login, form.login, .aio-login-woo-captcha, .cf-turnstile')
					)
				) {
					return true;
				}
			}
		}
		return false;
	}

	function runRenderPipeline(root) {
		if (rendering) {
			return;
		}

		rendering = true;

		try {
			if (typeof window.aioLoginFixWooLoginForms === 'function') {
				window.aioLoginFixWooLoginForms();
			} else if (typeof window.aioLoginPositionFormCaptchas === 'function') {
				window.aioLoginPositionFormCaptchas();
			}

			var scope = root && root.querySelectorAll ? root : document;
			renderAll(scope);
		} finally {
			rendering = false;
		}
	}

	function scheduleRender(root) {
		if (renderTimer) {
			clearTimeout(renderTimer);
		}

		renderTimer = setTimeout(function () {
			renderTimer = null;
			runRenderPipeline(root);
		}, 100);
	}

	window.aioLoginRenderWooCaptchas = scheduleRender;

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () {
			bindTurnstileFormSync();
			bindCheckoutAjaxCaptchaSync();
			resetTurnstileAfterLoginError();
			scheduleRender(document);
		});
	} else {
		bindTurnstileFormSync();
		bindCheckoutAjaxCaptchaSync();
		resetTurnstileAfterLoginError();
		scheduleRender(document);
	}

	document.addEventListener('click', function (event) {
		var target = event.target;
		if (!target || !target.closest) {
			return;
		}
		if (
			target.closest('.showlogin, .wc-block-components-checkout-login-prompt, .wc-block-components-checkout-returning-customer-login, [data-block-name="woocommerce/checkout-contact-information-block"] button')
		) {
			scheduleRender(document);
		}
	}, true);

	if ('undefined' !== typeof MutationObserver) {
		var observerTimer = null;
		var observer = new MutationObserver(function (mutations) {
			if (!shouldReactToMutation(mutations)) {
				return;
			}
			if (observerTimer) {
				clearTimeout(observerTimer);
			}
			observerTimer = setTimeout(function () {
				observerTimer = null;
				scheduleRender(document);
			}, 200);
		});

		observer.observe(document.body, { childList: true, subtree: true });
	}

	var config = getConfig();
	if (config.turnstileScript && !document.querySelector('script[src*="challenges.cloudflare.com/turnstile"]')) {
		var script = document.createElement('script');
		script.src = config.turnstileScript;
		script.async = true;
		script.defer = true;
		script.onload = function () { scheduleRender(document); };
		document.head.appendChild(script);
	}
})();
