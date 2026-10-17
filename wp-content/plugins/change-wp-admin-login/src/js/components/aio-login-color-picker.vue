<template>
	<input type="text" :value="modelValue" @input="$emit('update:modelValue', $event.target.value)" />
</template>

<script>
export default {
	name: 'aio-login-color-picker',

	props: {
		modelValue: {
			type: String,
			default: '',
		}
	},

	emits: ['update:modelValue'],

	watch: {
		modelValue(newVal) {
			if (jQuery(this.$el).data('wp-wpColorPicker')) {
				if (jQuery(this.$el).wpColorPicker('color') !== newVal) {
					jQuery(this.$el).wpColorPicker('color', newVal);
				}
			}
		}
	},

	mounted() {
		if (jQuery) {
			jQuery(this.$el).wpColorPicker({
				change: (event, ui) => {
					const color = ui.color.toString();
					this.$emit('update:modelValue', color);
				},
				clear: () => {
					this.$emit('update:modelValue', '');
				}
			});
		}
	},

	beforeUnmount() {
		if (jQuery(this.$el).data('wp-wpColorPicker')) {
			jQuery(this.$el).wpColorPicker('destroy');
		}
	}
}
</script>

<style scoped>

</style>