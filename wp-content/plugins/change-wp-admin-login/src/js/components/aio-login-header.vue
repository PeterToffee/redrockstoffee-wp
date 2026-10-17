<template>
	<div class="aio-login__header-container">
		<div class="aio-login__header-inner container">
			<div class="aio-login__header-logo">
				<slot name="logo"></slot>
			</div>
			<div class="aio-login__header-end">
				<div class="aio-login__header-actions">
					<slot name="actions"></slot>
				</div>
				<div class="aio-login__header-version">
					<slot name="version"></slot>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
export default {
	name: 'aio-login-header'
}
</script>

<style scoped>
.aio-login__header-container {
	background: #fff;
}

.aio-login__header-inner {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 16px;
	flex-wrap: wrap;
	min-height: 100px;
}

.aio-login__header-logo {
	flex: 0 1 auto;
	min-width: 0;
}

.aio-login__header-end {
	display: flex;
	align-items: center;
	justify-content: flex-end;
	gap: 16px;
	flex-wrap: wrap;
	margin-left: auto;
}

@media screen and (max-width: 782px) {
	.aio-login__header-inner {
		min-height: auto;
		padding: 12px 0;
		align-items: flex-start;
	}

	.aio-login__header-end {
		width: 100%;
		margin-left: 0;
		justify-content: flex-start;
	}
}
</style>
