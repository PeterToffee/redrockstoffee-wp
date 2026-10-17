(function () {
	'use strict';

	if (typeof aioLoginMagicLink === 'undefined') {
		return;
	}

	var loginEl = document.getElementById('login');
	var wcMode = !!aioLoginMagicLink.isWooCommerce;
	var panel = document.getElementById('aio-login-magic-link-panel');

	if (!panel) {
		return;
	}

	var activeHost = null;

	var steps = {
		contact: panel.querySelector('[data-ml-step="contact"]'),
		sent: panel.querySelector('[data-ml-step="sent"]'),
	};

	var els = {
		error: panel.querySelector('.aio-login-magic-link-step--contact .aio-login-otp-panel__error'),
		email: document.getElementById('aio-login-magic-link-email'),
		captcha: panel.querySelector('.aio-login-magic-link-captcha'),
		sendBtn: panel.querySelector('.aio-login-magic-link-send'),
	};

	function showError(message, useHtml) {
		if (!els.error) {
			return;
		}
		if (useHtml) {
			els.error.innerHTML = message || '';
		} else {
			els.error.textContent = message || '';
		}
		els.error.hidden = !message;
	}

	function formatNotRegisteredError(data) {
		var msg = (data && data.message) ? data.message : '';
		var registerUrl = (data && data.register_url) || aioLoginMagicLink.registerUrl || '';
		if (!registerUrl) {
			return msg;
		}
		var label = (aioLoginMagicLink.i18n && aioLoginMagicLink.i18n.registerLink) ? aioLoginMagicLink.i18n.registerLink : 'Register';
		return msg + ' <a href="' + registerUrl.replace(/"/g, '&quot;') + '">' + label + '</a>';
	}

	function showStep(name) {
		Object.keys(steps).forEach(function (key) {
			if (!steps[key]) {
				return;
			}
			steps[key].hidden = key !== name;
		});
	}

	function getHostFromLauncher(btn) {
		var checkoutBlock = btn.closest(
			'.woocommerce-checkout-social-login-block, .aio-login-woocommerce-magic-link-checkout, .aio-login-magic-link-checkout-footer-block'
		);
		if (checkoutBlock) {
			return checkoutBlock;
		}
		var form = btn.closest('form.woocommerce-form-login, form.woocommerce-form-register, form.login');
		if (form) {
			return form;
		}
		return btn.closest('.u-column1, .u-column2, .col-1, .col-2');
	}

	function getCheckoutEmailValue() {
		var selectors = [
			'.wc-block-components-email-input input[type="email"]',
			'input#email[type="email"]',
			'input[name="email"][type="email"]',
			'#billing_email',
			'input[name="billing_email"]',
		];
		for (var i = 0; i < selectors.length; i++) {
			var input = document.querySelector(selectors[i]);
			if (input && input.value) {
				return input.value.trim();
			}
		}
		return '';
	}

	function prefillEmailFromCheckout() {
		if (!els.email || !document.body.classList.contains('woocommerce-checkout')) {
			return;
		}
		var checkoutEmail = getCheckoutEmailValue();
		if (checkoutEmail) {
			els.email.value = checkoutEmail;
		}
	}

	function mountPanelInsideLoginform() {
		var body = document.body.classList;
		return body.contains('aio-login__template-09')
			|| body.contains('aio-login__template-06')
			|| body.contains('aio-login__template-01');
	}

	function mountPanelInLogin() {
		if (wcMode) {
			return;
		}
		if (!loginEl) {
			loginEl = document.getElementById('login');
		}
		if (!loginEl || !panel) {
			return;
		}
		var form = document.getElementById('loginform');
		if (mountPanelInsideLoginform() && form) {
			if (panel.parentNode === form) {
				return;
			}
			if (document.body.classList.contains('aio-login__template-09')) {
				var logo = form.querySelector('h1');
				if (logo) {
					form.insertBefore(panel, logo.nextSibling);
					return;
				}
			}
			form.insertBefore(panel, form.firstChild);
			return;
		}
		if (form && form.parentNode) {
			if (panel.parentNode === form.parentNode && panel.nextElementSibling === form) {
				return;
			}
			form.parentNode.insertBefore(panel, form);
			return;
		}
		if (panel.parentNode !== loginEl) {
			loginEl.insertBefore(panel, loginEl.firstChild);
		}
	}

	function mountPanelForWoo() {
		if (!wcMode || !activeHost || !activeHost.parentNode) {
			return;
		}
		if (panel.parentNode === activeHost.parentNode) {
			return;
		}
		activeHost.parentNode.insertBefore(panel, activeHost.nextSibling);
	}

	mountPanelInLogin();

	function insertAfter(node, reference) {
		if (!reference || !reference.parentNode) {
			return;
		}
		if (reference.nextSibling) {
			reference.parentNode.insertBefore(node, reference.nextSibling);
		} else {
			reference.parentNode.appendChild(node);
		}
	}

	function positionSocialAfterMagicLink() {
		var forms = document.querySelectorAll(
			'form#loginform, form.woocommerce-form-login, form.woocommerce-form-register'
		);
		forms.forEach(function (form) {
			var social = form.querySelector('.aio-login-social-login-buttons-wrapper');
			var magicLink = form.querySelector('.aio-login-magic-link-buttons-wrapper');
			if (social && magicLink && form.contains(social) && form.contains(magicLink)) {
				insertAfter(social, magicLink);
			}
		});
	}

	function positionMagicLinkButton() {
		var forms = document.querySelectorAll('form#loginform, form.woocommerce-form-login, form.woocommerce-form-register');
		forms.forEach(function (form) {
			var wrapper = form.querySelector('.aio-login-magic-link-buttons-wrapper');
			if (!wrapper) {
				return;
			}

			var otpWrapper = form.querySelector('.aio-login-otp-buttons-wrapper');
			if (otpWrapper && form.contains(otpWrapper)) {
				insertAfter(wrapper, otpWrapper);
				return;
			}

			var social = form.querySelector('.aio-login-social-login-buttons-wrapper');
			if (social && form.contains(social)) {
				form.insertBefore(wrapper, social);
				return;
			}

			var captcha = form.querySelector(
				'.h-captcha, .g-recaptcha, .cf-turnstile, .aio-login-turnstile-widget-error, input[name="cf-turnstile-error-code"]'
			);
			if (captcha && form.contains(captcha)) {
				form.insertBefore(wrapper, captcha);
				return;
			}
			var remember = form.querySelector('p.forgetmenot, .woocommerce-form-login__rememberme');
			if (remember) {
				var rememberRow = remember.closest('p.form-row, p') || remember;
				if (rememberRow.parentNode === form) {
					form.insertBefore(wrapper, rememberRow);
					return;
				}
			}
			var submit = form.querySelector('p.submit, .woocommerce-form-login__submit, .woocommerce-form-register__submit');
			if (submit) {
				var row = submit.closest('p') || submit;
				if (row.parentNode === form) {
					form.insertBefore(wrapper, row);
				}
			}
		});
	}

	function scheduleLoginFormExtrasLayout() {
		mountPanelInLogin();
		positionMagicLinkButton();
		positionSocialAfterMagicLink();
		if (typeof window.aioLoginPositionFormCaptchas === 'function') {
			window.aioLoginPositionFormCaptchas();
		}
	}

	function schedulePositionMagicLinkButton() {
		scheduleLoginFormExtrasLayout();
		setTimeout(scheduleLoginFormExtrasLayout, 0);
		setTimeout(scheduleLoginFormExtrasLayout, 150);
		setTimeout(scheduleLoginFormExtrasLayout, 500);
		setTimeout(scheduleLoginFormExtrasLayout, 800);
	}

	schedulePositionMagicLinkButton();
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', schedulePositionMagicLinkButton);
	}

	function showMagicLinkView() {
		if (loginEl) {
			loginEl.classList.remove('aio-login-otp-view--active');
			loginEl.classList.add('aio-login-magic-link-view--active');
		}
		if (wcMode) {
			document.body.classList.add('aio-login-magic-link-view--active');
			if (activeHost) {
				activeHost.classList.add('aio-login-magic-link-host--hidden');
				mountPanelForWoo();
			}
		}
		var otpPanel = document.getElementById('aio-login-otp-panel');
		if (otpPanel) {
			otpPanel.hidden = true;
			otpPanel.setAttribute('aria-hidden', 'true');
		}
		panel.hidden = false;
		panel.setAttribute('aria-hidden', 'false');
	}

	function showLoginView() {
		if (loginEl) {
			loginEl.classList.remove('aio-login-magic-link-view--active');
		}
		if (wcMode) {
			document.body.classList.remove('aio-login-magic-link-view--active');
			document.querySelectorAll('.aio-login-magic-link-host--hidden').forEach(function (host) {
				host.classList.remove('aio-login-magic-link-host--hidden');
			});
		}
		panel.hidden = true;
		panel.setAttribute('aria-hidden', 'true');
		showError('');
	}

	function captchaSatisfied() {
		if (!aioLoginMagicLink.captchaRequired || !window.aioLoginPasswordlessCaptcha) {
			return true;
		}
		return window.aioLoginPasswordlessCaptcha.satisfied(els.captcha, aioLoginMagicLink.captcha);
	}

	function updateSendEnabled() {
		if (!els.sendBtn) {
			return;
		}
		var ok = els.email && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(els.email.value.trim());
		if (ok && aioLoginMagicLink.captchaRequired) {
			ok = captchaSatisfied();
		}
		els.sendBtn.disabled = !ok;
	}

	function renderCaptcha() {
		if (!aioLoginMagicLink.captchaRequired || !window.aioLoginPasswordlessCaptcha || !els.captcha) {
			if (els.captcha) {
				els.captcha.hidden = true;
			}
			return;
		}
		els.captcha.hidden = false;
		window.aioLoginPasswordlessCaptcha.render(els.captcha, aioLoginMagicLink.captcha, updateSendEnabled);
	}

	function appendCaptchaToFormData(fd) {
		if (!aioLoginMagicLink.captchaRequired || !window.aioLoginPasswordlessCaptcha) {
			return Promise.resolve();
		}
		return window.aioLoginPasswordlessCaptcha.appendToFormDataAsync(els.captcha, aioLoginMagicLink.captcha, fd);
	}

	function ajaxSend(email) {
		var fd = new FormData();
		fd.append('action', 'aio_login_magic_link_send');
		fd.append('nonce', aioLoginMagicLink.nonce);
		fd.append('email', email);
		if (aioLoginMagicLink.isCheckout || document.body.classList.contains('woocommerce-checkout')) {
			fd.append('context', 'checkout');
		}
		return appendCaptchaToFormData(fd).then(function () {
			return fetch(aioLoginMagicLink.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) {
				return r.json();
			});
		});
	}

	function openPanel(launcher) {
		activeHost = launcher ? getHostFromLauncher(launcher) : null;
		showError('');
		showStep('contact');
		prefillEmailFromCheckout();
		showMagicLinkView();
		renderCaptcha();
		updateSendEnabled();
		setTimeout(function () {
			renderCaptcha();
			updateSendEnabled();
			if (els.email) {
				els.email.focus();
			}
		}, 80);
	}

	function doSend() {
		if (!els.email || els.sendBtn.disabled) {
			return;
		}
		els.sendBtn.disabled = true;
		ajaxSend(els.email.value.trim())
			.then(function (res) {
				els.sendBtn.disabled = false;
				if (!res.success) {
					if (res.data && res.data.code === 'not_registered') {
						showError(formatNotRegisteredError(res.data), true);
					} else {
						showError((res.data && res.data.message) || 'Error');
					}
					updateSendEnabled();
					return;
				}
				showStep('sent');
			})
			.catch(function () {
				els.sendBtn.disabled = false;
				showError('Network error');
				updateSendEnabled();
			});
	}

	document.addEventListener('click', function (e) {
		var launcher = e.target.closest('.aio-login-magic-link-launcher');
		if (!launcher) {
			return;
		}
		e.preventDefault();
		openPanel(launcher);
	});

	panel.querySelectorAll('[data-ml-back-to-login]').forEach(function (btn) {
		btn.addEventListener('click', showLoginView);
	});

	if (els.email) {
		els.email.addEventListener('input', updateSendEnabled);
		els.email.addEventListener('keydown', function (e) {
			if (e.key === 'Enter') {
				e.preventDefault();
				doSend();
			}
		});
	}

	if (els.sendBtn) {
		els.sendBtn.addEventListener('click', doSend);
	}
})();
