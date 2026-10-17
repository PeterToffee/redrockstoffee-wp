<template>
	<div v-if="page_loaded">
		<aio-login-form :action="nonce" v-on:handle-submit="handleSubmit">
			<template v-slot:title>
				<span>{{ $t("Logo") }}</span>
				<aio-login-tooltip
					:content="tooltipContent.logo.content"
					:title="tooltipContent.logo.title"
					placement="bottom"
				/>
			</template>

			<template v-slot:form-fields>
				<tr>
					<th>
						<label for="logo">{{ $t("Logo") }}</label>
					</th>

					<td>
						<aio-login-media
							id="logo"
							:title="$t('Upload Logo')"
							:default-image="default_image"
							:image="logo_image"
							v-on:image-updated="handelLogoUpload"
							v-on:image-removed="handleRemoveImage"
						/>
					</td>
				</tr>

				<tr>
					<th>
						<label for="logo-redirect-url">{{ $t("Logo Redirect URL") }}</label>
					</th>
					<td>
						<input type="text" class="regular-text" id="logo-redirect-url" v-model="form_data.redirect_url" />
					</td>

				</tr>

				<tr>
					<th>
						<label for="logo-width">{{ $t("Logo Width") }}</label>
					</th>

					<td>
						<input id="logo-width" min="0" max="400" type="number" class="regular-text" v-model="form_data.logo_width" />
					</td>
				</tr>

				<tr>
					<th>
						<label for="logo-height">{{ $t("Logo Height") }}</label>
					</th>

					<td>
						<input id="logo-height" min="0" max="350" type="number" class="regular-text" v-model="form_data.logo_height" />
					</td>
				</tr>

				<tr>
					<th>
						<label for="margin-bottom">{{ $t("Margin Bottom") }}</label>
					</th>

					<td>
						<input max="100" type="number" class="regular-text" id="margin-bottom"  v-model="form_data.margin_bottom"/>
					</td>
				</tr>
			</template>
		</aio-login-form>

		<aio-login-snackbar
			:message="snackbar.message"
			v-if="snackbar.show"
			:duration="snackbar.duration"
			v-on:close="handleCloseSnackbar"
		/>
	</div>
</template>

<script>
import tooltipContent from '../tooltip-content.js';

export default {
	name: 'logo',
	data: ( vm ) => ( {
		tooltipContent,
		page_loaded: false,

		namespace: 'aio-login/logo',

		logo_image: '',
		default_image: '',

		nonce: '',

		form_data: {
			logo_id: '',
			redirect_url: '',
			logo_width: '',
			logo_height: '',
			margin_bottom: '',
		},

		snackbar: {
			message: '',
			show: false,
			duration: 3000,
		}
	} ),

	methods: {
		handelLogoUpload( attachment ) {
			this.form_data.logo_id = attachment.id;
		},

		handleRemoveImage() {
			this.form_data.logo_id = 0;
		},

		handleSubmit( e ) {
			this.form_data['_wpnonce'] = this.nonce;

			axios.post( this.namespace + '/save-settings', this.form_data )
				.then( response => {
					this.snackbar.message = response.data.message;
					this.snackbar.show = true;
				} )
				.catch( error => {

				} );
		},

		handleCloseSnackbar() {
			this.snackbar.show = false;
		}
	},

	mounted() {

		axios.get( this.namespace + '/get-settings' )
			.then( response => {

				this.form_data.logo_height   = response.data.height;
				this.form_data.logo_width    = response.data.width;
				this.form_data.margin_bottom = response.data.margin_bottom;
				this.form_data.redirect_url  = response.data.url;
				this.form_data.logo_id       = response.data.logo_id;
				this.logo_image              = response.data.logo;
				this.default_image           = response.data.default_image;
				this.nonce                   = response.data.nonce;
				this.page_loaded             = true;
			} )
			.catch( response => {

			} );
	}
}
</script>

<style scoped>

</style>