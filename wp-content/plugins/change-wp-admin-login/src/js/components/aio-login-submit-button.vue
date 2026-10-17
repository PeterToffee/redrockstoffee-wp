<template>
	<div>
		<p class="submit">
			<button
				type="submit"
				:class="class"
				@click="( e ) => $emit('button-click', e )">
			<slot>{{ $t('Save Changes') }}</slot></button>
		</p>
	</div>
</template>

<script>
export default {
	name: 'aio-login-submit-button',

	props: {
		type: {
			type: String,
			default: 'primary'
		}
	},

	computed: {
		class() {
			return {
				'button': true,
				'aio-login__primary': this.type === 'primary',
				'button-secondary': this.type === 'secondary',
			};
		}
	}
}
</script>

<style scoped>
.aio-login__primary {
	border: #9516df;
	background-color: #9416de;
	color: #fff;
}
.aio-login__primary:hover {
	background-color: #7a12b8;
	border-color: #7a12b8;
	color: #fff;
}
</style>
