<template>
	<div class="aio-login-pro-feature aio-login-2fa-subtabs">
		<div
			class="aio-login-2fa-subtabs__inner"
			:class="{
				'aio-login-pro-overlay': innerLocked,
				'aio-login-2fa-subtabs__inner--locked': innerLocked
			}"
			@click="innerLocked ? handleProFeatureClick() : null"
		>
			<aio-login-two-factor-authentication
				v-if="currentRouteSlug === 'authentication-methods'"
				:key="currentRouteSlug"
				:has-pro="has_pro"
			/>

			<template v-else-if="currentRouteSlug === '2fa-policies'">
				<aio-login-two-factor-policies
					:key="currentRouteSlug"
					:has-pro="has_pro"
				/>
			</template>

			<template v-else-if="currentRouteSlug === 'advanced-settings'">
				<aio-login-two-factor-advanced-settings
					:key="currentRouteSlug"
					:has-pro="has_pro"
				/>
			</template>
		</div>
	</div>
</template>

<script>
export default {
	name: '2fa',

	data: () => ( {
		is_limited_user: window.aio_login__app_object.is_limited_user === 'true',
	} ),

	computed: {
		has_pro() {
			let p = this.$parent;
			while ( p ) {
				if ( 'current_is_pro' in p ) {
					return p.current_is_pro === true || p.current_is_pro === 'true';
				}
				p = p.$parent;
			}
			return false;
		},

		currentRouteSlug() {
			const path = this.$route && this.$route.path ? this.$route.path : '';
			return path.replace( '/', '' ) || 'authentication-methods';
		},

		/** Blur + block interactions on Authentication Methods when license is free (same pattern as other PRO screens). */
		showProOverlay() {
			return ! this.has_pro;
		},

		/** Policies / Advanced (and any plan-locked 2FA sub-tab): show UI under blur, click opens upgrade popup. */
		showSubtabPlanOverlay() {
			if ( this.is_limited_user ) {
				return false;
			}
			return this.isTwoFaSubTabRouteLocked( this.currentRouteSlug );
		},

		innerLocked() {
			return this.showProOverlay || this.showSubtabPlanOverlay;
		},
	},

	mounted() {
		if ( this.is_limited_user && this.currentRouteSlug !== 'authentication-methods' ) {
			this.$router.replace( '/authentication-methods' );
		}
	},

	methods: {
		isTwoFaSubTabRouteLocked( slug ) {
			const raw = typeof window !== 'undefined' && window.aio_login__object && window.aio_login__object.tabs;
			if ( ! raw ) {
				return false;
			}
			const twoFa = raw['2fa'];
			if ( ! twoFa || ! twoFa['sub-tabs'] ) {
				return false;
			}
			const subs = twoFa['sub-tabs'];
			const list = Array.isArray( subs ) ? subs : Object.values( subs );
			const found = list.find( ( st ) => st.slug === slug );
			return !!( found && found['is-pro'] );
		},

		handleProFeatureClick() {
			let p = this.$parent;
			while ( p ) {
				if ( 'popup' in p && typeof p.popup === 'boolean' ) {
					p.popup = true;
					return;
				}
				p = p.$parent;
			}
		},
	},
}
</script>

<style scoped>
.aio-login-2fa-subtabs {
	position: relative;
	min-height: 380px;
}

.aio-login-2fa-subtabs__inner {
	position: relative;
	min-height: inherit;
}

.aio-login-2fa-subtabs__inner--locked::after {
	content: '';
	position: absolute;
	inset: 0;
	background: rgba(255, 255, 255, 0.35);
	backdrop-filter: blur(1px);
	z-index: 20;
	cursor: pointer;
}

.aio-login-2fa-subtabs__inner--locked * {
	user-select: none;
}
</style>
