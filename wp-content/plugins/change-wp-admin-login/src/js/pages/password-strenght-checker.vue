<template>
	<div>
		<aio-login-password-strenght-checker
			:has-pro="has_pro"
		/>
	</div>
</template>

<script>
import resolveParentCurrentIsPro from '../resolve-parent-current-is-pro.js';

export default {
	name: 'password-strenght-checker',

	computed: {
		has_pro() {
			return resolveParentCurrentIsPro( this );
		},
	},
}
</script>

<style scoped>

</style>