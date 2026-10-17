<template>
	<div v-if="page_loaded" class="aio-login-psc-wrapper" @click="iWasTriggered">
		<div>
			<aio-login-form
				:action="nonce"
				v-on:handle-submit="handleSubmit"
			>
				<template v-slot:title>
					<span>{{ $t('Password Strength Checker') }}</span>
					<aio-login-tooltip
						:content="tooltipContent.passwordStrengthChecker.content"
						:title="tooltipContent.passwordStrengthChecker.title"
						placement="bottom"
					/>
				</template>

				<template v-slot:form-fields>
					<tr>
						<th>
							<label for="enable">{{ $t('Enable') }}</label>
						</th>
						<td>
							<aio-login-toggle
								id="enable"
								name="enable"
								:enabled="form_data.enabled"
								v-on:toggle-input="toggleInput"
							/>
							<p class="desc">
								<strong>
									{{ $t('Enable to set rules to improve user password security.') }}
								</strong>
							</p>
						</td>
					</tr>
				</template>
			</aio-login-form>
		</div>
		<div class="aio-login-psc-content-overflow" v-if="! hasPro"></div>
	</div>
</template>

<script>
import tooltipContent from '../../tooltip-content.js';

export default {
	name: 'aio-login-password-strenght-checker',

	slug: 'password-strenght-checker',

	props: {
		hasPro: {
			type: Boolean,
			default: false,
		},
	},

	data: ( vm ) => ( {
		tooltipContent,
		nonce: '',
		form_data: {
			enabled: false,
		},

		page_loaded: false,
	} ),

	methods: {
		iWasTriggered() {
			this.$parent.$parent.$parent.popup = true;
		},
		toggleInput( value ) {
			this.form_data.enabled = value;
		},

		loadComponent() {
			this.$nextTick( () => {
				this.page_loaded = true;
			} );

			axios.get( 'aio-login-pro/password-strenght-checker/get-settings' )
				.then( response => {
					this.form_data.enabled   = response.data.enabled;
					this.nonce               = response.data.nonce;
				} )
				.catch( error => {
				} );
		},

		handleSubmit() {
			if ( this.hasPro ) {
				this.form_data['_wpnonce'] = this.nonce;

				axios.post( 'aio-login-pro/password-strenght-checker/save-settings', this.form_data )
				.then( response => {
				} )
				.catch( error => {
				} );
			}
		}
	},

	mounted() {
		if ( ! this.hasPro ) {
			this.page_loaded = true;
		} else {
			this.loadComponent();
		}
	}
}
</script>

<style scoped>
.aio-login-psc-wrapper {
	position: relative;
}

.aio-login-psc-content-overflow {
	position: absolute;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;

	backdrop-filter: blur(1px);
}
</style>