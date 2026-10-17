<template>
	<div>
		<aio-login-disable-common-usernames
			:has-pro="has_pro"
		/>
	</div>
</template>

<script>
import resolveParentCurrentIsPro from '../resolve-parent-current-is-pro.js';

export default {
	name: 'disable-common-usernames',

	computed: {
		has_pro() {
			return resolveParentCurrentIsPro( this );
		},
	},
}
</script>

<style scoped>

</style>