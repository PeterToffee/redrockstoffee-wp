<template>
	<div>
		<h1>
			<span>{{ $t("Failed Logins") }}</span>
			<aio-login-tooltip
				:content="tooltipContent.failedLoginAttempts.content"
				:title="tooltipContent.failedLoginAttempts.title"
				placement="bottom"
			/>
		</h1>

		<aio-login-datatable
			:headers="headers"
			:rows="data"
		></aio-login-datatable>
	</div>
</template>

<script>
import tooltipContent from '../tooltip-content.js';
import { t } from '../i18n.js';

export default {
	name: 'failed-logins',

	data: ( vm ) => ( {
		tooltipContent,
		namespace: 'aio-login/logs/failed-login',

		headers: [
			{ value: t( 'ID' ), key: 'id' },
			{ value: t( 'User Login' ), key: 'user_login' },
			{ value: t( 'Date & Time' ), key: 'time' },
			{ value: t( 'Country' ), key: 'country' },
			{ value: t( 'City' ), key: 'city' },
			{ value: t( 'User Agent' ), key: 'user_agent' },
			{ value: t( 'IP Address' ), key: 'ip_address' },
		],

		data: [],
	} ),

	methods: {
		get_logs() {
			axios.get( this.namespace )
				.then( response => {
					this.data = response.data;
				} )
				.catch( error => {

				} );
		}
	},

	mounted() {
		this.get_logs();
	}
}
</script>

<style scoped>

</style>