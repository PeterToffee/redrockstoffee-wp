<template>
	<div v-if="page_loaded">
		<aio-login-form
			:action="nonce"
			v-on:handle-submit="submitHandler"
		>
			<template v-slot:title>
				<span>{{ $t("Change wp-admin login") }}</span>
				<aio-login-tooltip
					:content="tooltipContent.changeLoginUrl.content"
					placement="bottom"
				/>
			</template>

			<template v-slot:form-fields>
				<tr>
					<th scope="row">
						<label for="enable">{{ $t("Enable") }}</label>
					</th>
					<td>
						<aio-login-toggle
							id="enable"
							name="aio_login__cwpal_enable"
							:enabled="form_data.enabled"
							v-on:toggle-input="handleValue"
						/>

						<p class="desc">
							<strong>{{ $t("Enable to customize your admin login URL.") }}</strong>
						</p>
					</td>
				</tr>

				<tr v-if="form_data.enabled">
					<th scope="row">
						<label for="login-url">{{ $t("Login URL") }}</label>
					</th>
					<td>
						<code style="font-family: monospace">
							{{ login_url }}

							<input
								id="login-url"
								name="aio_login__cwpal_login_url"
								v-model="form_data.login_url"
								type="text"
								class="regular-text"
							/>
							<span v-if="trailing_slashes">/</span>
						</code>

						<p class="desc">
							<strong>{{ $t("Protect your website by changing the login page URL.") }}</strong>
						</p>
					</td>
				</tr>

				<tr v-if="form_data.enabled">
					<th scope="row">
						<label for="redirect-url">{{ $t("Redirect URL") }}</label>
					</th>
					<td>
						<code style="font-family: monospace">
							{{ redirect_url }}
							<input
								v-model="form_data.redirect_url"
								name="aio_login__cwpal_redirect_url"
								id="redirect-url"
								type="text"
								class="regular-text"
							/>
							/
						</code>

						<p class="desc">
							<strong>{{ $t("Specify URL where attempts to access wp-login or wp-admin should be redirected to. If custom URL is set above, By default, this will redirect to your site's Home page unless you set it to something else.") }}</strong>
						</p>
					</td>
				</tr>
			</template>
		</aio-login-form>

		<aio-login-snackbar
			:message="snackbar.message"
			:duration="snackbar.duration"
			v-if="snackbar.show"

			v-on:close="handleSnackbarClose"
		/>
	</div>
</template>

<script>
import tooltipContent from '../tooltip-content.js';

export default {
	name: 'change-login-url',

	data: ( vm ) => ( {
		tooltipContent,
		namespace: 'aio-login/change-wp-admin-login',

		page_loaded: false,

		nonce: '',

		login_url: aio_login__app_object.site_link_login_url,
		redirect_url: aio_login__app_object.site_link_redirect_url,
		trailing_slashes: 'true' === aio_login__app_object.trailing_slashes,

		siteurl: aio_login__app_object.site_url,

		form_data: {
			enabled: false,
			login_url: '',
			redirect_url: '',
		},

		snackbar: {
			message: '',
			duration: 3000,
			show: false,
		}
	} ),

	methods: {
		handleValue( value ) {
			this.form_data.enabled = value;
		},

		submitHandler( e ) {
			axios.post( this.namespace + '/save-settings', {
				_wpnonce: this.nonce,
				enabled: this.form_data.enabled,
				login_url: this.form_data.login_url,
				redirect_url: this.form_data.redirect_url,
			} )
				.then( response => {
					if (response.data.success) {
						this.snackbar.message = response.data.message;
						this.snackbar.show = true;
					} else {
						this.snackbar.message = 'Error saving settings. Please try again.';
						this.snackbar.show = true;
					}
				} )
				.catch( error => {
					console.error('Error saving settings:', error);
					this.snackbar.message = 'Error saving settings. Please try again.';
					this.snackbar.show = true;
				} );
		},

		handleSnackbarClose() {
			this.snackbar.show = false;
			this.snackbar.message = '';
		}
	},

	mounted() {

		axios.get( this.namespace + '/get-settings' )
			.then( response => {
				if (response.data.success) {
					var data = response.data.data;

					this.form_data.enabled      = data.enabled;
					this.form_data.login_url    = data.login_url;
					this.form_data.redirect_url = data.redirect_url;
					this.page_loaded            = true;
					this.nonce                  = data.nonce;
				} else {
					console.error('Error loading settings:', response.data);
					this.page_loaded = true;
				}
			} )
			.catch( error => {
				console.error('Error loading settings:', error);
				this.page_loaded = true;
			} );
	}
}
</script>

<style scoped>

</style>