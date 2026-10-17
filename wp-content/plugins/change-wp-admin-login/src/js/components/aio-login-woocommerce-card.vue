<template>
	<div v-if="page_loaded" class="aio-login-pro__social-login__card">
		<!-- Configured Tag -->
		<div 
			v-if="statusBadge && hasPro" 
			class="configured-tag"
			:class="{ 'enabled': statusBadge === 'green', 'disabled': statusBadge === 'orange' }"
		>
			{{ statusBadgeText }}
		</div>
		
		<!-- Top Section -->
		<div class="aio-login-pro__social-login__card__top">
			<img :src="getSrc('woocommerce')" :alt="'WooCommerce'" />
		</div>
		
		<!-- Bottom Section -->
		<div class="aio-login-pro__social-login__card__bottom">
			<label class="toggle-switch" @click="handleToggleClick" :class="{ 'disabled': !woocommerceActive }">
				<aio-login-toggle
					id="woocommerce"
					name="woocommerce"
					v-on:toggle-input="handleToggle"
					:enabled="enabled && woocommerceActive"
					:disabled="!hasPro || !woocommerceActive"
				/>
			</label>
			<!-- Show Configure button only if the toggle is enabled and Pro is active -->
			<button
				v-if="enabled && hasPro"
				class="configure-btn"
				@click="configureIntegration"
				@mouseenter="onHover"
				@mouseleave="onLeave"
			>{{ $t("Configure") }}</button>
		</div>

		<!-- Pro Overlay for Free Users -->
		<div v-if="!hasPro" class="aio-login-t-content-overflow" @click.stop="iWasTriggered"></div>
	</div>
</template>

<script>
import tooltipContent from '../tooltip-content.js';

export default {
	name: 'aio-login-woocommerce-card',

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
		}
	},

	data: ( vm ) => ( {
		tooltipContent,
		assetsUrl: aio_login__app_object.assets_url,
		page_loaded: true,
		isHovered: false,
		woocommerceActive: true // Default to true, will be updated from configData
	} ),

	computed: {
		statusBadge() {
			if (this.enabled && this.isConfigured()) {
				return 'green';
			} else if (!this.enabled && this.isConfigured()) {
				return 'orange';
			}
			return null;
		},

		statusBadgeText() {
			if (this.statusBadge === 'green') {
				return 'Configured';
			} else if (this.statusBadge === 'orange') {
				return 'Configured';
			}
			return '';
		}
	},

	methods: {
		loadComponent() {
			this.$nextTick( () => {
				this.page_loaded = true;
			} );
		},

		getSrc( icon ) {
			if (icon === 'woocommerce') {
				return this.assetsUrl + 'images/icons/woocommerce.svg';
			}
			return this.assetsUrl + `images/icons/${ icon }.png`;
		},

		isConfigured() {
			// Check if WooCommerce is actually configured
			if (!this.configData || Object.keys(this.configData).length === 0) {
				return false;
			}
			
			// Check if Captcha is enabled and has at least one provider configured
			if (this.configData.captchaEnabled) {
				const providers = this.configData.providers || {};
				for (const provider in providers) {
					if (providers[provider].login || providers[provider].registration) {
						return true;
					}
				}
			}
			
			// Check if Social Login is enabled and has at least one provider configured
			if (this.configData.socialLoginEnabled) {
				const socialProviders = this.configData.socialProviders || {};
				for (const provider in socialProviders) {
					if (socialProviders[provider].login || socialProviders[provider].registration || socialProviders[provider].checkout) {
						return true;
					}
				}
			}
			
			return false;
		},

		handleToggleClick(event) {
			// If Pro is not active, show popup on toggle click
			if (!this.hasPro) {
				event.preventDefault();
				event.stopPropagation();
				event.stopImmediatePropagation();
				this.iWasTriggered();
				return false;
			}
		},

		handleToggle( enabled ) {
			if (!this.woocommerceActive && enabled) {
				// Don't allow enabling if WooCommerce is not active
				return;
			}
			if (this.hasPro) {
				this.$emit('toggle-integration', enabled);
			} else {
				this.iWasTriggered();
			}
		},

		configureIntegration() {
			if (this.hasPro) {
				this.$emit('configure-integration');
			} else {
				this.iWasTriggered();
			}
		},

		iWasTriggered() {
			// Find the root app component that has the popup property
			let parent = this.$parent;
			while (parent) {
				if (parent.popup !== undefined) {
					parent.popup = true;
					return;
				}
				parent = parent.$parent;
			}
			// Fallback: try direct parent chain
			if (this.$parent && this.$parent.$parent) {
				this.$parent.$parent.popup = true;
			}
		},

		onHover() {
			this.isHovered = true;
		},

		onLeave() {
			this.isHovered = false;
		}
	},

	watch: {
		configData: {
			handler(newData) {
				if (newData && newData.woocommerceActive !== undefined) {
					this.woocommerceActive = newData.woocommerceActive;
				}
			},
			immediate: true,
			deep: true
		}
	},

	mounted() {
		// Component is ready
		// Check configData for WooCommerce active status
		if (this.configData && this.configData.woocommerceActive !== undefined) {
			this.woocommerceActive = this.configData.woocommerceActive;
		}
	}
}
</script>

<style scoped>
/* Exact copy of social login pro card styling */
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
  background-color: #22c55e !important; /* Green for enabled */
  box-shadow: 0 1px 3px rgba(34, 197, 94, 0.4) !important;
}

.configured-tag.disabled {
  background-color: #f97316 !important; /* Orange for disabled */
  box-shadow: 0 1px 3px rgba(249, 115, 22, 0.4) !important;
}

/* Social Login Card */
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

/* Top Section */
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

/* Bottom Section */
.aio-login-pro__social-login__card__bottom {
  flex: 1;
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px;
  border-top: 1px solid #ebe8eb;
}

/* Configure Button */
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
  background-color: #C9D2E3;
  color: #6e16df;
}

.woocommerce-required-notice {
  margin-top: 10px;
  padding: 8px 12px;
  background: #fff3cd;
  border: 1px solid #ffc107;
  border-radius: 4px;
  text-align: center;
}

.woocommerce-required-notice p {
  margin: 0;
  font-size: 11px;
  color: #856404;
  font-weight: 600;
}

.toggle-switch.disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>


