<template>
	<div>
		<aio-login-social-login
			:has-pro="has_pro"
			:social-login-premium-providers-allowed="social_login_premium_providers_allowed"
		/>
	</div>
</template>

<script>
import resolveParentCurrentIsPro from '../resolve-parent-current-is-pro.js';

export default {
	name: 'social-login',

	computed: {
		has_pro() {
			return resolveParentCurrentIsPro(this);
		},
		social_login_premium_providers_allowed() {
			const o = window.aio_login__app_object;
			if ( ! o || o.social_login_premium_providers_allowed === undefined ) {
				return null;
			}
			return o.social_login_premium_providers_allowed === 'true' || o.social_login_premium_providers_allowed === true;
		},
	},
}
</script>

<style scoped>

</style>