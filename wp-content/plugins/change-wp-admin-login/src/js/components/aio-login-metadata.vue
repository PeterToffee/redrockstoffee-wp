<template>
	<div class="aio-login__metadata-container container">
		<h2>{{ title }}</h2>
		<p>{{ description }}</p>
		<slot name="configuration"></slot>
	</div>
</template>

<script>
export default {
	name: 'aio-login-metadata',

	props: {
		title: {
			type: String,
			default: ''
		},
		description: {
			type: String,
			default: ''
		}
	}
}
</script>

<style scoped>
.aio-login__metadata-container {
	display: flex;
	flex-direction: column;
	height: auto;
	min-height: 180px;
	position: relative;
	border: 1px solid #ebebeb;
	background: #fff;
	border-radius: 8px;
	padding-top: 25px !important;
	padding-bottom: 25px !important;
	margin-right: 0;
	margin-bottom: 0;
	box-shadow: 0px 4px 16px 0px #00000014;
}

@media screen and (max-width: 782px) {
	.aio-login__metadata-container {
		min-height: 0;
		padding-top: 20px !important;
		padding-bottom: 20px !important;
	}
}

.aio-login__metadata-container h2 {
	font-size: 18px;
	font-weight: 600;
	line-height: 18px;
	color: #405980;
	flex-shrink: 0;
}

.aio-login__metadata-container p {
	font-size: 14px;
	font-weight: 400;
	line-height: 21px;
	color: #405980;
	flex: 1 1 auto;
	margin-bottom: 0;
}
</style>