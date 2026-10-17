<template>
	<div
		v-if="page_loaded"
		class="aio-login-magic-link aio-login-2fa-methods"
		:class="{ 'aio-login-pro-feature': !has_pro }"
		@click="!has_pro ? handleProFeatureClick() : null"
	>
		<div class="aio-login-magic-link__panel" :class="{ 'aio-login-magic-link__panel--locked': !has_pro }">
			<div class="aio-login-magic-link__row aio-login-magic-link__row--stacked">
				<div class="aio-login-magic-link__label-col">
					<label class="aio-login-2fa-methods__label aio-login-magic-link__label" for="magic_link_enable">
						{{ $t("Login Link") }}
						<aio-login-tooltip :content="tooltipContent.magicLinkEnable.content" />
					</label>
				</div>
				<div class="aio-login-magic-link__control-col">
					<aio-login-toggle
						id="magic_link_enable"
						name="magic_link_enable"
						:enabled="form.magic_link_enable"
						:disabled="fieldsDisabled"
						v-on:toggle-input="form.magic_link_enable = $event"
					/>
					<p class="aio-login-2fa-methods__help aio-login-magic-link__help">
						{{ $t("Allow users to sign in instantly with a secure one-time link sent to their email.") }}
					</p>
				</div>
			</div>

			<template v-if="form.magic_link_enable">
			<div class="aio-login-magic-link__row">
				<div class="aio-login-magic-link__label-col">
					<label class="aio-login-2fa-methods__label aio-login-magic-link__label" for="magic_link_validity">
						{{ $t("Login Link Validity") }}
						<aio-login-tooltip :content="tooltipContent.magicLinkValidity.content" />
					</label>
				</div>
				<div class="aio-login-magic-link__control-col">
					<div class="aio-login-magic-link__validity">
						<input
							id="magic_link_validity"
							type="number"
							min="1"
							max="999"
							v-model="form.magic_link_validity"
							class="aio-login-2fa-methods__input aio-login-magic-link__validity-input"
							:disabled="fieldsDisabled"
						/>
						<select
							id="magic_link_validity_unit"
							v-model="form.magic_link_validity_unit"
							class="aio-login-2fa-methods__input aio-login-magic-link__validity-unit"
							:disabled="fieldsDisabled"
						>
							<option value="minutes">{{ $t("Minutes") }}</option>
							<option value="hours">{{ $t("Hours") }}</option>
							<option value="days">{{ $t("Days") }}</option>
						</select>
					</div>
				</div>
			</div>

			<div class="aio-login-magic-link__row">
				<div class="aio-login-magic-link__label-col">
					<label class="aio-login-2fa-methods__label aio-login-magic-link__label" for="magic_link_max_requests">
						{{ $t("Login Link Requests") }}
						<aio-login-tooltip :content="tooltipContent.magicLinkRequests.content" />
					</label>
				</div>
				<div class="aio-login-magic-link__control-col">
					<input
						id="magic_link_max_requests"
						type="number"
						min="1"
						max="100"
						v-model="form.magic_link_max_requests"
						class="aio-login-2fa-methods__input aio-login-magic-link__requests-input"
						:disabled="fieldsDisabled"
					/>
				</div>
			</div>

			<div class="aio-login-magic-link__row">
				<div class="aio-login-magic-link__label-col">
					<label class="aio-login-2fa-methods__label aio-login-magic-link__label" for="magic_link_skip_2fa">
						{{ $t("Skip 2FA for Login Link") }}
						<aio-login-tooltip :content="tooltipContent.magicLinkSkip2fa.content" />
					</label>
				</div>
				<div class="aio-login-magic-link__control-col">
					<aio-login-toggle
						id="magic_link_skip_2fa"
						name="magic_link_skip_2fa"
						:enabled="form.magic_link_skip_2fa"
						:disabled="fieldsDisabled"
						v-on:toggle-input="form.magic_link_skip_2fa = $event"
					/>
				</div>
			</div>
			</template>

			<div class="aio-login-2fa-methods__actions aio-login-magic-link__actions">
				<button
					type="button"
					class="button aio-login-otp-login__save-btn"
					:disabled="saving"
					@click.stop="saveSettings"
				>
					{{ saving ? $t('Saving…') : $t('Save Changes') }}
				</button>
			</div>
		</div>

		<aio-login-snackbar
			v-if="snackbar.show"
			:message="snackbar.message"
			:duration="snackbar.duration"
			v-on:close="snackbar.show = false"
		/>
	</div>
</template>

<script>
import tooltipContent from '../tooltip-content.js';

export default {
	name: 'passwordless-login-link',

	data: () => ({
		tooltipContent,
		page_loaded: false,
		saving: false,
		nonce: '',
		api_has_pro: false,
		form: {
			magic_link_enable: false,
			magic_link_validity: '10',
			magic_link_validity_unit: 'minutes',
			magic_link_max_requests: '5',
			magic_link_skip_2fa: true,
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
		fieldsDisabled() {
			return !this.has_pro;
		},
	},

	mounted() {
		this.loadSettings();
	},

	methods: {
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
			axios.get( 'aio-login/magic-link/get-settings' ).then( ( res ) => {
				const d = res.data;
				this.nonce = d.nonce;
				this.api_has_pro = !!d.has_pro;
				this.form.magic_link_enable = !!d.magic_link_enable;
				this.form.magic_link_validity = String( d.magic_link_validity || '10' );
				this.form.magic_link_validity_unit = d.magic_link_validity_unit || 'minutes';
				this.form.magic_link_max_requests = String( d.magic_link_max_requests || '5' );
				this.form.magic_link_skip_2fa = !!d.magic_link_skip_2fa;
				this.page_loaded = true;
			} );
		},
		saveSettings() {
			if ( !this.has_pro ) {
				this.handleProFeatureClick();
				return;
			}
			this.saving = true;
			axios
				.post( 'aio-login/magic-link/save-settings', {
					_wpnonce: this.nonce,
					...this.form,
				} )
				.then( ( res ) => {
					this.saving = false;
					if ( res.data && res.data.success ) {
						this.snackbar.message = res.data.message || 'Saved';
						this.snackbar.show = true;
						try {
							localStorage.removeItem( 'aio_login_woocommerce_settings' );
						} catch ( e ) {
							// Ignore storage errors.
						}
						if ( res.data.data ) {
							this.loadSettings();
						}
					}
				} )
				.catch( ( err ) => {
					this.saving = false;
					this.snackbar.message = err?.response?.data?.message || 'Save failed';
					this.snackbar.show = true;
				} );
		},
	},
};
</script>
