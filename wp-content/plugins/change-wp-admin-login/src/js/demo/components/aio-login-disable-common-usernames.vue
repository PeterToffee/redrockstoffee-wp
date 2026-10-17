<template>
	<div v-if="page_loaded" class="aio-login-dcu-wrapper" @click="iWasTriggered">
		<div>
			<aio-login-form
				:action="nonce"
				v-on:handle-submit="handleSubmit"
			>
				<template v-slot:title>
					<span>{{ $t('Disable Common Usernames') }}</span>
					<aio-login-tooltip
						:content="tooltipContent.disableCommonUsernames.content"
						:title="tooltipContent.disableCommonUsernames.title"
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
									{{ $t('Enable to prevent use of common usernames.') }}
								</strong>
							</p>
						</td>
					</tr>

					<tr v-if="form_data.enabled">
						<th>
							<label for="disable-usernames">{{ $t('Usernames to disable') }}</label>
						</th>
						<td>
							<textarea
								class="regular-text"
								v-model="form_data.usernames"
							></textarea>
						</td>
					</tr>
				</template>
			</aio-login-form>
		</div>
		<div class="aio-login-dcu-content-overflow" v-if="! hasPro"></div>
	</div>
</template>

<script>
import tooltipContent from '../../tooltip-content.js';

export default {
	name: 'aio-login-disable-common-usernames',

	slug: 'disable-common-usernames',

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
			usernames: 'admin, test, user, admin123, test123, username',
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

			axios.get( 'aio-login-pro/disable-common-usernames/get-settings' )
				.then( response => {
					this.form_data.enabled   = response.data.enabled;
					this.form_data.usernames = response.data.usernames;
					this.nonce               = response.data.nonce;
				} )
				.catch( error => {
				} );
		},

		handleSubmit() {
			if ( this.hasPro ) {
				this.form_data['_wpnonce'] = this.nonce;

				axios.post( 'aio-login-pro/disable-common-usernames/save-settings', this.form_data )
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
.aio-login-dcu-wrapper {
	position: relative;
}

.aio-login-dcu-content-overflow {
	position: absolute;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;

	backdrop-filter: blur(1px);
}
</style>