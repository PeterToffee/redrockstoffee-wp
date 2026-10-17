<template>
	<nav
		class="aio-login__tabs-nav"
		:class="{ 'has-scroll-controls': showScrollControls }"
		:aria-label="$t('AIO Login sections')"
	>
		<button
			v-show="showScrollControls"
			type="button"
			class="aio-login__scroll-btn aio-login__scroll-btn--prev"
			:disabled="!canScrollLeft"
			:aria-label="$t('Scroll tabs left')"
			@click="scrollTabs( -1 )"
		>
			<span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
		</button>
		<div
			ref="tabsScroll"
			class="aio-login__tabs-scroll"
			@scroll="updateScrollState"
		>
			<div class="aio-login__tabs-track">
				<a
					v-for="( tab, t ) in tabs"
					:href="getHref( tab )"
					:class="getClasses( tab )"
					:key="t"
				>
					<img
						v-if="'getpro' !== tab.slug"
						class="aio-login__tab-icon"
						:class="{ 'aio-login__tab-icon--custom': isCustomSvgIcon( tab ) }"
						alt=""
						:src="getSrc( tab )"
					/>
					<span class="aio-login__tab-label">{{ tab.title }}</span>
					<img
						v-if="'getpro' === tab.slug"
						class="aio-login__getpro-crown"
						alt=""
						:src="assetsUrl + 'images/pro-crown.svg'"
					/>
					<span class="aio-login__pro-tab" v-if="tab['is-pro'] && ! hasPro">{{ $t("PRO") }}</span>
				</a>
			</div>
		</div>
		<button
			v-show="showScrollControls"
			type="button"
			class="aio-login__scroll-btn aio-login__scroll-btn--next"
			:disabled="!canScrollRight"
			:aria-label="$t('Scroll tabs right')"
			@click="scrollTabs( 1 )"
		>
			<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
		</button>
	</nav>
</template>

<script>
export default {
	name: 'aio-login-tabs',
	props: {
		tabs: {
			type: Array,
			required: true,
		},
		assetsUrl: {
			type: String,
			required: true,
		},
	},

	data: () => ( {
		test_tab: {},
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
		test_tab() {
			if ( this.$parent && typeof this.$parent.syncCurrentTabAccess === 'function' ) {
				this.$parent.syncCurrentTabAccess();
			}
		},
		tabs: {
			handler() {
				this.$nextTick( () => {
					this.updateScrollState();
					this.scrollActiveTabIntoView();
				} );
			},
			deep: true,
		},
	},

	mounted() {
		this.$nextTick( () => {
			this.updateScrollState();
			this.scrollActiveTabIntoView();
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
			return this.$refs.tabsScroll || null;
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

		scrollTabs( direction ) {
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

		scrollActiveTabIntoView() {
			const el = this.getScrollEl();
			if ( ! el ) {
				return;
			}
			const active = el.querySelector( '.aio-login__link-wrapper.active' );
			if ( active && typeof active.scrollIntoView === 'function' ) {
				active.scrollIntoView( {
					behavior: 'auto',
					block: 'nearest',
					inline: 'nearest',
				} );
			}
			this.updateScrollState();
		},

		getHref( tab ) {
			var location = window.aio_login__app_object.admin_url;
			return location + '&tab=' + tab.slug;
		},

		activeTab( tab ) {
			var location = window.location.href;
			var url = new URL( location );
			if ( ! url.searchParams.get( 'tab' ) ) {
				return tab.slug === 'dashboard';
			}
			return url.searchParams.get( 'tab' ) === tab.slug;
		},

		getClasses( tab ) {
			if ( this.activeTab( tab ) ) {
				this.test_tab = tab;
			}
			return {
				active: this.activeTab( tab ),
				'aio-login__link-wrapper': true,
				getpro: 'getpro' === tab.slug,
			};
		},

		getSrc( tab ) {
			if ( this.isCustomSvgIcon( tab ) ) {
				const suffix = this.activeTab( tab ) ? '' : '-inactive';
				return this.assetsUrl + 'images/' + tab.icon + suffix + '.svg';
			}
			if ( 'social-login' === tab.slug || 'integrations' === tab.slug ) {
				return this.assetsUrl + `images/icons/${ tab.icon }${ this.activeTab( tab ) ? '-active' : '' }.svg`;
			}
			return this.assetsUrl + `images/icons/${ tab.icon }${ this.activeTab( tab ) ? '-active' : '' }.png`;
		},

		isCustomSvgIcon( tab ) {
			return '2fa-icon' === tab.icon || 'passwordless-authetication-icon' === tab.icon;
		},
	},
};
</script>

<style scoped>
.aio-login__tabs-nav {
	width: 100%;
	max-width: 100%;
	display: flex;
	align-items: flex-end;
	gap: 0;
}

.aio-login__tabs-scroll {
	flex: 1 1 auto;
	min-width: 0;
	overflow-x: auto;
	overflow-y: hidden;
	-webkit-overflow-scrolling: touch;
	scrollbar-width: none;
	-ms-overflow-style: none;
}

.aio-login__tabs-scroll::-webkit-scrollbar {
	display: none;
	width: 0;
	height: 0;
}

.aio-login__scroll-btn {
	flex: 0 0 auto;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 36px;
	height: 48px;
	margin: 0;
	padding: 0;
	border: 0;
	border-radius: 4px 4px 0 0;
	background: #f8f8f8;
	color: #9516df;
	cursor: pointer;
	transition: background 0.2s ease, color 0.2s ease, opacity 0.2s ease;
}

.aio-login__scroll-btn:hover:not(:disabled) {
	background: #f3e8ff;
	color: #7a12b8;
}

.aio-login__scroll-btn:disabled {
	opacity: 0.35;
	cursor: default;
}

.aio-login__scroll-btn .dashicons {
	width: 22px;
	height: 22px;
	font-size: 22px;
	line-height: 22px;
}

.aio-login__tabs-track {
	display: inline-flex;
	flex-wrap: nowrap;
	align-items: flex-end;
	min-width: min-content;
}

.aio-login__link-wrapper {
	padding: 14px 20px;
	color: #7691b2;
	font-weight: 600;
	font-size: 16px;
	line-height: 1.25;
	text-decoration: none;
	background: #f8f8f8;
	display: inline-flex;
	align-items: center;
	gap: 8px;
	margin-right: 2px;
	border-radius: 4px 4px 0 0;
	vertical-align: bottom;
	flex-shrink: 0;
	white-space: nowrap;
}

.aio-login__tab-label {
	line-height: 1.25;
}

.aio-login__tab-icon {
	display: block;
	flex-shrink: 0;
	align-self: center;
	max-height: 24px;
	width: auto;
	height: auto;
	object-fit: contain;
}

.aio-login__tab-icon--custom {
	width: 24px;
	height: 24px;
	max-height: 24px;
}

.aio-login__link-wrapper.active {
	color: #9516df;
	background: #ffffff;
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

.aio-login__link-wrapper.getpro {
	background-image: linear-gradient(180deg, #9516df 0%, #510c79 100%);
	color: #fff;
	gap: 6px;
	font-weight: 700;
}

.aio-login__getpro-crown {
	width: 17px;
	height: 12px;
	object-fit: contain;
	flex-shrink: 0;
}

@media screen and (max-width: 960px) {
	.aio-login__link-wrapper {
		padding: 12px 16px;
		font-size: 15px;
	}
}

@media screen and (max-width: 782px) {
	.aio-login__scroll-btn {
		width: 32px;
		height: 44px;
	}

	.aio-login__link-wrapper {
		padding: 11px 14px;
		font-size: 14px;
		gap: 6px;
	}

	.aio-login__tab-icon {
		max-height: 20px;
	}

	.aio-login__pro-tab {
		font-size: 11px;
		min-width: 30px;
		padding: 4px 5px;
	}
}
</style>
