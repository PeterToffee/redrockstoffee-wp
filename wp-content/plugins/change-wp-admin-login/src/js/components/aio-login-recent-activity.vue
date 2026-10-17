<template>
	<div>
		<div class="aio-login__recent-activity-wrapper">
			<h2>
				<span>{{ $t("Recent Activity") }}</span>
				<aio-login-tooltip
					:content="tooltipContent.recentActivity.content"
					:title="tooltipContent.recentActivity.title"
					placement="bottom"
				/>
			</h2>

			<div class="aio-login__table-wrapper">
				<div class="aio-login-table-nav">
					<div
						v-for="(tab, i ) in tabs"
						:class="{'aio-login__table-nav-item': true, 'active': tab.active }"
					>
						<a
							class="aio-login__activate"
							:href="'#' + tab.slug"
							@click="e => toggleShift( e, tab )"
						>{{ tab.title }}</a>
					</div>
				</div>

				<div class="aio-login__table-scroll" v-if="'lockouts' === current_tab">
					<table class="aio-login__table">
						<thead>
						<tr>
							<th v-for="lockout in lockout_headers">
								{{ lockout.title }}
							</th>
						</tr>
						</thead>

						<tr v-if="lockouts.length" v-for="lockout in lockouts">
							<td v-for="lockout_header in lockout_headers">
								{{ lockout[ lockout_header.value ] }}
							</td>
						</tr>
						<tr v-else>
							<td colspan="5">{{ $t("No lockouts found") }}</td>
						</tr>
					</table>
				</div>

				<div class="aio-login__table-scroll" v-if="'failed-logins' === current_tab">
					<table class="aio-login__table">
						<thead>
						<tr>
							<th v-for="failed_login in failed_login_headers">
								{{ failed_login.title }}
							</th>
						</tr>
						</thead>

						<tr v-if="failed_logins.length" v-for="failed_login in failed_logins">
							<td v-for="failed_login_header in failed_login_headers">
								{{ failed_login[ failed_login_header.value ] }}
							</td>
						</tr>

						<tr v-else>
							<td colspan="7">{{ $t("No failed logins found") }}</td>
						</tr>
					</table>
				</div>

			</div>
		</div>

		<div class="mt-25 mb-25 text-center">
			<a
				:href="view_all_activity_logs"
				class="aio-login__view-all-activity"
			>{{ $t("View All Activity") }}</a>
		</div>
	</div>
</template>

<script>
import tooltipContent from '../tooltip-content.js';
import { t } from '../i18n.js';

export default {
	name: 'aio-login-recent-activity',

	data: () => ( {
		tooltipContent,
		view_all_activity_logs: aio_login__app_object.admin_url + '&tab=activity-log',

		tabs: [
			{
				title: t( 'Lockouts' ),
				slug: 'lockouts',
				url: '',
				active: true,
			},
			{
				title: t( 'Failed Logins' ),
				slug: 'failed-logins',
				url: '',
				active: false,
			},
		],

		lockout_headers: [
			{ title: t( 'Date & Time' ), value: 'time' },
			{ title: t( 'Country' ), value: 'country' },
			{ title: t( 'City' ), value: 'city' },
			{ title: t( 'User Agent' ), value: 'user_agent' },
			{ title: t( 'IP Address' ), value: 'ip_address' },
		],
		lockouts: [],

		failed_login_headers: [
			{ title: t( 'ID' ), value: 'id' },
			{ title: t( 'User Login' ), value: 'user_login' },
			{ title: t( 'Date & Time' ), value: 'time' },
			{ title: t( 'Country' ), value: 'country' },
			{ title: t( 'City' ), value: 'city' },
			{ title: t( 'User Agent' ), value: 'user_agent' },
			{ title: t( 'IP Address' ), value: 'ip_address' },
		],
		failed_logins: [],

		current_tab: 'lockouts',
	} ),

	watch: {
		current_tab( val ) {
			if ( 'lockouts' === val ) {
				// this.getLockoutLogs();
				this.view_all_activity_logs = aio_login__app_object.admin_url + '&tab=activity-log#/lockouts';
			}
			if ( 'failed-logins' === val ) {
				// this.getFailedAttemptLogs();
				this.view_all_activity_logs = aio_login__app_object.admin_url + '&tab=activity-log#/failed-logins';
			}
		}
	},

	methods: {
		toggleShift( e, tab ) {
			e.preventDefault();

			this.tabs.forEach( tab => {
				tab.active = false;
			} );

			tab.active       = true;
			this.current_tab = tab.slug;
		},

		getFailedAttemptLogs() {
			axios.get( 'aio-login/dashboard/logs/lockouts' )
				.then( response => {
					this.lockouts = response.data;
				} )
				.catch( error => {} );
		},

		getLockoutLogs() {
			axios.get( 'aio-login/dashboard/logs/failed-logins' )
				.then( response => {
					this.failed_logins = response.data;
				} )
				.catch( error => {} );
		},
	},

	mounted() {
		this.getFailedAttemptLogs();
		this.getLockoutLogs();
	},
}
</script>

<style scoped>
.aio-login__recent-activity-wrapper h2 {
	color: #405980;
	font-size: 24px;
	font-weight: 600;
}

.aio-login-table-nav {
	height: auto;
	min-height: 48px;
	border-radius: 8px 8px 0 0;
	background: #FBF5FF;
	display: flex;
	flex-wrap: wrap;
}

.aio-login__table-wrapper {
	border-radius: 0 0 8px 8px;
	overflow: hidden;
}

.aio-login__table-scroll {
	overflow-x: auto;
	-webkit-overflow-scrolling: touch;
	border-radius: 0 0 5px 5px;
}

.aio-login__table-nav-item {
	padding: 15px 30px;
	display: inline-block;
}

.aio-login__table-nav-item a {
	color: #9516DF;
	font-weight: 600;
	font-size: 16px;
	line-height: 16px;
	text-decoration: none;
}

.aio-login__table-nav-item:first-child {
	border-radius: 8px 0 0 0;
}

.aio-login__table-nav-item.active {
	background: #9516DF;
}

.aio-login__table-nav-item.active a {
	color: #fff;
}

.aio-login__activate {
	cursor: pointer;
	display: inline-block;
}

.aio-login__table {
	width: 100%;
	border-collapse: collapse;
	min-width: 720px;
}

.aio-login__table thead th {
	font-size: 16px;
	color: #405980;
	padding: 15px 30px;
	background: #e8e5e8;
	text-align: left;
	border-bottom: 1px solid #EBE8EB;
	font-weight: 600;
}

.aio-login__table td {
	font-weight: 400;
	font-size: 16px;
	color: #405980;
	background: #ffffff;
	border-bottom: 1px solid #EBE8EB;
	padding: 15px 30px;
	text-align: left;
}

.aio-login__table tr:last-child td {
	border-bottom: none;
}

.aio-login__view-all-activity {
	color: #9516DF;
	font-weight: 600;
	font-size: 16px;
	line-height: 16px;
	text-decoration: none;
	background: #fff;
	padding: 12px 24px;
	border-radius: 4px;
}

@media screen and (max-width: 782px) {
	.aio-login__recent-activity-wrapper h2 {
		font-size: 20px;
	}

	.aio-login__table-nav-item {
		padding: 12px 16px;
	}

	.aio-login__table-nav-item a {
		font-size: 14px;
	}

	.aio-login__table {
		min-width: 640px;
	}

	.aio-login__table thead th,
	.aio-login__table td {
		padding: 12px 16px;
		font-size: 14px;
	}
}
</style>