<template>
	<div>
		<aio-login-form
			:action="nonce"
			v-on:handle-submit="handleSubmit"
		>
			<template v-slot:title>
				<span>{{ $t("User Enumeration Protection") }}</span>
				<aio-login-tooltip
					:content="tooltipContent.userEnumerationProtection.content"
					:title="tooltipContent.userEnumerationProtection.title"
					placement="bottom"
				/>
			</template>
		
			<template v-slot:form-fields>
				<tr>
					<th>
						<label for="enable_protection">{{ $t("Enable Protection") }}</label>
					</th>
					<td>
						<aio-login-toggle
							id="enable_protection"
							name="enable_protection"
							:enabled="form_data.enable_protection"
							v-on:toggle-input="handleEnableProtection"
						/>
						<p class="desc">
							<strong>{{ $t("Enable to hide usernames to strengthen site security.") }}</strong>
						</p>
					</td>
				</tr>

				<tr v-if="form_data.enable_protection">
					<th>
						<label for="stop_oembed_calls">{{ $t("Stop oEmbed Calls Revealing User IDs") }}</label>
					</th>
					<td>
						<aio-login-toggle
							id="stop_oembed_calls"
							name="stop_oembed_calls"
							:enabled="form_data.stop_oembed_calls"
							v-on:toggle-input="handleStopOembedCalls"
						/>
						<p class="description">{{ $t("Prevents WordPress from exposing login IDs through embedded author links.") }}</p>
					</td>
				</tr>

				<tr v-if="form_data.enable_protection">
					<th>
						<label for="disable_author_sitemaps">{{ $t("Disable WP Core Author Sitemaps") }}</label>
					</th>
					<td>
						<aio-login-toggle
							id="disable_author_sitemaps"
							name="disable_author_sitemaps"
							:enabled="form_data.disable_author_sitemaps"
							v-on:toggle-input="handleDisableAuthorSitemaps"
						/>
						<p class="description">{{ $t("Removes author-based sitemaps to prevent automated exposure of usernames.") }}</p>
					</td>
				</tr>

				<tr v-if="form_data.enable_protection">
					<th>
						<label for="remove_comment_numbers">{{ $t("Prevent Username from Comment Authors") }}</label>
					</th>
					<td>
						<aio-login-toggle
							id="remove_comment_numbers"
							name="remove_comment_numbers"
							:enabled="form_data.remove_comment_numbers"
							v-on:toggle-input="handleRemoveCommentNumbers"
						/>
						<p class="description">{{ $t("Obfuscates comment author names to prevent username enumeration through comments.") }}</p>
					</td>
				</tr>

				<tr v-if="form_data.enable_protection">
					<th>
						<label for="protect_rest_api">{{ $t("Protect REST API User Endpoints") }}</label>
					</th>
					<td>
						<aio-login-toggle
							id="protect_rest_api"
							name="protect_rest_api"
							:enabled="form_data.protect_rest_api"
							v-on:toggle-input="handleProtectRestApi"
						/>
						<p class="description">{{ $t("Blocks unauthorized access to WordPress REST API user endpoints.") }}</p>
					</td>
				</tr>

				<tr v-if="form_data.enable_protection">
					<th>
						<label for="login_registration_errors">{{ $t("Generic Login & Registration Errors") }}</label>
					</th>
					<td>
						<aio-login-toggle
							id="login_registration_errors"
							name="login_registration_errors"
							:enabled="form_data.login_registration_errors"
							v-on:toggle-input="handleLoginRegistrationErrors"
						/>
						<p class="description">{{ $t("Provides generic error messages to prevent username enumeration through login/registration forms.") }}</p>
					</td>
				</tr>


			</template>
		</aio-login-form>

		<div class="aio-login__note-section">
			<div class="aio-login__note">
				<p><strong>{{ $t("Note:") }}</strong> {{ $t("This protection does not apply on admin pages to avoid conflicts. It's recommended to use with a firewall or IP blocking tool (e.g., Fail2Ban) for better security on VPS or dedicated servers.") }}</p>
			</div>
		</div>

		<aio-login-snackbar
			:message="snackbar.message"
			v-if="snackbar.show"
			:duration="snackbar.timeout"
			v-on:close="handleSnackbarClose"
		/>
	</div>
</template>

<script>
import tooltipContent from '../tooltip-content.js';

export default {
	name: 'user-enumeration-protection',

	data: () => ({
		tooltipContent,
		nonce: '',
		form_data: {
			enable_protection: false,
			stop_oembed_calls: false,
			disable_author_sitemaps: false,
			remove_comment_numbers: false,
			protect_rest_api: false,
			login_registration_errors: false,
		},
		has_pro: false,
		snackbar: {
			message: '',
			show: false,
			timeout: 3000,
		},
	}),

	mounted() {
		this.loadComponent();
	},

	methods: {
		loadComponent() {
			axios.get('aio-login/dashboard/user-enumeration-settings')
				.then(response => {
					if (response.data.success) {
						const data = response.data.data;
						this.form_data.enable_protection = data.enable_protection === 'on';
						this.form_data.stop_oembed_calls = data.stop_oembed_calls === 'on';
						this.form_data.disable_author_sitemaps = data.disable_author_sitemaps === 'on';
						this.form_data.remove_comment_numbers = data.remove_comment_numbers === 'on';
						this.form_data.protect_rest_api = data.protect_rest_api === 'on';
						this.form_data.login_registration_errors = data.login_registration_errors === 'on';
						this.has_pro = data.has_pro === 'true';
						this.nonce = response.data.nonce || '';
					}
				})
				.catch(error => {
					console.error('Error loading settings:', error);
				});
		},

		handleEnableProtection(value) {
			this.form_data.enable_protection = value;
		},

		handleStopOembedCalls(value) {
			this.form_data.stop_oembed_calls = value;
		},

		handleDisableAuthorSitemaps(value) {
			this.form_data.disable_author_sitemaps = value;
		},

		handleRemoveCommentNumbers(value) {
			this.form_data.remove_comment_numbers = value;
		},

		handleProtectRestApi(value) {
			this.form_data.protect_rest_api = value;
		},

		handleLoginRegistrationErrors(value) {
			this.form_data.login_registration_errors = value;
		},

		handleProFeatureClick() {
			if (!this.has_pro) {
				this.$parent.$parent.popup = true;
			}
		},

		handleSubmit() {
			const settings = {
				enable_protection: this.form_data.enable_protection ? 'on' : 'off',
				stop_oembed_calls: this.form_data.stop_oembed_calls ? 'on' : 'off',
				disable_author_sitemaps: this.form_data.disable_author_sitemaps ? 'on' : 'off',
				remove_comment_numbers: this.form_data.remove_comment_numbers ? 'on' : 'off',
				protect_rest_api: this.form_data.protect_rest_api ? 'on' : 'off',
				login_registration_errors: this.form_data.login_registration_errors ? 'on' : 'off',
			};

			axios.post('aio-login/dashboard/update/user-enumeration-settings', {
				settings: settings,
				_wpnonce: this.nonce
			})
			.then(response => {
				if (response.data.success) {
					this.showSnackbar('Settings saved successfully!');
				} else {
					this.showSnackbar('Error saving settings.');
				}
			})
			.catch(error => {
				console.error('Error saving settings:', error);
				this.showSnackbar('Error saving settings.');
			});
		},

		showSnackbar(message) {
			this.snackbar.message = message;
			this.snackbar.show = true;
		},

		handleSnackbarClose() {
			this.snackbar.show = false;
		},
	},
};
</script> 