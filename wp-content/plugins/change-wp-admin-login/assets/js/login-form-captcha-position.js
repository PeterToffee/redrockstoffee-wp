(function () {
	'use strict';

	var positioning = false;
	var modalFixTimer = null;

	var LOGIN_FORM_SELECTOR = 'form#loginform, form.woocommerce-form-login, form.woocommerce-form-register, form.login, form.woocommerce-form--login';

	function captchaWidgetLooksRendered(el) {
		if (!el) {
			return false;
		}
		if (el.querySelector('iframe')) {
			return true;
		}
		if (el.getAttribute('data-aio-wp-turnstile-rendered') === '1' || el.getAttribute('data-aio-turnstile-rendered') === '1') {
			return true;
		}
		if (el.shadowRoot) {
			return !!(el.shadowRoot.querySelector('iframe') || el.shadowRoot.querySelector('input[name="cf-turnstile-response"]'));
		}
		return false;
	}

	function isFormFieldRow(el) {
		return !!(el && el.matches && el.matches('p, .form-row, .woocommerce-form-row, .wc-block-components-text-input'));
	}

	function needsStackedFieldLayout(form) {
		if (!form) {
			return false;
		}

		if (
			form.closest(
				'.wc-block-components-modal, .elementor-popup-modal, .elementor-lightbox, .dialog-widget-content, [role="dialog"]'
			)
		) {
			return true;
		}

		if (document.body.classList.contains('woocommerce-checkout')) {
			return true;
		}

		return !!form.querySelector(
			'.form-row-first, .form-row-last, .woocommerce-form-row--first, .woocommerce-form-row--last'
		);
	}

	function getFieldRow(form, inputSelector, rowSelector) {
		var field = form.querySelector(inputSelector);
		if (field) {
			var passwordWrapper = field.closest('.password-input');
			if (passwordWrapper) {
				var rowFromWrapper = passwordWrapper.closest('p, .form-row, .woocommerce-form-row, .wc-block-components-text-input');
				if (rowFromWrapper) {
					return rowFromWrapper;
				}
			}

			var row = field.closest('p, .form-row, .woocommerce-form-row, .wc-block-components-text-input');
			if (row) {
				return row;
			}
		}
		return form.querySelector(rowSelector);
	}

	function applyRowLayout(row) {
		if (!row || !row.style) {
			return;
		}

		row.style.cssFloat = 'none';
		row.style.width = '100%';
		row.style.maxWidth = '100%';
		row.style.display = 'block';
		row.style.clear = 'both';
		row.style.marginBottom = '16px';
		row.style.position = 'static';
		row.style.left = 'auto';
		row.style.right = 'auto';
		row.style.visibility = 'visible';
		row.style.opacity = '1';
		row.style.height = 'auto';
		row.style.maxHeight = 'none';
		row.style.overflow = 'visible';
		row.style.clip = 'auto';
	}

	/**
	 * Classic Woo login puts username/password side-by-side (float). Checkout modals
	 * (WC Blocks, Elementor popups) clip the password unless fields are stacked.
	 */
	function alignPasswordToggleButtons(form) {
		if (!form) {
			return;
		}

		form.querySelectorAll('.password-input').forEach(function (wrapper) {
			var input = wrapper.querySelector('input[type="password"], input[type="text"]');
			var toggle = wrapper.querySelector('.show-password-input');
			if (!input || !toggle) {
				return;
			}

			wrapper.style.display = 'block';
			wrapper.style.position = 'relative';

			var inputTop = input.offsetTop;
			var inputHeight = input.offsetHeight;
			var toggleHeight = toggle.offsetHeight || 22;
			var topOffset = inputTop + Math.max(0, (inputHeight - toggleHeight) / 2);

			toggle.style.position = 'absolute';
			toggle.style.right = '0.75em';
			toggle.style.top = topOffset + 'px';
			toggle.style.bottom = 'auto';
			toggle.style.transform = 'none';
			toggle.style.margin = '0';
			toggle.style.height = '22px';
			toggle.style.width = '22px';
			toggle.style.display = 'inline-flex';
			toggle.style.alignItems = 'center';
			toggle.style.justifyContent = 'center';
			toggle.style.zIndex = '2';
		});
	}

	function fixWooLoginFieldLayout(form) {
		if (!form || typeof form.querySelectorAll !== 'function') {
			return;
		}

		if (!needsStackedFieldLayout(form)) {
			return;
		}

		var usernameRow = getFieldRow(form, '#username, input[name="username"]', '.form-row-first');
		var passwordRow = getFieldRow(form, '#password, input[name="password"]', '.form-row-last');

		if (usernameRow && passwordRow && usernameRow !== passwordRow) {
			if (isFormFieldRow(usernameRow) && isFormFieldRow(passwordRow) && usernameRow.nextElementSibling !== passwordRow) {
				usernameRow.parentNode.insertBefore(passwordRow, usernameRow.nextSibling);
			}
		}

		form.querySelectorAll('.form-row-first, .form-row-last, .woocommerce-form-row--first, .woocommerce-form-row--last').forEach(applyRowLayout);

		if (passwordRow) {
			applyRowLayout(passwordRow);
		}

		form.querySelectorAll('.clear').forEach(function (clearNode) {
			if (clearNode && clearNode.style) {
				clearNode.style.display = 'none';
			}
		});

		var passwordInput = form.querySelector('#password, input[name="password"]');
		if (passwordInput) {
			passwordInput.style.display = 'block';
			passwordInput.style.visibility = 'visible';
			passwordInput.style.opacity = '1';
			passwordInput.style.width = '100%';
			passwordInput.style.maxWidth = '100%';
			passwordInput.style.boxSizing = 'border-box';
			passwordInput.style.height = 'auto';
			passwordInput.style.minHeight = '40px';

			var passwordWrapper = passwordInput.closest('.password-input, .woocommerce-input-wrapper');
			if (passwordWrapper) {
				if (passwordWrapper.classList.contains('password-input')) {
					passwordWrapper.style.display = 'block';
					passwordWrapper.style.position = 'relative';
				} else {
					passwordWrapper.style.display = 'block';
				}
				passwordWrapper.style.visibility = 'visible';
				passwordWrapper.style.width = '100%';
				passwordWrapper.style.maxWidth = '100%';
				passwordWrapper.style.overflow = 'visible';
			}

			if (passwordInput.closest('.password-input')) {
				passwordInput.style.paddingRight = '2.5rem';
			}
		}

		alignPasswordToggleButtons(form);

		var passwordLabel = form.querySelector('label[for="password"]');
		if (passwordLabel) {
			passwordLabel.style.display = 'block';
			passwordLabel.style.visibility = 'visible';
		}
	}

	function fixAllLoginForms() {
		document.querySelectorAll(LOGIN_FORM_SELECTOR).forEach(fixWooLoginFieldLayout);
	}

	function scheduleModalLayoutFix() {
		if (modalFixTimer) {
			clearTimeout(modalFixTimer);
		}

		fixAllLoginForms();
		modalFixTimer = setTimeout(function () {
			modalFixTimer = null;
			fixAllLoginForms();
		}, 100);
		setTimeout(fixAllLoginForms, 350);
		setTimeout(fixAllLoginForms, 800);
	}

	/**
	 * Row immediately above Log In / Register (captcha sits directly above it).
	 */
	function getSubmitRow(form) {
		var btn = form.querySelector(
			'.woocommerce-form-login__submit, .woocommerce-form-register__submit, #wp-submit, p.submit input[type="submit"]'
		);
		if (btn) {
			var row = btn.closest('p');
			if (row && row.parentNode === form) {
				return row;
			}
		}
		return form.querySelector('p.submit');
	}

	/**
	 * Where captcha should sit.
	 * WP core #loginform: before .forgetmenot so Remember Me + Log In stay a clean float row.
	 * Woo / other forms: right above submit.
	 */
	function getCaptchaInsertBefore(form) {
		if (form && (form.id === 'loginform' || (form.matches && form.matches('form#loginform')))) {
			var forget = form.querySelector('p.forgetmenot');
			if (forget && forget.parentNode === form) {
				return forget;
			}
		}
		return getSubmitRow(form);
	}

	/**
	 * Move captcha to the bottom: after OTP → Login Link → Social, right above
	 * Remember Me (WP) or submit (Woo).
	 */
	function positionLoginFormCaptchas() {
		if (positioning) {
			return;
		}

		positioning = true;

		try {
			document.querySelectorAll(LOGIN_FORM_SELECTOR).forEach(function (form) {
				fixWooLoginFieldLayout(form);

				var selectors = [
					'.aio-login-wp-captcha',
					'.aio-login-woo-captcha',
					'input[name="cf-turnstile-error-code"]',
					'.cf-turnstile',
					'.aio-login-turnstile-widget-error',
					'.h-captcha',
					'.g-recaptcha',
					'#g-recaptcha-response',
				];

				var nodes = [];
				selectors.forEach(function (sel) {
					form.querySelectorAll(sel).forEach(function (el) {
						if (el.closest('#aio-login-otp-panel, #aio-login-magic-link-panel')) {
							return;
						}
						if (el.closest('.aio-login-wp-captcha, .aio-login-woo-captcha')) {
							return;
						}
						if (el.matches('.cf-turnstile, .h-captcha, .g-recaptcha') && captchaWidgetLooksRendered(el)) {
							return;
						}
						if (nodes.indexOf(el) === -1) {
							nodes.push(el);
						}
					});
				});

				form.querySelectorAll('.aio-login-wp-captcha, .aio-login-woo-captcha').forEach(function (wrapper) {
					if (wrapper.closest('#aio-login-otp-panel, #aio-login-magic-link-panel')) {
						return;
					}
					if (wrapper.querySelector('.cf-turnstile') && captchaWidgetLooksRendered(wrapper.querySelector('.cf-turnstile'))) {
						return;
					}
					if (nodes.indexOf(wrapper) === -1) {
						nodes.push(wrapper);
					}
				});

				if (!nodes.length) {
					return;
				}

				var insertBefore = getCaptchaInsertBefore(form);
				if (!insertBefore) {
					return;
				}

				nodes.forEach(function (node) {
					if (node.parentNode !== form) {
						return;
					}
					if (node.nextElementSibling === insertBefore) {
						return;
					}
					form.insertBefore(node, insertBefore);
				});
			});

			document.dispatchEvent(new CustomEvent('aioLoginCaptchaRepositioned'));
		} finally {
			positioning = false;
		}
	}

	function schedulePosition() {
		scheduleModalLayoutFix();
		positionLoginFormCaptchas();
		setTimeout(positionLoginFormCaptchas, 150);
		setTimeout(positionLoginFormCaptchas, 500);
	}

	function isModalContainer(node) {
		if (!node || !node.matches) {
			return false;
		}

		return node.matches(
			'.wc-block-components-modal, .elementor-popup-modal, .elementor-lightbox, .dialog-widget-content, [role="dialog"]'
		);
	}

	function bindModalObservers() {
		if (typeof MutationObserver === 'undefined') {
			return;
		}

		var modalObserver = new MutationObserver(function (mutations) {
			var shouldFix = false;

			mutations.forEach(function (mutation) {
				if (mutation.type === 'attributes' && isModalContainer(mutation.target)) {
					shouldFix = true;
				}
			});

			if (shouldFix) {
				scheduleModalLayoutFix();
				positionLoginFormCaptchas();
			}
		});

		document.querySelectorAll(
			'.wc-block-components-modal, .elementor-popup-modal, .elementor-lightbox, .dialog-widget-content, [role="dialog"]'
		).forEach(function (modal) {
			modalObserver.observe(modal, {
				attributes: true,
				attributeFilter: ['aria-hidden', 'class', 'style', 'open'],
			});
		});

		var bodyObserver = new MutationObserver(function (mutations) {
			var shouldFix = false;

			mutations.forEach(function (mutation) {
				mutation.addedNodes.forEach(function (node) {
					if (node.nodeType !== 1) {
						return;
					}

					if (
						isModalContainer(node) ||
						(node.matches && node.matches(LOGIN_FORM_SELECTOR)) ||
						(node.querySelector && node.querySelector('.wc-block-components-modal, ' + LOGIN_FORM_SELECTOR))
					) {
						shouldFix = true;
					}

					if (isModalContainer(node)) {
						modalObserver.observe(node, {
							attributes: true,
							attributeFilter: ['aria-hidden', 'class', 'style', 'open'],
						});
					}
				});
			});

			if (shouldFix) {
				scheduleModalLayoutFix();
				positionLoginFormCaptchas();
			}
		});

		bodyObserver.observe(document.body, { childList: true, subtree: true });
	}

	window.aioLoginPositionFormCaptchas = positionLoginFormCaptchas;
	window.aioLoginFixWooLoginFieldLayout = fixWooLoginFieldLayout;
	window.aioLoginFixWooLoginForms = scheduleModalLayoutFix;

	var resizeTimer = null;
	window.addEventListener('resize', function () {
		if (resizeTimer) {
			clearTimeout(resizeTimer);
		}
		resizeTimer = setTimeout(function () {
			resizeTimer = null;
			fixAllLoginForms();
		}, 150);
	});

	document.addEventListener(
		'click',
		function (event) {
			var target = event.target;
			if (!target || !target.closest) {
				return;
			}

			if (
				target.closest(
					'.showlogin, .wc-block-components-checkout-login-prompt, .wc-block-components-checkout-returning-customer-login, [data-block-name="woocommerce/checkout-contact-information-block"] button, a[href*="login"], .elementor-button-link'
				)
			) {
				scheduleModalLayoutFix();
			}
		},
		true
	);

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () {
			schedulePosition();
			bindModalObservers();
		});
	} else {
		schedulePosition();
		bindModalObservers();
	}
})();
