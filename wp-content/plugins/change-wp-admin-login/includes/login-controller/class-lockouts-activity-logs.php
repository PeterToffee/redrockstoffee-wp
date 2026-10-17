<?php
/**
 * Class Lockouts_Activity_Logs
 *
 * @deprecated 2.0.0
 *
 * @package AIO Login
 */

namespace AIO_Login\Login_Controller;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'AIO_Login\\Login_Controller\\Lockouts_Activity_Logs' ) && class_exists( 'WP_list_Table' ) ) {
	/**
	 * Class Lockouts_Activity_Logs
	 *
	 * @deprecated 2.0.0
	 */
	class Lockouts_Activity_Logs extends \WP_List_Table {

		/**
		 * Get columns
		 */
		public function get_columns() {
			$columns = array(
				'cb'         => '<input type="checkbox" />',
				'login_time' => __( 'Date & Time', 'aio-login' ),
				'country'    => __( 'Country', 'aio-login' ),
				'city'       => __( 'City', 'aio-login' ),
				'user_agent' => __( 'User Agent', 'aio-login' ),
				'ip_address' => __( 'IP Address', 'aio-login' ),
			);

			return apply_filters( 'aio_login__lockouts_columns', $columns );
		}

		/**
		 * Column cb
		 *
		 * @param array $item Item.
		 *
		 * @return string
		 */
		public function column_cb( $item ) {
			return sprintf(
				'<input type="checkbox" name="id[]" value="%s" />',
				$item['id']
			);
		}

		/**
		 * Delete lockout attempt
		 */
		private function delete_lockout_attempt() {
			if ( isset( $_POST['_delete_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_delete_nonce'] ) ), 'delete-bulk' ) ) {
				if ( isset( $_POST['action'] ) && 'delete' === sanitize_text_field( wp_unslash( $_POST['action'] ) ) && isset( $_POST['action2'] ) && $_POST['action'] === $_POST['action2'] ) {
					if ( isset( $_POST['id'] ) && is_array( $_POST['id'] ) ) {
						$id = array_map( 'sanitize_text_field', wp_unslash( $_POST['id'] ) );
						Failed_Logins::delete_lockouts( $id );
					}
				}
			}
		}

		/**
		 * Prepare items
		 */
		public function prepare_items() {
			$this->delete_lockout_attempt();

			$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

			$per_page     = $this->get_items_per_page( 'aio_login_lockouts_per_page', 10 );
			$current_page = $this->get_pagenum();
			$offset       = ( $current_page - 1 ) * $per_page;
			$this->items  = Failed_Logins::get_locked_ips( -1 );
			$total_items  = count( $this->items );

			$this->set_pagination_args(
				array(
					'total_items' => $total_items,
					'per_page'    => $per_page,
				)
			);

			$this->items = array_splice( $this->items, $offset, $per_page );
		}

		/**
		 * Get sortable columns
		 */
		public function get_sortable_columns() {
			return array();
		}

		/**
		 * Bulk actions
		 *
		 * @param string $which Which.
		 */
		public function bulk_actions( $which = '' ) {
			$id   = '';
			$name = '';
			if ( 'top' === $which ) {
				$name = 'action';
				$id   = 'bulk-action-selector-top';
			}

			if ( 'bottom' === $which ) {
				$name = 'action2';
				$id   = 'bulk-action-selector-bottom';
			}
			echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">
				<option value="null">' . esc_attr__( 'Bulk actions', 'aio-login' ) . '</option>
				<option value="delete">' . esc_attr__( 'Delete', 'aio-login' ) . '</option>
			</select>

			<input type="hidden" name="_delete_nonce" value="' . esc_attr( wp_create_nonce( 'delete-bulk' ) ) . '" />

			<input type="submit" class="button action" value="' . esc_attr__( 'Apply', 'aio-login' ) . '">';

			parent::bulk_actions( $which );
		}

		/**
		 * Column default
		 *
		 * @param object $item Item.
		 * @param string $column_name Column name.
		 */
		public function column_default( $item, $column_name ) {
			switch ( $column_name ) {
				case 'login_time':
					return gmdate( 'Y-m-d h:i a', $item['time'] );
				case 'country':
				case 'city':
				case 'user_agent':
				case 'ip_address':
					return $item[ $column_name ];

				default:
					return 'data not found';
			}
		}
	}
}
