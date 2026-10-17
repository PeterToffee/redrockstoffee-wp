(function () {
	'use strict';

	if (typeof aioLoginOtp === 'undefined') {
		return;
	}

	var state = {
		channel: '',
		token: '',
		length: 4,
		resendIn: 0,
		resendTimer: null,
		redirectUrl: '',
		redirectTimer: null,
		redirectStarted: false,
		verifyBlocked: false,
	};

	var loginEl = document.getElementById('login');
	var panel = document.getElementById('aio-login-otp-panel');

	if (!panel) {
		return;
	}

	function mountPanelInsideLoginform() {
		var body = document.body.classList;
		return body.contains('aio-login__template-09')
			|| body.contains('aio-login__template-06')
			|| body.contains('aio-login__template-01');
	}

	function mountPanelInLogin() {
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

	mountPanelInLogin();

	/**
	 * Place OTP buttons after username/password and before social login (all templates).
	 */
	function positionOtpButtons() {
		var form = document.getElementById('loginform');
		if (!form) {
			return;
		}
		var wrapper = form.querySelector('.aio-login-otp-buttons-wrapper');
		if (!wrapper) {
			return;
		}
		var magicLink = form.querySelector('.aio-login-magic-link-buttons-wrapper');
		if (magicLink && magicLink.parentNode === form) {
			form.insertBefore(wrapper, magicLink);
			return;
		}
		var social = form.querySelector('.aio-login-social-login-buttons-wrapper');
		if (social && social.parentNode === form) {
			form.insertBefore(wrapper, social);
			return;
		}
		var remember = form.querySelector('p.forgetmenot');
		if (remember && remember.parentNode === form) {
			form.insertBefore(wrapper, remember);
			return;
		}
		var submit = form.querySelector('p.submit');
		if (submit && submit.parentNode === form) {
			form.insertBefore(wrapper, submit);
		}
	}

	positionOtpButtons();
	if (typeof window.aioLoginPositionFormCaptchas === 'function') {
		window.aioLoginPositionFormCaptchas();
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () {
			mountPanelInLogin();
			positionOtpButtons();
			if (typeof window.aioLoginPositionFormCaptchas === 'function') {
				window.aioLoginPositionFormCaptchas();
			}
		});
	}

	var steps = {
		contact: panel.querySelector('[data-step="contact"]'),
		verify: panel.querySelector('[data-step="verify"]'),
		success: panel.querySelector('[data-step="success"]'),
	};

	var els = {
		title: panel.querySelector('.aio-login-otp-step--contact .aio-login-otp-panel__title'),
		contactError: panel.querySelector('.aio-login-otp-step--contact .aio-login-otp-panel__error'),
		verifyError: panel.querySelector('.aio-login-otp-step--verify .aio-login-otp-panel__error'),
		emailWrap: panel.querySelector('.aio-login-otp-field--email'),
		smsWrap: panel.querySelector('.aio-login-otp-field--sms'),
		email: document.getElementById('aio-login-otp-email'),
		country: document.getElementById('aio-login-otp-country'),
		phone: document.getElementById('aio-login-otp-phone'),
		phoneDial: document.getElementById('aio-login-otp-phone-dial'),
		captcha: panel.querySelector('.aio-login-otp-captcha'),
		sendBtn: panel.querySelector('.aio-login-otp-send'),
		verifyBtn: panel.querySelector('.aio-login-otp-verify'),
		otpCode: document.getElementById('aio-login-otp-code'),
		resendBtn: panel.querySelector('.aio-login-otp-resend'),
		resendTimer: panel.querySelector('.aio-login-otp-resend-timer'),
		continueBtn: panel.querySelector('.aio-login-otp-continue'),
		backToLoginWrap: panel.querySelector('.aio-login-otp-panel__back-wrap'),
	};

	function showError(el, message, useHtml) {
		if (!el) {
			return;
		}
		if (useHtml) {
			el.innerHTML = message || '';
		} else {
			el.textContent = message || '';
		}
		el.hidden = !message;
	}

	function formatNotRegisteredError(data) {
		var msg = (data && data.message) ? data.message : '';
		var registerUrl = (data && data.register_url) || (aioLoginOtp && aioLoginOtp.registerUrl) || '';
		if (!registerUrl) {
			return msg;
		}
		var label = (aioLoginOtp.i18n && aioLoginOtp.i18n.registerLink) ? aioLoginOtp.i18n.registerLink : 'Register';
		return msg + ' <a href="' + registerUrl.replace(/"/g, '&quot;') + '">' + label + '</a>';
	}

	function showStep(name) {
		Object.keys(steps).forEach(function (key) {
			if (!steps[key]) {
				return;
			}
			steps[key].hidden = key !== name;
		});
		if (els.backToLoginWrap) {
			els.backToLoginWrap.hidden = name === 'success';
		}
		if (panel) {
			panel.classList.remove(
				'aio-login-otp-panel--step-contact',
				'aio-login-otp-panel--step-verify',
				'aio-login-otp-panel--step-success'
			);
			panel.classList.add('aio-login-otp-panel--step-' + name);
		}
		if (name === 'verify' && els.otpCode) {
			setTimeout(function () {
				els.otpCode.focus();
			}, 0);
		}
	}

	function showOtpView() {
		if (!loginEl) {
			loginEl = document.getElementById('login');
		}
		if (loginEl) {
			loginEl.classList.add('aio-login-otp-view--active');
		}
		panel.hidden = false;
		panel.setAttribute('aria-hidden', 'false');
	}

	function showLoginView() {
		if (loginEl) {
			loginEl.classList.remove('aio-login-otp-view--active');
		}
		panel.hidden = true;
		panel.setAttribute('aria-hidden', 'true');
		state.channel = '';
		state.token = '';
		state.verifyBlocked = false;
		state.redirectUrl = '';
		state.redirectStarted = false;
		if (state.redirectTimer) {
			clearTimeout(state.redirectTimer);
			state.redirectTimer = null;
		}
		clearResendTimer();
		showError(els.contactError, '');
		showError(els.verifyError, '');
	}

	function clearResendTimer() {
		if (state.resendTimer) {
			clearInterval(state.resendTimer);
			state.resendTimer = null;
		}
	}

	function populateCountries() {
		if (!els.country || !aioLoginOtp.countryCodes) {
			return;
		}
		els.country.innerHTML = '';
		var defaultIso = aioLoginOtp.defaultCountryIso || '';
		var defaultCode = aioLoginOtp.defaultCountry || '';
		aioLoginOtp.countryCodes.forEach(function (c) {
			var opt = document.createElement('option');
			opt.value = c.code;
			opt.textContent = c.label;
			opt.dataset.iso = c.iso;
			if (defaultIso && c.iso === defaultIso) {
				opt.selected = true;
			} else if (!defaultIso && c.code === defaultCode) {
				opt.selected = true;
			}
			els.country.appendChild(opt);
		});
		syncDialDisplay();
	}

	function configureOtpField(len) {
		if (!els.otpCode) {
			return;
		}
		state.length = len || 4;
		els.otpCode.maxLength = state.length;
		els.otpCode.value = '';
		els.otpCode.setAttribute(
			'aria-label',
			(aioLoginOtp.i18n && aioLoginOtp.i18n.otpLabel) ? aioLoginOtp.i18n.otpLabel : 'Verification code'
		);
		updateVerifyEnabled();
	}

	function bindOtpCodeField() {
		if (!els.otpCode || els.otpCode.dataset.bound === '1') {
			return;
		}
		els.otpCode.dataset.bound = '1';
		els.otpCode.addEventListener('input', function () {
			els.otpCode.value = els.otpCode.value.replace(/\D/g, '').slice(0, state.length);
			updateVerifyEnabled();
		});
		els.otpCode.addEventListener('keydown', function (e) {
			if (e.key === 'Enter') {
				e.preventDefault();
				tryVerify();
			}
		});
		els.otpCode.addEventListener('paste', function (e) {
			e.preventDefault();
			var pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, state.length);
			els.otpCode.value = pasted;
			updateVerifyEnabled();
		});
	}

	function getOtpValue() {
		if (!els.otpCode) {
			return '';
		}
		return els.otpCode.value.replace(/\D/g, '');
	}

	function clearOtpInputs() {
		if (!els.otpCode) {
			return;
		}
		els.otpCode.value = '';
		els.otpCode.focus();
		updateVerifyEnabled();
	}

	function digitsOnly(value) {
		return String(value || '').replace(/\D/g, '');
	}

	function currentDialCode() {
		if (!els.country) {
			return '';
		}
		return String(els.country.value || '');
	}

	function currentCountryIso() {
		if (!els.country || !els.country.selectedOptions || !els.country.selectedOptions[0]) {
			return '';
		}
		return els.country.selectedOptions[0].dataset.iso || '';
	}

	function syncDialDisplay() {
		if (els.phoneDial) {
			els.phoneDial.textContent = currentDialCode();
		}
	}

	function normalizeLocalPhoneDigits(raw) {
		return digitsOnly(raw);
	}

	function parsePastedPhone(pasted) {
		var raw = String(pasted || '').trim();
		var ccDigits = digitsOnly(currentDialCode());
		var numDigits = digitsOnly(raw);

		if (raw.indexOf('+') === 0 && ccDigits !== '' && numDigits.indexOf(ccDigits) === 0) {
			return numDigits.slice(ccDigits.length);
		}

		// Full international paste without "+" (e.g. 16465180948 for +1).
		if (ccDigits !== '' && numDigits.length > ccDigits.length + 6 && numDigits.indexOf(ccDigits) === 0) {
			return numDigits.slice(ccDigits.length);
		}

		return numDigits;
	}

	function getInvalidPhoneMessage() {
		return (aioLoginOtp.i18n && aioLoginOtp.i18n.invalidPhone)
			? aioLoginOtp.i18n.invalidPhone
			: 'Please enter a valid phone number.';
	}

	function validatePhone(showFieldError) {
		if (!els.phone) {
			return { valid: false, digits: '', e164: '' };
		}

		var ccDigits = digitsOnly(currentDialCode());
		var numDigits = normalizeLocalPhoneDigits(els.phone.value);

		if (numDigits === '') {
			if (showFieldError) {
				showError(els.contactError, getInvalidPhoneMessage());
			}
			return { valid: false, digits: '', e164: '' };
		}

		var e164 = '+' + ccDigits + numDigits;
		if (e164.length < 8 || e164.length > 16) {
			if (showFieldError) {
				showError(els.contactError, getInvalidPhoneMessage());
			}
			return { valid: false, digits: numDigits, e164: e164 };
		}

		return { valid: true, digits: numDigits, e164: e164 };
	}

	function sanitizePhoneInput() {
		if (!els.phone) {
			return;
		}
		var normalized = normalizeLocalPhoneDigits(els.phone.value);
		if (els.phone.value !== normalized) {
			els.phone.value = normalized;
		}
	}

	function updateSendEnabled() {
		if (!els.sendBtn) {
			return;
		}
		var ok = false;
		if (state.channel === 'email') {
			ok = els.email && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(els.email.value.trim());
		} else if (state.channel === 'sms') {
			ok = validatePhone(false).valid;
		}
		if (ok && aioLoginOtp.captchaRequired && window.aioLoginPasswordlessCaptcha) {
			ok = window.aioLoginPasswordlessCaptcha.satisfied(els.captcha, aioLoginOtp.captcha);
		}
		els.sendBtn.disabled = !ok;
	}

	function updateVerifyEnabled() {
		if (!els.verifyBtn) {
			return;
		}
		if (state.verifyBlocked) {
			els.verifyBtn.disabled = true;
			return;
		}
		els.verifyBtn.disabled = getOtpValue().length !== state.length;
	}

	function renderCaptcha() {
		if (!window.aioLoginPasswordlessCaptcha) {
			return;
		}
		window.aioLoginPasswordlessCaptcha.render(els.captcha, aioLoginOtp.captcha, updateSendEnabled);
	}

	function appendCaptchaToFormData(fd) {
		if (window.aioLoginPasswordlessCaptcha) {
			return window.aioLoginPasswordlessCaptcha.appendToFormDataAsync(els.captcha, aioLoginOtp.captcha, fd);
		}
		if (!aioLoginOtp.captcha || !aioLoginOtp.captcha.provider) {
			return Promise.resolve();
		}
		var p = aioLoginOtp.captcha.provider;
		if (p === 'recaptcha' && aioLoginOtp.captcha.version === 'v3' && window.aioLoginPasswordlessCaptcha) {
			return window.aioLoginPasswordlessCaptcha.getRecaptchaV3Token(aioLoginOtp.captcha, 'login').then(function (token) {
				fd.append('g-recaptcha-response', token);
			});
		}
		if (p === 'recaptcha') {
			var resp = '';
			if (window.grecaptcha) {
				resp = window.grecaptcha.getResponse();
			}
			fd.append('g-recaptcha-response', resp);
		} else if (p === 'hcaptcha' && window.hcaptcha) {
			fd.append('h-captcha-response', window.hcaptcha.getResponse());
		} else if (p === 'turnstile') {
			var el = els.captcha.querySelector('[name="cf-turnstile-response"]');
			if (el) {
				fd.append('cf-turnstile-response', el.value);
			}
		}
		return Promise.resolve();
	}

	function ajax(action, data) {
		var fd = new FormData();
		fd.append('action', action);
		fd.append('nonce', aioLoginOtp.nonce);
		Object.keys(data).forEach(function (k) {
			fd.append(k, data[k]);
		});
		return appendCaptchaToFormData(fd).then(function () {
			return fetch(aioLoginOtp.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) {
				return r.json();
			});
		});
	}

	function startResendCountdown(seconds) {
		clearResendTimer();
		state.resendIn = seconds;
		if (els.resendBtn) {
			els.resendBtn.hidden = true;
			els.resendBtn.disabled = true;
		}
		if (els.resendTimer) {
			els.resendTimer.hidden = false;
			els.resendTimer.textContent = aioLoginOtp.i18n.resendIn.replace('%d', String(seconds));
		}
		state.resendTimer = setInterval(function () {
			state.resendIn -= 1;
			if (state.resendIn <= 0) {
				clearResendTimer();
				if (els.resendTimer) {
					els.resendTimer.hidden = true;
				}
				if (els.resendBtn) {
					els.resendBtn.hidden = false;
					els.resendBtn.disabled = false;
				}
				return;
			}
			if (els.resendTimer) {
				els.resendTimer.textContent = aioLoginOtp.i18n.resendIn.replace('%d', String(state.resendIn));
			}
		}, 1000);
	}

	function openChannel(channel) {
		if (channel === 'sms' && (!aioLoginOtp.countryCodes || !aioLoginOtp.countryCodes.length)) {
			showError(
				els.contactError,
				(aioLoginOtp.i18n && aioLoginOtp.i18n.noSmsCountries)
					? aioLoginOtp.i18n.noSmsCountries
					: 'SMS login is not available.'
			);
			return;
		}
		state.channel = channel;
		state.token = '';
		state.verifyBlocked = false;
		showError(els.contactError, '');
		showError(els.verifyError, '');
		if (els.emailWrap) {
			els.emailWrap.hidden = channel !== 'email';
		}
		if (els.smsWrap) {
			els.smsWrap.hidden = channel !== 'sms';
		}
		if (els.title) {
			els.title.textContent = channel === 'email' ? aioLoginOtp.i18n.continueEmail : aioLoginOtp.i18n.continueSms;
		}
		showStep('contact');
		showOtpView();
		renderCaptcha();
		if (channel === 'sms') {
			syncDialDisplay();
		}
		updateSendEnabled();
		setTimeout(function () {
			renderCaptcha();
			updateSendEnabled();
			if (channel === 'email' && els.email) {
				els.email.focus();
			}
			if (channel === 'sms' && els.phone) {
				els.phone.focus();
			}
		}, 80);
	}

	function showVerifyBlocked(message, length) {
		state.verifyBlocked = true;
		showError(els.contactError, '');
		configureOtpField(length || 4);
		showStep('verify');
		showError(els.verifyError, message);
		if (els.verifyBtn) {
			els.verifyBtn.disabled = true;
		}
		if (els.resendBtn) {
			els.resendBtn.hidden = true;
			els.resendBtn.disabled = true;
		}
		if (els.resendTimer) {
			els.resendTimer.hidden = true;
		}
	}

	function doSend() {
		var payload = { channel: state.channel };
		if (state.channel === 'email') {
			payload.email = els.email.value.trim();
		} else {
			var phoneData = validatePhone(true);
			if (!phoneData.valid) {
				updateSendEnabled();
				return;
			}
			payload.country_code = els.country.value;
			payload.phone = phoneData.digits;
			payload.country_iso = currentCountryIso();
		}
		els.sendBtn.disabled = true;
		ajax('aio_login_otp_send', payload).then(function (res) {
			els.sendBtn.disabled = false;
			if (!res.success) {
				if (res.data && res.data.code === 'verify_blocked') {
					showVerifyBlocked(res.data.message, res.data.length);
					return;
				}
				if (res.data && res.data.code === 'not_registered') {
					showError(els.contactError, formatNotRegisteredError(res.data), true);
				} else {
					showError(els.contactError, (res.data && res.data.message) || 'Error');
				}
				return;
			}
			state.token = res.data.token;
			state.length = res.data.length || 4;
			state.verifyBlocked = false;
			configureOtpField(state.length);
			showStep('verify');
			startResendCountdown(res.data.resend_in || 60);
			clearOtpInputs();
		}).catch(function () {
			els.sendBtn.disabled = false;
			showError(els.contactError, 'Network error');
		});
	}

	function goToRedirect() {
		if (!state.redirectUrl || state.redirectStarted) {
			return;
		}
		state.redirectStarted = true;
		if (state.redirectTimer) {
			clearTimeout(state.redirectTimer);
			state.redirectTimer = null;
		}
		window.location.href = state.redirectUrl;
	}

	function enableResendNow() {
		clearResendTimer();
		if (els.resendBtn) {
			els.resendBtn.hidden = false;
			els.resendBtn.disabled = false;
		}
		if (els.resendTimer) {
			els.resendTimer.hidden = true;
		}
	}

	function isVerifyBlockedResponse(data) {
		if (!data || !data.code) {
			return false;
		}
		return data.code === 'verify_blocked' || data.code === 'ip_blocked' || data.code === 'max_attempts';
	}

	function tryVerify() {
		if (els.verifyBtn.disabled || state.verifyBlocked) {
			return;
		}
		els.verifyBtn.disabled = true;
		ajax('aio_login_otp_verify', { token: state.token, otp: getOtpValue() }).then(function (res) {
			if (!res.success) {
				if (isVerifyBlockedResponse(res.data)) {
					showVerifyBlocked((res.data && res.data.message) || 'Error', state.length);
					return;
				}
				if (res.data && res.data.code === 'otp_expired') {
					enableResendNow();
				}
				els.verifyBtn.disabled = false;
				updateVerifyEnabled();
				showError(els.verifyError, (res.data && res.data.message) || 'Error');
				clearOtpInputs();
				return;
			}
			state.redirectUrl = res.data.redirect || '';
			if (res.data.requires_2fa && aioLoginOtp.i18n) {
				var successTitle = steps.success && steps.success.querySelector('.aio-login-otp-panel__title');
				var successMessage = steps.success && steps.success.querySelector('.aio-login-otp-success-message');
				if (successTitle && aioLoginOtp.i18n.successTitle2fa) {
					successTitle.textContent = aioLoginOtp.i18n.successTitle2fa;
				}
				if (successMessage && aioLoginOtp.i18n.successMessage2fa) {
					successMessage.textContent = aioLoginOtp.i18n.successMessage2fa;
				}
			}
			showStep('success');
			if (state.redirectTimer) {
				clearTimeout(state.redirectTimer);
			}
			state.redirectStarted = false;
			state.redirectTimer = setTimeout(function () {
				goToRedirect();
			}, res.data.requires_2fa ? 800 : 1500);
		}).catch(function () {
			els.verifyBtn.disabled = false;
			showError(els.verifyError, 'Network error');
		});
	}

	function doResend() {
		els.resendBtn.disabled = true;
		ajax('aio_login_otp_resend', { token: state.token }).then(function (res) {
			if (!res.success) {
				if (res.data && res.data.code === 'verify_blocked') {
					showVerifyBlocked(res.data.message, res.data.length);
					return;
				}
				if (res.data && res.data.code === 'invalid_session') {
					showError(els.verifyError, (res.data && res.data.message) || 'Error');
					return;
				}
				if (res.data && res.data.resend_in) {
					startResendCountdown(res.data.resend_in);
				} else {
					els.resendBtn.disabled = false;
				}
				showError(els.verifyError, (res.data && res.data.message) || 'Error');
				return;
			}
			state.verifyBlocked = false;
			startResendCountdown(res.data.resend_in || 60);
			clearOtpInputs();
			showError(els.verifyError, '');
			updateVerifyEnabled();
		}).catch(function () {
			els.resendBtn.disabled = false;
			showError(els.verifyError, 'Network error');
		});
	}

	document.querySelectorAll('.aio-login-otp-launcher').forEach(function (btn) {
		btn.addEventListener('click', function () {
			openChannel(btn.getAttribute('data-channel'));
		});
	});

	panel.querySelectorAll('[data-otp-back-to-login]').forEach(function (btn) {
		btn.addEventListener('click', showLoginView);
	});

	panel.querySelectorAll('[data-otp-back]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			if (!steps.verify.hidden) {
				showStep('contact');
				showError(els.verifyError, '');
				return;
			}
			showLoginView();
		});
	});

	if (els.sendBtn) {
		els.sendBtn.addEventListener('click', doSend);
	}
	if (els.verifyBtn) {
		els.verifyBtn.addEventListener('click', tryVerify);
	}
	if (els.resendBtn) {
		els.resendBtn.addEventListener('click', doResend);
	}
	if (els.continueBtn) {
		els.continueBtn.addEventListener('click', goToRedirect);
	}
	if (els.email) {
		els.email.addEventListener('input', updateSendEnabled);
	}
	if (els.phone) {
		els.phone.addEventListener('input', function () {
			sanitizePhoneInput();
			showError(els.contactError, '');
			updateSendEnabled();
		});
		els.phone.addEventListener('paste', function (e) {
			e.preventDefault();
			var pasted = (e.clipboardData || window.clipboardData).getData('text');
			els.phone.value = parsePastedPhone(pasted);
			showError(els.contactError, '');
			updateSendEnabled();
		});
		els.phone.addEventListener('keydown', function (e) {
			if (e.key === 'Enter' && els.sendBtn && !els.sendBtn.disabled) {
				e.preventDefault();
				doSend();
			}
		});
	}
	if (els.country) {
		els.country.addEventListener('change', function () {
			syncDialDisplay();
			sanitizePhoneInput();
			showError(els.contactError, '');
			updateSendEnabled();
			if (els.phone) {
				els.phone.focus();
			}
		});
	}

	populateCountries();
	bindOtpCodeField();
})();
