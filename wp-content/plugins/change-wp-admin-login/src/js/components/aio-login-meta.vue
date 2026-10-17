<template>
	<div class="aio-login__meta-container">
		<div>
			<aio-login-metadata
				:title="$t('Custom Login URL')"
				:description="$t('Attackers often try exploits on /wp-login or /wp-admin as a default login URL for WordPress. Change it to avoid these attacks and have an easily memorizable login URL.')"
			>
				<template v-slot:configuration>
					<div class="aio-login__configuration-switch aio-login-configuration-btn-wrapper">
						<a class="aio-login-configuration-btn" :href="adminURL + '&tab=login-protection'">{{ $t("Configure") }}</a>
					</div>
				</template>
			</aio-login-metadata>
		</div>
		<div>
			<aio-login-metadata
				:title="$t('Limit Login Attempts')"
				:description="$t('Limit the number of times a user IP can attempt to log in to your wp-admin with incorrect credentials. Once the login attempt limit is reached, the IP from which the attempts have originated will be blocked for default period of time.')"
				@click="iWasTriggered"
			>
				<template v-slot:configuration>
					<div class="aio-login__configuration-switch">
						<select
							class="aio-login__configuration-select"
							:class="{ 'aio-login__configuration-select--locked': !has_pro }"
							v-model="limit_login_attempts"
							@change="limitLoginAttempts"
							@mousedown="handleLimitLoginSelectInteraction"
							@click="handleLimitLoginSelectInteraction"
						>
							<option value="on">{{ $t("On") }}</option>
							<option value="off">{{ $t("Off") }}</option>
						</select>
					</div>
				</template>
			</aio-login-metadata>
		</div>
		<div>
			<aio-login-metadata
				:title="$t('Two Factor Authentication')"
				:description="$t('Two-factor authentication forces admin users to login only after providing a token, generated from the Authenticator applications. When you enable this option, all admin users will be asked to configure their two-factor authentication in the Authenticator app on their next login.')"
				@click="handleTwoFactorCardClick"
			>
				<template v-slot:configuration>
					<div class="aio-login__configuration-switch">
						<div class="">
							<aio-login-toggle
								id="toggle-tfa"
								name="toggle-tfa"
								:disabled="!has_pro || !two_factor_allowed"
								v-on:toggle-input="twoFactorAuthenticationSettings"
								:enabled="two_factor_auth"
							/>
						</div>
					</div>
				</template>
			</aio-login-metadata>
		</div>
		<div>
			<aio-login-metadata
				:title="$t('Block IP Address')"
				:description="$t('By default your WordPress login can be accessed by any IP or user. You can use this feature to allow login only for specific IPs or users in order to prevent brute-force attacks or malicious login attempts.')"
				@click="iWasTriggered"
			>
				<template v-slot:configuration>
					<div class="aio-login__configuration-switch">
						<div class="">
							<aio-login-toggle
								id="toggle-bipa"
								name="toggle-bipa"
								:disabled="! has_pro"
								v-on:toggle-input="blockIPAddressSettings"
								:enabled="block_ip_address"
							/>
						</div>
					</div>
				</template>
			</aio-login-metadata>
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
export default {
	name: 'aio-login-meta',

	data: ( vm ) => ( {
		adminURL: aio_login__app_object.admin_url,

		has_pro: 'true' === aio_login__app_object.has_pro,
		two_factor_allowed: 'true' === aio_login__app_object['2fa_authenticator_allowed'],

		limit_login_attempts: 'off',

		two_factor_auth: false,
		block_ip_address: false,

		snackbar: {
			message: '',
			duration: 3000,
			show: false
		},
	} ),

	watch: {
	},

	methods: {

		iWasTriggered() {
			if ( ! this.has_pro ) {
				this.$parent.$parent.popup = true;
			}
		},

		handleTwoFactorCardClick() {
			if ( ! this.has_pro || ! this.two_factor_allowed ) {
				this.$parent.$parent.popup = true;
			}
		},

		limitLoginAttempts() {
			if ( ! this.has_pro ) {
				this.iWasTriggered();
				return;
			}
			axios.post( 'aio-login/dashboard/update/limit-login-attempts', {
				value: this.limit_login_attempts,
			} )
			.then( response => {
				if ( 'success' === response.data.status	) {
					if ( 'on' === this.limit_login_attempts ) {
						window.location.href = aio_login__app_object.admin_url + '&tab=login-protection#/limit-login-attempts';
					}
				}

				this.snackbar.message = response.data.message;
				this.snackbar.show = true;
			} )
			.catch( error => {

			}  );
		},

		handleCloseSnackbar() {
			this.snackbar.show = false;
		},

		twoFactorAuthenticationSettings( i ) {
			if ( ! this.has_pro || ! this.two_factor_allowed ) {
				this.handleTwoFactorCardClick();
				return;
			}
			var value = i ? 'on' : 'off';

			axios.post( 'aio-login/dashboard/update/two-factor-authentication', {
				value: value
			} )
				.then( response => {
					if ( 'success' === response.data.status	) {
						if ( i ) {
							window.location.href = aio_login__app_object.admin_url + '&tab=2fa#/authentication-methods';
						}
					}

					this.snackbar.message = response.data.message;
					this.snackbar.show = true;
				} )
				.catch( error => {

				}  );
		},

		handleLimitLoginSelectInteraction( event ) {
			if ( this.has_pro ) {
				return;
			}
			if ( event ) {
				event.preventDefault();
				event.stopPropagation();
			}
			this.iWasTriggered();
		},

		blockIPAddressSettings( i ) {
			var value = i ? 'on' : 'off';

			axios.post( 'aio-login/dashboard/update/block-ip-address', {
				value: value
			} )
				.then( response => {
					if ( 'success' === response.data.status	) {
						if ( i ) {
							window.location.href = aio_login__app_object.admin_url + '&tab=security#/block-ip-addresses';
						}
					}

					this.snackbar.message = response.data.message;
					this.snackbar.show = true;
				} )
				.catch( error => {

				}  );
		},


	},

	mounted() {
		axios.get( 'aio-login/dashboard/get-settings' )
			.then( response => {
				this.limit_login_attempts = response.data.limit_login_attempts;
				this.two_factor_auth = 'on' === response.data.two_factor_auth;
				this.block_ip_address = 'on' === response.data.block_ip_address;
			} )
			.catch( error => {} );
	},
}
</script>

<style scoped>
.aio-login__meta-container {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 15px;
}

@media screen and (max-width: 782px) {
	.aio-login__meta-container {
		grid-template-columns: 1fr;
		gap: 12px;
	}
}

.aio-login-configuration-btn-wrapper {
	background: #F7ECFD;
	border-radius: 4px;
	padding: 12px 24px;
}

.aio-login-configuration-btn {
	color: #9516DF;
	line-height: 14px;
	text-decoration: none;
	font-size: 16px;
	font-weight: 600;
}

.aio-login__configuration-select {
	margin-top: 0 !important;
	padding: 12px 16px !important;
	width: 100px !important;
	font-size: 14px !important;
	line-height: 14px !important;
	font-weight: 600 !important;
	color: #9516DF !important;
	border: 1px solid #9516DF !important;
}

.aio-login__configuration-select--locked {
	opacity: 0.6;
	cursor: not-allowed;
}

.aio-login__configuration-switch {
	margin-top: auto;
	align-self: flex-end;
	padding-top: 16px;
	flex-shrink: 0;
}
</style>