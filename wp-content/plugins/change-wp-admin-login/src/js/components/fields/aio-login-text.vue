<template>
		<input
			v-if="'textarea' !== type"
			class="regular-text"
			:id="id"
			:name="name"
			:value="modelValue"
			:type="type"
			:placeholder="placeholder"
			:readonly="readonly"
			:disabled="disabled"
			@input="$emit( 'update:modelValue', $event.target.value ); $emit( 'input', $event.target.value )"
		>

		<textarea
			v-else
			class="regular-text"
			:id="id"
			:name="name"
			:placeholder="placeholder"
			:readonly="readonly"
			:disabled="disabled"
		>{{ modelValue }}</textarea>
</template>

<script>
export default {
	name: 'aio-login-text',

	props: {
		type: {
			type: String,
			default: 'text',
		},
		id: {
			type: String,
			required: true,
		},
		name: {
			type: String,
			required: true,
		},
		modelValue: {
			type: [String, Number],
			default: '',
		},
		readonly: {
			type: Boolean,
			default: false,
		},
		disabled: {
			type: Boolean,
			default: false,
		},
		placeholder: {
			type: String,
			default: '',
		},
	},
}
</script>

<style scoped>

</style>