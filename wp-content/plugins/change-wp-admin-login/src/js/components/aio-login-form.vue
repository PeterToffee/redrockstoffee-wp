<template>
	<div>
		<form :action="ajaxurl" method="post" @submit="submitHandler">
			<h2>
				<slot name="title"></slot>
			</h2>
			<p class="description">
				<strong>
					<slot name="description"></slot>
				</strong>
			</p>
			<input type="hidden" name="_wpnonce" :value="action">

			<table class="form-table">
				<tbody>
					<slot name="form-fields"></slot>
				</tbody>
			</table>

			<aio-login-submit-button />
		</form>
	</div>
</template>

<script>
export default {
	name: 'aio-login-form',
	props: {
		action: {
			type: String,
			required: true,
		},
	},

	data: () => ( {
		ajaxurl: ajaxurl,
	} ),

	methods: {
		submitHandler( e ) {
			e.preventDefault();
			this.$emit( 'handle-submit', e );
		},
	},

	mounted() {

	},
}
</script>

<style scoped>

</style>