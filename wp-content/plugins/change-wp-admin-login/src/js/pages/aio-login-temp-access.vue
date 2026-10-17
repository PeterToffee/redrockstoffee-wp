<template>
	<aio-login-temporary-access
		:has-pro="has_pro"
		:temp-access-allowed="temp_access_allowed"
	/>
</template>

<script>
import resolveParentCurrentIsPro from '../resolve-parent-current-is-pro.js';

export default {
	name: 'aio-login-temp-access',

	computed: {
		has_pro() {
			return resolveParentCurrentIsPro(this);
		},
		temp_access_allowed() {
			const o = window.aio_login__app_object;
			if ( ! o || o.temp_access_allowed === undefined ) {
				return null;
			}
			return o.temp_access_allowed === 'true' || o.temp_access_allowed === true;
		},
	},

	methods: {

	}
}
</script>

<style scoped>

</style>