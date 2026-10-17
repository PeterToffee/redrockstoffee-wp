/**
 * Importing layouts and components
 */
import aioLoginApp from './layouts/aio-login-app.vue';
/**
 * Importing header components
 */
import aioLoginHeader from './components/aio-login-header.vue';
import aioLoginTabs from './components/aio-login-tabs.vue';
import aioLoginSubTabs from './components/aio-login-sub-tabs.vue';
/**
 * Importing dashboard components
 */
import aioLoginDashboard from './pages/aio-login-dashboard.vue';
import aioLoginCards from './components/aio-login-cards.vue';
import aioLoginCard from './components/aio-login-card.vue';
import aioLoginDaysSelector from './components/aio-login-days-selector.vue';

import aioLoginTempAccess from './pages/aio-login-temp-access.vue';
import aioLoginSocialLoginMain from './pages/social-login-main.vue';
import aioLoginIntegrations from './pages/integrations.vue';
import aioLoginCaptcha from './demo/components/aio-login-captcha.vue';
import aioLoginRecaptchaPopup from './components/aio-login-recaptcha-popup.vue';
import aioLoginCaptchaVerify from './components/aio-login-captcha-verify.vue';
import aioLoginHcaptchaCard from './components/aio-login-hcaptcha-card.vue';
import aioLoginHcaptchaPopup from './components/aio-login-hcaptcha-popup.vue';
import aioLoginTurnstileCard from './components/aio-login-turnstile-card.vue';
import aioLoginTurnstilePopup from './components/aio-login-turnstile-popup.vue';
import aioLoginWooCommercePopup from './components/aio-login-woocommerce-popup.vue';
import aioLoginWooCommerceCard from './components/aio-login-woocommerce-card.vue';
import aioLoginNotificationChannelCard from './components/aio-login-notification-channel-card.vue';
/**
 * Importing metadata components
 */
import aioLoginMeta from './components/aio-login-meta.vue';
import aioLoginMetadata from './components/aio-login-metadata.vue';
/**
 * Importing pro branding components
 */
import aioLoginProBranding from './components/aio-login-pro-branding.vue';
import aioLoginRecentActivity from './components/aio-login-recent-activity.vue';
import aioLoginDashboardDocs from './components/aio-login-dashboard-docs.vue';

/**
 * Importing global components
 */
import aioLoginForm from './components/aio-login-form.vue';
import aioLoginSubmitButton from './components/aio-login-submit-button.vue';
import aioLoginMedia from './components/aio-login-media.vue';
import aioLoginColorPicker from "./components/aio-login-color-picker.vue";

import aioLoginToggle from "./components/fields/aio-login-toggle.vue";
import aioLoginText from "./components/fields/aio-login-text.vue";

import aioLoginSnackbar from "./components/aio-login-snackbar.vue";

import aioLoginGetPro from './components/aio-login-get-pro.vue';

import aioLoginLoginRedirection from './pages/login-redirection-feature.js';

import aioLoginDatatable from './components/aio-login-datatable.vue';
import aioLoginPopup from './components/aio-login-popup.vue';
import aioLoginProPopup from './components/aio-login-pro-popup.vue';
import aioLoginAppsumoHelloBar from './components/aio-login-appsumo-hello-bar.vue';
import aioLoginTooltip from './components/aio-login-tooltip.vue';
import { t as aioLoginT } from './i18n.js';


let aio_login__mount_helper = function() {
};

aio_login__mount_helper.prototype.mount = function( element, required_components = {} ) {

	function is_pro_tab( tab ) {
		return typeof tab['is-pro'] !== 'undefined' && tab['is-pro'] === true;
	}

	const { vue, vueRouter, aio_login__object } = window,
		{ createApp, resolveComponent } = vue,
		{ createRouter, createWebHashHistory } = vueRouter,
		{ routes } = aio_login__object;

	let aio_login__app = createApp( {} );

	// Global i18n helper for templates: {{ $t('Save Changes') }} / this.$t(...)
	aio_login__app.config.globalProperties.t = aioLoginT;
	aio_login__app.config.globalProperties.$t = aioLoginT;

	if ( routes.length ) {
		aio_login__app.use( createRouter( {
			history: createWebHashHistory(),
			routes,
		} ) );
	} else {
		aio_login__app.component( 'router-view', { template: `` } )
	}

	let container = this;

		Object.values( aio_login__app_object.tabs ).forEach( function( tab ) {
			if ( tab['sub-tabs'] ) {
				Object.values( tab['sub-tabs'] ).forEach( function( sub_tab ) {
					Object.keys(required_components).forEach(function (component_name) {
						if (
							sub_tab.slug === required_components[component_name].slug &&
							(
								is_pro_tab( sub_tab ) ||
								sub_tab.slug === 'authentication-methods'
							)
						) {
							aio_login__app.component(component_name, required_components[component_name]);
						}
					});
				} );
			} else {
				Object.keys(required_components).forEach(function (component_name) {
					if ( tab.slug === required_components[component_name].slug && is_pro_tab( tab ) ) {
						aio_login__app.component(component_name, required_components[component_name]);
					}
				});
			}
		} )

	// Register Pro components after free demos so real Pro UI wins (e.g. 2FA Authentication Methods).
	if ( window.aio_login_pro && window.aio_login_pro.plugin ) {
		aio_login__app.use( window.aio_login_pro.plugin(), { aio_login__object: aio_login__object, axios: axios, tabs: aio_login__app_object.tabs } );
	}

	aio_login__app.component( 'aio-login-login-redirection', aioLoginLoginRedirection );

	aio_login__app.component( 'aio-login-app', aioLoginApp );
	aio_login__app.component( 'aio-login-header', aioLoginHeader );
	aio_login__app.component( 'aio-login-tabs', aioLoginTabs );
	aio_login__app.component( 'aio-login-sub-tabs', aioLoginSubTabs );
	aio_login__app.component( 'aio-login-dashboard', aioLoginDashboard );
	aio_login__app.component( 'aio-login-cards', aioLoginCards );
	aio_login__app.component( 'aio-login-card', aioLoginCard );
	aio_login__app.component( 'aio-login-days-selector', aioLoginDaysSelector );
	aio_login__app.component( 'aio-login-meta', aioLoginMeta );
	aio_login__app.component( 'aio-login-metadata', aioLoginMetadata );
	aio_login__app.component( 'aio-login-pro-branding', aioLoginProBranding );
	aio_login__app.component( 'aio-login-recent-activity', aioLoginRecentActivity );
	aio_login__app.component( 'aio-login-dashboard-docs', aioLoginDashboardDocs );
	aio_login__app.component( 'aio-login-temp-access', aioLoginTempAccess );
	aio_login__app.component( 'aio-login-social-login-main', aioLoginSocialLoginMain );
	aio_login__app.component( 'aio-login-integrations', aioLoginIntegrations );
	aio_login__app.component( 'aio-login-captcha', aioLoginCaptcha );
	aio_login__app.component( 'aio-login-recaptcha-popup', aioLoginRecaptchaPopup );
	aio_login__app.component( 'aio-login-captcha-verify', aioLoginCaptchaVerify );
	aio_login__app.component( 'aio-login-hcaptcha-card', aioLoginHcaptchaCard );
	aio_login__app.component( 'aio-login-hcaptcha-popup', aioLoginHcaptchaPopup );
	aio_login__app.component( 'aio-login-turnstile-card', aioLoginTurnstileCard );
	aio_login__app.component( 'aio-login-turnstile-popup', aioLoginTurnstilePopup );
	aio_login__app.component( 'aio-login-woocommerce-popup', aioLoginWooCommercePopup );
	aio_login__app.component( 'aio-login-woocommerce-card', aioLoginWooCommerceCard );
	aio_login__app.component( 'aio-login-notification-channel-card', aioLoginNotificationChannelCard );
	aio_login__app.component( 'aio-login-form', aioLoginForm );
	aio_login__app.component( 'aio-login-submit-button', aioLoginSubmitButton );
	aio_login__app.component( 'aio-login-media', aioLoginMedia );
	aio_login__app.component( 'aio-login-color-picker', aioLoginColorPicker );
	aio_login__app.component( 'aio-login-toggle', aioLoginToggle );
	aio_login__app.component( 'aio-login-text', aioLoginText );
	aio_login__app.component( 'aio-login-datatable', aioLoginDatatable );
	aio_login__app.component( 'aio-login-snackbar', aioLoginSnackbar );
	aio_login__app.component( 'aio-login-getpro', aioLoginGetPro );
	aio_login__app.component( 'aio-login-popup', aioLoginPopup );
	aio_login__app.component( 'aio-login-pro-popup', aioLoginProPopup );
	aio_login__app.component( 'aio-login-appsumo-hello-bar', aioLoginAppsumoHelloBar );
	aio_login__app.component( 'aio-login-tooltip', aioLoginTooltip );

	aio_login__app.mount( element );
}

window.aio_login__mount_helper_instance = new aio_login__mount_helper();

try {

	require('./demo-components-vue.js');
} catch ( e ) {}




