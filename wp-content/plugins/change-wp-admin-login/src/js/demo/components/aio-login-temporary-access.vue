<template>
	<div
		v-if="page_loaded"
		class="aio-login-ta-wrapper"
		@click="iWasTriggered"
	>

		<aio-login-form
			:action="nonce"
			v-on:handle-submit="handleSubmit"
		>
			<template v-slot:title>
				<span>{{ $t('Temporary Access') }}</span>
				<aio-login-tooltip
					:content="tooltipContent.temporaryAccess.content"
					:title="tooltipContent.temporaryAccess.title"
					placement="bottom"
				/>
			</template>

			<template v-slot:description>
				{{ $t('Create a temporary login link that you can share with other people. You can set the link’s lifespan and the maximum usage limit to prevent abuse.') }}
				<br>
				{{ $t('If needed') }}
				<a href="https://wordpress-1080859-4061994.cloudwaysapps.com/wp-admin/user-new.php">{{ $t('create a new WP user') }} </a>
				{{ $t('"guest" instead of using one of the existing user.') }}
			</template>

			<template v-slot:form-fields>
				<tr>
					<th>
						<label for="enable">{{ $t('Enable Temp Access') }}</label>
					</th>
					<td>

						<aio-login-toggle
							id="enable"
							name="enable_temp_access"
							:enabled="form_fields.enabled"

							v-on:toggle-input="e => form_fields.enabled = e"
						/>
						<p class="desc">
							<strong>
								{{ $t('Enable to allow users temporary admin access securely.') }}
							</strong>
						</p>
					</td>
				</tr>
			</template>
		</aio-login-form>

		<div v-if="load_application">

			<aio-login-datatable :headers="[]" :features="[
				{
					hook: 'myToolbar',
					callback: testCallback,
					data: {},
				}
			]"></aio-login-datatable>

		</div>

		<div
			class="aio-login-ta-content-overflow"
			v-if="! hasPro"
		></div>
	</div>
</template>

<script>
import tooltipContent from '../../tooltip-content.js';

export default {
	name: 'aio-login-temporary-access',

	slug: 'temp-access',

	props: {
		hasPro: {
			type: Boolean,
			default: false
		}
	},

	data: ( vm ) => ( {
		tooltipContent,
		nonce: '',
		page_loaded: false,

		form_fields: {
			enabled: false,
		},

		application_loaded: false,

		temp_access_enabled: false,
	} ),

	watch: {
		'form_fields.enabled'( value ) {
			this.temp_access_enabled = value;
		}
	},

	computed: {
		load_application() {
			return this.application_loaded && this.hasPro && this.page_loaded && this.temp_access_enabled;
		}
	},

	methods: {
		iWasTriggered() {
			this.$parent.$parent.popup = true;
		},
		loadComponent() {
			this.$nextTick( () => {
				this.page_loaded = true;
			} );

			axios.get( 'aio-login-pro/temporary-access/get-settings' )
				.then( response => {
					this.form_fields.enabled = response.data.enabled;
					this.nonce = response.data.nonce;

					this.application_loaded = true;
				} )
				.catch( error => {
				} );
		},

		handleSubmit() {
			if ( this.hasPro ) {
				this.form_fields['_wpnonce'] = this.nonce;
				axios.post( 'aio-login-pro/temporary-access/save-settings', this.form_fields )
					.then( response => {
					} )
					.catch( error => {
					} );
			}
		},

		testCallback( s, o ) {
			let container = document.createElement( 'div' );

			let button = document.createElement( 'button' );
			button.innerText = 'Test Button';
			button.addEventListener( 'click', () => {
				alert( 'Button Clicked' );
			} );

			container.appendChild( button );

			return container;
		}
	},

	mounted() {
		if ( ! this.hasPro ) {
			this.page_loaded = true;
		} else {
			this.loadComponent();
		}
	},

}
</script>

<style scoped>
.aio-login-ta-wrapper {
	position: relative;
}

.aio-login-ta-content-overflow {
	position: absolute;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;

	backdrop-filter: blur(1px);
}
</style>