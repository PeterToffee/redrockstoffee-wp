<template>
	<div v-if="show" class="popup-overlay" @click="closePopup">
		<div class="popup-content woocommerce-popup" @click.stop>
			<div class="popup-header">
				<div class="popup-title">
					<img :src="getWooCommerceIcon()" alt="WooCommerce" class="woocommerce-icon" />
					<h2>{{ $t("WooCommerce Integration Settings") }}</h2>
				</div>
				<a href="#" class="back-link" @click.prevent="closePopup">{{ $t("← Back") }}</a>
			</div>

			<div class="woocommerce-settings">
				<!-- WooCommerce Integration Section -->
				<div class="settings-section">
					<div class="section-header">
						<h3>{{ $t("WooCommerce Integration") }}</h3>
						<p class="section-description">{{ $t("Enable WooCommerce integration to secure user interactions with Social Login and CAPTCHA.") }}</p>
						<div v-if="!woocommerceActive" class="woocommerce-notice notice notice-warning inline" style="margin-top: 15px;">
							<p><strong>{{ $t("WooCommerce is required for this integration.") }}</strong> {{ $t("Please install and activate the WooCommerce plugin to use this feature.") }}</p>
						</div>
					</div>
					<div class="toggle-wrapper">
						<label class="toggle-switch" :class="{ 'disabled': !woocommerceActive }">
							<aio-login-toggle
								id="woocommerce-integration"
								name="woocommerce-integration"
								v-on:toggle-input="handleWooCommerceToggle"
								:enabled="formData.woocommerceEnabled && woocommerceActive"
								:disabled="!woocommerceActive"
							/>
						</label>
					</div>
				</div>

				<!-- Captcha Section -->
				<div class="settings-section">
					<div class="section-header">
						<h3>{{ $t("Captcha") }}</h3>
						<p class="section-description">
							{{ $t("To protect forms from bots and spam by adding Captcha,") }} 
							<a href="#" class="link-text" @click.prevent="goToCaptcha">{{ $t("click here") }}</a>.
						</p>
					</div>
					<div class="toggle-wrapper">
						<label class="toggle-switch">
							<aio-login-toggle
								id="captcha-settings"
								name="captcha-settings"
								v-on:toggle-input="handleCaptchaToggle"
								:enabled="formData.captchaEnabled"
							/>
						</label>
					</div>
					
					<div v-if="formData.captchaEnabled" class="captcha-providers">
						<!-- reCAPTCHA -->
						<div class="provider-item">
							<div class="provider-header">
								<img :src="getProviderIcon('recaptcha')" alt="reCAPTCHA" class="provider-icon" />
								<span class="provider-name">{{ $t("Recaptcha") }}</span>
							</div>
							<div class="provider-options">
								<div class="option-item">
									<span>{{ $t("Login") }}</span>
									<label class="toggle-switch">
										<aio-login-toggle
											id="recaptcha-login"
											name="recaptcha-login"
											v-on:toggle-input="(val) => updateProviderOption('recaptcha', 'login', val)" :enabled="formData.providers.recaptcha.login" />
									</label>
								</div>
								<div class="option-item">
									<span>{{ $t("Registration") }}</span>
									<label class="toggle-switch">
										<aio-login-toggle
											id="recaptcha-registration"
											name="recaptcha-registration"
											v-on:toggle-input="(val) => updateProviderOption('recaptcha', 'registration', val)" :enabled="formData.providers.recaptcha.registration" />
									</label>
								</div>
							</div>
						</div>

						<!-- hCaptcha -->
						<div class="provider-item">
							<div class="provider-header">
								<img :src="getProviderIcon('hcaptcha')" alt="hCaptcha" class="provider-icon" />
								<span class="provider-name">{{ $t("Hcaptcha") }}</span>
							</div>
							<div class="provider-options">
								<div class="option-item">
									<span>{{ $t("Login") }}</span>
									<label class="toggle-switch">
										<aio-login-toggle
											id="hcaptcha-login"
											name="hcaptcha-login"
											v-on:toggle-input="(val) => updateProviderOption('hcaptcha', 'login', val)" :enabled="formData.providers.hcaptcha.login" />
									</label>
								</div>
								<div class="option-item">
									<span>{{ $t("Registration") }}</span>
									<label class="toggle-switch">
										<aio-login-toggle
											id="hcaptcha-registration"
											name="hcaptcha-registration"
											v-on:toggle-input="(val) => updateProviderOption('hcaptcha', 'registration', val)" :enabled="formData.providers.hcaptcha.registration" />
									</label>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- Social Login Section -->
				<div class="settings-section">
					<div class="section-header">
						<h3>{{ $t("Social Login") }}</h3>
						<p class="section-description">
							{{ $t("To allow users to log in with their social accounts,") }} 
							<a href="#" class="link-text" @click.prevent="goToSocialLogin">{{ $t("click here") }}</a>.
						</p>
					</div>
					<div class="toggle-wrapper">
						<label class="toggle-switch">
							<aio-login-toggle
								id="social-login-options"
								name="social-login-options"
								v-on:toggle-input="handleSocialLoginToggle"
								:enabled="formData.socialLoginEnabled"
							/>
						</label>
					</div>
					
					<div v-if="formData.socialLoginEnabled" class="social-providers">
						<!-- Microsoft -->
						<div class="provider-item">
							<div class="provider-header">
								<img :src="getProviderIcon('microsoft')" alt="Microsoft" class="provider-icon" />
								<span class="provider-name">{{ $t("Microsoft") }}</span>
							</div>
							<div class="provider-options">
								<div class="option-item">
									<span>{{ $t("Login") }}</span>
									<label class="toggle-switch">
										<aio-login-toggle
											id="microsoft-login"
											name="microsoft-login"
											v-on:toggle-input="(val) => updateSocialProviderOption('microsoft', 'login', val)" :enabled="formData.socialProviders.microsoft.login" />
									</label>
								</div>
								<div class="option-item">
									<span>{{ $t("Registration") }}</span>
									<label class="toggle-switch">
										<aio-login-toggle
											id="microsoft-registration"
											name="microsoft-registration"
											v-on:toggle-input="(val) => updateSocialProviderOption('microsoft', 'registration', val)" :enabled="formData.socialProviders.microsoft.registration" />
									</label>
								</div>
								<div class="option-item">
									<span>{{ $t("Checkout") }}</span>
									<label class="toggle-switch">
										<aio-login-toggle
											id="microsoft-checkout"
											name="microsoft-checkout"
											v-on:toggle-input="(val) => updateSocialProviderOption('microsoft', 'checkout', val)" :enabled="formData.socialProviders.microsoft.checkout" />
									</label>
								</div>
							</div>
						</div>

						<!-- Google -->
						<div class="provider-item">
							<div class="provider-header">
								<img :src="getProviderIcon('google')" alt="Google" class="provider-icon" />
								<span class="provider-name">{{ $t("Google") }}</span>
							</div>
							<div class="provider-options">
								<div class="option-item">
									<span>{{ $t("Login") }}</span>
									<label class="toggle-switch">
										<aio-login-toggle
											id="google-login"
											name="google-login"
											v-on:toggle-input="(val) => updateSocialProviderOption('google', 'login', val)" :enabled="formData.socialProviders.google.login" />
									</label>
								</div>
								<div class="option-item">
									<span>{{ $t("Registration") }}</span>
									<label class="toggle-switch">
										<aio-login-toggle
											id="google-registration"
											name="google-registration"
											v-on:toggle-input="(val) => updateSocialProviderOption('google', 'registration', val)" :enabled="formData.socialProviders.google.registration" />
									</label>
								</div>
								<div class="option-item">
									<span>{{ $t("Checkout") }}</span>
									<label class="toggle-switch">
										<aio-login-toggle
											id="google-checkout"
											name="google-checkout"
											v-on:toggle-input="(val) => updateSocialProviderOption('google', 'checkout', val)" :enabled="formData.socialProviders.google.checkout" />
									</label>
								</div>
							</div>
						</div>

						<!-- Facebook -->
						<div class="provider-item">
							<div class="provider-header">
								<img :src="getProviderIcon('facebook')" alt="Facebook" class="provider-icon" />
								<span class="provider-name">{{ $t("Facebook") }}</span>
							</div>
							<div class="provider-options">
								<div class="option-item">
									<span>{{ $t("Login") }}</span>
									<label class="toggle-switch">
										<aio-login-toggle
											id="facebook-login"
											name="facebook-login"
											v-on:toggle-input="(val) => updateSocialProviderOption('facebook', 'login', val)" :enabled="formData.socialProviders.facebook.login" />
									</label>
								</div>
								<div class="option-item">
									<span>{{ $t("Registration") }}</span>
									<label class="toggle-switch">
										<aio-login-toggle
											id="facebook-registration"
											name="facebook-registration"
											v-on:toggle-input="(val) => updateSocialProviderOption('facebook', 'registration', val)" :enabled="formData.socialProviders.facebook.registration" />
									</label>
								</div>
								<div class="option-item">
									<span>{{ $t("Checkout") }}</span>
									<label class="toggle-switch">
										<aio-login-toggle
											id="facebook-checkout"
											name="facebook-checkout"
											v-on:toggle-input="(val) => updateSocialProviderOption('facebook', 'checkout', val)" :enabled="formData.socialProviders.facebook.checkout" />
									</label>
								</div>
							</div>
						</div>

						<!-- Apple -->
						<div class="provider-item">
							<div class="provider-header">
								<img :src="getProviderIcon('apple')" alt="Apple" class="provider-icon" />
								<span class="provider-name">{{ $t("Apple") }}</span>
							</div>
							<div class="provider-options">
								<div class="option-item">
									<span>{{ $t("Login") }}</span>
									<label class="toggle-switch">
										<aio-login-toggle
											id="apple-login"
											name="apple-login"
											v-on:toggle-input="(val) => updateSocialProviderOption('apple', 'login', val)" :enabled="formData.socialProviders.apple.login" />
									</label>
								</div>
								<div class="option-item">
									<span>{{ $t("Registration") }}</span>
									<label class="toggle-switch">
										<aio-login-toggle
											id="apple-registration"
											name="apple-registration"
											v-on:toggle-input="(val) => updateSocialProviderOption('apple', 'registration', val)" :enabled="formData.socialProviders.apple.registration" />
									</label>
								</div>
								<div class="option-item">
									<span>{{ $t("Checkout") }}</span>
									<label class="toggle-switch">
										<aio-login-toggle
											id="apple-checkout"
											name="apple-checkout"
											v-on:toggle-input="(val) => updateSocialProviderOption('apple', 'checkout', val)" :enabled="formData.socialProviders.apple.checkout" />
									</label>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div class="popup-footer">
				<button class="save-btn" @click="saveSettings">{{ $t("Save") }}</button>
			</div>
		</div>
	</div>
</template>

<script>
export default {
	name: 'aio-login-woocommerce-popup',

	props: {
		show: {
			type: Boolean,
			default: false
		},
		initialData: {
			type: Object,
			default: () => ({})
		}
	},

	data() {
		return {
			assetsUrl: aio_login__app_object.assets_url,
			woocommerceActive: true, // Default to true, will be updated from initialData
			formData: {
				woocommerceEnabled: false,
				captchaEnabled: false,
				socialLoginEnabled: false,
				providers: {
					recaptcha: {
						login: false,
						registration: false
					},
					hcaptcha: {
						login: false,
						registration: false
					}
				},
				socialProviders: {
					microsoft: {
						login: false,
						registration: false,
						checkout: false
					},
					google: {
						login: false,
						registration: false,
						checkout: false
					},
					facebook: {
						login: false,
						registration: false,
						checkout: false
					},
					apple: {
						login: false,
						registration: false,
						checkout: false
					}
				}
			}
		}
	},

	watch: {
		initialData: {
			handler(newData) {
				if (newData && Object.keys(newData).length > 0) {
					this.formData = { ...this.formData, ...newData };
					// Update WooCommerce active status
					if (newData.woocommerceActive !== undefined) {
						this.woocommerceActive = newData.woocommerceActive;
						// If WooCommerce is not active, disable the integration
						if (!this.woocommerceActive) {
							this.formData.woocommerceEnabled = false;
						}
					}
				}
			},
			immediate: true,
			deep: true
		}
	},

	methods: {
		getWooCommerceIcon() {
			return this.assetsUrl + 'images/icons/woocommerce-logo.png';
		},

		getProviderIcon(provider) {
			const iconMap = {
				'recaptcha': 'recaptcha',
				'hcaptcha': 'hcaptcha',
				'microsoft': 'microsoft',
				'google': 'google',
				'facebook': 'facebook'
			};
			return this.assetsUrl + `images/icons/${iconMap[provider] || provider}.png`;
		},

		handleWooCommerceToggle(enabled) {
			// Prevent enabling if WooCommerce is not active
			if (enabled && !this.woocommerceActive) {
				this.$emit('error', 'WooCommerce is required for this integration. Please install and activate the WooCommerce plugin.');
				return;
			}
			this.formData.woocommerceEnabled = enabled;
		},

		handleCaptchaToggle(enabled) {
			this.formData.captchaEnabled = enabled;
		},

		handleSocialLoginToggle(enabled) {
			this.formData.socialLoginEnabled = enabled;
		},

		updateProviderOption(provider, option, value) {
			if (this.formData.providers[provider]) {
				this.formData.providers[provider][option] = value;
			}
		},

		updateSocialProviderOption(provider, option, value) {
			if (this.formData.socialProviders[provider]) {
				this.formData.socialProviders[provider][option] = value;
			}
		},

		goToCaptcha() {
			// Navigate to captcha settings
			const url = new URL(window.location.href);
			url.searchParams.set('tab', 'security');
			window.location.href = url.toString();
		},

		goToSocialLogin() {
			// Navigate to social login settings
			const url = new URL(window.location.href);
			url.searchParams.set('tab', 'social-login');
			window.location.href = url.toString();
		},

		closePopup() {
			this.$emit('close');
		},

		saveSettings() {
			this.$emit('save', { ...this.formData });
			this.closePopup();
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
	z-index: 10000;
}

.popup-content.woocommerce-popup {
	background-color: white;
	border-radius: 8px;
	width: 90%;
	max-width: 900px;
	max-height: 90vh;
	overflow-y: auto;
	padding: 0;
}

.popup-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 30px 40px;
	border-bottom: 1px solid #EBE8EB;
}

.popup-title {
	display: flex;
	align-items: center;
	gap: 15px;
}

.woocommerce-icon {
	width: 48px;
	height: 48px;
}

.popup-title h2 {
	margin: 0;
	font-size: 24px;
	font-weight: 600;
	color: #404280;
}

.back-link {
	color: #6E16DF;
	text-decoration: none;
	font-weight: 500;
}

.back-link:hover {
	text-decoration: underline;
}

.woocommerce-settings {
	padding: 30px 40px;
}

.settings-section {
	margin-bottom: 40px;
}

.section-header {
	margin-bottom: 20px;
}

.section-header h3 {
	margin: 0 0 10px 0;
	font-size: 20px;
	font-weight: 600;
	color: #404280;
}

.section-description {
	margin: 0;
	color: #606C80;
	font-size: 14px;
}

.link-text {
	color: #6E16DF;
	text-decoration: none;
	cursor: pointer;
}

.link-text:hover {
	text-decoration: underline;
}

.toggle-wrapper {
	margin: 20px 0;
}

.captcha-providers,
.social-providers {
	margin-top: 20px;
	padding-left: 20px;
}

.provider-item {
	margin-bottom: 30px;
	padding: 20px;
	background: #F9F9F9;
	border-radius: 8px;
}

.provider-header {
	display: flex;
	align-items: center;
	gap: 12px;
	margin-bottom: 15px;
}

.provider-icon {
	width: 32px;
	height: 32px;
}

.provider-name {
	font-size: 16px;
	font-weight: 600;
	color: #404280;
}

.provider-options {
	display: flex;
	flex-direction: column;
	gap: 15px;
}

.option-item {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 10px 0;
	border-bottom: 1px solid #EBE8EB;
}

.option-item:last-child {
	border-bottom: none;
}

.option-item span {
	font-size: 14px;
	color: #606C80;
}

.popup-footer {
	padding: 20px 40px;
	border-top: 1px solid #EBE8EB;
	text-align: right;
}

.save-btn {
	background: #6E16DF;
	color: white;
	border: none;
	border-radius: 4px;
	padding: 12px 40px;
	font-size: 16px;
	font-weight: 600;
	cursor: pointer;
	transition: background 0.3s;
}

.save-btn:hover {
	background: #510C79;
}
</style>

