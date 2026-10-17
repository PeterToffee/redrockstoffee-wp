<template>
	<div>
		<div class="aio-login-t-wrapper">
			<div>
				<h3>
					<span>{{ $t("Captcha Settings") }}</span>
					<aio-login-tooltip
						:content="tooltipContent.captcha.content"
						placement="bottom"
					/>
				</h3>
				<div v-if="showSingleCaptchaNotice && anyCaptchaEnabled && !noteDismissed" class="captcha-note">
					<span>{{ $t("Note: You can only enable one captcha at a time.") }}</span>
					<button type="button" class="dismiss-btn" @click="dismissNote" aria-label="Dismiss">×</button>
				</div>
			</div>
			<div class="aio-login-pro__social-login">
				<!-- Google reCAPTCHA Card -->
				<aio-login-captcha
					:has-pro="has_pro"
					:enabled="form_data.enabled"
					:config-data="configData"
					:api-nonce="nonce"
					@toggle-captcha="handleToggleCaptcha"
					@save-settings="handleSaveSettings"
				/>

				<!-- hCaptcha Card (Business+; reCAPTCHA card uses has_pro only) -->
				<aio-login-hcaptcha-card
					:has-pro="hcaptchaUnlocked"
					:enabled="hcaptcha_form_data.enabled"
					:config-data="hcaptchaConfigData"
					:api-nonce="hcaptcha_nonce"
					@toggle-captcha="handleToggleHCaptcha"
					@save-settings="handleSaveHCaptchaSettings"
				/>

				<!-- Cloudflare Turnstile Card (Business+) -->
				<aio-login-turnstile-card
					:has-pro="turnstileUnlocked"
					:enabled="turnstile_form_data.enabled"
					:config-data="turnstileConfigData"
					:api-namespace="turnstile_namespace"
					:api-nonce="turnstile_nonce"
					@toggle-captcha="handleToggleTurnstile"
					@save-settings="handleSaveTurnstileSettings"
				/>
			</div>
		</div>

		<aio-login-snackbar
			:message="snackbar.message"
			:duration="snackbar.duration"
			v-if="snackbar.show"
			v-on:close="handleCloseSnackbar"
		/>
	</div>
</template>

<script>
import tooltipContent from '../tooltip-content.js';
import resolveParentCurrentIsPro from '../resolve-parent-current-is-pro.js';
import { t } from '../i18n.js';

export default {
	name: 'grecaptcha',

	data: () => ( {
		tooltipContent,
		nonce: '',
		namespace: 'aio-login/grecaptcha',
		form_data: {
			enabled: false,
			version: 'v2',
			v2_site_key: '',
			v2_secret_key: '',
			theme: 'light',
			v3_site_key: '',
			v3_secret_key: '',
			threshold: '0.5',
			validated: false,
		},
		hcaptcha_namespace: 'aio-login/hcaptcha',
		hcaptcha_nonce: '',
		hcaptcha_form_data: {
			enabled: false,
			site_key: '',
			secret_key: '',
			theme: 'light',
			size: 'normal',
			language: 'en',
			validated: false,
		},
		turnstile_namespace: 'aio-login/turnstile',
		turnstile_nonce: '',
		turnstile_form_data: {
			enabled: false,
			site_key: '',
			secret_key: '',
			theme: 'auto',
			size: 'normal',
			language: 'auto',
			validated: false,
		},
		snackbar: {
			message: '',
			duration: 3000,
			show: false,
		},
		noteDismissed: false,
		showSingleCaptchaNotice: false
	} ),

	computed: {
		has_pro() {
			return resolveParentCurrentIsPro(this);
		},
		hcaptchaPlanAllowed() {
			const o = typeof window !== 'undefined' ? window.aio_login__app_object : null;
			return o && ( o.captcha_hcaptcha_plan_unlocked === 'true' || o.captcha_hcaptcha_plan_unlocked === true );
		},
		turnstilePlanAllowed() {
			const o = typeof window !== 'undefined' ? window.aio_login__app_object : null;
			return o && ( o.captcha_turnstile_plan_unlocked === 'true' || o.captcha_turnstile_plan_unlocked === true );
		},
		hcaptchaUnlocked() {
			return this.has_pro && this.hcaptchaPlanAllowed;
		},
		turnstileUnlocked() {
			return this.has_pro && this.turnstilePlanAllowed;
		},
		configData() {
			return {
				version: this.form_data.version,
				siteKey: this.form_data.version === 'v2' ? this.form_data.v2_site_key : this.form_data.v3_site_key,
				secretKey: this.form_data.version === 'v2' ? this.form_data.v2_secret_key : this.form_data.v3_secret_key,
				theme: this.form_data.theme,
				threshold: this.form_data.threshold,
				validated: this.form_data.validated,
			};
		},
		hcaptchaConfigData() {
			return {
				siteKey: this.hcaptcha_form_data.site_key,
				secretKey: this.hcaptcha_form_data.secret_key,
				theme: this.hcaptcha_form_data.theme,
				size: this.hcaptcha_form_data.size,
				language: this.hcaptcha_form_data.language,
				validated: this.hcaptcha_form_data.validated,
			};
		},
		turnstileConfigData() {
			return {
				siteKey: this.turnstile_form_data.site_key,
				secretKey: this.turnstile_form_data.secret_key,
				theme: this.turnstile_form_data.theme,
				size: this.turnstile_form_data.size,
				language: this.turnstile_form_data.language,
				validated: this.turnstile_form_data.validated,
			};
		},
		anyCaptchaEnabled() {
			return !!(this.form_data.enabled || this.hcaptcha_form_data.enabled || this.turnstile_form_data.enabled);
		}
	},

	methods: {
		hasAnotherCaptchaEnabled(currentCaptcha) {
			const recaptchaEnabled = !!this.form_data.enabled;
			const hcaptchaEnabled = !!this.hcaptcha_form_data.enabled;
			const turnstileEnabled = !!this.turnstile_form_data.enabled;

			if (currentCaptcha === 'recaptcha') {
				return hcaptchaEnabled || turnstileEnabled;
			}
			if (currentCaptcha === 'hcaptcha') {
				return recaptchaEnabled || turnstileEnabled;
			}
			if (currentCaptcha === 'turnstile') {
				return recaptchaEnabled || hcaptchaEnabled;
			}

			return false;
		},

		maybeShowSingleCaptchaNotice(currentCaptcha, enabled) {
			if (!enabled) {
				return;
			}
			if (this.hasAnotherCaptchaEnabled(currentCaptcha)) {
				this.showSingleCaptchaNotice = true;
				this.noteDismissed = false;
			}
		},

		handleToggleCaptcha(enabled) {
			this.maybeShowSingleCaptchaNotice('recaptcha', enabled);
			this.form_data.enabled = enabled;
			
			// If enabling reCAPTCHA, disable hCaptcha
			if (enabled) {
				this.hcaptcha_form_data.enabled = false;
				this.turnstile_form_data.enabled = false;
				// Save hCaptcha disabled state
				this.saveHCaptchaSettings();
				this.saveTurnstileSettings();
			}
			
			this.saveSettings();
		},

		handleSaveSettings(data) {
			this.form_data.version = data.version;
			this.form_data.theme = data.theme;
			this.form_data.threshold = data.threshold;
			this.form_data.validated = !!data.validated;
			
			if (data.version === 'v2') {
				this.form_data.v2_site_key = data.siteKey;
				this.form_data.v2_secret_key = data.secretKey;
			} else {
				this.form_data.v3_site_key = data.siteKey;
				this.form_data.v3_secret_key = data.secretKey;
			}
			
			this.saveSettings(true);
		},

		saveSettings(fromVerifiedSave = false) {
			const payload = { ...this.form_data, _wpnonce: this.nonce };
			if (fromVerifiedSave) {
				payload.validated = true;
			}
			axios.post(this.namespace + '/save-settings', payload)
				.then(response => {
					if (response.data && typeof response.data.validated !== 'undefined') {
						this.form_data.validated = !!response.data.validated;
					} else if (fromVerifiedSave) {
						this.form_data.validated = true;
					}
					this.snackbar.message = response.data.message;
					this.snackbar.show = true;
				})
				.catch(error => {
					const message = (error.response && error.response.data && error.response.data.message)
						? error.response.data.message
						: t( 'Error saving settings.' );
					this.snackbar.message = message;
					this.snackbar.show = true;
				});
		},

		handleCloseSnackbar() {
			this.snackbar.show = false;
		},

		dismissNote() {
			this.noteDismissed = true;
		},

		handleToggleHCaptcha(enabled) {
			if ( enabled && ! this.hcaptchaUnlocked ) {
				return;
			}
			this.maybeShowSingleCaptchaNotice('hcaptcha', enabled);
			this.hcaptcha_form_data.enabled = enabled;
			
			// If enabling hCaptcha, disable reCAPTCHA
			if (enabled) {
				this.form_data.enabled = false;
				this.turnstile_form_data.enabled = false;
				// Save reCAPTCHA disabled state
				this.saveSettings();
				this.saveTurnstileSettings();
			}
			
			this.saveHCaptchaSettings();
		},

		handleSaveHCaptchaSettings(data) {
			this.hcaptcha_form_data.theme = data.theme;
			this.hcaptcha_form_data.size = data.size;
			this.hcaptcha_form_data.language = data.language;
			this.hcaptcha_form_data.site_key = data.siteKey;
			this.hcaptcha_form_data.secret_key = data.secretKey;
			this.hcaptcha_form_data.validated = !!data.validated;
			this.saveHCaptchaSettings(true);
		},

		saveHCaptchaSettings(fromVerifiedSave = false) {
			const payload = { ...this.hcaptcha_form_data, _wpnonce: this.hcaptcha_nonce };
			if (fromVerifiedSave) {
				payload.validated = true;
			}
			axios.post(this.hcaptcha_namespace + '/save-settings', payload)
				.then(response => {
					if (response.data && typeof response.data.validated !== 'undefined') {
						this.hcaptcha_form_data.validated = !!response.data.validated;
					} else if (fromVerifiedSave) {
						this.hcaptcha_form_data.validated = true;
					}
					this.snackbar.message = response.data.message;
					this.snackbar.show = true;
				})
				.catch(error => {
					const message = (error.response && error.response.data && error.response.data.message)
						? error.response.data.message
						: t( 'Error saving hCaptcha settings.' );
					this.snackbar.message = message;
					this.snackbar.show = true;
				});
		},

		handleToggleTurnstile(enabled) {
			if ( enabled && ! this.turnstileUnlocked ) {
				return;
			}
			this.maybeShowSingleCaptchaNotice('turnstile', enabled);
			this.turnstile_form_data.enabled = enabled;

			// If enabling Turnstile, disable reCAPTCHA and hCaptcha.
			if (enabled) {
				this.form_data.enabled = false;
				this.hcaptcha_form_data.enabled = false;
				this.saveSettings();
				this.saveHCaptchaSettings();
			}

			this.saveTurnstileSettings();
		},

		handleSaveTurnstileSettings(data) {
			this.turnstile_form_data.theme = data.theme;
			this.turnstile_form_data.size = data.size;
			this.turnstile_form_data.language = data.language;
			this.turnstile_form_data.site_key = data.siteKey;
			this.turnstile_form_data.secret_key = data.secretKey;
			this.turnstile_form_data.validated = !!data.validated;
			this.saveTurnstileSettings(true);
		},

		saveTurnstileSettings(fromVerifiedSave = false) {
			const payload = { ...this.turnstile_form_data, _wpnonce: this.turnstile_nonce };
			if (fromVerifiedSave) {
				payload.validated = true;
			}
			axios.post(this.turnstile_namespace + '/save-settings', payload)
				.then(response => {
					if (response.data && typeof response.data.validated !== 'undefined') {
						this.turnstile_form_data.validated = !!response.data.validated;
					} else if (fromVerifiedSave) {
						this.turnstile_form_data.validated = true;
					}
					this.snackbar.message = response.data.message;
					this.snackbar.show = true;
				})
				.catch(error => {
					const message = (error.response && error.response.data && error.response.data.message)
						? error.response.data.message
						: t( 'Error saving Turnstile settings.' );
					this.snackbar.message = message;
					this.snackbar.show = true;
				});
		},


		loadHCaptchaSettings() {
			axios.get(this.hcaptcha_namespace + '/get-settings')
				.then(response => {
					this.hcaptcha_form_data.enabled = response.data.enabled;
					this.hcaptcha_form_data.site_key = response.data.site_key || '';
					this.hcaptcha_form_data.secret_key = response.data.secret_key || '';
					this.hcaptcha_form_data.theme = response.data.theme || 'light';
					this.hcaptcha_form_data.size = response.data.size || 'normal';
					this.hcaptcha_form_data.language = response.data.language || 'en';
					this.hcaptcha_form_data.validated = !!response.data.validated;
					this.hcaptcha_nonce = response.data.nonce;
				})
				.catch(error => {
					console.error('Error loading hCaptcha settings:', error);
				});
		},

		loadTurnstileSettings() {
			axios.get(this.turnstile_namespace + '/get-settings')
				.then(response => {
					this.turnstile_form_data.enabled = response.data.enabled;
					this.turnstile_form_data.site_key = response.data.site_key || '';
					this.turnstile_form_data.secret_key = response.data.secret_key || '';
					this.turnstile_form_data.theme = response.data.theme || 'auto';
					this.turnstile_form_data.size = response.data.size || 'normal';
					this.turnstile_form_data.language = response.data.language || 'auto';
					this.turnstile_form_data.validated = !!response.data.validated;
					this.turnstile_nonce = response.data.nonce;
				})
				.catch(error => {
					console.error('Error loading Turnstile settings:', error);
				});
		}
	},

	mounted() {
		// Load Google reCAPTCHA settings
		axios.get(this.namespace + '/get-settings')
			.then(response => {
				this.form_data.enabled = response.data.enabled;
				this.form_data.version = response.data.version;
				this.form_data.v2_site_key = response.data.v2_site_key;
				this.form_data.v2_secret_key = response.data.v2_secret_key;
				this.form_data.theme = response.data.theme;
				this.form_data.v3_site_key = response.data.v3_site_key;
				this.form_data.v3_secret_key = response.data.v3_secret_key;
				this.form_data.threshold = response.data.threshold;
				this.form_data.validated = !!response.data.validated;
				this.nonce = response.data.nonce;
			})
			.catch(error => {
				console.error('Error loading settings:', error);
			});

		// Load hCaptcha settings
		this.loadHCaptchaSettings();
		// Load Turnstile settings
		this.loadTurnstileSettings();
	}
}
</script>

<style scoped>
/* Social Login Container */
.aio-login-pro__social-login {
  display: flex;
  flex-wrap: wrap;
  gap: 20px;
}

.captcha-note {
  margin: 10px 0 20px 0;
  padding: 12px 16px;
  background-color: #FFF3CD;
  border-left: 4px solid #FFC107;
  color: #856404;
  font-size: 14px;
  border-radius: 4px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  position: relative;
}

.captcha-note span {
  flex: 1;
}

.dismiss-btn {
  background: none;
  border: none;
  color: #856404;
  font-size: 24px;
  line-height: 1;
  cursor: pointer;
  padding: 0;
  margin-left: 12px;
  width: 24px;
  height: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  transition: background-color 0.2s ease;
}

.dismiss-btn:hover {
  background-color: rgba(133, 100, 4, 0.1);
}
</style>