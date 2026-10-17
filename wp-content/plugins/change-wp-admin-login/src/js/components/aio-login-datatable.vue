<template>
<table>
	<thead>
	<tr>
		<th v-for="header in headers">{{ header['value'] }}</th>
	</tr>
	</thead>

	<tfoot>
	<tr>
		<th v-for="header in headers">{{ header['value'] }}</th>
	</tr>
	</tfoot>
</table>
</template>

<script>
import { t } from '../i18n.js';

export default {
	name: 'aio-login-datatable',

	props: {
		headers: {
			type: Array,
			required: true,
		},

		rows: {
			type: Array,
			default: () => [],
		},
	},

	data: () => ( {
		datatable: null,
	} ),

	watch: {
		rows() {
			if ( this.datatable ) {
				this.datatable.destroy();
			}
			this.datatable = this.createDatatableInstance();
		}
	},

	methods: {
		getColumns() {
			return this.headers.map( header => {
				if ( header.callback ) {
					return {
						title: header['value'],
						data: header['key'],
						render: header.callback,
					};
				}
				return {
					title: header['value'],
					data: header['key'],
				};
			} );
		},

		getLanguage() {
			return {
				emptyTable: t( 'No data available in table' ),
				info: t( 'Showing _START_ to _END_ of _TOTAL_ entries' ),
				infoEmpty: t( 'Showing 0 to 0 of 0 entries' ),
				infoFiltered: t( '(filtered from _MAX_ total entries)' ),
				lengthMenu: t( '_MENU_ entries per page' ),
				loadingRecords: t( 'Loading...' ),
				processing: t( 'Processing...' ),
				search: t( 'Search:' ),
				zeroRecords: t( 'No matching records found' ),
				paginate: {
					first: t( 'First' ),
					last: t( 'Last' ),
					next: t( 'Next' ),
					previous: t( 'Previous' ),
				},
			};
		},

		createDatatableInstance() {
			const timeColumnIndex = this.headers.findIndex(
				header => header && header.key === 'time'
			);

			var kf = {
				columns: this.getColumns(),
				data: this.rows,
				responsive: true,
				language: this.getLanguage(),
			}
			if ( timeColumnIndex >= 0 ) {
				kf.order = [ [ timeColumnIndex, 'desc' ] ];
			}
			return new Datatable.default(
				this.$el,
				kf
			);
		}
	},

	mounted() {
		this.datatable = this.createDatatableInstance();
	},
}
</script>

<style scoped>
</style>

