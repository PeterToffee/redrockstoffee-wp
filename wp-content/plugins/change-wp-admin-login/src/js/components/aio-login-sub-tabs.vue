<template>
	<div class="aio-login__subtabs-container">
		<div
			class="aio-login__subtabs-scroll-wrap"
			:class="{ 'has-scroll-controls': showScrollControls }"
		>
			<button
				v-show="showScrollControls"
				type="button"
				class="aio-login__scroll-btn aio-login__scroll-btn--prev"
				:disabled="!canScrollLeft"
				:aria-label="$t('Scroll sub-tabs left')"
				@click="scrollSubTabs( -1 )"
			>
				<span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
			</button>
			<div
				ref="subTabsScroll"
				class="aio-login__subtabs-scroll"
				@scroll="updateScrollState"
			>
				<div class="aio-login__subtabs-links" role="tablist">
					<router-link
						v-for="(subTab, index) in subTabs"
						:key="index"
						:to="subTab.slug"
						class="aio-login__subtab"
						active-class="active"
						role="tab"
					>
						<span class="aio-login__subtab-label">{{ subTab.title }}</span>
						<span class="aio-login__pro-tab" v-if="subTab['is-pro'] && ! hasPro">{{ $t("PRO") }}</span>
					</router-link>
				</div>
			</div>
			<button
				v-show="showScrollControls"
				type="button"
				class="aio-login__scroll-btn aio-login__scroll-btn--next"
				:disabled="!canScrollRight"
				:aria-label="$t('Scroll sub-tabs right')"
				@click="scrollSubTabs( 1 )"
			>
				<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
			</button>
		</div>
		<div class="aio-login__subtabs-actions" v-if="$slots.actions">
			<slot name="actions"></slot>
		</div>
	</div>
</template>

<script>
export default {
	name: 'aio-login-sub-tabs',

	props: {
		subTabs: {
			type: Array,
			required: true,
		},
	},

	data: () => ( {
		showScrollControls: false,
		canScrollLeft: false,
		canScrollRight: false,
	} ),

	computed: {
		hasPro() {
			const h = window.aio_login__app_object && window.aio_login__app_object.has_pro;
			return h === 'true' || h === true;
		},
	},

	watch: {
		subTabs: {
			handler() {
				this.$nextTick( () => {
					this.updateScrollState();
					this.scrollActiveSubTabIntoView();
				} );
			},
			deep: true,
		},
		$route() {
			this.$nextTick( () => {
				this.scrollActiveSubTabIntoView();
			} );
		},
	},

	mounted() {
		this.$nextTick( () => {
			this.updateScrollState();
			this.scrollActiveSubTabIntoView();
		} );
		if ( typeof window !== 'undefined' ) {
			window.addEventListener( 'resize', this.onResize, { passive: true } );
		}
	},

	beforeUnmount() {
		if ( typeof window !== 'undefined' ) {
			window.removeEventListener( 'resize', this.onResize );
		}
	},

	methods: {
		onResize() {
			this.updateScrollState();
		},

		getScrollEl() {
			return this.$refs.subTabsScroll || null;
		},

		updateScrollState() {
			const el = this.getScrollEl();
			if ( ! el ) {
				this.showScrollControls = false;
				this.canScrollLeft = false;
				this.canScrollRight = false;
				return;
			}

			const maxScroll = Math.max( 0, el.scrollWidth - el.clientWidth );
			const slop = 2;
			this.showScrollControls = maxScroll > slop;
			this.canScrollLeft = el.scrollLeft > slop;
			this.canScrollRight = el.scrollLeft < maxScroll - slop;
		},

		scrollSubTabs( direction ) {
			const el = this.getScrollEl();
			if ( ! el || ! direction ) {
				return;
			}
			const amount = Math.max( 200, Math.floor( el.clientWidth * 0.65 ) );
			el.scrollBy( {
				left: direction * amount,
				behavior: 'smooth',
			} );
		},

		scrollActiveSubTabIntoView() {
			const el = this.getScrollEl();
			if ( ! el ) {
				return;
			}
			const active = el.querySelector( '.aio-login__subtab.active' );
			if ( active && typeof active.scrollIntoView === 'function' ) {
				active.scrollIntoView( {
					behavior: 'auto',
					block: 'nearest',
					inline: 'nearest',
				} );
			}
			this.updateScrollState();
		},
	},
};
</script>

<style scoped>
.aio-login__subtabs-container {
	min-height: 48px;
	border-bottom: 1px solid #e8e8e8;
	display: flex;
	flex-wrap: nowrap;
	align-items: stretch;
	gap: 0;
	padding-right: 0;
	background: #fff;
}

.aio-login__subtabs-scroll-wrap {
	flex: 1 1 auto;
	min-width: 0;
	display: flex;
	align-items: stretch;
}

.aio-login__subtabs-scroll {
	flex: 1 1 auto;
	min-width: 0;
	overflow-x: auto;
	overflow-y: hidden;
	-webkit-overflow-scrolling: touch;
	scrollbar-width: none;
	-ms-overflow-style: none;
}

.aio-login__subtabs-scroll::-webkit-scrollbar {
	display: none;
	width: 0;
	height: 0;
}

.aio-login__scroll-btn {
	flex: 0 0 auto;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 34px;
	min-height: 48px;
	margin: 0;
	padding: 0;
	border: 0;
	background: #fff;
	color: #9516df;
	cursor: pointer;
	transition: background 0.2s ease, color 0.2s ease, opacity 0.2s ease;
}

.aio-login__scroll-btn:hover:not(:disabled) {
	background: #faf5ff;
	color: #7a12b8;
}

.aio-login__scroll-btn:disabled {
	opacity: 0.35;
	cursor: default;
}

.aio-login__scroll-btn .dashicons {
	width: 20px;
	height: 20px;
	font-size: 20px;
	line-height: 20px;
}

.aio-login__subtabs-links {
	display: inline-flex;
	flex-wrap: nowrap;
	align-items: stretch;
	min-width: min-content;
}

.aio-login__subtabs-actions {
	display: flex;
	align-items: center;
	flex-shrink: 0;
	padding: 0 16px 0 8px;
}

.aio-login__subtab {
	display: inline-flex;
	align-items: center;
	gap: 8px;
	padding: 13px 24px;
	color: #7691b2;
	font-weight: 600;
	font-size: 16px;
	text-decoration: none;
	line-height: 1.25;
	flex-shrink: 0;
	white-space: nowrap;
	border-bottom: 2px solid transparent;
	margin-bottom: -1px;
	box-sizing: border-box;
}

.aio-login__subtab-label {
	line-height: 1.25;
}

.aio-login__subtab.active {
	border-bottom-color: #9516df;
	color: #9516df;
}

.aio-login__pro-tab {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	box-sizing: border-box;
	min-width: 34px;
	padding: 5px 6px;
	margin: 0;
	background: #9516df;
	border-radius: 3px;
	color: #ffffff;
	font-family: Figtree, sans-serif;
	font-size: 12px;
	font-weight: 700;
	line-height: 1;
	letter-spacing: 0.02em;
	text-transform: uppercase;
	flex-shrink: 0;
}

@media screen and (max-width: 960px) {
	.aio-login__subtab {
		padding: 12px 18px;
		font-size: 15px;
	}
}

@media screen and (max-width: 782px) {
	.aio-login__subtabs-container {
		flex-direction: column;
		align-items: stretch;
		min-height: 0;
	}

	.aio-login__subtabs-scroll-wrap {
		width: 100%;
		border-bottom: 1px solid #f0f0f1;
	}

	.aio-login__scroll-btn {
		width: 30px;
		min-height: 44px;
	}

	.aio-login__subtab {
		padding: 11px 14px;
		font-size: 14px;
		gap: 6px;
	}

	.aio-login__subtabs-actions {
		width: 100%;
		justify-content: flex-end;
		padding: 10px 12px;
		box-sizing: border-box;
	}

	.aio-login__pro-tab {
		font-size: 11px;
		min-width: 30px;
		padding: 4px 5px;
	}
}
</style>
