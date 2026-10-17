<template>
	<div class="aio-login__pro-branding-container">
		<div class="aio-login__pro-flex">
			<div>
				<h2>{{ $t("Get the Most Powerful WordPress Login Plugin Today") }}</h2>
				<p>
					{{ $t("Join over 90K smart website owners who use AIO Login to improve their") }}
					<br />
					{{ $t("WordPress admin login security and customization") }}
				</p>
			</div>
			<div class="aio-login__button-wrapper">
				<a
					href="https://aiologin.com/pricing/?utm_source=plugin&utm_medium=dashboard_pro_banner&utm_campaign=plugin"
				>{{ $t("Get AIO Login Pro") }}</a>
			</div>
		</div>
	</div>
</template>

<script>
export default {
	name: 'aio-login-pro-branding'
}
</script>

<style scoped>
.aio-login__pro-branding-container {
	height: 238px;
	background-color: #630f95;
	background-image: url("../../images/colored-lock-left.png"), url("../../images/colored-lock-right.png");
	background-position: top left, bottom right;
	background-repeat: no-repeat;
	border-radius: 8px;
	padding: 0 65px 0 82px;
}

.aio-login__pro-flex {
	display: flex;
	justify-content: space-between;
	align-items: center;
	width: 100%;
	height: 100%;
}

.aio-login__pro-branding-container h2, .aio-login__pro-branding-container p {
	color: #fff;
}

.aio-login__pro-branding-container h2 {
	font-weight: 600;
	font-size: 24px;
	line-height: 24px;
}

.aio-login__pro-branding-container p {
	font-weight: 400;
	font-size: 18px;
	line-height: 27px;
}

.aio-login__pro-branding-container a {
	font-weight: 600;
	font-size: 18px;
	line-height: 18px;
	color: #630F95;
	text-decoration: none;
}

.aio-login__button-wrapper {
	border-radius: 8px;
	padding: 16px 32px;
	margin-top: 20px;
	background: #FFBA08;
	flex-shrink: 0;
}

@media screen and (max-width: 960px) {
	.aio-login__pro-branding-container {
		height: auto;
		padding: 28px 24px;
		background-image: none;
	}

	.aio-login__pro-flex {
		flex-direction: column;
		align-items: flex-start;
		gap: 20px;
		height: auto;
	}

	.aio-login__pro-branding-container p br {
		display: none;
	}

	.aio-login__button-wrapper {
		margin-top: 0;
		width: 100%;
		box-sizing: border-box;
		text-align: center;
	}
}
</style>