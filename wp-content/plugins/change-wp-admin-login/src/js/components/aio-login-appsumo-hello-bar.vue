<template>
	<div
		v-if="visible"
		class="aio-login-appsumo-hello-bar"
		role="region"
		:aria-label="ariaLabel"
	>
		<div
			class="aio-login-appsumo-hello-bar__bokeh aio-login-appsumo-hello-bar__bokeh--left"
			:style="{ backgroundImage: `url(${assetsUrl}images/appsumo/hello-bar-bokeh-left.svg)` }"
			aria-hidden="true"
		></div>
		<div
			class="aio-login-appsumo-hello-bar__bokeh aio-login-appsumo-hello-bar__bokeh--right"
			:style="{ backgroundImage: `url(${assetsUrl}images/appsumo/hello-bar-bokeh-right.svg)` }"
			aria-hidden="true"
		></div>

		<div class="aio-login-appsumo-hello-bar__inner">
			<div class="aio-login-appsumo-hello-bar__copy">
				<span class="aio-login-appsumo-hello-bar__headline-wrap">
					<span class="aio-login-appsumo-hello-bar__headline">{{ headline }}</span>
				</span>
				<p class="aio-login-appsumo-hello-bar__text">
					{{ messageBefore }}<span class="aio-login-appsumo-hello-bar__brand">{{ messageHighlight }}</span>
				</p>
			</div>

			<a
				class="aio-login-appsumo-hello-bar__cta"
				:href="ctaUrl"
				target="_blank"
				rel="noopener noreferrer"
			>
				{{ ctaLabel }}
			</a>
		</div>

		<button
			type="button"
			class="aio-login-appsumo-hello-bar__close"
			:aria-label="dismissLabel"
			@click="dismiss"
		>
			<svg width="15" height="15" viewBox="0 0 15 15" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
				<circle cx="7.5" cy="7.5" r="7.5" fill="#ffffff" fill-opacity="0.8" />
				<path d="M10.1469 10.8536C10.1933 10.9 10.2484 10.9368 10.309 10.962C10.3697 10.9871 10.4346 11 10.5003 11C10.5659 11 10.6309 10.9871 10.6915 10.962C10.7521 10.9368 10.8072 10.9 10.8536 10.8536C10.9 10.8072 10.9368 10.7521 10.962 10.6915C10.9871 10.6309 11 10.5659 11 10.5003C11 10.4346 10.9871 10.3697 10.962 10.309C10.9368 10.2484 10.9 10.1933 10.8536 10.1469L8.20673 7.5L10.8536 4.8531C10.9 4.80669 10.9368 4.7516 10.962 4.69097C10.9871 4.63034 11 4.56536 11 4.49973C11 4.43411 10.9871 4.36912 10.962 4.30849C10.9368 4.24786 10.9 4.19277 10.8536 4.14637C10.8072 4.09996 10.7521 4.06315 10.6915 4.03804C10.6309 4.01293 10.5659 4 10.5003 4C10.4346 4 10.3697 4.01293 10.309 4.03804C10.2484 4.06315 10.1933 4.09996 10.1469 4.14637L7.5 6.79327L4.8531 4.14637C4.80669 4.09996 4.7516 4.06315 4.69097 4.03804C4.63034 4.01293 4.56536 4 4.49973 4C4.3672 4 4.24009 4.05265 4.14637 4.14637C4.09996 4.19277 4.06315 4.24786 4.03804 4.30849C4.01293 4.36912 4 4.43411 4 4.49973C4 4.63227 4.05265 4.75938 4.14637 4.8531L6.79327 7.5L4.14637 10.1469C4.09996 10.1933 4.06315 10.2484 4.03804 10.309C4.01293 10.3697 4 10.4346 4 10.5003C4 10.5659 4.01293 10.6309 4.03804 10.6915C4.06315 10.7521 4.09996 10.8072 4.14637 10.8536C4.19277 10.9 4.24786 10.9368 4.30849 10.962C4.36912 10.9871 4.43411 11 4.49973 11C4.56536 11 4.63034 10.9871 4.69097 10.962C4.7516 10.9368 4.80669 10.9 4.8531 10.8536L7.5 8.20673L10.1469 10.8536Z" fill="#000000" fill-opacity="0.6" />
			</svg>
		</button>
	</div>
</template>

<script>
const STORAGE_KEY = 'aio_login_appsumo_hello_bar_dismissed';

export default {
	name: 'aio-login-appsumo-hello-bar',

	data: () => ( {
		dismissed: false,
	} ),

	computed: {
		assetsUrl() {
			return window.aio_login__app_object.assets_url || '';
		},

		shouldShow() {
			const o = window.aio_login__app_object || {};
			return o.show_appsumo_hello_bar === true || o.show_appsumo_hello_bar === 'true';
		},

		visible() {
			return this.shouldShow && ! this.dismissed;
		},

		ctaUrl() {
			const o = window.aio_login__app_object || {};
			return o.appsumo_deal_url || 'https://appsumo.8odi.net/DWbJmq';
		},

		headline() {
			return this.getString( 'hello_bar_headline', 'Good News!' );
		},

		messageBefore() {
			return this.getString( 'hello_bar_message_before', 'All In One Login is live on ' );
		},

		messageHighlight() {
			return this.getString( 'hello_bar_message_highlight', 'AppSumo' );
		},

		ctaLabel() {
			return this.getString( 'hello_bar_cta_label', 'Get Lifetime Deal Now' );
		},

		ariaLabel() {
			return this.getString( 'hello_bar_aria_label', 'AppSumo promotion' );
		},

		dismissLabel() {
			return this.getString( 'hello_bar_dismiss_label', 'Dismiss promotion' );
		},
	},

	mounted() {
		try {
			this.dismissed = window.localStorage.getItem( STORAGE_KEY ) === '1';
		} catch ( e ) {
			this.dismissed = false;
		}
	},

	methods: {
		getString( key, fallback ) {
			const o = window.aio_login__app_object || {};
			if ( typeof o[ key ] === 'string' && o[ key ].length ) {
				return o[ key ];
			}
			return fallback;
		},

		dismiss() {
			this.dismissed = true;
			try {
				window.localStorage.setItem( STORAGE_KEY, '1' );
			} catch ( e ) {
				// Ignore storage failures; banner still hides for this session.
			}
		},
	},
};
</script>

<style scoped>
.aio-login-appsumo-hello-bar {
	position: relative;
	width: 100%;
	min-height: 50px;
	background: #7ef689;
	overflow: hidden;
	box-sizing: border-box;
}

.aio-login-appsumo-hello-bar__bokeh {
	position: absolute;
	top: -68px;
	width: 305px;
	height: 177px;
	pointer-events: none;
	background-repeat: no-repeat;
	background-size: contain;
	background-position: center;
	z-index: 0;
}

.aio-login-appsumo-hello-bar__bokeh--left {
	left: -59px;
	transform: rotate(180deg);
}

.aio-login-appsumo-hello-bar__bokeh--right {
	right: -59px;
}

.aio-login-appsumo-hello-bar__inner {
	position: relative;
	z-index: 1;
	display: flex;
	align-items: center;
	justify-content: center;
	flex-wrap: wrap;
	gap: 12px 20px;
	width: 100%;
	min-height: 50px;
	padding: 9px 56px;
	box-sizing: border-box;
}

.aio-login-appsumo-hello-bar__copy {
	display: flex;
	align-items: center;
	justify-content: center;
	flex-wrap: wrap;
	gap: 8px 20px;
	text-align: center;
}

.aio-login-appsumo-hello-bar__headline-wrap {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-height: 28px;
	flex-shrink: 0;
}

.aio-login-appsumo-hello-bar__headline {
	display: inline-block;
	font-family: 'Protest Riot', cursive !important;
	font-style: normal !important;
	font-weight: 400 !important;
	font-size: 16px;
	line-height: 1.5;
	color: #000000;
	text-align: center;
	transform: rotate(-3.2deg);
	white-space: nowrap;
	letter-spacing: 0;
	-webkit-font-smoothing: antialiased;
}

.aio-login-appsumo-hello-bar__text {
	margin: 0;
	font-family: Figtree, sans-serif !important;
	font-size: 14px;
	line-height: 1;
	font-weight: 600;
	color: #000000;
	white-space: nowrap;
}

.aio-login-appsumo-hello-bar__brand {
	font-family: Figtree, sans-serif !important;
	font-weight: 700 !important;
}

.aio-login-appsumo-hello-bar__cta {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	padding: 9px 13px;
	border: 1px solid #000000;
	border-radius: 6px;
	background: #ffffff;
	color: #000000;
	font-size: 14px;
	font-weight: 600;
	line-height: 1;
	text-decoration: none;
	box-shadow: 0 2px 0 #000000;
	white-space: nowrap;
	transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.aio-login-appsumo-hello-bar__cta:hover,
.aio-login-appsumo-hello-bar__cta:focus {
	color: #000000;
	transform: translateY(1px);
	box-shadow: 0 1px 0 #000000;
}

.aio-login-appsumo-hello-bar__close {
	position: absolute;
	top: 50%;
	right: 20px;
	z-index: 2;
	transform: translateY(-50%);
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 28px;
	height: 28px;
	padding: 0;
	border: 0;
	background: transparent;
	cursor: pointer;
	flex-shrink: 0;
}

.aio-login-appsumo-hello-bar__close:hover svg circle,
.aio-login-appsumo-hello-bar__close:focus svg circle {
	fill-opacity: 1;
}

.aio-login-appsumo-hello-bar__close svg {
	display: block;
	width: 15px;
	height: 15px;
}

@media (max-width: 782px) {
	.aio-login-appsumo-hello-bar__inner {
		padding-right: 48px;
		padding-left: 12px;
	}

	.aio-login-appsumo-hello-bar__close {
		right: 12px;
	}

	.aio-login-appsumo-hello-bar__copy {
		flex-direction: column;
		gap: 4px;
	}

	.aio-login-appsumo-hello-bar__text,
	.aio-login-appsumo-hello-bar__headline {
		white-space: normal;
	}
}
</style>
