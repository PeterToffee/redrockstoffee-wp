<template>
	<div v-if="show" class="popup-overlay" @click="closePopup">
		<div class="popup-content" @click.stop>
			<div class="popup-header">
				<div class="popup-header-title">
					<img
						:src="popupHeaderIcon"
						:alt="$t('Cloudflare turnstile')"
						class="popup-header-title__icon"
					/>
					<h2 class="popup-header-title__text">{{ $t("Turnstile Configuration") }}</h2>
				</div>
				<button class="close-btn" @click="closePopup">&times;</button>
			</div>

			<div class="popup-steps">
				<div class="step-container">
					<h4>{{ $t("Getting Started") }}</h4>
					<div
						class="step"
						:class="{ active: currentStep >= 1 }"
						:style="{ backgroundColor: currentStep >= 1 ? '#9516DF' : '#EBE8EB', color: currentStep < 1 ? '#9516DF' : 'white' }">
						1
					</div>
				</div>
				<div class="connector" :style="{ backgroundColor: currentStep >= 2 ? '#9516DF' : '#C9D2E3' }"></div>

				<div class="step-container">
					<h4>{{ $t("Configuration") }}</h4>
					<div
						class="step"
						:class="{ active: currentStep >= 2 }"
						:style="{ backgroundColor: currentStep >= 2 ? '#9516DF' : '#EBE8EB', color: currentStep < 2 ? '#9516DF' : 'white' }">
						2
					</div>
				</div>
				<div class="connector" :style="{ backgroundColor: currentStep >= 3 ? '#9516DF' : '#C9D2E3' }"></div>

				<div class="step-container">
					<h4>{{ $t("Settings") }}</h4>
					<div
						class="step"
						:class="{ active: currentStep >= 3 }"
						:style="{ backgroundColor: currentStep >= 3 ? '#9516DF' : '#EBE8EB', color: currentStep < 3 ? '#9516DF' : 'white' }">
						3
					</div>
				</div>
			</div>

			<div class="popup-modal-body">
			<div v-if="currentStep === 1" class="aio-login-pro__first-step">
				<h4 class="aio-login-pro__first-step__title">{{ $t("Getting Started") }}</h4>
				<p class="aio-login-pro__first-step__content">{{ $t("To protect your website from spam and bots, start by adding Cloudflare Turnstile to your WordPress login page.") }}</p>
				<h5 class="aio-login-pro__first-step__instruction">{{ $t("Instruction:") }}</h5>
				<p class="aio-login-pro__first-step__instructions">{{ $t("1. Register your site and get the keys ->") }} <a href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank" class="aio-login-pro__first-step__doc-link">{{ $t("Click Here") }}</a></p>
				<p class="aio-login-pro__first-step__instructions">{{ $t("2. Need help creating keys? ->") }} <a href="https://developers.cloudflare.com/turnstile/get-started/" target="_blank" class="aio-login-pro__first-step__doc-link">{{ $t("Click Here") }}</a></p>
				<p class="aio-login-pro__first-step__next-step-note">{{ $t("3. Once you have your keys, click Next to enter them.") }}</p>
			</div>

			<div v-if="currentStep === 2" class="aio-login-pro__step2">
				<p class="aio-login-pro__step2__description">
					{{ $t("Enter your Cloudflare Turnstile details.") }}
				</p>
				<div class="aio-login-pro__inline-form">
					<div class="aio-login-pro__form-group">
						<label for="turnstile-site-key" class="aio-login-pro__form-label">
							{{ $t("Site Key") }} <span class="aio-login-pro__required">*</span>
						</label>
						<div class="input-with-delete">
							<input
								id="turnstile-site-key"
								type="text"
								v-model="formData.siteKey"
								class="aio-login-pro__form-input"
								:class="{ 'aio-login-pro__error': showValidationError && !formData.siteKey.trim() }"
								:placeholder="$t('Enter Site Key')"
								autocomplete="off"
							/>
							<button v-if="formData.siteKey" @click="clearField('siteKey')" class="clear-btn">×</button>
						</div>
						<span
							v-if="showValidationError && !formData.siteKey.trim()"
							class="aio-login-pro__error-message"
						>
							{{ $t("This field is required") }}
						</span>
					</div>
					<div class="aio-login-pro__form-group">
						<label for="turnstile-secret-key" class="aio-login-pro__form-label">
							{{ $t("Secret Key") }} <span class="aio-login-pro__required">*</span>
						</label>
						<div class="input-with-delete">
							<input
								id="turnstile-secret-key"
								type="password"
								v-model="formData.secretKey"
								class="aio-login-pro__form-input"
								:class="{ 'aio-login-pro__error': showValidationError && !formData.secretKey.trim() }"
								:placeholder="$t('Enter Secret Key')"
								autocomplete="new-password"
							/>
							<button v-if="formData.secretKey" @click="clearField('secretKey')" class="clear-btn">×</button>
						</div>
						<span
							v-if="showValidationError && !formData.secretKey.trim()"
							class="aio-login-pro__error-message"
						>
							{{ $t("This field is required") }}
						</span>
					</div>
				</div>
			</div>

			<div v-if="currentStep === 3" class="aio-login-pro__step3">
				<p class="aio-login-pro__step3__description">
					{{ $t("These settings are not compulsory.") }}
				</p>
				<div class="aio-login-pro__step3__layout">
					<div class="aio-login-pro__step3__column">
						<div class="aio-login-pro__form-group">
							<label for="turnstile-language" class="aio-login-pro__form-label">{{ $t("Language for Captcha:") }}</label>
							<select id="turnstile-language" v-model="formData.language" class="aio-login-pro__form-input">
								<option value="auto">{{ $t("Auto (Browser language)") }}</option>
								<option value="en">{{ $t("English") }}</option>
								<option value="ar">{{ $t("Arabic") }}</option>
								<option value="de">{{ $t("German") }}</option>
								<option value="es">{{ $t("Spanish") }}</option>
								<option value="fr">{{ $t("French") }}</option>
								<option value="hi">{{ $t("Hindi") }}</option>
								<option value="id">{{ $t("Indonesian") }}</option>
								<option value="it">{{ $t("Italian") }}</option>
								<option value="ja">{{ $t("Japanese") }}</option>
								<option value="ko">{{ $t("Korean") }}</option>
								<option value="nl">{{ $t("Dutch") }}</option>
								<option value="pl">{{ $t("Polish") }}</option>
								<option value="pt">{{ $t("Portuguese") }}</option>
								<option value="ru">{{ $t("Russian") }}</option>
								<option value="tr">{{ $t("Turkish") }}</option>
								<option value="uk">{{ $t("Ukrainian") }}</option>
								<option value="ur">{{ $t("Urdu") }}</option>
								<option value="vi">{{ $t("Vietnamese") }}</option>
								<option value="zh-CN">{{ $t("Chinese (Simplified)") }}</option>
								<option value="zh-TW">{{ $t("Chinese (Traditional)") }}</option>
							</select>
						</div>
						<div class="aio-login-pro__form-group">
							<label for="turnstile-theme" class="aio-login-pro__form-label">{{ $t("Theme:") }}</label>
							<select id="turnstile-theme" v-model="formData.theme" class="aio-login-pro__form-input">
								<option value="auto">{{ $t("Auto") }}</option>
								<option value="light">{{ $t("Light") }}</option>
								<option value="dark">{{ $t("Dark") }}</option>
							</select>
						</div>
						<div class="aio-login-pro__form-group">
							<label for="turnstile-size" class="aio-login-pro__form-label">{{ $t("Size:") }}</label>
							<select id="turnstile-size" v-model="formData.size" class="aio-login-pro__form-input">
								<option value="normal">{{ $t("Normal") }}</option>
								<option value="compact">{{ $t("Compact") }}</option>
								<option value="flexible">{{ $t("Flexible") }}</option>
							</select>
						</div>
					</div>
				</div>

				<p v-if="!hasEnteredKeys" class="aio-login-pro__step3__description aio-login-pro__error-message">
					{{ $t("Site key or secret key is missing. Go back to Configuration and enter both keys.") }}
				</p>

				<aio-login-captcha-verify
					v-if="hasEnteredKeys"
					:key="verifyComponentKey"
					:namespace="apiNamespace"
					:nonce="apiNonce"
					:payload="testPayload"
					v-model:verified="connectionVerified"
				/>
			</div>

			</div>

			<div class="popup-footer">
				<button v-if="currentStep === 2" @click="prevStep" class="back-btn">{{ $t("Back") }}</button>
				<button v-if="currentStep === 3" @click="prevStep" class="back-btn">{{ $t("Back") }}</button>
				<div class="popup-footer-left">
					<button v-if="currentStep === 1" @click="nextStep" class="next-btn">{{ $t("Next") }}</button>
				</div>
				<button v-if="currentStep === 2" @click="nextStep" class="next-btn">{{ $t("Next") }}</button>
				<button v-if="currentStep === 3" @click="finish" class="finish-btn" :disabled="!connectionVerified">{{ $t("Finished") }}</button>
			</div>
		</div>
	</div>
</template>

<script>
import { resetCaptchaEnvironment, isolateActiveCaptchaProvider } from '../captcha-dom-cleanup.js';

export default {
	name: 'aio-login-turnstile-popup',

	props: {
		show: {
			type: Boolean,
			default: false
		},
		initialData: {
			type: Object,
			default: () => ({})
		},
		apiNamespace: {
			type: String,
			default: 'aio-login/turnstile',
		},
		apiNonce: {
			type: String,
			default: '',
		},
	},

	data() {
		return {
			assetsUrl: aio_login__app_object.assets_url,
			popupHeaderIcon: aio_login__app_object.assets_url + 'images/icons/turnstile.svg',
			currentStep: 1,
			showValidationError: false,
			formData: {
				siteKey: '',
				secretKey: '',
				theme: 'auto',
				size: 'normal',
				language: 'auto'
			},
			connectionVerified: false,
			verifySessionKey: 0,
		}
	},

	computed: {
		canProceed() {
			if (this.currentStep === 1) return true;
			if (this.currentStep === 2) {
				return this.getNormalizedSiteKey() !== '' && this.getNormalizedSecretKey() !== '';
			}
			if (this.currentStep === 3) return true;
			return false;
		},
		hasEnteredKeys() {
			return this.getNormalizedSiteKey() !== '' && this.getNormalizedSecretKey() !== '';
		},
		verifyComponentKey() {
			return [
				this.verifySessionKey,
				this.getNormalizedSiteKey(),
				this.getNormalizedSecretKey(),
				this.formData.theme || 'auto',
				this.formData.size || 'normal',
				this.formData.language || 'auto',
			].join('|');
		},
		testPayload() {
			return {
				site_key: this.getNormalizedSiteKey(),
				secret_key: this.getNormalizedSecretKey(),
				siteKey: this.getNormalizedSiteKey(),
				secretKey: this.getNormalizedSecretKey(),
				theme: this.formData.theme || 'auto',
				size: this.formData.size || 'normal',
				language: this.formData.language || 'auto',
			};
		},
	},

	watch: {
		show(newVal) {
			if (newVal) {
				this.currentStep = 1;
				this.syncFormKeysFromInitialData();
				this.connectionVerified = !!this.initialData.validated;
				this.verifySessionKey = 0;
				this.showValidationError = false;
				// Clear other providers' leftovers; keep Turnstile script intact.
				isolateActiveCaptchaProvider( 'turnstile' );
				document.body.style.overflow = 'hidden';
				document.body.classList.add('aio-login-modal-open');
			} else {
				resetCaptchaEnvironment();
				document.body.style.overflow = '';
				document.body.classList.remove('aio-login-modal-open');
			}
		}
	},

	methods: {
		getNormalizedSiteKey() {
			return String(this.formData.siteKey || this.formData.site_key || '').trim();
		},

		getNormalizedSecretKey() {
			return String(this.formData.secretKey || this.formData.secret_key || '').trim();
		},

		syncFormKeysFromInitialData() {
			const initial = this.initialData || {};
			this.formData = {
				siteKey: String(initial.siteKey || initial.site_key || '').trim(),
				secretKey: String(initial.secretKey || initial.secret_key || '').trim(),
				theme: initial.theme || 'auto',
				size: initial.size || 'normal',
				language: initial.language || 'auto',
			};
		},

		syncFormKeys() {
			this.formData.siteKey = this.getNormalizedSiteKey();
			this.formData.secretKey = this.getNormalizedSecretKey();
			delete this.formData.site_key;
			delete this.formData.secret_key;
		},

		closePopup() {
			this.$emit('close');
		},

		nextStep() {
			if (this.currentStep === 2) {
				this.showValidationError = true;
				this.syncFormKeys();
				if (!this.canProceed) {
					return;
				}
			}
			if (this.canProceed && this.currentStep < 3) {
				if (this.currentStep === 2) {
					this.connectionVerified = false;
					this.verifySessionKey = Date.now();
				}
				this.currentStep++;
			}
		},

		prevStep() {
			if (this.currentStep > 1) {
				this.currentStep--;
			}
		},

		clearField(field) {
			this.formData[field] = '';
		},

		finish() {
			if (!this.connectionVerified) {
				return;
			}
			this.$emit('save', {
				...this.formData,
				validated: true,
			});
		}
	}
}
</script>

<style scoped>
.popup-overlay {
	position: fixed;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	background: rgba(0, 0, 0, 0.5);
	display: flex;
	justify-content: center;
	align-items: center;
	z-index: 1000;
}

.popup-content {
	background-color: white;
	padding: 10px 50px 50px 50px;
	border-radius: 8px;
	width: 80%;
	max-width: 800px;
}

.popup-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding-bottom: 14px;
	margin-bottom: 14px;
	border-bottom: 1px solid #e6e8ef;
	width: 100%;
	/* Keep close control on the visual right in RTL admin */
	direction: ltr;
}

.popup-header-title {
	display: inline-flex;
	align-items: center;
	gap: 10px;
	min-width: 0;
}

.popup-header-title__icon {
	width: 32px;
	height: 32px;
	object-fit: contain;
	flex-shrink: 0;
}

.popup-header-title__text {
	margin: 0;
	color: #202939;
	font-size: clamp(24px, 2.2vw, 32px);
	font-weight: 600;
	line-height: 1.15;
	letter-spacing: -0.02em;
	word-break: break-word;
}

.popup-steps {
	display: flex;
	align-items: center;
	justify-content: center;
	margin: 0 auto 20px;
	width: fit-content;
	max-width: 100%;
	gap: 0;
}

.step {
	width: 40px;
	height: 40px;
	border-radius: 50%;
	display: flex;
	justify-content: center;
	align-items: center;
	color: white;
	font-weight: bold;
	font-size: 16px;
	transition: background-color 0.3s ease;
	background-color: #EBE8EB;
}

.step.active {
	background-color: #9516DF;
}

.popup-footer {
	display: flex;
	justify-content: space-between;
	width: 100%;
	margin-top: 40px;
}

.popup-footer-left {
	flex-grow: 1;
	text-align: right;
}

.next-btn, .finish-btn, .back-btn {
	padding: 10px 20px;
	border: none;
	border-radius: 5px;
	font-size: 16px;
	cursor: pointer;
}

.next-btn {
	background: linear-gradient(262.54deg, #F7ECFD -5.51%, #8076FF 252.88%);
	color: #6E16DF;
}

.finish-btn {
	background: linear-gradient(262.54deg, #F7ECFD -5.51%, #8076FF 252.88%);
	color: #6E16DF;
}

.back-btn {
	background: linear-gradient(262.54deg, #EBE8EB -5.51%, #C9D2E3 252.88%);
	color: #8498B4;
}

.close-btn {
	position: relative;
	font-size: 21px;
	border-radius: 50%;
	cursor: pointer;
	background: none;
	border: 1px solid #7E869E40;
	flex-shrink: 0;
}

.connector {
	height: 2px;
	flex: 0 0 200px;
	background-color: #C9D2E3;
	margin: 0;
	min-width: 200px;
}

.step-container {
	flex: 0 0 40px;
	display: flex;
	align-items: center;
	justify-content: center;
	width: 40px;
	position: relative;
	padding-top: 26px;
}

.step-container h4 {
	position: absolute;
	left: 50%;
	bottom: calc(100% + 8px);
	transform: translateX(-50%);
	font-size: 13px;
	font-weight: bold;
	color: #9516DF;
	margin: 0;
	white-space: nowrap;
}

.aio-login-pro__first-step .aio-login-pro__first-step__title{
	color: #404280;
	font-size: 24px;
	font-style: normal;
	font-weight: 600;
	line-height: normal;
}

.aio-login-pro__first-step .aio-login-pro__first-step__instruction{
	color: #404280;
	font-size: 20px;
	font-style: normal;
	font-weight: 600;
	line-height: normal;
}

.aio-login-pro__first-step .aio-login-pro__first-step__content{
	color: #606C80;
	font-size: 14px;
	font-style: normal;
	font-weight: 400;
}

.aio-login-pro__first-step .aio-login-pro__first-step__doc-link{
	color: #6E16DF;
	font-family: Figtree;
	font-size: 12px;
	font-style: normal;
	font-weight: 700;
	line-height: 12px;
	text-decoration-line: underline;
	text-decoration-style: solid;
	text-decoration-skip-ink: none;
	text-decoration-thickness: auto;
	text-underline-offset: auto;
	text-underline-position: from-font;
}

.aio-login-pro__first-step p{
	color: #606C80;
	font-family: Figtree;
	font-size: 12px;
	font-style: normal;
	font-weight: 400;
}

.aio-login-pro__step2__description {
	color: #606C80;
	font-size: 14px;
	margin-bottom: 20px;
}

.aio-login-pro__inline-form {
	display: flex;
	gap: 20px;
	margin-top: 10px;
}

.aio-login-pro__form-group {
	display: flex;
	flex-direction: column;
	flex: 1;
}

.aio-login-pro__form-label {
	font-size: 14px;
	margin-bottom: 5px;
	font-weight: 600;
}

.aio-login-pro__form-input {
	padding: 10px 35px 10px 10px;
	font-size: 14px;
	border-radius: 4px;
	transition: border-color 0.2s ease-in-out;
	border: 1px solid #EBE8EB !important;
	background: #FFF;
	height: 40px;
	width: 100%;
	box-sizing: border-box;
}

.aio-login-pro__form-input[type="text"],
.aio-login-pro__form-input[type="password"] {
	padding: 10px 35px 10px 10px;
}

select.aio-login-pro__form-input {
	padding: 12px 30px 12px 10px;
	background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6,9 12,15 18,9'%3e%3c/polyline%3e%3c/svg%3e");
	background-repeat: no-repeat;
	background-position: right 10px center;
	background-size: 16px;
	appearance: none;
	-moz-appearance: none;
	-webkit-appearance: none;
	text-align: left;
	text-align-last: left;
	line-height: 1.4;
	height: 44px;
	box-sizing: border-box;
}

select.aio-login-pro__form-input option {
	text-align: left;
	padding: 5px;
}

.aio-login-pro__form-input:focus {
	outline: none;
	border-color: #9516df;
}

.aio-login-pro__required {
	color: #ff0000;
}

.aio-login-pro__error {
	border-color: #ff0000 !important;
}

.aio-login-pro__error-message {
	color: #ff0000;
	font-size: 12px;
	margin-top: 5px;
}

.aio-login-pro__step3__description {
	color: #606C80;
	font-size: 14px;
	margin-bottom: 20px;
}

.aio-login-pro__step3__layout {
	display: flex;
	gap: 20px;
}

.aio-login-pro__step3__column {
	flex: 1;
}

.input-with-delete {
	position: relative;
}

.clear-btn {
	position: absolute;
	right: 8px;
	top: 50%;
	transform: translateY(-50%);
	background: transparent;
	color: #999;
	border: none;
	width: 24px;
	height: 24px;
	cursor: pointer;
	font-size: 18px;
	display: flex;
	align-items: center;
	justify-content: center;
	z-index: 10;
	pointer-events: auto;
}

.clear-btn:hover {
	color: #999;
}

body.aio-login-modal-open {
	overflow: hidden !important;
}

.popup-overlay * {
	box-sizing: border-box !important;
}

/* Social login popup parity overrides */
.popup-content {
	padding: 28px 50px 36px 50px;
	max-height: min(90vh, calc(100vh - 32px));
	display: flex;
	flex-direction: column;
	box-sizing: border-box;
	overflow: hidden;
	margin: auto;
}

.popup-header {
	align-items: center;
	justify-content: space-between;
	padding-bottom: 16px;
	margin-bottom: 50px;
	border-bottom: 1px solid #e3e7ef;
	flex-shrink: 0;
}

.popup-header-title__icon {
	width: 36px;
	height: 36px;
	object-fit: contain;
}

.popup-header-title__text {
	margin: 0 0 2px 10px;
	color: #202939;
	font-size: clamp(18px, 2.5vw, 30px);
	font-weight: 600;
	line-height: 1.35;
	word-break: normal;
}

.popup-modal-body {
	flex: 1 1 auto;
	min-height: 0;
	overflow-x: hidden;
	overflow-y: auto;
	-webkit-overflow-scrolling: touch;
	overscroll-behavior: contain;
	padding: 4px 8px 0 0;
	box-sizing: border-box;
	scrollbar-width: thin;
	scrollbar-color: #c9d2e3 transparent;
}

.popup-steps {
	padding: 0;
	flex-shrink: 0;
}

.step {
	width: 30px;
	height: 30px;
	font-size: 14px;
}

.connector {
	flex: 0 0 210px;
	min-width: 210px;
}

.step-container {
	flex: 0 0 30px;
	width: 30px;
	padding-top: 0;
}

.step-container h4 {
	font-size: 14px;
	font-weight: 600;
	line-height: 1.2;
	text-align: center;
}

.popup-footer {
	flex-shrink: 0;
	margin-top: auto;
	padding-top: 16px;
	border-top: 1px solid #e3e7ef;
}

.popup-footer-left {
	text-align: right;
}

.next-btn,
.finish-btn,
.back-btn {
	padding: 14px 28px;
	font-weight: 700;
}

.back-btn {
	color: #404280;
}
</style>
