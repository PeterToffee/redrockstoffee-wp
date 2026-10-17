<template>
	<div :id="'aio-login__days-selector-' + type" :class="container_class">
		<p style="margin-top: 0;" @click="toggleDaysSelector">
			<span>{{ title }}</span>
			<span class="aio-login__selector"></span>
		</p>

		<div
			:id="'days-selector-' + type + '-container'"
			class="aio-login__days-selector container"
			:style="( days_selector ) ? 'display: block;' : 'display: none;'"
		>
			<div>
				<label :for="'today-' + type">
					<input
						type="radio"
						:id="'today-' + type"
						:name="'days-' + type"
						value="today"
						@click="updateDays"
						checked
					>
					{{ $t("Today") }}
				</label>
			</div>
			<div>
				<label :for="'last-7-days-' + type">
					<input
						type="radio"
						:id="'last-7-days-' + type"
						:name="'days-' + type"
						value="last_7_days"
						@click="updateDays"
					>
					{{ $t("Last 7 days") }}
				</label>
			</div>
			<div>
				<label :for="'last-14-days-' + type">
					<input
						type="radio"
						:id="'last-14-days-' + type"
						:name="'days-' + type"
						value="last_14_days"
						@click="updateDays"
					>
					{{ $t("Last 14 days") }}
				</label>
			</div>
			<div>
				<label :for="'last-month-days-' + type">
					<input
						type="radio"
						:id="'last-month-days-' + type"
						:name="'days-' + type"
						value="last_month"
						@click="updateDays"
					>
					{{ $t("Last month") }}
				</label>
			</div>
		</div>
	</div>
</template>

<script>
import { t } from '../i18n.js';

export default {
	name: 'aio-login-days-selector',

	props: {
		type: {
			type: String,
			required: true,
		},
	},

	data: () => ( {
		title: t( 'Today' ),
		days_selector: false,
		days: {
			'last_month': t( 'Last month' ),
			'last_14_days': t( 'Last 14 days' ),
			'last_7_days': t( 'Last 7 days' ),
			'today': t( 'Today' ),
		},
	} ),

	watch: {
		days_selector( value ) {
			if ( value ) {
				document.getElementById( 'days-selector-' + this.type + '-container' ).style.display = 'block';
			} else {
				document.getElementById( 'days-selector-' + this.type + '-container' ).style.display = 'none';
			}
		}
	},

	computed: {
		container_class() {
			return {
				'aio-login__card-selector-container': true,
				'container-success': 'success' === this.type,
				'container-failed': 'failed' === this.type,
				'container-lockouts': 'lockouts' === this.type,
			};
		},
	},

	methods: {
		toggleDaysSelector() {
			this.days_selector = !this.days_selector;
		},

		updateDays( e ) {
			this.title = this.days[ e.target.value ];
			this.$emit( 'update-days', e.target.value, this.type )
			this.days_selector = false;
		},
	},

	mounted() {
		document.addEventListener( 'click', ( event ) => {
			if ( ! event.target.closest( '#aio-login__days-selector-' + this.type ) ) {
				this.days_selector = false;
			}
		} );
	}
}
</script>

<style scoped>
.aio-login__selector {
	display: inline-block;
	margin-left: 5px;
	margin-bottom: 5px;
	width: 7px;
	height: 7px;
	border-top-width: 1px;
	border-top-style: solid;
	border-left-width: 1px;
	border-left-style: solid;
	transform: rotate( 225deg );
}

.aio-login__card-selector-container p {
	font-size: 18px;
	font-weight: 500;
	line-height: 21.6px;
	cursor: pointer;
}

.container-success .aio-login__selector {
	border-color: #6B998F;
}

.container-failed .aio-login__selector {
	border-color: #99876B;
}

.container-lockouts .aio-login__selector {
	border-color: #796B99;
}

.aio-login__days-selector {
	position: absolute;
	background: #fff;
	border: 1px solid #ebebeb;
	box-shadow: 0px 4px 16px 0px #00000014;
	top: 85px;
	left: 23px;
	border-radius: 8px;
	padding-top: 25px !important;
	padding-bottom: 25px !important;
	z-index: 1;
}

.aio-login__days-selector label {
	font-size: 16px;
	font-weight: 500;
	color: #405980;
	line-height: 25px;
}

.aio-login__days-selector input[type="radio"] {
	width: 20px;
	height: 20px;
	border-color: #9516DF;
}

.aio-login__days-selector input[type="radio"]::before {
	background-color: #9516DF;
	width: 12px;
	height: 12px;
}

</style>