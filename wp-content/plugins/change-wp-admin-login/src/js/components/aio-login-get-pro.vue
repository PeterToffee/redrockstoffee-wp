<template>
	<div class="aio-login__get-pro-shell">
		<div class="aio-login__get-pro-content">
			<h1>{{ $t("All-In-One Login") }} <span>{{ $t("PRO") }}</span> {{ $t("Features 👑") }}</h1>
			<div class="aio-login__features-grid">
				<div class="aio-login__feature" v-for="feature in features" :key="feature.title">
					<div class="aio-login__feature-icon-wrap">
						<img class="aio-login__feature-icon" :src="assetsUrl + feature.img" :alt="feature.title">
					</div>
					<h3 class="aio-login__feature-title">{{ feature.title }}</h3>
					<p class="aio-login__feature-desc">{{ feature.description }}</p>
				</div>
			</div>
			<div class="aio-login__cta-wrap">
				<a
					class="aio-login__get-pro-btn"
					href="https://aiologin.com/pricing/?utm_source=plugn-redirect&utm_medium=upgrade-to-pro&utm_campaign=get-pro&utm_id=plugin"
					target="_blank"
					rel="noopener noreferrer"
				>
					{{ $t("Get Pro") }}
				</a>
			</div>
		</div>
	</div>
</template>

<script>
export default {
	name: 'aio-login-get-pro',
	data: () => ( {
		assetsUrl: aio_login__app_object.assets_url,
		features: [
			{
				img: 'images/icons/get-pro-icons/2fa-authentication.svg',
				title: '2FA Authentication',
				description: 'Add extra security with 2FA using authenticator apps (TOTP) or email verification for safer user logins.',
			},
			{
				img: 'images/icons/get-pro-icons/social-login.svg',
				title: 'Social Login',
				description: 'Let users log in via Google, Facebook, Apple, Discord, and GitHub for a fast, secure, and seamless experience.',
			},
			{
				img: 'images/icons/get-pro-icons/customize-design.svg',
				title: 'Customize Design',
				description: 'Customize your login page with templates, fonts, and styling options to match your brand and improve UX.',
			},
			{
				img: 'images/icons/get-pro-icons/limit-login-attepmt.svg',
				title: 'Limit Login Attempts',
				description: 'Prevent brute-force attacks by limiting login attempts and automatically blocking suspicious IP addresses.',
			},
			{
				img: 'images/icons/get-pro-icons/passwordless-login.svg',
				title: 'Passwordless Login (Temp Access URL)',
				description: 'Generate secure temporary access links so users can log in without passwords, ensuring quick and controlled access.',
			},
			{
				img: 'images/icons/get-pro-icons/ip-access-control.svg',
				title: 'IP Access Control (Whitelist & Blacklist)',
				description: 'Allow trusted IPs and block suspicious ones to control access and protect your login page from unauthorized users.',
			},
			{
				img: 'images/icons/get-pro-icons/spam-protection.svg',
				title: 'Spam Protection (CAPTCHA)',
				description: 'Protect forms and logins using hCaptcha and Cloudflare Turnstile to block bots and reduce spam effectively.',
			},
			{
				img: 'images/icons/get-pro-icons/woocommerce.svg',
				title: 'WooCommerce (Integration)',
				description: 'Secure WooCommerce login with social login and seamless authentication for a safer store experience.',
			},
		],
	} ),
}
</script>

<style scoped>
.aio-login__get-pro-shell {
	background: #ffffff;
	padding: 20px;
}

.aio-login__get-pro-content {
	padding: 52px 64px 48px;
	border-radius: 16px;
	position: relative;
	overflow: hidden;
	background-image:
		radial-gradient(152% 132% at 136% 112%, #00CC99 13%, #141B34 45%, #9516df 127%),
		linear-gradient(118deg, #000000 0%, #ffffff 54%, #142448 100%);
	background-repeat: no-repeat;
	background-size: cover;
	background-position: center;
	color: #fff;
	isolation: isolate;
}

.aio-login__get-pro-content::before {
	content: '';
	position: absolute;
	inset: 0;
	background: url('../../images/aoibg.svg') center/cover no-repeat;
	opacity: 0.42;
	pointer-events: none;
	z-index: 0;
}

.aio-login__get-pro-content::after {
	content: '';
	position: absolute;
	inset: 0;
	background:
		radial-gradient(56% 60% at 89% 94%, rgba(197, 145, 255, 0.35) 0%, rgba(197, 145, 255, 0) 72%),
		radial-gradient(42% 45% at 9% 12%, rgba(146, 47, 223, 0.28) 0%, rgba(146, 47, 223, 0) 70%);
	pointer-events: none;
	z-index: 0;
}

.aio-login__get-pro-content > * {
	position: relative;
	z-index: 1;
}

.aio-login__get-pro-content h1 {
	margin: 0;
	text-align: center;
	color: #fff;
	font-family: Figtree, sans-serif;
	font-size: 44px;
	font-weight: 700;
	line-height: 1.2;
}

.aio-login__get-pro-content h1 span {
	color: #fbbc09;
}

.aio-login__features-grid {
	display: grid;
	grid-template-columns: repeat(4, minmax(0, 1fr));
	gap: 50px;
	margin-top: 58px;
}

.aio-login__feature {
	display: flex;
	flex-direction: column;
	align-items: flex-start;
}

.aio-login__feature-icon {
	width: 52px;
	height: 52px;
	object-fit: contain;
}

.aio-login__feature-title {
	margin: 12px 0 12px;
	color: #fff;
	font-family: Figtree, sans-serif !important;
	font-size: 26px;
	font-weight: 700;
	line-height: 1.22;
}

.aio-login__feature-desc {
	margin: 0;
	color: #ebc8ff;
	font-family: Figtree, sans-serif;
	font-size: 14px;
	font-weight: 500;
	line-height: 1.44;
}

.aio-login__cta-wrap {
	margin-top: 42px;
	text-align: center;
}

.aio-login__get-pro-btn {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	box-sizing: border-box;
	gap: 10px;
	padding: 16px 38px;
	border-radius: 8px;
	border: 2px solid transparent;
	background:
		linear-gradient(#141b34, #141b34) padding-box,
		linear-gradient(90deg, #00cc99 0%, #ffc130 33%, #de79ff 66%, #ff8f67 100%) border-box;
	background-size: auto, 220% 100%;
	background-position: 0 0, 0% 50%;
	color: #fff;
	text-decoration: none;
	font-family: Figtree, sans-serif;
	font-weight: 600;
	font-size: 24px;
	line-height: 1.2;
	transition: transform 0.2s ease;
}

.aio-login__get-pro-btn:hover {
	animation: aio-login-get-pro-border-move 1.2s linear infinite;
	transform: translateY(-1px);
}

.aio-login__get-pro-btn::after {
	content: '';
	width: 28px;
	height: 10px;
	background: url('../../images/aoa-arrow.svg') center/contain no-repeat;
}

@keyframes aio-login-get-pro-border-move {
	0% {
		background-position: 0 0, 0% 50%;
	}
	100% {
		background-position: 0 0, 220% 50%;
	}
}

@media (max-width: 1366px) {
	.aio-login__features-grid {
		grid-template-columns: repeat(2, minmax(0, 1fr));
		gap: 38px 30px;
	}
}

@media (max-width: 1024px) {
	.aio-login__get-pro-shell {
		padding: 14px;
	}

	.aio-login__get-pro-content {
		padding: 30px 22px 28px;
	}

	.aio-login__get-pro-content h1 {
		font-size: 34px;
	}

	.aio-login__features-grid {
		margin-top: 30px;
		gap: 30px 24px;
	}

	.aio-login__feature-title {
		font-size: 21px;
	}

	.aio-login__feature-desc {
		font-size: 13px;
	}

	.aio-login__get-pro-btn {
		font-size: 20px;
		padding: 14px 28px;
	}
}

@media (max-width: 767px) {
	.aio-login__get-pro-content {
		padding: 22px 16px 22px;
	}

	.aio-login__get-pro-content h1 {
		font-size: 24px;
		line-height: 1.28;
	}

	.aio-login__features-grid {
		grid-template-columns: 1fr;
		gap: 22px;
		margin-top: 24px;
	}

	.aio-login__feature-title {
		font-size: 28px;
		margin: 10px 0 8px;
	}

	.aio-login__feature-desc {
		font-size: 16px;
	}

	.aio-login__cta-wrap {
		margin-top: 26px;
	}

	.aio-login__get-pro-btn {
		width: 100%;
		max-width: 100%;
		font-size: 18px;
		padding: 13px 14px;
	}
}

</style>