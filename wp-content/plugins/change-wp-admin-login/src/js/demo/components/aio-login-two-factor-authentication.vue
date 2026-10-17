<template>
	<div class="aio-login-2fa-methods">
		<p class="aio-login-2fa-methods__method-note">
			{{ $t('Only one site-wide method can be active: turn on either Email OTP or Authenticator App, not both.') }}
		</p>

		<div class="aio-login-2fa-methods__list">
			<div class="aio-login-2fa-methods__item aio-login-2fa-policies__card">
				<div class="aio-login-2fa-policies__card-header">
					<span class="aio-login-2fa-policies__icon" aria-hidden="true">
						<svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M4 6.5h16c.83 0 1.5.67 1.5 1.5v8c0 .83-.67 1.5-1.5 1.5H4A1.5 1.5 0 012.5 16V8c0-.83.67-1.5 1.5-1.5z" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round"/>
							<path d="M3 7.5l8.65 5.77a1.5 1.5 0 001.7 0L22 7.5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
					</span>
					<div class="aio-login-2fa-methods__content">
						<h2 class="aio-login-2fa-methods__title">
							{{ $t('Email One-Time Code') }}
							<aio-login-tooltip :content="tooltipContent.twoFactorEmailOtp.content" />
						</h2>
						<div class="aio-login-2fa-methods__desc">{{ $t('Send a one-time verification code to users via email during login.') }}</div>
					</div>
					<div class="aio-login-2fa-methods__controls">
						<button type="button" class="aio-login-2fa-methods__expand-btn" disabled tabindex="-1" aria-hidden="true">
							<span class="aio-login-2fa-methods__chevron" />
						</button>
						<aio-login-toggle id="demo_email_otp" name="demo_email_otp" :enabled="false" />
					</div>
				</div>
			</div>

			<div class="aio-login-2fa-methods__item aio-login-2fa-policies__card">
				<div class="aio-login-2fa-policies__card-header">
					<span class="aio-login-2fa-policies__icon" aria-hidden="true">
						<svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<rect x="7" y="3" width="10" height="18" rx="2.25" stroke="currentColor" stroke-width="1.75"/>
							<path d="M10 6.5h4" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
							<circle cx="12" cy="17.25" r="1.1" fill="currentColor"/>
						</svg>
					</span>
					<div class="aio-login-2fa-methods__content">
						<h2 class="aio-login-2fa-methods__title">
							{{ $t('Authenticator App (TOTP)') }}
							<aio-login-tooltip :content="tooltipContent.twoFactorTotp.content" />
						</h2>
						<div class="aio-login-2fa-methods__desc">{{ $t('Allow users to verify login using an authenticator app such as Google Authenticator.') }}</div>
					</div>
					<div class="aio-login-2fa-methods__controls">
						<button type="button" class="aio-login-2fa-methods__expand-btn" disabled tabindex="-1" aria-hidden="true">
							<span class="aio-login-2fa-methods__chevron" />
						</button>
						<aio-login-toggle id="demo_totp" name="demo_totp" :enabled="false" />
					</div>
				</div>
			</div>

			<div class="aio-login-2fa-methods__item aio-login-2fa-policies__card">
				<div class="aio-login-2fa-policies__card-header">
					<span class="aio-login-2fa-policies__icon" aria-hidden="true">
						<svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M12 2.75a7.25 7.25 0 017.25 7.25v2.1l1.28 2.2c.38.66-.1 1.48-.86 1.48H4.33c-.76 0-1.24-.82-.86-1.48l1.28-2.2V10A7.25 7.25 0 0112 2.75z" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round"/>
							<path d="M9.5 18.25a2.5 2.5 0 005 0" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
						</svg>
					</span>
					<div class="aio-login-2fa-methods__content">
						<h2 class="aio-login-2fa-methods__title">
							{{ $t('Remember Device') }}
							<aio-login-tooltip :content="tooltipContent.twoFactorRememberDevice.content" />
						</h2>
						<div class="aio-login-2fa-methods__desc">{{ $t('Skip OTP verification on trusted browsers for a specific duration.') }}</div>
					</div>
					<div class="aio-login-2fa-methods__controls">
						<button type="button" class="aio-login-2fa-methods__expand-btn" disabled tabindex="-1" aria-hidden="true">
							<span class="aio-login-2fa-methods__chevron" />
						</button>
						<aio-login-toggle id="demo_remember" name="demo_remember" :enabled="false" />
					</div>
				</div>
			</div>
		</div>

		<div class="aio-login-2fa-methods__actions">
			<aio-login-submit-button v-on:button-click="noopDemoSave" />
		</div>
	</div>
</template>

<script>
import tooltipContent from '../../tooltip-content.js';

export default {
	name: 'aio-login-two-factor-authentication',

	slug: 'authentication-methods',

	props: {
		hasPro: {
			type: Boolean,
			default: false,
		},
	},

	data: () => ( {
		tooltipContent,
	} ),

	methods: {
		noopDemoSave() {},
	},
}
</script>

<style scoped>
.aio-login-2fa-methods__expand-btn[disabled] {
	opacity: 0.6;
	cursor: default;
	pointer-events: none;
}
</style>
