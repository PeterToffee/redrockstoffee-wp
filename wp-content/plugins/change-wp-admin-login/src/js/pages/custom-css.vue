<template>
	<div v-if="page_loaded">
		<aio-login-form
			:action="nonce"
			v-on:handle-submit="submitHandler"
		>
			<template v-slot:title>
				<span>{{ $t("Custom CSS") }}</span>
				<aio-login-tooltip
					:content="tooltipContent.customCss.content"
					:title="tooltipContent.customCss.title"
					placement="bottom"
				/>
			</template>

			<template v-slot:form-fields>
				<tr>
					<th>
						<label for="custom-css">{{ $t("Custom CSS") }}</label>
					</th>

					<td>
						<p class="description">
							<b>{{ $t("Enter custom CSS without using &lt;style&gt;&lt;/style&gt;") }}</b>
						</p>

						<textarea class="regular-text" v-model="form_data.custom_css"></textarea>
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
	name: 'custom-css',

	data: () => ( {
		tooltipContent,
		page_loaded: false,

		namespace: 'aio-login/custom-css',

		nonce: '',

		form_data: {
			custom_css: '',
		},

		snackbar: {
			message: '',
			show: false,
			duration: 3000,
		},
	} ),

	methods: {
		submitHandler( e ) {
			this.form_data['_wpnonce'] = this.nonce;

			axios.post( this.namespace + '/save-custom-css-settings', this.form_data )
				.then( response => {
					this.snackbar.message = response.data.message;
					this.snackbar.show   = true;
				} )
				.catch( error => {

				} );
		},

		handleCloseSnackbar() {
			this.snackbar.show = false;
		},
	},

	mounted() {
		axios.get( this.namespace + '/get-settings' )
			.then( response => {

				this.form_data.custom_css = response.data.custom_css;
				this.nonce                = response.data.nonce;
				this.page_loaded          = true;
			} )
			.catch( error => {

			} );
	}
}
</script>

<style scoped>

</style>