<template>
	<aio-login-cards class="mb-25"></aio-login-cards>

	<aio-login-meta class="mb-25"></aio-login-meta>

	<aio-login-pro-branding v-if="! has_pro"></aio-login-pro-branding>
</template>

<script>
export default {
	name: 'aio-login-dashboard',
  data: () => ( {
    has_pro: 'true' === aio_login__app_object.has_pro,
  } ),
}
</script>

<style scoped>
</style>