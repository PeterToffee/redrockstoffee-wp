<template>
	<div
		class="aio-login__snackbar"
		@mouseenter="handleMouseEnter"
		@mouseleave="handleMouseLeave"
	>
		<div class="aio-login__snackbar__content">

			{{ message }}

			<button type="button" @click="handleClose">&times;</button>
		</div>
	</div>
</template>

<script>
export default {
	name: 'aio-login-snackbar',

	props: {
		message: {
			type: String,
			required: true,
		},

		duration: {
			type: Number,
			default: 5000,
		},

		/** Milliseconds to wait after the pointer leaves before dismissing. */
		dismissAfterLeave: {
			type: Number,
			default: 3000,
		},
	},

	data: () => ( {
		disable: false,
		dismissTimer: null,
		isHovered: false,
	} ),

	watch: {
		disable( value ) {
			if ( value ) {
				this.$emit( 'close' );
			}
		},
	},

	methods: {
		clearDismissTimer() {
			if ( this.dismissTimer ) {
				clearTimeout( this.dismissTimer );
				this.dismissTimer = null;
			}
		},

		scheduleDismiss( delayMs ) {
			this.clearDismissTimer();
			const delay = Math.max( 0, Number( delayMs ) || 0 );
			if ( delay <= 0 ) {
				this.disable = true;
				return;
			}
			this.dismissTimer = setTimeout( () => {
				this.dismissTimer = null;
				if ( ! this.isHovered ) {
					this.disable = true;
				}
			}, delay );
		},

		handleClose() {
			this.clearDismissTimer();
			this.disable = true;
		},

		handleMouseEnter() {
			this.isHovered = true;
			this.clearDismissTimer();
		},

		handleMouseLeave() {
			this.isHovered = false;
			this.scheduleDismiss( this.dismissAfterLeave );
		},
	},

	mounted() {
		this.scheduleDismiss( this.duration );
	},

	beforeUnmount() {
		this.clearDismissTimer();
	},
}
</script>

<style scoped>
.aio-login__snackbar {
	position: fixed;
	bottom: 40px;
	right: 40px;
	z-index: 100010;
	background: #9516df;
	color: #fff;
	padding: 10px 20px;
	border-radius: 5px;
}

.aio-login__snackbar__content {
	display: flex;
	justify-content: space-between;
	align-items: center;
}

.aio-login__snackbar__content button {
	background: none;
	border: none;
	color: #fff;
	cursor: pointer;
}
</style>
