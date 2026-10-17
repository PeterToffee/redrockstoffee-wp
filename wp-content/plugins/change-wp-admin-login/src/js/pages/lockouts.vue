<template>
	<div>
		<h1>
			<span>{{ $t("Lockouts") }}</span>
			<aio-login-tooltip
				:content="tooltipContent.loginAttemptLogs.content"
				:title="tooltipContent.loginAttemptLogs.title"
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
	name: 'lockouts',

	data: ( vm ) => ( {
		tooltipContent,
		namespace: 'aio-login/logs/lockouts',

		headers: [
			{ key: 'time', value: t( 'Date & Time' ) },
			{ key: 'country', value: t( 'Country' ) },
			{ key: 'city', value: t( 'City' ) },
			{ key: 'user_agent', value: t( 'User Agent' ) },
			{ key: 'ip_address', value: t( 'IP Address' ) },
		],

		data: [],
	} ),

	methods: {
		getLogs() {
			axios.get( this.namespace )
				.then( response => {
					this.data = response.data;
				} );
		}
	},

	mounted() {
		this.getLogs();
	},
}
</script>

<style scoped>

</style>