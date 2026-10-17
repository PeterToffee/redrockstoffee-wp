<template>
	<div>
		<aio-login-elements
			v-if="has_pro"
			:has-pro="has_pro"
		/>
		<aio-login-getpro v-else />
	</div>
</template>

<script>
import resolveParentCurrentIsPro from '../resolve-parent-current-is-pro.js';

export default {
	name: 'elements',

	computed: {
		has_pro() {
			return resolveParentCurrentIsPro( this ) || window.aio_login__app_object.has_pro === 'true' || window.aio_login__app_object.has_pro === true;
		},
	},
}
</script>

<style scoped>

</style>
