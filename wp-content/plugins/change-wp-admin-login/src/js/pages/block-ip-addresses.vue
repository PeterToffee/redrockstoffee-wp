<template>
	<div>
		<aio-login-block-ip-address
			:has-pro="has_pro"
		/>
	</div>
</template>

<script>
import resolveParentCurrentIsPro from '../resolve-parent-current-is-pro.js';

export default {
	name: 'block-ip-addresses',

	computed: {
		has_pro() {
			return resolveParentCurrentIsPro( this );
		},
	},
}
</script>

<style scoped>

</style>