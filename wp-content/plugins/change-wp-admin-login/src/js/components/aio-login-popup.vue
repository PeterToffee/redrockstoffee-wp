<template>
	<div class="aio-login__popup-wrapper">
		<div class="aio-login__popup-container" :class="containerClass" :style="container_style">
			<span class="aio-login__popup-close">
				<button :style="'color: ' + ( 'nocontent' === content ? '#ffffff' : '#000000' ) + ';' " @click="e => $emit( 'close-popup' )">&times;</button>
			</span>
			<div class="container" v-if="'content' === content">

				<div v-if="'content' === content" class="aio-login__popup-title">
					<h2>
						<slot name="popup-title"></slot>
					</h2>
				</div>

				<div v-if="'content' === content" class="aio-login__popup-content">
					<slot name="popup-content"></slot>
				</div>

				<div v-if="'content' === content" class="aio-login__popup-footer">
					<slot name="popup-footer"></slot>
				</div>

			</div>
			<div v-if="'nocontent' === content">
				<slot name="default"></slot>
			</div>
		</div>
	</div>
</template>

<script>
export default {
	name: 'aio-login-popup',

	props: {
		width: {
			type: String,
			default: '500px'
		},

		height: {
			type: String,
			default: '500px'
		},

		content: {
			type: String,
			default: 'content'
		},

		style: {
			type: Object,
			default: () => {}
		},

		/** Extra class on the modal box (e.g. per-screen styling from Pro). */
		containerClass: {
			type: String,
			default: '',
		},
	},

	computed: {
		container_style() {
			return {
				width: this.width,
				height: this.height
			};
		}
	}
}
</script>

<style scoped>
.aio-login__popup-wrapper {
	position: fixed;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;
	background-color: rgba( 0, 0, 0, 0.5);
	/* Above login-redirection rule modal (.aio-login-lr-modal-backdrop uses 99999). */
	z-index: 100000;
	backdrop-filter: blur( 1px );
	display: flex;
	justify-content: center;
	align-items: center;
}

.aio-login__popup-container {
	width: 500px;
	height: 500px;
	background-color: #fff;
	box-shadow: 0 0 10px rgba( 0, 0, 0, 0.1 );
	position: relative;
	border: 1px solid #9416de;
}

.aio-login__popup-close {
	position: absolute;
	top: 10px;
	right: 10px;
	z-index: 40;
}

.aio-login__popup-close button {
	background: none;
    border: none;
    font-size: 30px;
    font-weight: 700;
    color: #6e16df !important;
    cursor: pointer;
}

.aio-login__popup-title {
	height: 33px;
}

.aio-login__popup-content {
	/* height: 400px; */
}

.aio-login__popup-footer {
	height: 50px;
}

</style>