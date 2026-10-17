(function (window) {
	'use strict';

	/**
	 * Render captcha inside a passwordless panel (OTP / login link).
	 *
	 * @param {HTMLElement} container Captcha mount node.
	 * @param {object}        captcha  Provider config from PHP.
	 * @param {Function}      onChange Called when verification state changes.
	 */
	function render(container, captcha, onChange) {
		if (!container) {
			return;
		}
		onChange = typeof onChange === 'function' ? onChange : function () {};

		if (!captcha || !captcha.provider) {
			container.hidden = true;
			container.innerHTML = '';
			return;
		}

		container.hidden = false;
		container.innerHTML = '';
		delete container.dataset.recaptchaWidgetId;
		delete container.dataset.hcaptchaWidgetId;
		delete container.dataset.turnstileWidgetId;

		var provider = captcha.provider;

		if (provider === 'recaptcha' && captcha.version === 'v2') {
			var recaptchaEl = document.createElement('div');
			recaptchaEl.className = 'g-recaptcha';
			recaptchaEl.setAttribute('data-sitekey', captcha.site_key);
			container.appendChild(recaptchaEl);

			(function waitRecaptcha(attempts) {
				if (window.grecaptcha && window.grecaptcha.render) {
					var widgetId = window.grecaptcha.render(recaptchaEl, {
						sitekey: captcha.site_key,
						theme: captcha.theme || 'light',
						callback: onChange,
						'expired-callback': onChange,
					});
					container.dataset.recaptchaWidgetId = String(widgetId);
					return;
				}
				if (attempts < 40) {
					setTimeout(function () {
						waitRecaptcha(attempts + 1);
					}, 100);
				}
			})(0);
			return;
		}

		if (provider === 'recaptcha' && captcha.version === 'v3') {
			container.hidden = true;
			container.innerHTML = '';

			(function waitRecaptchaV3(attempts) {
				if (window.grecaptcha && typeof window.grecaptcha.execute === 'function') {
					window.grecaptcha.ready(function () {
						onChange();
					});
					return;
				}
				if (attempts < 40) {
					setTimeout(function () {
						waitRecaptchaV3(attempts + 1);
					}, 100);
				}
			})(0);
			return;
		}

		if (provider === 'hcaptcha') {
			var hcaptchaEl = document.createElement('div');
			hcaptchaEl.className = 'h-captcha';
			container.appendChild(hcaptchaEl);

			(function waitHcaptcha(attempts) {
				if (window.hcaptcha && window.hcaptcha.render) {
					var widgetId = window.hcaptcha.render(hcaptchaEl, {
						sitekey: captcha.site_key,
						theme: captcha.theme || 'light',
						size: captcha.size || 'normal',
						callback: onChange,
						'expired-callback': onChange,
					});
					container.dataset.hcaptchaWidgetId = String(widgetId);
					return;
				}
				if (attempts < 40) {
					setTimeout(function () {
						waitHcaptcha(attempts + 1);
					}, 100);
				}
			})(0);
			return;
		}

		if (provider === 'turnstile') {
			var turnstileEl = document.createElement('div');
			turnstileEl.className = 'cf-turnstile';
			container.appendChild(turnstileEl);

			(function waitTurnstile(attempts) {
				if (window.turnstile && window.turnstile.render) {
					var widgetId = window.turnstile.render(turnstileEl, {
						sitekey: captcha.site_key,
						theme: captcha.theme || 'auto',
						callback: onChange,
						'expired-callback': onChange,
					});
					container.dataset.turnstileWidgetId = String(widgetId);
					return;
				}
				if (attempts < 40) {
					setTimeout(function () {
						waitTurnstile(attempts + 1);
					}, 100);
				}
			})(0);
		}
	}

	/**
	 * @param {HTMLElement} container
	 * @param {object}        captcha
	 * @return {boolean}
	 */
	function satisfied(container, captcha) {
		if (!captcha || !captcha.provider) {
			return true;
		}

		var provider = captcha.provider;

		if (provider === 'recaptcha') {
			if (captcha.version === 'v3') {
				return !!(window.grecaptcha && typeof window.grecaptcha.execute === 'function');
			}
			if (!container || !container.dataset.recaptchaWidgetId || !window.grecaptcha) {
				return false;
			}
			return window.grecaptcha.getResponse(container.dataset.recaptchaWidgetId) !== '';
		}

		if (provider === 'hcaptcha') {
			if (!container || !container.dataset.hcaptchaWidgetId || !window.hcaptcha) {
				return false;
			}
			return window.hcaptcha.getResponse(container.dataset.hcaptchaWidgetId) !== '';
		}

		if (provider === 'turnstile') {
			var input = container && container.querySelector('[name="cf-turnstile-response"]');
			return !!(input && input.value);
		}

		return true;
	}

	/**
	 * @param {object} captcha
	 * @param {string} action
	 * @return {Promise<string>}
	 */
	function getRecaptchaV3Token(captcha, action) {
		action = action || 'login';

		if (!captcha || captcha.provider !== 'recaptcha' || captcha.version !== 'v3' || !captcha.site_key) {
			return Promise.resolve('');
		}

		return new Promise(function (resolve) {
			(function waitRecaptchaV3(attempts) {
				if (window.grecaptcha && typeof window.grecaptcha.execute === 'function') {
					window.grecaptcha.ready(function () {
						window.grecaptcha.execute(captcha.site_key, { action: action })
							.then(function (token) {
								resolve(token || '');
							})
							.catch(function () {
								resolve('');
							});
					});
					return;
				}
				if (attempts < 40) {
					setTimeout(function () {
						waitRecaptchaV3(attempts + 1);
					}, 100);
					return;
				}
				resolve('');
			})(0);
		});
	}

	/**
	 * @param {HTMLElement} container
	 * @param {object}        captcha
	 * @param {FormData}      fd
	 */
	function appendToFormData(container, captcha, fd) {
		if (!captcha || !captcha.provider || !fd) {
			return;
		}

		var provider = captcha.provider;

		if (provider === 'recaptcha') {
			if (captcha.version === 'v3') {
				return;
			}
			var recaptchaId = container && container.dataset.recaptchaWidgetId;
			var recaptchaResp = '';
			if (window.grecaptcha && recaptchaId) {
				recaptchaResp = window.grecaptcha.getResponse(recaptchaId);
			}
			fd.append('g-recaptcha-response', recaptchaResp);
			return;
		}

		if (provider === 'hcaptcha') {
			var hcaptchaId = container && container.dataset.hcaptchaWidgetId;
			if (window.hcaptcha && hcaptchaId) {
				fd.append('h-captcha-response', window.hcaptcha.getResponse(hcaptchaId));
			}
			return;
		}

		if (provider === 'turnstile') {
			var turnstileInput = container && container.querySelector('[name="cf-turnstile-response"]');
			if (turnstileInput) {
				fd.append('cf-turnstile-response', turnstileInput.value);
			}
		}
	}

	/**
	 * Append captcha fields, fetching a fresh reCAPTCHA v3 token when needed.
	 *
	 * @param {HTMLElement} container
	 * @param {object}        captcha
	 * @param {FormData}      fd
	 * @return {Promise<void>}
	 */
	function appendToFormDataAsync(container, captcha, fd) {
		if (!captcha || !captcha.provider || !fd) {
			return Promise.resolve();
		}

		if (captcha.provider === 'recaptcha' && captcha.version === 'v3') {
			return getRecaptchaV3Token(captcha, 'login').then(function (token) {
				fd.append('g-recaptcha-response', token);
			});
		}

		appendToFormData(container, captcha, fd);
		return Promise.resolve();
	}

	window.aioLoginPasswordlessCaptcha = {
		render: render,
		satisfied: satisfied,
		appendToFormData: appendToFormData,
		appendToFormDataAsync: appendToFormDataAsync,
		getRecaptchaV3Token: getRecaptchaV3Token,
	};
})(window);
