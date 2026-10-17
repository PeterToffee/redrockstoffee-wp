<template>
	<div v-if="page_loaded" class="aio-login-lla-wrapper">
		<div>
		<aio-login-form
			:action="nonce"
			v-on:handle-submit="handleSubmit"
		>
			<template v-slot:title>
				<span>{{ $t("Limit Login Attempts") }}</span>
				<aio-login-tooltip
					:content="tooltipContent.limitLoginAttempts.content"
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
							name="aio_login__lla_enable"
							:enabled="form_data.enabled"
							v-on:toggle-input="handleValue"
						/>

						<p class="desc">
							<strong>{{ $t("Enable to restrict repeated login attempts per IP.") }}</strong>
						</p>
					</td>
				</tr>

				<tr v-if="form_data.enabled">
					<th scope="row">
						<label for="maximum-attempts">{{ $t("Maximum Attempts") }}</label>
					</th>

					<td>
						<input
							id="maximum-attempts"
					        name="aio_login_limit_attempts_maximum_attempts"
					        v-model="form_data.maximum_attempts"
					        type="number"
							min="1"
							class="regular-text"
						/>
					</td>
				</tr>

				<tr v-if="form_data.enabled">
					<th scope="row">
						<label for="timeout">{{ $t("Timeout (min)") }}</label>
					</th>

					<td>
						<input
							id="timeout"
							name="aio_login_limit_attempts_timeout"
							v-model="form_data.timeout"
							type="number"
							min="1"
							class="regular-text"
						/>
					</td>
				</tr>

				<tr v-if="form_data.enabled">
					<th scope="row">
						<label for="lockout-message">{{ $t("Lockout Message") }}</label>
					</th>

					<td>
						<textarea
							id="lockout-message"
							name="aio_login_limit_attempts_lockout_message"
							v-model="form_data.lockout_message"
							class="regular-text"
						></textarea>
					</td>
				</tr>
			</template>
		</aio-login-form>

		<aio-login-snackbar
			:message="snackbar.message"
			v-if="snackbar.show"
			:duration="snackbar.timeout"
			v-on:close="handleSnackbarClose"
		/>
		</div>

		<div
			v-if="!has_pro"
			class="aio-login-lla-content-overflow"
			role="presentation"
			@click="openParentProPopup"
		></div>
	</div>
</template>

<script>
import tooltipContent from '../tooltip-content.js';
import resolveParentCurrentIsPro from '../resolve-parent-current-is-pro.js';
import { t } from '../i18n.js';

export default {
	name: 'limit-login-attempts',

	computed: {
		has_pro() {
			return resolveParentCurrentIsPro( this );
		},
	},

	data: ( vm ) => ( {
		tooltipContent,
		page_loaded: false,

		nonce: '',

		form_data: {
			enabled: false,
			maximum_attempts: '5',
			timeout: '5',
			lockout_message: t( 'You have been blocked due to too many unsuccessful login attempts. Please try again in %d minutes.' ),
		},

		snackbar: {
			message: '',
			show: false,
			timeout: 3000,
		},

		namespace: 'aio-login/limit-login-attempts',
	} ),

	methods: {
		openParentProPopup() {
			let p = this.$parent;
			while ( p ) {
				if ( typeof p.openProPopup === 'function' ) {
					p.openProPopup();
					return;
				}
				p = p.$parent;
			}
		},

		handleValue( value ) {
			this.form_data.enabled = value;
		},

		handleSubmit( e ) {
			if ( ! this.has_pro ) {
				return;
			}

			axios.post( this.namespace + '/save-settings', {
				enabled: this.form_data.enabled,
				maximum_attempts: this.form_data.maximum_attempts,
				timeout: this.form_data.timeout,
				lockout_message: this.form_data.lockout_message,
				_wpnonce: this.nonce,
			} )
				.then( response => {

					this.snackbar.message = response.data.message;
					this.snackbar.show = true;

				} )
				.catch( error => {

				} );
		},

		handleSnackbarClose() {
			this.snackbar.show = false;
		},

		loadSettings() {
			axios.get( this.namespace + '/get-settings' )
				.then( response => {
					this.form_data.enabled          = response.data.enabled;
					this.form_data.maximum_attempts = response.data.maximum_attempts;
					this.form_data.timeout          = response.data.timeout;
					this.form_data.lockout_message  = response.data.lockout_message;
					this.nonce                      = response.data.nonce;
					this.page_loaded                = true;
				} )
				.catch( error => {
					this.page_loaded = true;
				} );
		},
	},

	mounted() {
		this.$nextTick( () => {
			if ( this.has_pro ) {
				this.loadSettings();
			} else {
				this.page_loaded = true;
			}
		} );
	},
}
</script>

<style scoped>
.aio-login-lla-wrapper {
	position: relative;
}

.aio-login-lla-content-overflow {
	position: absolute;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;
	z-index: 10;
	cursor: pointer;
	backdrop-filter: blur( 1px );
}
</style>