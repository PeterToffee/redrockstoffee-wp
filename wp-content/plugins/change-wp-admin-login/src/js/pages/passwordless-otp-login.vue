<template>
	<div v-if="page_loaded" class="aio-login-2fa-methods aio-login-otp-login">
		<div class="aio-login-2fa-methods__list">
			<!-- Email OTP Login -->
			<div class="aio-login-2fa-methods__item aio-login-2fa-policies__card">
				<div class="aio-login-2fa-policies__card-header">
					<span class="aio-login-2fa-policies__icon" aria-hidden="true">
						<svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M4 6.5h16c.83 0 1.5.67 1.5 1.5v8c0 .83-.67 1.5-1.5 1.5H4A1.5 1.5 0 012.5 16V8c0-.83.67-1.5 1.5-1.5z" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round"/>
							<path d="M3 7.5l8.65 5.77a1.5 1.5 0 001.7 0L22 7.5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
					</span>
					<div class="aio-login-2fa-methods__content">
						<h2 class="aio-login-2fa-methods__title">
							{{ $t("Email OTP Login") }}
							<aio-login-tooltip :content="tooltipContent.passwordlessEmailOtp.content" />
						</h2>
						<div v-if="isCardExpanded('email')" class="aio-login-2fa-methods__desc">
							{{ $t("Allow users to sign in with a one-time verification code sent to their email address.") }}
						</div>
					</div>
					<div class="aio-login-2fa-methods__controls">
						<button
							type="button"
							class="aio-login-2fa-methods__expand-btn"
							@click="toggleCard('email')"
							:aria-expanded="isCardExpanded('email')"
							:aria-label="$t('Toggle Email OTP Login settings')"
						>
							<span class="aio-login-2fa-methods__chevron" :class="{ 'is-expanded': isCardExpanded('email') }"></span>
						</button>
						<aio-login-toggle
							id="email_otp_enable"
							name="email_otp_enable"
							:enabled="form.email_enable"
							v-on:toggle-input="form.email_enable = $event"
						/>
					</div>
				</div>

				<div v-if="form.email_enable && isCardExpanded('email')" class="aio-login-2fa-policies__card-body">
					<div class="aio-login-2fa-methods__grid aio-login-2fa-methods__grid--four">
						<div>
							<label class="aio-login-2fa-methods__label" for="email_length">
								{{ $t("OTP Length") }}
								<aio-login-tooltip :content="tooltipContent.passwordlessOtpLength.content" />
							</label>
							<select id="email_length" v-model="form.email_length" class="aio-login-2fa-methods__input" :disabled="!form.email_enable">
								<option value="4">{{ $t("4 digits") }}</option>
								<option value="6">{{ $t("6 digits") }}</option>
								<option value="8">{{ $t("8 digits") }}</option>
							</select>
						</div>
						<div>
							<label class="aio-login-2fa-methods__label" for="email_expiration">
								{{ $t("OTP Expiration (minutes)") }}
								<aio-login-tooltip :content="tooltipContent.passwordlessOtpExpiration.content" />
							</label>
							<input id="email_expiration" type="number" min="1" max="60" v-model="form.email_expiration" :disabled="!form.email_enable" class="aio-login-2fa-methods__input" />
						</div>
						<div>
							<label class="aio-login-2fa-methods__label" for="email_resend">
								{{ $t("Resend timer (seconds)") }}
								<aio-login-tooltip :content="tooltipContent.passwordlessOtpResend.content" />
							</label>
							<input id="email_resend" type="number" min="30" max="600" v-model="form.email_resend_timer" :disabled="!form.email_enable" class="aio-login-2fa-methods__input" />
						</div>
						<div>
							<label class="aio-login-2fa-methods__label" for="email_retries">
								{{ $t("Maximum retry attempts") }}
								<aio-login-tooltip :content="tooltipContent.passwordlessOtpRetries.content" />
							</label>
							<input id="email_retries" type="number" min="1" max="20" v-model="form.email_max_retries" :disabled="!form.email_enable" class="aio-login-2fa-methods__input" />
						</div>
					</div>

					<div class="aio-login-2fa-methods__field aio-login-otp-login__field--half">
						<label class="aio-login-2fa-methods__label" for="email_block_duration">
								{{ $t("Block duration (minutes)") }}
								<aio-login-tooltip :content="tooltipContent.passwordlessOtpBlockDuration.content" />
							</label>
							<input id="email_block_duration" type="number" min="1" max="1440" v-model="form.email_block_duration" :disabled="!form.email_enable" class="aio-login-2fa-methods__input" />
						<p class="aio-login-2fa-methods__help">{{ $t("How long the IP stays blocked after too many failed email OTP attempts. Shown under Activity Log → Lockouts.") }}</p>
					</div>
					<div v-if="has_pro" class="aio-login-otp-login__toggle-field">
						<label class="aio-login-2fa-methods__label">
							{{ $t("Skip 2FA for Email login") }}
								<aio-login-tooltip :content="tooltipContent.passwordlessOtpSkip2fa.content" />
							</label>
							<p class="aio-login-2fa-methods__help">{{ $t("When enabled, users who sign in with email OTP are not prompted for AIO Login two-factor authentication.") }}</p>
							<aio-login-toggle
								id="email_skip_2fa"
								name="email_skip_2fa"
								:enabled="form.email_skip_2fa"
								:disabled="!form.email_enable"
								v-on:toggle-input="form.email_skip_2fa = $event"
							/>
					</div>
				</div>
			</div>

			<!-- SMS OTP Login -->
			<div
				class="aio-login-2fa-methods__item aio-login-2fa-policies__card aio-login-otp-login__sms-card"
				:class="{ 'aio-login-pro-feature': !has_pro, 'aio-login-otp-login__sms-card--locked': !has_pro }"
				@click="!has_pro ? handleProFeatureClick() : null"
			>
				<div class="aio-login-2fa-policies__card-header">
					<span class="aio-login-2fa-policies__icon" aria-hidden="true">
						<svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2v10z" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
					</span>
					<div class="aio-login-2fa-methods__content">
						<h2 class="aio-login-2fa-methods__title">
							{{ $t("SMS OTP Login") }} 
							<aio-login-tooltip :content="tooltipContent.passwordlessSmsOtp.content" />
						</h2>
						<div v-if="has_pro && form.sms_enable && isCardExpanded('sms')" class="aio-login-2fa-methods__desc">
							{{ $t("Allow users to sign in with a one-time verification code sent via SMS (requires Twilio).") }}
						</div>
					</div>
					<div class="aio-login-2fa-methods__controls">
						<button
							type="button"
							class="aio-login-2fa-methods__expand-btn"
							@click.stop="toggleCard('sms')"
							:aria-expanded="isCardExpanded('sms')"
							:aria-label="$t('Toggle SMS OTP Login settings')"
							:disabled="!has_pro || !form.sms_enable"
						>
							<span class="aio-login-2fa-methods__chevron" :class="{ 'is-expanded': isCardExpanded('sms') }"></span>
						</button>
						<aio-login-toggle
							id="sms_otp_enable"
							name="sms_otp_enable"
							:enabled="has_pro && form.sms_enable"
							:disabled="!has_pro"
							v-on:toggle-input="onSmsEnableToggle"
						/>
					</div>
				</div>

				<div
					v-if="has_pro && form.sms_enable && isCardExpanded('sms')"
					class="aio-login-2fa-policies__card-body"
				>
					<div class="aio-login-otp-login__twilio-box">
						<div class="aio-login-otp-login__twilio-box-header">
							<span class="aio-login-otp-login__twilio-box-icon" aria-hidden="true">
								<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
									<path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
								</svg>
							</span>
							<h3 class="aio-login-otp-login__twilio-box-title">{{ $t("Twilio Configuration") }}</h3>
						</div>
						<div class="aio-login-2fa-methods__grid">
						<div>
							<label class="aio-login-2fa-methods__label" for="twilio_sid">
								{{ $t("Account SID") }} <span class="aio-login-pro__required">*</span>
							</label>
							<input
								id="twilio_sid"
								type="text"
								v-model="form.twilio_account_sid"
								class="aio-login-2fa-methods__input"
								:class="{ 'aio-login-lr-input-error': !!twilio_errors.account_sid }"
								:disabled="smsFieldsDisabled"
								:required="form.sms_enable"
								autocomplete="off"
								@input="clearTwilioError('account_sid')"
							/>
							<p v-if="twilio_errors.account_sid" class="aio-login-lr-field-error">{{ twilio_errors.account_sid }}</p>
						</div>
						<div>
							<label class="aio-login-2fa-methods__label" for="twilio_token">
								{{ $t("Auth Token") }} <span class="aio-login-pro__required">*</span>
								<aio-login-tooltip :content="tooltipContent.passwordlessTwilioToken.content" />
							</label>
							<input
								id="twilio_token"
								type="password"
								v-model="form.twilio_auth_token"
								class="aio-login-2fa-methods__input"
								:class="{ 'aio-login-lr-input-error': !!twilio_errors.auth_token }"
								:disabled="smsFieldsDisabled"
								:required="form.sms_enable && !twilio_auth_token_stored"
								:placeholder="twilio_auth_token_stored ? 'Leave blank to keep existing' : ''"
								autocomplete="new-password"
								@input="clearTwilioError('auth_token')"
							/>
							<p v-if="twilio_errors.auth_token" class="aio-login-lr-field-error">{{ twilio_errors.auth_token }}</p>
						</div>
					</div>
					<div class="aio-login-2fa-methods__grid">
						<div>
							<label class="aio-login-2fa-methods__label" for="twilio_from">
								{{ $t("Sender number") }} <span class="aio-login-pro__required">*</span>
							</label>
							<input
								id="twilio_from"
								type="text"
								v-model="form.twilio_sender_number"
								class="aio-login-2fa-methods__input"
								:class="{ 'aio-login-lr-input-error': !!twilio_errors.sender_number }"
								:disabled="smsFieldsDisabled"
								:required="form.sms_enable"
								autocomplete="off"
								@input="clearTwilioError('sender_number')"
							/>
							<p v-if="twilio_errors.sender_number" class="aio-login-lr-field-error">{{ twilio_errors.sender_number }}</p>
						</div>
						<div>
							<label class="aio-login-2fa-methods__label" for="default_country">{{ $t("Default country code") }}</label>
							<select id="default_country" v-model="form.sms_default_country_iso" class="aio-login-2fa-methods__input" :disabled="smsFieldsDisabled">
								<option v-for="c in countryCodes" :key="c.iso" :value="c.iso">{{ c.label }}</option>
							</select>
						</div>
					</div>
					<div class="aio-login-2fa-methods__field aio-login-otp-login__twilio-box-field">
						<label id="sms-allowed-countries-label" class="aio-login-2fa-methods__label">{{ $t("Allowed countries") }}</label>
						<div
							ref="countryPickerRoot"
							class="aio-login-lr-ms aio-login-otp-login__country-ms"
							:class="{ 'is-open': country_picker_open, 'is-disabled': smsFieldsDisabled }"
						>
							<button
								type="button"
								id="sms-allowed-countries-trigger"
								class="aio-login-lr-ms-trigger"
								:class="{ 'aio-login-lr-input-error': !!allowed_countries_error }"
								:disabled="smsFieldsDisabled"
								aria-haspopup="listbox"
								:aria-expanded="country_picker_open ? 'true' : 'false'"
								aria-labelledby="sms-allowed-countries-label sms-allowed-countries-trigger"
								@click.stop="toggleCountryPicker"
							>
								<div class="aio-login-lr-ms-pills">
									<template v-if="!form.sms_allowed_countries.length">
										<span class="aio-login-lr-ms-trigger-placeholder">{{ $t("Choose countries...") }}</span>
									</template>
									<span
										v-for="iso in form.sms_allowed_countries"
										:key="'country-pill-' + iso"
										class="aio-login-lr-ms-pill"
									>
										{{ labelForCountryIso(iso) }}
										<button
											type="button"
											class="aio-login-lr-ms-pill-remove"
											:aria-label="'Remove ' + labelForCountryIso(iso)"
											:disabled="smsFieldsDisabled"
											@click.stop="removeCountryTag(iso)"
										>&times;</button>
									</span>
								</div>
								<span class="aio-login-lr-ms-chevron dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
							</button>
							<div
								v-show="country_picker_open"
								id="sms-allowed-countries-panel"
								class="aio-login-lr-ms-panel"
								role="listbox"
								aria-multiselectable="true"
								@click.stop
							>
								<div class="aio-login-lr-ms-search">
									<span class="dashicons dashicons-search" aria-hidden="true"></span>
									<input
										type="search"
										v-model.trim="country_picker_search"
										class="aio-login-lr-ms-search-input"
										placeholder="Search countries..."
										autocomplete="off"
										@click.stop
										@keydown.esc.stop.prevent="country_picker_open = false"
									/>
								</div>
								<div v-if="filteredAllowedCountries.length" class="aio-login-lr-ms-select-all">
									<label class="aio-login-lr-ms-option aio-login-lr-ms-select-all-label">
										<input
											ref="selectAllCountryCheckbox"
											type="checkbox"
											class="aio-login-lr-ms-check"
											:checked="allAllowedCountriesSelected"
											:disabled="smsFieldsDisabled"
											@click.stop
											@change="toggleSelectAllAllowedCountries"
										/>
										<span class="aio-login-lr-ms-option-label">
											{{ country_picker_search ? 'Select all matching' : 'Select all countries' }}
										</span>
									</label>
								</div>
								<ul class="aio-login-lr-ms-list">
									<li v-for="c in filteredAllowedCountries" :key="c.iso" class="aio-login-lr-ms-item">
										<label class="aio-login-lr-ms-option">
											<input
												type="checkbox"
												class="aio-login-lr-ms-check"
												:value="c.iso"
												v-model="form.sms_allowed_countries"
												:disabled="smsFieldsDisabled"
												@click.stop
											/>
											<span class="aio-login-lr-ms-option-label">{{ c.label }} ({{ c.iso }})</span>
										</label>
									</li>
									<li v-if="!filteredAllowedCountries.length" class="aio-login-lr-ms-empty">{{ $t("No countries match your search.") }}</li>
								</ul>
								<div class="aio-login-lr-ms-footer aio-login-lr-ms-footer--actions">
									<span class="aio-login-lr-ms-footer-count">{{ form.sms_allowed_countries.length }} {{ $t('selected') }}</span>
									<span class="aio-login-lr-ms-footer-buttons">
										<button type="button" class="aio-login-lr-ms-clear-all" @click.stop="selectAllAllowedCountries">{{ $t("Select all") }}</button>
										<button type="button" class="aio-login-lr-ms-clear-all" @click.stop="clearAllAllowedCountries">{{ $t("Clear all") }}</button>
									</span>
								</div>
							</div>
						</div>
						<p v-if="allowed_countries_error" class="aio-login-lr-field-error">{{ allowed_countries_error }}</p>
						<p class="aio-login-2fa-methods__help">{{ $t("Only phone numbers from selected countries can request SMS OTP login. At least one country is required.") }}</p>
					</div>
					</div>

					<h3 class="aio-login-otp-login__section-title aio-login-otp-login__section-title--sms">{{ $t("SMS OTP") }}</h3>
					<div class="aio-login-2fa-methods__grid aio-login-2fa-methods__grid--four">
						<div>
							<label class="aio-login-2fa-methods__label" for="sms_length">{{ $t("OTP Length") }}</label>
							<select id="sms_length" v-model="form.sms_length" class="aio-login-2fa-methods__input" :disabled="smsFieldsDisabled">
								<option value="4">{{ $t("4 digits") }}</option>
								<option value="6">{{ $t("6 digits") }}</option>
								<option value="8">{{ $t("8 digits") }}</option>
							</select>
						</div>
						<div>
							<label class="aio-login-2fa-methods__label" for="sms_expiration">{{ $t("Expiration (minutes)") }}</label>
							<input id="sms_expiration" type="number" min="1" max="60" v-model="form.sms_expiration" :disabled="smsFieldsDisabled" class="aio-login-2fa-methods__input" />
						</div>
						<div>
							<label class="aio-login-2fa-methods__label" for="sms_resend">{{ $t("Resend timer (seconds)") }}</label>
							<input id="sms_resend" type="number" min="30" max="600" v-model="form.sms_resend_timer" :disabled="smsFieldsDisabled" class="aio-login-2fa-methods__input" />
						</div>
						<div>
							<label class="aio-login-2fa-methods__label" for="sms_retries">{{ $t("Max retries") }}</label>
							<input id="sms_retries" type="number" min="1" max="20" v-model="form.sms_max_retries" :disabled="smsFieldsDisabled" class="aio-login-2fa-methods__input" />
						</div>
					</div>

					<div class="aio-login-2fa-methods__field aio-login-otp-login__field--half">
						<label class="aio-login-2fa-methods__label" for="sms_block_duration">
								{{ $t("Block duration (minutes)") }}
								<aio-login-tooltip :content="tooltipContent.passwordlessOtpBlockDuration.content" />
							</label>
							<input id="sms_block_duration" type="number" min="1" max="1440" v-model="form.sms_block_duration" :disabled="smsFieldsDisabled" class="aio-login-2fa-methods__input" />
						<p class="aio-login-2fa-methods__help">{{ $t("How long the IP stays blocked after too many failed SMS OTP attempts. Shown under Activity Log → Lockouts.") }}</p>
					</div>
					<div class="aio-login-otp-login__toggle-field">
						<label class="aio-login-2fa-methods__label">
							{{ $t("Skip 2FA for SMS login") }}
							<aio-login-tooltip :content="tooltipContent.passwordlessOtpSkip2fa.content" />
						</label>
						<p class="aio-login-2fa-methods__help">{{ $t("When enabled, users who sign in with SMS OTP are not prompted for AIO Login two-factor authentication.") }}</p>
						<aio-login-toggle
							id="sms_skip_2fa"
							name="sms_skip_2fa"
							:enabled="form.sms_skip_2fa"
							:disabled="smsFieldsDisabled"
							v-on:toggle-input="form.sms_skip_2fa = $event"
						/>
					</div>
				</div>
			</div>
		</div>

		<div class="aio-login-2fa-methods__actions">
			<button type="button" class="button button-primary aio-login-otp-login__save-btn" @click="saveSettings" :disabled="saving">
				{{ saving ? $t('Saving…') : $t('Save Changes') }}
			</button>
		</div>

		<aio-login-snackbar
			:message="snackbar.message"
			:duration="snackbar.duration"
			v-if="snackbar.show"
			v-on:close="snackbar.show = false"
		/>
	</div>
</template>

<script>
import tooltipContent from '../tooltip-content.js';
export default {
	name: 'passwordless-otp-login',

	data: () => ({
		tooltipContent,
		page_loaded: false,
		saving: false,
		nonce: '',
		api_has_pro: false,
		expandedCards: {
			email: true,
			sms: false,
		},
		countryCodes: [],
		country_picker_open: false,
		country_picker_search: '',
		allowed_countries_error: '',
		twilio_auth_token_stored: false,
		twilio_errors: {
			account_sid: '',
			auth_token: '',
			sender_number: '',
		},
		form: {
			email_enable: false,
			email_block_duration: '15',
			email_length: '6',
			email_expiration: '10',
			email_resend_timer: '60',
			email_max_retries: '5',
			email_skip_2fa: true,
			sms_enable: false,
			sms_length: '6',
			sms_expiration: '10',
			sms_resend_timer: '60',
			sms_max_retries: '5',
			sms_block_duration: '15',
			sms_skip_2fa: true,
			twilio_account_sid: '',
			twilio_auth_token: '',
			twilio_sender_number: '',
			sms_default_country_iso: 'US',
			sms_allowed_countries: [],
		},
		snackbar: { message: '', duration: 3000, show: false },
	}),

	computed: {
		has_pro() {
			const o = window.aio_login__app_object;
			if ( o && ( o.has_pro === true || o.has_pro === 'true' ) ) {
				return true;
			}
			return this.api_has_pro === true;
		},
		smsFieldsDisabled() {
			return ! this.has_pro || ! this.form.sms_enable;
		},
		filteredAllowedCountries() {
			const q = ( this.country_picker_search || '' ).toLowerCase();
			const list = this.countryCodes || [];
			if ( ! q ) {
				return list;
			}
			return list.filter( ( c ) => {
				return c.iso.toLowerCase().includes( q )
					|| c.label.toLowerCase().includes( q )
					|| String( c.code || '' ).toLowerCase().includes( q );
			} );
		},
		selectableAllowedCountryIsos() {
			const source = this.country_picker_search
				? this.filteredAllowedCountries
				: ( this.countryCodes || [] );
			return source.map( ( c ) => c.iso );
		},
		allAllowedCountriesSelected() {
			const isos = this.selectableAllowedCountryIsos;
			if ( ! isos.length ) {
				return false;
			}
			return isos.every( ( iso ) => this.form.sms_allowed_countries.includes( iso ) );
		},
		allowedCountriesIndeterminate() {
			const isos = this.selectableAllowedCountryIsos;
			if ( ! isos.length ) {
				return false;
			}
			const selected = isos.filter( ( iso ) => this.form.sms_allowed_countries.includes( iso ) );
			return selected.length > 0 && selected.length < isos.length;
		},
	},

	mounted() {
		this.loadSettings();
		this.onCountryPickerOutside = this.onCountryPickerOutside.bind( this );
		document.addEventListener( 'click', this.onCountryPickerOutside );
	},

	beforeUnmount() {
		document.removeEventListener( 'click', this.onCountryPickerOutside );
	},

	watch: {
		allowedCountriesIndeterminate() {
			this.$nextTick( () => this.applySelectAllIndeterminate() );
		},
		country_picker_open( open ) {
			if ( open ) {
				this.$nextTick( () => this.applySelectAllIndeterminate() );
			}
		},
		'form.sms_allowed_countries'() {
			if ( this.country_picker_open ) {
				this.$nextTick( () => this.applySelectAllIndeterminate() );
			}
		},
	},

	methods: {
		setAllowedCountriesError() {
			this.allowed_countries_error = 'Select at least one allowed country for SMS login.';
		},
		clearTwilioError(field) {
			if (field && this.twilio_errors[field] !== undefined) {
				this.twilio_errors[field] = '';
			}
		},
		clearTwilioErrors() {
			this.twilio_errors = {
				account_sid: '',
				auth_token: '',
				sender_number: '',
			};
		},
		validateTwilioConfig() {
			this.clearTwilioErrors();
			if (!this.has_pro || !this.form.sms_enable) {
				return true;
			}

			const sid = String(this.form.twilio_account_sid || '').trim();
			const sender = String(this.form.twilio_sender_number || '').trim();
			const token = String(this.form.twilio_auth_token || '').trim();
			const hasNewToken = token !== '' && token !== '••••••••••••';
			const hasStoredToken = !!this.twilio_auth_token_stored;

			if (!sid) {
				this.twilio_errors.account_sid = 'Account SID is required.';
			}
			if (!hasStoredToken && !hasNewToken) {
				this.twilio_errors.auth_token = 'Auth Token is required.';
			}
			if (!sender) {
				this.twilio_errors.sender_number = 'Sender number is required.';
			}

			const firstError = this.twilio_errors.account_sid
				|| this.twilio_errors.auth_token
				|| this.twilio_errors.sender_number;
			if (firstError) {
				this.expandedCards.sms = true;
				this.$nextTick(() => {
					const focusId = this.twilio_errors.account_sid
						? 'twilio_sid'
						: (this.twilio_errors.auth_token ? 'twilio_token' : 'twilio_from');
					const el = document.getElementById(focusId);
					if (el && typeof el.focus === 'function') {
						el.focus();
					}
				});
				return false;
			}
			return true;
		},
		validateAllowedCountries() {
			if ( ! this.has_pro || ! this.form.sms_enable ) {
				this.allowed_countries_error = '';
				return true;
			}
			if ( ! this.form.sms_allowed_countries.length ) {
				this.setAllowedCountriesError();
				this.country_picker_open = true;
				return false;
			}
			this.allowed_countries_error = '';
			return true;
		},
		applySelectAllIndeterminate() {
			const el = this.$refs.selectAllCountryCheckbox;
			if ( el ) {
				el.indeterminate = this.allowedCountriesIndeterminate;
			}
		},
		isCardExpanded(key) {
			return !!this.expandedCards[key];
		},
		toggleCard(key) {
			if ( key === 'sms' ) {
				if ( ! this.has_pro ) {
					this.handleProFeatureClick();
					return;
				}
				if ( ! this.form.sms_enable ) {
					return;
				}
				this.expandedCards.sms = !this.expandedCards.sms;
				return;
			}
			this.expandedCards[key] = !this.expandedCards[key];
		},
		onSmsEnableToggle(enabled) {
			if ( ! this.has_pro ) {
				this.form.sms_enable = false;
				this.expandedCards.sms = false;
				return;
			}
			this.form.sms_enable = enabled;
			this.expandedCards.sms = enabled;
			if ( ! enabled ) {
				this.country_picker_open = false;
			}
		},
		labelForCountryIso(iso) {
			const match = ( this.countryCodes || [] ).find( ( c ) => c.iso === iso );
			return match ? match.label : iso;
		},
		toggleCountryPicker() {
			if ( this.smsFieldsDisabled ) {
				return;
			}
			this.country_picker_open = !this.country_picker_open;
			if ( this.country_picker_open ) {
				this.country_picker_search = '';
			}
		},
		removeCountryTag(iso) {
			if ( this.smsFieldsDisabled ) {
				return;
			}
			this.form.sms_allowed_countries = this.form.sms_allowed_countries.filter( ( c ) => c !== iso );
		},
		clearAllAllowedCountries() {
			if ( this.smsFieldsDisabled ) {
				return;
			}
			this.form.sms_allowed_countries = [];
		},
		selectAllAllowedCountries() {
			if ( this.smsFieldsDisabled ) {
				return;
			}
			const isos = ( this.countryCodes || [] ).map( ( c ) => c.iso );
			this.form.sms_allowed_countries = Array.from( new Set( isos ) );
		},
		toggleSelectAllAllowedCountries() {
			if ( this.smsFieldsDisabled ) {
				return;
			}
			const isos = this.selectableAllowedCountryIsos;
			if ( this.allAllowedCountriesSelected ) {
				this.form.sms_allowed_countries = this.form.sms_allowed_countries.filter(
					( iso ) => !isos.includes( iso )
				);
				return;
			}
			this.form.sms_allowed_countries = Array.from(
				new Set( [ ...this.form.sms_allowed_countries, ...isos ] )
			);
		},
		onCountryPickerOutside(ev) {
			const root = this.$refs.countryPickerRoot;
			if ( this.country_picker_open && root && !root.contains( ev.target ) ) {
				this.country_picker_open = false;
			}
		},
		handleProFeatureClick() {
			let p = this.$parent;
			while ( p ) {
				if ( 'popup' in p && typeof p.popup === 'boolean' ) {
					p.popup = true;
					return;
				}
				p = p.$parent;
			}
		},
		loadSettings() {
			axios.get('aio-login/passwordless-otp/get-settings').then((res) => {
				const d = res.data;
				this.nonce = d.nonce;
				this.countryCodes = d.country_codes || [];
				this.form.email_enable = !!d.email_enable;
				this.form.email_length = String(d.email_length || '6');
				this.form.email_expiration = String(d.email_expiration || '10');
				this.form.email_resend_timer = String(d.email_resend_timer || '60');
				this.form.email_max_retries = String(d.email_max_retries || '5');
				this.form.email_block_duration = String(d.email_block_duration || '15');
				this.form.email_skip_2fa = !!d.email_skip_2fa;
				this.api_has_pro = !!d.has_pro;
				this.form.sms_enable = !!d.sms_enable;
				if ( this.has_pro ) {
					this.expandedCards.sms = this.form.sms_enable;
				} else {
					this.expandedCards.sms = false;
				}
				this.form.sms_length = String(d.sms_length || '6');
				this.form.sms_expiration = String(d.sms_expiration || '10');
				this.form.sms_resend_timer = String(d.sms_resend_timer || '60');
				this.form.sms_max_retries = String(d.sms_max_retries || '5');
				this.form.sms_block_duration = String(d.sms_block_duration || '15');
				this.form.sms_skip_2fa = !!d.sms_skip_2fa;
				this.form.twilio_account_sid = d.twilio_account_sid || '';
				this.twilio_auth_token_stored = !!d.twilio_auth_token_stored;
				this.form.twilio_auth_token = this.twilio_auth_token_stored ? '••••••••••••' : '';
				this.form.twilio_sender_number = d.twilio_sender_number || '';
				this.form.sms_default_country_iso = d.sms_default_country_iso || 'US';
				this.form.sms_allowed_countries = Array.isArray(d.sms_allowed_countries) ? d.sms_allowed_countries : [];
				this.allowed_countries_error = '';
				this.clearTwilioErrors();
				this.page_loaded = true;
			});
		},
		saveSettings() {
			if ( !this.validateTwilioConfig() ) {
				const msg = this.twilio_errors.account_sid
					|| this.twilio_errors.auth_token
					|| this.twilio_errors.sender_number
					|| 'Please fill in all required Twilio fields.';
				this.snackbar.message = msg;
				this.snackbar.show = true;
				return;
			}
			if ( !this.validateAllowedCountries() ) {
				this.snackbar.message = this.allowed_countries_error;
				this.snackbar.show = true;
				return;
			}
			this.saving = true;
			const payload = {
				_wpnonce: this.nonce,
				...this.form,
			};
			if ( ! this.has_pro ) {
				delete payload.sms_enable;
				delete payload.twilio_account_sid;
				delete payload.twilio_auth_token;
				delete payload.twilio_sender_number;
				delete payload.sms_default_country_iso;
				delete payload.sms_allowed_countries;
				delete payload.sms_length;
				delete payload.sms_expiration;
				delete payload.sms_resend_timer;
				delete payload.sms_max_retries;
				delete payload.sms_block_duration;
				delete payload.sms_skip_2fa;
			}
			if (payload.twilio_auth_token === '••••••••••••') {
				payload.twilio_auth_token = '';
			}
			axios.post('aio-login/passwordless-otp/save-settings', payload).then((res) => {
				this.saving = false;
				if (res.data && res.data.success) {
					this.allowed_countries_error = '';
					this.snackbar.message = res.data.message || 'Saved';
					this.snackbar.show = true;
					if (res.data.data) {
						this.loadSettings();
					}
				}
			}).catch((err) => {
				this.saving = false;
				const msg = err?.response?.data?.message || 'Save failed';
				const code = err?.response?.data?.code || '';
				this.snackbar.message = msg;
				this.snackbar.show = true;
				if ( code === 'sms_allowed_countries_required' ) {
					this.allowed_countries_error = msg;
					this.country_picker_open = true;
				}
				if ( code === 'twilio_account_sid_required' ) {
					this.twilio_errors.account_sid = msg;
					this.expandedCards.sms = true;
				}
				if ( code === 'twilio_auth_token_required' ) {
					this.twilio_errors.auth_token = msg;
					this.expandedCards.sms = true;
				}
				if ( code === 'twilio_sender_number_required' ) {
					this.twilio_errors.sender_number = msg;
					this.expandedCards.sms = true;
				}
			});
		},
	},
};
</script>
