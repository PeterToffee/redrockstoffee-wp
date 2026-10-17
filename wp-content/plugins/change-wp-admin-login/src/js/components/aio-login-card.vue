<template>
	<div :class="container_class">
		<div v-if="'customize' === type">
			<div class="aio-login__card-content">
				<p>
					{{ $t("Customize your") }}
					<br>
					{{ $t("WP Login") }}
				</p>
				<p class="mt-40">
					<a :href="adminURL">{{ $t("Lets Go") }}</a>
				</p>
			</div>
		</div>
		<div v-else>
			<h3 style="margin-bottom: 0px;">
				{{ title }}
			</h3>
			<aio-login-days-selector
				:type="type"
				v-on:update-days="updateDays"
			></aio-login-days-selector>

			<h2>{{ count }}</h2>
		</div>
	</div>
</template>

<script>
export default {
	name: 'aio-login-card',
	props: {
		type: {
			type: String,
			required: true
		},
		title: {
			type: String,
			default: ''
		}
	},

	data: () => ( {
		count: 0,

		adminURL: ( typeof aio_login__app_object.login_customizer_url === 'string' && aio_login__app_object.login_customizer_url )
			? aio_login__app_object.login_customizer_url
			: aio_login__app_object.admin_url,
	} ),

	computed: {
		container_class() {
			return {
				'aio-login__card-container': true,
				'container': true,
				'container-success': 'success' === this.type,
				'container-failed': 'failed' === this.type,
				'container-lockouts': 'lockouts' === this.type,
				'container-customize': 'customize' === this.type
			};
		}
	},

	methods: {
		updateDays( days, type ) {
			this.getCount( type, days );
		},

		getCount( type, duration ) {
			axios.get( 'aio-login/dashboard/get-counts', {
				params: {
					type: type,
					duration: duration
				},
			} )
			.then( response => {
				this.count = response.data.count;
			} )
			.catch( error => {

			} );
		},
	},

	mounted() {
		if ( 'customize' !== this.type ) {
			this.getCount( this.type, 'today' )
		}
	},
}
</script>

<style scoped>
.aio-login__card-container {
	display: flex;
	flex-direction: column;
	border-radius: 8px;
	height: auto;
	min-height: 160px;
	position: relative;
	padding-top: 25px !important;
	padding-bottom: 25px !important;
	margin-right: 0;
}

@media screen and (max-width: 782px) {
	.aio-login__card-container {
		min-height: 140px;
		padding-top: 20px !important;
		padding-bottom: 20px !important;
	}
}

.aio-login__card-container h3 {
	font-size: 18px;
	font-weight: 600;
	line-height: 21.6px;
}

.aio-login__card-container h2 {
	font-size: 32px;
	font-weight: 600;
	margin-top: auto;
	align-self: flex-end;
}

.container-success {
	color: #6B998F;
	background: #ECFAF7;
}

.container-success h3,
.container-success h2 {
	color: #6B998F;
}

.container-failed {
	color: #99876B;
	background: #FAF4EB;
}

.container-failed h3,
.container-failed h2 {
	color: #99876B;
}

.container-lockouts {
	color: #796B99;
	background: #EEEBFA;
}

.container-lockouts h3,
.container-lockouts h2 {
	color: #796B99;
}

.container-customize {
	background-image: url( "../../images/magic-wand-larg.png" ), url( "../../images/magic-wand-small.png" ), linear-gradient( 180deg, #00cc99 0%, #00b386 100% );
	background-repeat: no-repeat;
	background-position: top left, bottom right;
	color: #fff;
}

.aio-login__card-content {
	text-align: center;
}

.aio-login__card-content p {
	margin-top: 35px;
	margin-bottom: 20px;
	font-size: 18px;
	font-weight: 600;
	color: #fff;
	line-height: 21.6px;
}

.aio-login__card-content a {
	color: #fff;
	border: 1px solid #00A27A;
	background: #00AD82;
	box-shadow: 0 4px 16px 0 #00cd9a;

	text-decoration: none;

	font-size: 16px;
	font-weight: 600;
	padding: 12px 24px;
	border-radius: 4px;
}
</style>