<template>
	<div v-if="page_loaded" class="aio-login-t-wrapper" @click="iWasTriggered">
		<h3>{{ $t('Social Login Settings') }}</h3>
            <div class="aio-login-pro__social-login">
                <div v-for="provider in providers" :key="provider.id" class="aio-login-pro__social-login__card">
                <!-- Top Section -->
                <div class="aio-login-pro__social-login__card__top">
                    <img :src="getSrc( provider.id )" :alt="provider.name" />
                    <p>{{ provider.name }}</p>
                </div>
                <!-- Bottom Section -->
                <div class="aio-login-pro__social-login__card__bottom">
                    <label class="toggle-switch">
                    <aio-login-toggle
                        :id="provider.id"
                        :name="provider.id"
                        v-on:toggle-input="handleToggle"
                        :enabled="provider.enabled"
                    />
                    </label>
                    <button class="configure-btn" @click="configureProvider(provider.id)">
                    {{ $t('Configure') }}
                    </button>
                </div>
                </div>
            </div>
		<!-- Overflow Content for Free Users -->
		<div v-if="!hasPro" class="aio-login-t-content-overflow" @click="iWasTriggered"></div>
	</div>
</template>


<script>
export default {
	name: 'aio-login-social-login',

  slug: 'social-login',

	props: {
		hasPro: {
			type: Boolean,
			default: false,
		},
        providers: {
            type: Array,
            required: true,
            default: () => [
                { id: "google", name: "Google", enabled: false },
                { id: "microsoft", name: "Microsoft", enabled: false },
                { id: "facebook", name: "Facebook", enabled: false },
                { id: "line", name: "Line", enabled: false },
                { id: "github", name: "GitHub", enabled: false },
                { id: "discord", name: "Discord", enabled: false },
                { id: "apple", name: "Apple", enabled: false },
            ],
        },
	},

	data: ( vm ) => ( {
        assetsUrl: aio_login__app_object.assets_url,
		page_loaded: false,
	} ),

	methods: {
		iWasTriggered() {
			this.$parent.$parent.popup = true;
		},

		loadComponent() {
			this.$nextTick( () => {
				this.page_loaded = true;
			} );
		},
        getSrc( icon ) {
				return this.assetsUrl + `images/icons/${ icon }.png`;
		},

		handleToggle( providerId, enabled ) {
			// Trigger popup for free users
			if ( ! this.hasPro ) {
				this.iWasTriggered();
			}
		},

		configureProvider( providerId ) {
			// Trigger popup for free users
			if ( ! this.hasPro ) {
				this.iWasTriggered();
			}
		},
	},

	mounted() {
		if ( ! this.hasPro ) {
			this.page_loaded = true;
		} else {
			this.loadComponent();
		}
	}
}
</script>

<style scoped>
.aio-login-t-wrapper {
	position: relative;
}

.aio-login-t-content-overflow {
	position: absolute;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;
	backdrop-filter: blur(1px);
}

/* Social Login Container */
.aio-login-pro__social-login {
  display: flex;
  flex-wrap: wrap;
  gap: 20px;
}

/* Social Login Card */
.aio-login-pro__social-login__card {
  border: 1px solid #ebe8eb;
  border-radius: 10px;
  height: 200px;
  width: 320px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}

/* Top Section */
.aio-login-pro__social-login__card__top {
  flex: 4;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 10px;
  text-align: center;
}

.aio-login-pro__social-login__card__top img {
  width: 48px;
  height: 48px;
  margin-bottom: 10px;
}

.aio-login-pro__social-login__card__top p {
  font-size: 14px;
  font-weight: bold;
  margin: 0;
}

/* Bottom Section */
.aio-login-pro__social-login__card__bottom {
  flex: 1;
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px;
  border-top: 1px solid #ebe8eb;
}

/* Configure Button */
.configure-btn {
  background: #f7ecfd;
  color: #6e16df;
  border: none;
  border-radius: 4px;
  font-size: 14px;
  cursor: pointer;
  transition: 0.3s;
  padding: 9px 18px;
}

.configure-btn:hover {
  background-color: #0d74c2;
  color: #fff;
}


</style>