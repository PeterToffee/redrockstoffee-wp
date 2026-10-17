<template>
	<div v-if="page_loaded" class="aio-login-lr-wrapper">
		<aio-login-login-redirection />
	</div>
</template>

<script>
export default {
	name: 'login-redirection',

	data: () => ( {
		page_loaded: false,
	} ),

	mounted() {
		this.page_loaded = true;
	},
}
</script>

<style scoped>
.aio-login-lr-wrapper {
	position: relative;
}
</style>
