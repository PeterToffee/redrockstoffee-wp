<template>
	<span class="aio-login-tooltip-wrap" ref="wrapRef" @mouseenter="show" @mouseleave="hide">
		<span
			class="aio-login-tooltip-icon"
			:aria-label="$t('Help')"
			role="img"
		>?</span>
		<Teleport to="body">
			<transition name="aio-login-tooltip-fade">
				<div
					v-if="visible"
					class="aio-login-tooltip-modal"
					:class="resolvedPlacement"
					ref="popoverRef"
					:style="popoverStyle"
					@mouseenter="keepVisible"
					@mouseleave="hide"
					role="tooltip"
				>
					<p v-if="title" class="aio-login-tooltip-title">{{ title }}</p>
					<div class="aio-login-tooltip-body" v-html="content"></div>
				</div>
			</transition>
		</Teleport>
	</span>
</template>

<script>
export default {
	name: 'aio-login-tooltip',
	props: {
		content: {
			type: String,
			default: '',
		},
		title: {
			type: String,
			default: '',
		},
		placement: {
			type: String,
			default: 'bottom',
			validator: (v) => ['top', 'bottom', 'left', 'right'].includes(v),
		},
	},
	data() {
		return {
			visible: false,
			hideTimer: null,
			popoverStyle: {},
			resolvedPlacement: 'bottom',
		};
	},
	mounted() {
		this.onReposition = () => {
			if (this.visible) {
				this.positionPopover();
			}
		};
		window.addEventListener('scroll', this.onReposition, true);
		window.addEventListener('resize', this.onReposition);
	},
	beforeUnmount() {
		window.removeEventListener('scroll', this.onReposition, true);
		window.removeEventListener('resize', this.onReposition);
		if (this.hideTimer) {
			clearTimeout(this.hideTimer);
			this.hideTimer = null;
		}
	},
	methods: {
		show() {
			if (this.hideTimer) {
				clearTimeout(this.hideTimer);
				this.hideTimer = null;
			}
			this.visible = true;
			this.$nextTick(() => {
				this.positionPopover();
			});
		},
		keepVisible() {
			if (this.hideTimer) {
				clearTimeout(this.hideTimer);
				this.hideTimer = null;
			}
		},
		hide() {
			this.hideTimer = setTimeout(() => {
				this.visible = false;
				this.hideTimer = null;
			}, 100);
		},
		positionPopover() {
			const wrap = this.$refs.wrapRef;
			const pop = this.$refs.popoverRef;
			if (!wrap || !pop) {
				return;
			}

			const icon = wrap.getBoundingClientRect();
			const gap = 8;
			const margin = 12;
			const vw = window.innerWidth;
			const vh = window.innerHeight;
			const width = Math.min(Math.max(pop.offsetWidth || 220, 180), vw - margin * 2);
			const height = pop.offsetHeight || 80;
			let placement = this.placement || 'bottom';
			let top = 0;
			let left = 0;

			if (placement === 'left' || placement === 'right') {
				top = icon.top + icon.height / 2 - height / 2;
				if (placement === 'right') {
					left = icon.right + gap;
					if (left + width > vw - margin) {
						placement = 'left';
						left = icon.left - gap - width;
					}
				} else {
					left = icon.left - gap - width;
					if (left < margin) {
						placement = 'right';
						left = icon.right + gap;
					}
				}
			} else {
				left = icon.left;
				if (left + width > vw - margin) {
					left = icon.right - width;
				}
				if (left < margin) {
					left = margin;
				}
				if (left + width > vw - margin) {
					left = Math.max(margin, vw - margin - width);
				}

				if (placement === 'top') {
					top = icon.top - gap - height;
					if (top < margin) {
						placement = 'bottom';
						top = icon.bottom + gap;
					}
				} else {
					top = icon.bottom + gap;
					if (top + height > vh - margin && icon.top - gap - height >= margin) {
						placement = 'top';
						top = icon.top - gap - height;
					}
				}
			}

			if (top < margin) {
				top = margin;
			}
			if (top + height > vh - margin) {
				top = Math.max(margin, vh - margin - height);
			}

			this.resolvedPlacement = placement;
			this.popoverStyle = {
				position: 'fixed',
				top: Math.round(top) + 'px',
				left: Math.round(left) + 'px',
				maxWidth: width + 'px',
				zIndex: 100001,
			};
		},
	},
};
</script>

<style scoped>
.aio-login-tooltip-wrap {
	display: inline-flex;
	align-items: center;
	vertical-align: middle;
	margin-left: 6px;
	position: relative;
}
.aio-login-tooltip-icon {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 18px;
	height: 18px;
	border-radius: 50%;
	border: 1px solid #c9d2e3;
	background: #f5f6f9;
	color: #7691b2;
	font-size: 12px;
	font-weight: 600;
	line-height: 1;
	cursor: help;
}
.aio-login-tooltip-icon:hover {
	border-color: #9516df;
	color: #9516df;
	background: #faf5fd;
}
.aio-login-tooltip-modal {
	min-width: 220px;
	max-width: 360px;
	padding: 12px 14px;
	background: #fff;
	border: 1px solid #e8e8e8;
	border-radius: 8px;
	box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12);
	font-family: Figtree, sans-serif;
	font-size: 13px;
	line-height: 1.5;
	color: #333;
	text-align: left;
	pointer-events: auto;
	box-sizing: border-box;
}
.aio-login-tooltip-title {
	margin: 0 0 6px 0;
	font-weight: 600;
	color: #151515;
}
.aio-login-tooltip-body {
	margin: 0;
}
.aio-login-tooltip-body :deep(p) {
	margin: 0 0 6px 0;
}
.aio-login-tooltip-body :deep(p:last-child) {
	margin-bottom: 0;
}
.aio-login-tooltip-fade-enter-active,
.aio-login-tooltip-fade-leave-active {
	transition: opacity 0.15s ease;
}
.aio-login-tooltip-fade-enter-from,
.aio-login-tooltip-fade-leave-to {
	opacity: 0;
}
</style>
