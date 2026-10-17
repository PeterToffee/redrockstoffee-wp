<template>
	<div class="aio-login__toggle-switch-wrapper">
		<input
			class="aio-login__toggle-field"
			type="checkbox"
			:id="id"
			:name="name"
			value="1"
			:checked="enabled"
			:disabled="disabled"
			@click="handelEvent"
		/>
		<label
			class="aio-login__toggle-switch"
			:for="id"
		>
			<span class="aio-login__toggle-indicator"></span>
		</label>
	</div>
</template>

<script>
export default {
	name: 'aio-login-toggle',

	props: {
		id: {
			type: String,
			required: true,
		},
		name: {
			type: String,
			required: true,
		},
		enabled: {
			type: Boolean,
			default: false,
		},

		disabled: {
			type: Boolean,
			default: false,
		},
	},

	data: ( vm ) => ( {} ),

	methods: {
		handelEvent( e ) {
			this.$emit( 'toggle-input', e.target.checked );
		}
	}
}
</script>

<style scoped>
.aio-login__toggle-switch-wrapper {
	width: 48px;
	height: 24px;
	border: 1px solid #9516DF;
	border-radius: 50px;
}

.aio-login__toggle-switch {
	width: 100%;
	height: 100%;
	display: block;
	border-radius: 50px;
	background: #F7ECFD;
	position: relative;
	left: 0;
	transition: left 0.2s ease-in-out;
}

.aio-login__toggle-indicator {
	width: 20px;
	height: 20px;
	border-radius: 50px;
	background: #9516DF;
	position: absolute;
	top: 2px;
	left: 2px;
	transition: left 0.2s ease-in-out;
}

.aio-login__toggle-field:checked + .aio-login__toggle-switch {
	background: #9516DF;
	transition: all 0.2s ease-in-out;
}

.aio-login__toggle-field:checked + .aio-login__toggle-switch .aio-login__toggle-indicator {
	left: 26px;
	background-color: #F7ECFD;
	transition: all 0.2s ease-in-out;
}

.aio-login__toggle-field {
	display: none;
}

.aio-login__toggle-field:disabled + .aio-login__toggle-switch {
	opacity: 0.5;
}
</style>