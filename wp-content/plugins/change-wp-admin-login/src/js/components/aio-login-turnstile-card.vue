<template>
	<div v-if="page_loaded" class="aio-login-pro__social-login__card">
		<span v-if="showProMarketingBadge" class="aio-login__pro-tab">{{ $t("PRO") }}</span>

		<div
			v-if="statusBadge && hasPro"
			class="configured-tag"
			:class="{ 'enabled': statusBadge === 'green', 'disabled': statusBadge === 'orange' }"
		>
			{{ statusBadgeText }}
		</div>

		<div class="aio-login-pro__social-login__card__top">
			<img :src="getSrc('turnstile')" :alt="'Cloudflare Turnstile'" />
			<p>
				<span>{{ $t("Cloudflare Turnstile") }}</span>
			</p>
		</div>

		<div class="aio-login-pro__social-login__card__bottom">
			<label class="toggle-switch" @click="handleToggleClick">
				<aio-login-toggle
					id="turnstile"
					name="turnstile"
					v-on:toggle-input="handleToggle"
					:enabled="enabled"
					:disabled="!hasPro"
				/>
			</label>
			<button
				v-if="enabled && hasPro"
				class="configure-btn"
				@click="configureCaptcha"
				@mouseenter="onHover"
				@mouseleave="onLeave"
			>{{ $t("Configure") }}</button>
		</div>

		<div v-if="!hasPro" class="aio-login-t-content-overflow" @click.stop="iWasTriggered"></div>

		<aio-login-turnstile-popup
			:show="showPopup"
			:initial-data="popupData"
			:api-namespace="apiNamespace"
			:api-nonce="apiNonce"
			@close="closePopup"
			@save="saveSettings"
		/>
	</div>
</template>

<script>
export default {
	name: 'aio-login-turnstile-card',

	props: {
		hasPro: {
			type: Boolean,
			default: false,
		},
		enabled: {
			type: Boolean,
			default: false,
		},
		configData: {
			type: Object,
			default: () => ({})
		},
		apiNonce: {
			type: String,
			default: '',
		},
		apiNamespace: {
			type: String,
			default: 'aio-login/turnstile',
		},
	},

	data: () => ({
		assetsUrl: aio_login__app_object.assets_url,
		page_loaded: false,
		showPopup: false,
		popupData: {},
		isHovered: false
	}),

	computed: {
		proPluginActive() {
			const o = typeof window !== 'undefined' ? window.aio_login__app_object : null;
			return !!( o && ( o.has_pro === 'true' || o.has_pro === true ) );
		},
		showProMarketingBadge() {
			return ! this.hasPro && ! this.proPluginActive;
		},
		statusBadge() {
			if ( ! this.hasValidKeys() ) {
				return null;
			}
			// Configured + enabled = green; configured but disabled = orange.
			return this.enabled ? 'green' : 'orange';
		},
		statusBadgeText() {
			return this.statusBadge ? 'Configured' : '';
		}
	},

	methods: {
		getSrc(icon) {
			if (icon === 'turnstile') {
				return this.assetsUrl + 'images/icons/turnstile.svg';
			}
			return this.assetsUrl + `images/icons/${icon}.png`;
		},
		hasValidKeys() {
			return this.configData.siteKey && this.configData.secretKey;
		},
		handleToggleClick(event) {
			if (!this.hasPro) {
				event.preventDefault();
				event.stopPropagation();
				event.stopImmediatePropagation();
				this.iWasTriggered();
				return false;
			}
		},
		handleToggle(enabled) {
			if (this.hasPro) {
				this.$emit('toggle-captcha', enabled);
			} else {
				this.iWasTriggered();
			}
		},
		configureCaptcha() {
			if (this.hasPro) {
				this.popupData = { ...this.configData };
				this.showPopup = true;
			} else {
				this.iWasTriggered();
			}
		},
		iWasTriggered() {
			let parent = this.$parent;
			while (parent) {
				if (parent.popup !== undefined) {
					parent.popup = true;
					return;
				}
				parent = parent.$parent;
			}
			if (this.$parent && this.$parent.$parent) {
				this.$parent.$parent.popup = true;
			}
		},
		closePopup() {
			this.showPopup = false;
			this.popupData = {};
		},
		saveSettings(data) {
			this.$emit('save-settings', data);
			this.closePopup();
		},
		onHover() {
			this.isHovered = true;
		},
		onLeave() {
			this.isHovered = false;
		}
	},

	mounted() {
		this.page_loaded = true;
	}
}
</script>

<style scoped>
.aio-login-t-content-overflow { position: absolute; top: 0; left: 0; width: 100%; height: 100%; backdrop-filter: blur(0.5px); z-index: 100; cursor: pointer; background: rgba(255, 255, 255, 0.3); }
.aio-login__pro-tab { position: absolute;
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
	z-index: 101; }
.configured-tag { position: absolute !important; top: 8px !important; right: 8px !important; padding: 3px 6px !important; font-size: 10px !important; font-weight: 700 !important; border-radius: 8px !important; color: white !important; z-index: 999 !important; text-transform: uppercase !important; white-space: nowrap !important; }
.configured-tag.enabled { background-color: #22c55e !important; }
.configured-tag.disabled { background-color: #f97316 !important; }
.aio-login-pro__social-login__card { position: relative !important; overflow: visible !important; border: 1px solid #ebe8eb; border-radius: 10px; height: 200px; width: 320px; display: flex; flex-direction: column; justify-content: space-between; }
.aio-login-pro__social-login__card__top { flex: 4; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 10px; text-align: center; }
.aio-login-pro__social-login__card__top img { width: 48px; height: 48px; margin-bottom: 10px; object-fit: contain; }
.aio-login-pro__social-login__card__top p { font-size: 14px; font-weight: bold; margin: 0 0 5px 0; }
.aio-login-pro__social-login__card__bottom { flex: 1; display: flex; justify-content: space-between; align-items: center; padding: 10px; border-top: 1px solid #ebe8eb; }
.configure-btn { background: #f7ecfd; color: #6e16df; border: none; border-radius: 4px; font-size: 14px; cursor: pointer; transition: 0.3s; padding: 9px 18px; }
.configure-btn:hover { background-color: #C9D2E3; color: #6e16df; }
</style>
