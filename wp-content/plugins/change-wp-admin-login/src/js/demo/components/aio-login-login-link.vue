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
				<span>Login Link</span>
				<aio-login-tooltip
					:content="tooltipContent.temporaryAccess.content"
					:title="tooltipContent.temporaryAccess.title"
					placement="bottom"
				/>
			</template>

			<template v-slot:description>
				Create a passwordless login link that you can share with other people. You can set the link’s lifespan and the maximum usage limit to prevent abuse.
				<br>
				If needed,
				<a :href="add_user_url">create a new WP user</a>
				instead of using one of the existing users.
			</template>

			<template v-slot:form-fields>
				<tr>
					<th>
						<label for="enable-login-link">Enable Login Link</label>
					</th>
					<td>
						<aio-login-toggle
							id="enable-login-link"
							name="enable_temp_access"
							:enabled="form_fields.enabled"
							v-on:toggle-input="e => form_fields.enabled = e"
						/>
						<p class="desc">
							<strong>
								Enable to allow secure passwordless login links.
							</strong>
						</p>
					</td>
				</tr>
			</template>
		</aio-login-form>

		<div v-if="load_application">
			<aio-login-datatable
				:headers="[]"
				:features="[
					{
						hook: 'myToolbar',
						callback: testCallback,
						data: {},
					},
				]"
			></aio-login-datatable>
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
	name: 'aio-login-login-link',

	slug: 'login-link',

	props: {
		hasPro: {
			type: Boolean,
			default: false,
		},
	},

	data: () => ( {
		tooltipContent,
		nonce: '',
		page_loaded: false,
		form_fields: {
			enabled: false,
		},
		application_loaded: false,
		temp_access_enabled: false,
	} ),

	computed: {
		load_application() {
			return this.application_loaded && this.hasPro && this.page_loaded && this.temp_access_enabled;
		},
		add_user_url() {
			if ( window.aio_login__app_object && window.aio_login__app_object.add_new_user ) {
				return window.aio_login__app_object.add_new_user;
			}
			return '/wp-admin/user-new.php';
		},
	},

	watch: {
		'form_fields.enabled'( value ) {
			this.temp_access_enabled = value;
		},
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
				.then( ( response ) => {
					this.form_fields.enabled = response.data.enabled;
					this.nonce = response.data.nonce;
					this.application_loaded = true;
				} )
				.catch( () => {} );
		},
		handleSubmit() {
			if ( this.hasPro ) {
				this.form_fields._wpnonce = this.nonce;
				axios.post( 'aio-login-pro/temporary-access/save-settings', this.form_fields )
					.catch( () => {} );
			}
		},
		testCallback() {
			const container = document.createElement( 'div' );
			const button = document.createElement( 'button' );
			button.innerText = 'Test Button';
			container.appendChild( button );
			return container;
		},
	},

	mounted() {
		if ( ! this.hasPro ) {
			this.page_loaded = true;
		} else {
			this.loadComponent();
		}
	},
};
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
