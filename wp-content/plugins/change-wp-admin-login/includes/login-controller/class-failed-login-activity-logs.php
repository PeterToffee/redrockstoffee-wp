<?php
/**
 * Class Failed_Login_Activity_Logs
 *
 * @deprecated 2.0.0
 *
 * @package AIO Login
 */

namespace AIO_Login\Login_Controller;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'AIO_Login\\Login_Controller\\Failed_Login_Activity_Logs' ) && class_exists( 'WP_List_Table' ) ) {
	/**
	 * Class Failed_Login_Activity_Logs
	 *
	 * @deprecated 2.0.0
	 */
	class Failed_Login_Activity_Logs extends \WP_List_Table {

		/**
		 * Get columns
		 */
		public function get_columns() {
			$columns = array(
				'cb'         => '<input type="checkbox" />',
				'id'         => __( 'ID', 'aio-login' ),
				'user_login' => __( 'User Login', 'aio-login' ),
				'login_time' => __( 'Date & Time', 'aio-login' ),
				'country'    => __( 'Country', 'aio-login' ),
				'city'       => __( 'City', 'aio-login' ),
				'user_agent' => __( 'User Agent', 'aio-login' ),
				'ip_address' => __( 'IP Address', 'aio-login' ),
			);

			return apply_filters( 'aio_login__failed_login_columns', $columns );
		}

		/**
		 * Prepare items
		 */
		public function prepare_items() {
			$order   = $this->get_order();
			$orderby = $this->get_orderby();

			$this->delete_failed_login_attempt();

			$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

			$items_per_page = 10;
			$current_page   = $this->get_pagenum();
			$offset         = ( $current_page - 1 ) * $items_per_page;

			$data        = Failed_Logins::query_all_logs( 'failed', '', $orderby, $order );
			$total_items = count( $data );

			$this->set_pagination_args(
				array(
					'total_items' => $total_items,
					'per_page'    => $items_per_page,
				)
			);

			$this->items = array_slice( $data, $offset, $items_per_page );
		}

		/**
		 * Display bulk actions.
		 *
		 * @param string $which Position of nav.
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
		 * Display checkboxes
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
		 * No items
		 */
		public function no_items() {
			esc_html_e( 'No failed logs found.', 'aio-login' );
		}

		/**
		 * Column default
		 *
		 * @param array  $item Item.
		 * @param string $column_name Column name.
		 *
		 * @return mixed|string
		 */
		protected function column_default( $item, $column_name ) {
			switch ( $column_name ) {
				case 'id':
					$id      = $item[ $column_name ];
					$actions = array(
						'delete' => sprintf(
							'<a href="%s" onclick="return confirm(\'%s\')">%s</a>',
							wp_nonce_url(
								add_query_arg(
									array(
										'action' => 'delete',
										'id'     => $item['id'],
									)
								),
								'delete_failed_login_attempt'
							),
							esc_attr__( 'Are you sure you want to delete this item?', 'aio-login' ),
							esc_html__( 'Delete', 'aio-login' )
						),
					);

					return $id . $this->row_actions( $actions );
				case 'country':
				case 'city':
				case 'ip_address':
				case 'user_login':
				case 'user_agent':
					return $item[ $column_name ];
				case 'login_time':
					return gmdate( 'Y-m-d h:i a', $item['time'] );
				default:
					return apply_filters( 'aio_login__failed_login_data_' . $column_name, $item, $column_name );
			}
		}

		/**
		 * Get order
		 *
		 * @return string
		 */
		private function get_order() {
			if ( isset( $_GET['order'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return sanitize_text_field( wp_unslash( $_GET['order'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}

			return '';
		}

		/**
		 * Get orderby
		 *
		 * @return string
		 */
		private function get_orderby() {
			if ( isset( $_GET['orderby'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return sanitize_text_field( wp_unslash( $_GET['orderby'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}

			return '';
		}

		/**
		 * Delete failed login attempt
		 */
		private function delete_failed_login_attempt() {
			if ( isset( $_POST['_delete_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_delete_nonce'] ) ), 'delete-bulk' ) ) {
				if ( isset( $_POST['action'] ) && 'delete' === sanitize_text_field( wp_unslash( $_POST['action'] ) ) && isset( $_POST['action2'] ) && $_POST['action'] === $_POST['action2'] ) {
					if ( isset( $_POST['id'] ) && is_array( $_POST['id'] ) ) {
						$id = array_map( 'sanitize_text_field', wp_unslash( $_POST['id'] ) );
						Failed_Logins::delete_logs( $id );
					}
				}
			}

			if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'delete_failed_login_attempt' ) ) {
				if ( isset( $_GET['action'] ) && 'delete' === sanitize_text_field( wp_unslash( $_GET['action'] ) ) ) {
					if ( isset( $_GET['id'] ) ) {
						$id = sanitize_text_field( wp_unslash( $_GET['id'] ) );
						Failed_Logins::delete_logs( $id );
					}
				}
			}
		}
	}
}
