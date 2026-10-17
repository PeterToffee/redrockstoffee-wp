<template>
	<div v-if="page_loaded" class="aio-login-pro__social-login__card">
		<template v-if="loading">
			<div class="aio-login-pro__social-login__card__top">
				<div class="notifications-card-skeleton notifications-card-skeleton--logo"></div>
				<div class="notifications-card-skeleton notifications-card-skeleton--title"></div>
			</div>
			<div class="aio-login-pro__social-login__card__bottom">
				<div class="notifications-card-skeleton notifications-card-skeleton--toggle"></div>
				<div class="notifications-card-skeleton notifications-card-skeleton--button"></div>
			</div>
		</template>
		<template v-else>
		<span v-if="showProMarketingBadge" class="aio-login__pro-tab">{{ $t("PRO") }}</span>
		<div
			v-if="statusBadge && hasPro"
			class="configured-tag"
			:class="{ 'enabled': statusBadge === 'green', 'disabled': statusBadge === 'orange' }"
		>
			{{ statusBadgeText }}
		</div>

		<div class="aio-login-pro__social-login__card__top">
			<img :src="iconSrc" :alt="title" />
			<p class="aio-login-pro__social-login__card__title">
				<span>{{ title }}</span>
			</p>
		</div>

		<div class="aio-login-pro__social-login__card__bottom">
			<label class="toggle-switch" @click="handleToggleClick">
				<aio-login-toggle
					:id="toggleId"
					:name="toggleName"
					v-on:toggle-input="handleToggle"
					:enabled="enabled"
					:disabled="!hasPro"
				/>
			</label>
			<button
				v-if="enabled && hasPro"
				class="configure-btn"
				@click="configureIntegration"
				@mouseenter="onHover"
				@mouseleave="onLeave"
			>{{ $t("Configure") }}</button>
		</div>

		<div v-if="!hasPro" class="aio-login-t-content-overflow" @click.stop="iWasTriggered"></div>
		</template>
	</div>
</template>

<script>
export default {
	name: 'aio-login-notification-channel-card',

	props: {
		channel: {
			type: String,
			required: true,
			validator: ( v ) => [ 'slack', 'webhook' ].includes( v ),
		},
		hasPro: {
			type: Boolean,
			default: false,
		},
		enabled: {
			type: Boolean,
			default: false,
		},
		loading: {
			type: Boolean,
			default: false,
		},
		configData: {
			type: Object,
			default: () => ( {} ),
		},
	},

	data: () => ( {
		assetsUrl: aio_login__app_object.assets_url,
		assetsVer: aio_login__app_object.version || '',
		page_loaded: true,
		isHovered: false,
	} ),

	computed: {
		proPluginActive() {
			const o = typeof window !== 'undefined' ? window.aio_login__app_object : null;
			return !!( o && ( o.has_pro === 'true' || o.has_pro === true ) );
		},
		showProMarketingBadge() {
			return ! this.hasPro && ! this.proPluginActive;
		},
		title() {
			return 'slack' === this.channel ? 'Slack' : 'Webhook';
		},

		toggleId() {
			return 'aio-login-notification-' + this.channel;
		},

		toggleName() {
			return 'aio-login-notification-' + this.channel;
		},

		iconSrc() {
			const base = this.assetsUrl + 'images/icons/notification-' + this.channel + '.svg';
			return this.assetsVer ? base + '?ver=' + encodeURIComponent( this.assetsVer ) : base;
		},

		statusBadge() {
			if ( this.enabled && this.isConfigured() ) {
				return 'green';
			}
			if ( ! this.enabled && this.isConfigured() ) {
				return 'orange';
			}
			return null;
		},

		statusBadgeText() {
			if ( this.statusBadge === 'green' || this.statusBadge === 'orange' ) {
				return 'Configured';
			}
			return '';
		},
	},

	methods: {
		isConfigured() {
			const url = this.configData && ( this.configData.url || this.configData.webhook_url || '' );
			return String( url ).trim().length > 0;
		},

		handleToggleClick( event ) {
			if ( ! this.hasPro ) {
				event.preventDefault();
				event.stopPropagation();
				event.stopImmediatePropagation();
				this.iWasTriggered();
				return false;
			}
		},

		handleToggle( on ) {
			if ( this.hasPro ) {
				this.$emit( 'toggle-integration', on );
			} else {
				this.iWasTriggered();
			}
		},

		configureIntegration() {
			if ( this.hasPro ) {
				this.$emit( 'configure-integration' );
			} else {
				this.iWasTriggered();
			}
		},

		iWasTriggered() {
			let parent = this.$parent;
			while ( parent ) {
				if ( parent.popup !== undefined ) {
					parent.popup = true;
					return;
				}
				parent = parent.$parent;
			}
			if ( this.$parent && this.$parent.$parent ) {
				this.$parent.$parent.popup = true;
			}
		},

		onHover() {
			this.isHovered = true;
		},

		onLeave() {
			this.isHovered = false;
		},
	},
}
</script>

<style scoped>
.aio-login-t-content-overflow {
	position: absolute;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;
	backdrop-filter: blur(0.5px);
	z-index: 100;
	cursor: pointer;
	background: rgba(255, 255, 255, 0.3);
}

.aio-login__pro-tag {
	position: absolute;
	top: 8px;
	left: 8px;
	display: inline-flex;
    align-items: center;
    justify-content: center;
    box-sizing: border-box;
    min-width: 34px;
    padding: 5px 6px;
    margin: 0;
    background: #9516DF;
    border-radius: 3px;
    color: #FFFFFF;
    font-family: Figtree, sans-serif;
    font-size: 12px;
    font-weight: 700;
    line-height: 1;
    letter-spacing: 0.02em;
    text-transform: uppercase;
    /* height: 18px; */
    flex-shrink: 0;
	z-index: 101;
}

.configured-tag {
	position: absolute !important;
	top: 8px !important;
	right: 8px !important;
	padding: 3px 6px !important;
	font-size: 10px !important;
	font-weight: 700 !important;
	border-radius: 8px !important;
	color: white !important;
	z-index: 999 !important;
	text-transform: uppercase !important;
	letter-spacing: 0.3px !important;
	line-height: 1 !important;
	display: inline-block !important;
	white-space: nowrap !important;
}

.configured-tag.enabled {
	background-color: #22c55e !important;
	box-shadow: 0 1px 3px rgba(34, 197, 94, 0.4) !important;
}

.configured-tag.disabled {
	background-color: #f97316 !important;
	box-shadow: 0 1px 3px rgba(249, 115, 22, 0.4) !important;
}

.aio-login-pro__social-login__card {
	position: relative !important;
	overflow: visible !important;
	border: 1px solid #ebe8eb;
	border-radius: 10px;
	height: 200px;
	width: 320px;
	display: flex;
	flex-direction: column;
	justify-content: space-between;
}

.aio-login-pro__social-login__card__top {
	flex: 2;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	padding: 10px;
	text-align: center;
}

.aio-login-pro__social-login__card__top img {
	width: auto;
	height: auto;
	max-width: 230px;
	max-height: 100px;
	object-fit: contain;
}

.aio-login-pro__social-login__card__top p {
	font-size: 14px;
	font-weight: bold;
	margin: 0 0 5px 0;
}

.aio-login-pro__social-login__card__bottom {
	flex: 1;
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 10px;
	border-top: 1px solid #ebe8eb;
}

.configure-btn {
	background: #f7ecfd;
	color: #6e16df;
	border: none;
	border-radius: 4px;
	font-size: 14px;
	cursor: pointer;
	transition: 0.3s;
	padding: 9px 18px;
}

.configure-btn:hover {
	background-color: #c9d2e3;
	color: #6e16df;
}

.notifications-card-skeleton {
	display: block;
	border-radius: 6px;
	background: linear-gradient( 90deg, #f2f4f8 25%, #e7ebf3 50%, #f2f4f8 75% );
	background-size: 200% 100%;
	animation: notifications-card-skeleton-shimmer 1.3s ease-in-out infinite;
}

.notifications-card-skeleton--logo {
	width: 160px;
	height: 56px;
}

.notifications-card-skeleton--title {
	width: 90px;
	height: 14px;
	margin-top: 10px;
}

.notifications-card-skeleton--toggle {
	width: 50px;
	height: 24px;
}

.notifications-card-skeleton--button {
	width: 90px;
	height: 32px;
}

@keyframes notifications-card-skeleton-shimmer {
	0% {
		background-position: 200% 0;
	}
	100% {
		background-position: -200% 0;
	}
}
</style>
