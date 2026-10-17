<template>

	<div :style="popupCssVars">
		<aio-login-popup :width="popupWidth" :height="popupHeight" content="nocontent" :container-class="isAppSumoPopup ? 'aio-login-pro-popup--appsumo-shell' : 'aio-login-pro-popup--freemius-shell'" v-on:close-popup="e => $emit( 'close-popup', e )">
			<template v-slot:default>
				<div class="aio-login-pro__container" :class="{ 'aio-login-pro__container--appsumo': isAppSumoPopup, 'aio-login-pro__container--freemius': !isAppSumoPopup }">
					<template v-if="isAppSumoPopup">
						<div class="aio-login-appsumo-popup">
							<div class="aio-login-appsumo-popup__logo-wrap">
								<img :src="appsumoImageBase + 'logo.svg'" :alt="$t('AIO Login')" class="aio-login-appsumo-popup__logo">
							</div>

							<div class="aio-login-appsumo-popup__headline">
								<div class="aio-login-appsumo-popup__title-row">
									<h2 class="aio-login-appsumo-popup__title">{{ $t("Go Unlimited with AIO Login") }}</h2>
									<img :src="appsumoImageBase + 'infinity.svg'" alt="" class="aio-login-appsumo-popup__infinity" aria-hidden="true">
								</div>
								<p class="aio-login-appsumo-popup__subtitle">{{ $t("Unlimited Sites + Lifetime Access") }}</p>
							</div>

							<div class="aio-login-appsumo-popup__features">
								<div class="aio-login-appsumo-popup__feature">
									<img :src="appsumoImageBase + 'feature-2fa.svg'" alt="" aria-hidden="true">
									<span>{{ $t("2FA Authenticator") }}</span>
								</div>
								<div class="aio-login-appsumo-popup__feature">
									<img :src="appsumoImageBase + 'feature-ip.svg'" alt="" aria-hidden="true">
									<span>{{ $t("IP Protection") }}</span>
								</div>
								<div class="aio-login-appsumo-popup__feature">
									<img :src="appsumoImageBase + 'feature-social.svg'" alt="" aria-hidden="true">
									<span>{{ $t("Social Login") }}</span>
									<span class="aio-login-appsumo-popup__social-icons">
										<img :src="appsumoImageBase + 'social-discord.svg'" alt="" aria-hidden="true">
										<img :src="appsumoImageBase + 'social-apple.svg'" alt="" aria-hidden="true">
									</span>
								</div>
								<div class="aio-login-appsumo-popup__feature">
									<img :src="appsumoImageBase + 'feature-cloudflare.svg'" alt="" aria-hidden="true">
									<span>{{ $t("Cloudflare turnstile") }}</span>
								</div>
								<div class="aio-login-appsumo-popup__feature">
									<img :src="appsumoImageBase + 'feature-woo.svg'" alt="" aria-hidden="true">
									<span>{{ $t("WooCommerce Integration") }}</span>
								</div>
								<div class="aio-login-appsumo-popup__feature">
									<img :src="appsumoImageBase + 'feature-customizer.svg'" alt="" aria-hidden="true">
									<span>{{ $t("Login Page Customizer") }}</span>
								</div>
							</div>

							<a class="aio-login-appsumo-popup__cta" href="https://appsumo.com/products/aiologin/?p=1#pricePlans" target="_blank" rel="noopener noreferrer">
								{{ $t("Upgrade Now") }}
							</a>
						</div>
					</template>
					<div v-else class="aio-login-freemius-popup">
						<div class="aio-login-freemius-popup__logo-wrap">
							<img :src="assets_url + 'images/logo.svg'" alt="AIO Login" class="aio-login-freemius-popup__logo">
						</div>
						<h2 class="aio-login-freemius-popup__title">{{ $t("To access more features and options") }}</h2>
						<div class="aio-login-freemius-popup__features">
							<div class="aio-login-freemius-popup__feature">
								<img :src="assets_url + 'images/feature-2fa.svg'" alt="" aria-hidden="true">
								<span>{{ $t("2FA Authenticator") }}</span>
							</div>
							<div class="aio-login-freemius-popup__feature">
								<img :src="assets_url + 'images/feature-ip.svg'" alt="" aria-hidden="true">
								<span>{{ $t("IP Protection") }}</span>
							</div>
							<div class="aio-login-freemius-popup__feature">
								<img :src="assets_url + 'images/feature-social.svg'" alt="" aria-hidden="true">
								<span>{{ $t("Social Login") }}</span>
								<span class="aio-login-freemius-popup__social-icons">
									<img :src="assets_url + 'images/social-discord.svg'" alt="" aria-hidden="true">
									<img :src="assets_url + 'images/social-apple.svg'" alt="" aria-hidden="true">
									<img :src="assets_url + 'images/social-google.svg'" alt="" aria-hidden="true">
								</span>
							</div>
							<div class="aio-login-freemius-popup__feature">
								<img :src="assets_url + 'images/feature-customizer.svg'" alt="" aria-hidden="true">
								<span>{{ $t("Security Notification") }}</span>
								<span class="aio-login-freemius-popup__social-icons">
									<span class="aio-login-freemius-popup__notify-pill">
										<img :src="assets_url + 'images/icons/notification-slack.svg'" class="aio-login-freemius-popup__notify-icon" alt="" aria-hidden="true">
									</span>
									<span class="aio-login-freemius-popup__notify-pill">
										<img :src="assets_url + 'images/icons/notification-webhook.svg'" class="aio-login-freemius-popup__notify-icon" alt="" aria-hidden="true">
									</span>
								</span>
							</div>
							<div class="aio-login-freemius-popup__feature">
								<img :src="assets_url + 'images/feature-woo.svg'" alt="" aria-hidden="true">
								<span>{{ $t("WooCommerce Integration") }}</span>
							</div>
							<div class="aio-login-freemius-popup__feature">
								<img :src="assets_url + 'images/feature-cloudflare.svg'" alt="" aria-hidden="true">
								<span>{{ $t("Cloudflare turnstile") }}</span>
							</div>
						</div>
						<a class="aio-login-freemius-popup__cta" target="_blank" href="https://aiologin.com/pricing/?utm_source=plugin&utm_medium=pro_pop_up&utm_campaign=plugin">{{ popupCtaLabel }}</a>
					</div>
				</div>
			</template>
		</aio-login-popup>
	</div>

</template>

<script>
export default {
	name: 'aio-login-pro-popup',
	data: () => ( {
		assets_url: window.aio_login__app_object.assets_url,
	} ),
	computed: {
		isAppSumoPopup() {
			const o = typeof window !== 'undefined' ? window.aio_login__app_object : null;
			return o && o.upgrade_popup_variant === 'appsumo';
		},
		proPluginActive() {
			const o = typeof window !== 'undefined' ? window.aio_login__app_object : null;
			return !!( o && ( o.has_pro === 'true' || o.has_pro === true ) );
		},
		popupCtaLabel() {
			return this.proPluginActive ? 'Get Business Plan Now' : 'Get AIO Login Pro';
		},
		popupWidth() {
			return this.isAppSumoPopup ? '550px' : '550px';
		},
		popupHeight() {
			return this.isAppSumoPopup ? '483px' : '483px';
		},
		appsumoImageBase() {
			return this.assets_url + 'images/';
		},
		popupCssVars() {
			return {
				'--aio-login-appsumo-close-url': `url("${this.assets_url}images/close.svg")`,
			};
		},
	},
}
</script>

<style scoped>
.aio-login-pro__container {

	display: flex;
	justify-content: center;
	align-items: center;

	width: 576px;
	height: 340px;
	background-color: #490f95;
	box-shadow: 0px 4px 16px 6px rgba( 0, 0, 0, 0.08 );
	position: relative;
	background-image: url( ../../images/popup-left.png ), url( ../../images/popup-right.png );
	background-repeat: no-repeat;
	background-position: left bottom, right bottom;
	overflow: hidden;
}

.aio-login-pro__container::before {
	content: '';
	position: absolute;
	bottom: 0;
	left: -120px;
	width: 270px;
	height: 270px;
	background-color: #0c9;
	opacity: 0.5;
	border-radius: 270px;
	filter: blur( 125px )
}

/* AppSumo build: slightly warmer accent glow */
.aio-login-pro__container--appsumo::before {
	background-color: #ff6b35;
	opacity: 0.45;
}

.aio-login-pro__container::after {
	content: '';
	position: absolute;
	bottom: 0;
	right: -120px;
	width: 270px;
	height: 270px;
	background-color: #FFBA08;
	opacity: 0.5;
	border-radius: 270px;
	filter: blur( 125px )
}

.aio-login__button {
	display: inline-flex;
	padding: 16px 32px;
	justify-content: center;
	align-items: center;
	gap: 10px;

	border-radius: 4px;
	background: #FFBA08;

	text-decoration: none;

	color: #490F95;
	font-family: Figtree;
	font-size: 18px;
	font-style: normal;
	font-weight: 600;
	line-height: 100%; /* 18px */
}

.aio-login__title {
	color: #FFF;
	font-family: Figtree;
	font-size: 24px;
	font-style: normal;
	font-weight: 400;
	line-height: 24px; /* 100% */
}

.aio-login-pro__container--appsumo {
	width: 550px;
	height: 483px;
	position: relative;
	border-radius: 30px;
	background: linear-gradient(133.124deg, #ffffff 9.9663%, #bda0ee 100%);
	box-shadow: 0 0 44px rgba(0, 0, 0, 0.45);
	overflow: hidden;
}

.aio-login-pro__container--appsumo::before,
.aio-login-pro__container--appsumo::after {
	display: none;
}

.aio-login-pro__container--freemius {
	width: 550px;
	height: 483px;
	border-radius: 30px;
	background: transparent;
	box-shadow: none;
}

.aio-login-pro__container--freemius::before,
.aio-login-pro__container--freemius::after {
	display: none;
}

:deep(.aio-login__popup-container.aio-login-pro-popup--freemius-shell) {
	border: none !important;
	background: transparent !important;
	box-shadow: none !important;
}

.aio-login-pro-popup--freemius-shell :deep(.aio-login__popup-close) {
	top: 19px;
	right: 13px;
}

.aio-login-pro-popup--freemius-shell :deep(.aio-login__popup-close button) {
	width: 26px;
	height: 26px;
	padding: 0;
	border-radius: 50%;
	background: transparent;
	color: transparent !important;
	background-image: var(--aio-login-appsumo-close-url);
	background-repeat: no-repeat;
	background-position: center;
	background-size: contain;
}

.aio-login-freemius-popup {
	position: relative;
	width: 100%;
	height: 100%;
	padding: 0;
	box-sizing: border-box;
	border-radius: 30px;
	background: linear-gradient(129.44deg, #FFFFFF 9.97%, #6E16DF 225.3%);
	box-shadow: 0 0 44px rgba(0, 0, 0, 0.45);
	overflow: hidden;
}

.aio-login-freemius-popup__logo-wrap {
	position: absolute;
	top: 36px;
	left: 50%;
	transform: translateX(-50%);
}

.aio-login-freemius-popup__logo {
	width: 196px;
	height: 72px;
	object-fit: contain;
}

.aio-login-freemius-popup__title {
	position: absolute;
	top: 156px;
	left: 50%;
	transform: translateX(-50%);
	margin: 0;
	width: 460px;
	text-align: center;
	font-family: Poppins, sans-serif !important;
	font-size: 30px;
	font-weight: 700;
	line-height: 1.2;
	color: #6e16df;
}

.aio-login-freemius-popup__features {
	position: absolute;
	left: 33px;
	top: 264px;
	width: 484px;
	display: grid;
	grid-template-columns: 1fr 1fr;
	column-gap: 26px;
	row-gap: 13px;
}

.aio-login-freemius-popup__feature {
	display: flex;
	align-items: center;
	gap: 10px;
	color: #3c0c79;
	font-family: Poppins, sans-serif !important;
	font-size: 14px;
	font-weight: 500;
	line-height: 1.35;
}

.aio-login-freemius-popup__feature > img {
	width: 20px;
	height: 20px;
	flex: 0 0 20px;
}

.aio-login-freemius-popup__social-icons {
	display: inline-flex;
	align-items: center;
	gap: 4px;
}

.aio-login-freemius-popup__social-icons img {
	width: 20px;
	height: 20px;
}

.aio-login-freemius-popup__social-icons .aio-login-freemius-popup__notify-icon {
	width: 12px;
	height: 12px;
	display: block;
	flex: 0 0 12px;
}

.aio-login-freemius-popup__social-icons .aio-login-freemius-popup__notify-pill {
	width: 20px;
	height: 20px;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	background: #ffffff;
	border-radius: 50%;
	box-shadow: 0 1px 2px rgba(17, 24, 39, 0.18);
}

.aio-login-freemius-popup__cta {
	position: absolute;
	left: 50%;
	top: 388px;
	transform: translateX(-50%);
	display: inline-flex;
	align-items: center;
	justify-content: center;
	padding: 15px 20px;
	min-width: 238px;
	border-radius: 10px;
	background: #6e16df;
	color: #fff;
	font-family: Poppins, sans-serif !important;
	font-size: 18px;
	font-weight: 600;
	line-height: 1.5;
	text-decoration: none;
}

.aio-login-freemius-popup__cta:hover {
	background: #5e11c3;
}

:deep(.aio-login__popup-container.aio-login-pro-popup--appsumo-shell) {
	border: none !important;
	background: transparent !important;
	box-shadow: none !important;
}

.aio-login-pro-popup--appsumo-shell :deep(.aio-login__popup-close) {
	top: 19px;
	right: 13px;
}

.aio-login-pro-popup--appsumo-shell :deep(.aio-login__popup-close button) {
	width: 26px;
	height: 26px;
	padding: 0;
	border-radius: 50%;
	background: transparent;
	color: transparent !important;
	background-image: var(--aio-login-appsumo-close-url);
	background-repeat: no-repeat;
	background-position: center;
	background-size: contain;
}

.aio-login-appsumo-popup {
	position: relative;
	width: 100%;
	height: 100%;
}

.aio-login-appsumo-popup__logo-wrap {
	position: absolute;
	left: 50%;
	top: 40px;
	transform: translateX(-50%);
}

.aio-login-appsumo-popup__logo {
	display: block;
	width: 196px;
	height: 72px;
	object-fit: contain;
}

.aio-login-appsumo-popup__headline {
	position: absolute;
	left: 50%;
	top: 154px;
	transform: translateX(-50%);
	width: 471px;
}

.aio-login-appsumo-popup__title-row {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 8px;
}

.aio-login-appsumo-popup__title {
	margin: 0;
	font-family: Poppins, sans-serif !important;
	font-size: 30px;
	font-weight: 700;
	line-height: 1;
	color: #6e16df;
	white-space: nowrap;
}

.aio-login-appsumo-popup__infinity {
	width: 40px;
	height: 20px;
	flex: 0 0 40px;
}

.aio-login-appsumo-popup__subtitle {
	margin: 14px 0 0;
	text-align: center;
	font-family: Poppins, sans-serif !important;
	font-size: 18px;
	font-weight: 500;
	line-height: 1.4;
	color: #3c0c79;
}

.aio-login-appsumo-popup__features {
	position: absolute;
	left: 54px;
	top: 267px;
	width: 484px;
	display: grid;
	grid-template-columns: 1fr 1fr;
	column-gap: 26px;
	row-gap: 13px;
}

.aio-login-appsumo-popup__feature {
	display: flex;
	align-items: center;
	gap: 12px;
	font-family: Poppins, sans-serif !important;
	font-size: 14.246px;
	font-weight: 600;
	line-height: 1.4;
	color: #3c0c79;
}

.aio-login-appsumo-popup__feature > img {
	width: 20px;
	height: 20px;
	flex: 0 0 20px;
	display: block;
}

.aio-login-appsumo-popup__social-icons {
	display: inline-flex;
	align-items: center;
	gap: 5px;
	margin-left: -2px;
}

.aio-login-appsumo-popup__social-icons img {
	width: 20px;
	height: 20px;
	display: block;
}

.aio-login-appsumo-popup__cta {
	position: absolute;
	left: 193px;
	top: 396px;
	width: 164px;
	height: 47px;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	border-radius: 10px;
	background: #6e16df;
	color: #fff;
	text-decoration: none;
	font-family: Poppins, sans-serif !important;
	font-size: 18px;
	font-weight: 600;
	line-height: 1.5;
}
.aio-login-appsumo-popup__cta:hover{
	background: linear-gradient(129.44deg, #FFFFFF 9.97%, #6E16DF 225.3%);
    color: #6e16df;
	border: 1px solid;
}
</style>
