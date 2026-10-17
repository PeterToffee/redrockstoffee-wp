<template>
	<div>
		<aio-login-templates
			:has-pro="has_pro"
			:premium-templates-unlocked="templates_premium_plan_unlocked"
		/>
	</div>
</template>

<script>
import resolveParentCurrentIsPro from '../resolve-parent-current-is-pro.js';

export default {
	name: 'templates',

	computed: {
		has_pro() {
			return resolveParentCurrentIsPro( this );
		},
		templates_premium_plan_unlocked() {
			const o = typeof aio_login__app_object !== 'undefined' ? aio_login__app_object : {};
			return o.templates_premium_plan_unlocked === 'true' || o.templates_premium_plan_unlocked === true;
		},
	},
}
</script>

<style scoped>

</style>