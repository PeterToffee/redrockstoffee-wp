<?php
/**
 * User phone meta for SMS OTP login, profile field, and admin notices.
 *
 * @package AIO_Login
 */

namespace AIO_Login\Passwordless_Otp;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'AIO_Login\\Passwordless_Otp\\OTP_User_Phone' ) ) {
	/**
	 * OTP_User_Phone
	 */
	final class OTP_User_Phone {

		public const META_PHONE       = 'aio_login_phone';
		public const META_PHONE_LOCAL = 'aio_login_phone_local';
		public const META_PHONE_ISO   = 'aio_login_phone_iso';
		public const META_PROMPT      = 'aio_login_prompt_add_phone';

		/**
		 * @return self
		 */
		public static function get_instance() {
			static $instance = null;
			if ( null === $instance ) {
				$instance = new self();
			}
			return $instance;
		}

		private function __construct() {
			add_action( 'user_register', array( $this, 'on_user_register' ), 10, 1 );
			add_action( 'show_user_profile', array( $this, 'render_profile_fields' ) );
			add_action( 'edit_user_profile', array( $this, 'render_profile_fields' ) );
			add_action( 'user_profile_update_errors', array( $this, 'validate_profile_fields' ), 10, 3 );
			add_action( 'personal_options_update', array( $this, 'save_profile_fields' ) );
			add_action( 'edit_user_profile_update', array( $this, 'save_profile_fields' ) );
			add_action( 'admin_init', array( $this, 'maybe_dismiss_notice' ) );
			add_action( 'admin_notices', array( $this, 'render_add_phone_notice' ) );
			add_action( 'admin_notices', array( $this, 'render_profile_save_notice' ) );
		}

		/**
		 * @param int $user_id User ID.
		 * @return bool
		 */
		public static function user_has_phone( $user_id ) {
			$user_id = (int) $user_id;
			if ( $user_id <= 0 ) {
				return false;
			}

			$phone = (string) get_user_meta( $user_id, self::META_PHONE, true );
			return '' !== trim( $phone );
		}

		/**
		 * @param int $user_id User ID.
		 * @return string
		 */
		public static function get_phone( $user_id ) {
			$stored = (string) get_user_meta( (int) $user_id, self::META_PHONE, true );
			return self::normalize_stored_e164( $stored );
		}

		/**
		 * @param string $stored Raw meta value.
		 * @return string
		 */
		private static function normalize_stored_e164( $stored ) {
			$stored = preg_replace( '/\s+/', '', (string) $stored );
			if ( '' === $stored ) {
				return '';
			}
			if ( '+' !== $stored[0] ) {
				$digits = preg_replace( '/\D+/', '', $stored );
				return '' === $digits ? '' : '+' . $digits;
			}
			return $stored;
		}

		/**
		 * @param int $user_id User ID.
		 */
		public function on_user_register( $user_id ) {
			$user_id = (int) $user_id;
			if ( $user_id <= 0 || ! OTP_Settings::is_sms_login_available() ) {
				return;
			}

			update_user_meta( $user_id, self::META_PROMPT, '1' );
		}

		/**
		 * @param \WP_User $user User.
		 */
		public function render_profile_fields( $user ) {
			if ( ! ( $user instanceof \WP_User ) ) {
				return;
			}

			if ( ! current_user_can( 'edit_user', $user->ID ) ) {
				return;
			}

			$stored       = self::get_phone( $user->ID );
			$default_iso  = OTP_Settings::get_sms_default_country_iso();
			$cc           = OTP_Settings::get_dial_code_for_country_iso( $default_iso );
			$local        = (string) get_user_meta( $user->ID, self::META_PHONE_LOCAL, true );
			$selected_iso = (string) get_user_meta( $user->ID, self::META_PHONE_ISO, true );
			$countries    = OTP_Settings::get_country_codes();

			if ( '' !== $stored ) {
				$parts = OTP_Service::split_e164( $stored, $selected_iso );
				if ( is_array( $parts ) ) {
					$cc           = $parts['code'];
					$selected_iso = $parts['iso'];
					if ( '' === trim( $local ) ) {
						$local = $parts['local'];
					}
				}
			} elseif ( '' === $selected_iso ) {
				$selected_iso = $default_iso;
				$cc           = OTP_Settings::get_dial_code_for_country_iso( $default_iso );
			}

			?>
			<style>
				.aio-login-profile-phone-field {
					display: flex;
					align-items: stretch;
					max-width: 25em;
				}
				.aio-login-profile-phone-field__dial {
					display: inline-flex;
					align-items: center;
					padding: 0 10px;
					border: 1px solid #8c8f94;
					border-right: 0;
					border-radius: 4px 0 0 4px;
					background: #f6f7f7;
					color: #1d2327;
					font-size: 14px;
					line-height: 2;
					user-select: none;
					white-space: nowrap;
				}
				.aio-login-profile-phone-field input.regular-text {
					border-radius: 0 4px 4px 0;
					flex: 1;
					min-width: 0;
					margin: 0;
				}
			</style>
			<h2 id="aio-login-phone-section"><?php esc_html_e( 'SMS login phone', 'change-wp-admin-login' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Add your mobile number to sign in with Continue with SMS on the login page.', 'change-wp-admin-login' ); ?>
			</p>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="aio_login_phone_country"><?php esc_html_e( 'Country code', 'change-wp-admin-login' ); ?></label></th>
					<td>
						<select name="aio_login_phone_country" id="aio_login_phone_country" class="regular-text">
							<?php
							foreach ( $countries as $country ) :
								$is_selected = ( $country['iso'] === $selected_iso );
								?>
								<option value="<?php echo esc_attr( $country['code'] ); ?>" data-iso="<?php echo esc_attr( $country['iso'] ); ?>" <?php selected( $is_selected, true ); ?>>
									<?php echo esc_html( $country['label'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<input type="hidden" name="aio_login_phone_country_iso" id="aio_login_phone_country_iso" value="<?php echo esc_attr( $selected_iso ); ?>" />
					</td>
				</tr>
				<tr>
					<th><label for="aio_login_phone_number"><?php esc_html_e( 'Phone number', 'change-wp-admin-login' ); ?></label></th>
					<td>
						<div class="aio-login-profile-phone-field">
							<span class="aio-login-profile-phone-field__dial" id="aio_login_phone_dial" aria-hidden="true"><?php echo esc_html( $cc ); ?></span>
							<input
								type="tel"
								name="aio_login_phone_number"
								id="aio_login_phone_number"
								value="<?php echo esc_attr( preg_replace( '/\D+/', '', $local ) ); ?>"
								class="regular-text"
								autocomplete="tel-national"
								inputmode="numeric"
							/>
						</div>
						<p class="description"><?php esc_html_e( 'The country code is shown automatically and cannot be edited here. Enter the rest of your mobile number.', 'change-wp-admin-login' ); ?></p>
					</td>
				</tr>
			</table>
			<script>
			(function () {
				var select = document.getElementById('aio_login_phone_country');
				var iso = document.getElementById('aio_login_phone_country_iso');
				var dial = document.getElementById('aio_login_phone_dial');
				var phoneInput = document.getElementById('aio_login_phone_number');
				if (!select || !iso || !dial || !phoneInput) {
					return;
				}

				function currentDialCode() {
					var opt = select.options[select.selectedIndex];
					return opt ? String(opt.value || '') : '';
				}

				function syncIso() {
					var opt = select.options[select.selectedIndex];
					iso.value = opt && opt.dataset.iso ? opt.dataset.iso : '';
				}

				function syncDialDisplay() {
					dial.textContent = currentDialCode();
				}

				select.addEventListener('change', function () {
					syncIso();
					syncDialDisplay();
					phoneInput.focus();
				});

				syncIso();
				syncDialDisplay();
			})();
			</script>
			<?php
		}

		/**
		 * @param \WP_Error $errors   Errors.
		 * @param bool      $update  Whether this is an existing user being updated.
		 * @param \WP_User  $user    User object.
		 */
		public function validate_profile_fields( $errors, $update, $user ) {
			if ( ! $update || ! ( $user instanceof \WP_User ) ) {
				return;
			}

			if ( ! current_user_can( 'edit_user', $user->ID ) ) {
				return;
			}

			$parsed = $this->parse_profile_phone_post();
			if ( null === $parsed ) {
				return;
			}

			if ( is_wp_error( $parsed ) ) {
				$errors->add( 'aio_login_phone', $parsed->get_error_message() );
			}
		}

		/**
		 * @param int $user_id User ID.
		 */
		public function save_profile_fields( $user_id ) {
			$user_id = (int) $user_id;
			if ( $user_id <= 0 || ! current_user_can( 'edit_user', $user_id ) ) {
				return;
			}

			$parsed = $this->parse_profile_phone_post();
			if ( null === $parsed ) {
				delete_user_meta( $user_id, self::META_PHONE );
				delete_user_meta( $user_id, self::META_PHONE_LOCAL );
				delete_user_meta( $user_id, self::META_PHONE_ISO );
				delete_user_meta( $user_id, self::META_PROMPT );
				return;
			}

			if ( is_wp_error( $parsed ) ) {
				return;
			}

			$iso = $this->iso_from_country_post( isset( $_POST['aio_login_phone_country'] ) ? sanitize_text_field( wp_unslash( $_POST['aio_login_phone_country'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

			$local_save = '';
			$parts      = OTP_Service::split_e164( $parsed, $iso );
			if ( is_array( $parts ) ) {
				$local_save = $parts['local'];
			}

			update_user_meta( $user_id, self::META_PHONE, $parsed );
			if ( '' !== $local_save ) {
				update_user_meta( $user_id, self::META_PHONE_LOCAL, $local_save );
			}
			if ( '' !== $iso ) {
				update_user_meta( $user_id, self::META_PHONE_ISO, $iso );
			} else {
				delete_user_meta( $user_id, self::META_PHONE_ISO );
			}
			delete_user_meta( $user_id, self::META_PROMPT );

			set_transient( 'aio_login_phone_saved_' . $user_id, '1', 30 );
		}

		/**
		 * Parse profile phone POST; null when clearing the field.
		 *
		 * @return string|\WP_Error|null E.164 phone, error, or null when empty.
		 */
		private function parse_profile_phone_post() {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WP core profile save.
			if ( ! isset( $_POST['aio_login_phone_number'] ) ) {
				return null;
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$cc  = isset( $_POST['aio_login_phone_country'] ) ? sanitize_text_field( wp_unslash( $_POST['aio_login_phone_country'] ) ) : '';
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$num = sanitize_text_field( wp_unslash( $_POST['aio_login_phone_number'] ) );

			if ( '' === trim( $num ) ) {
				return null;
			}

			$iso = $this->iso_from_country_post( $cc );

			$cc_digits  = preg_replace( '/\D+/', '', $cc );
			$num_digits = preg_replace( '/\D+/', '', $num );
			if ( '' !== $cc_digits && '' !== $num_digits && 0 === strpos( $num_digits, $cc_digits ) ) {
				$num_digits = substr( $num_digits, strlen( $cc_digits ) );
			}
			$num = $num_digits;

			$phone = OTP_Service::normalize_phone( $cc, $num, $iso, false );
			if ( is_wp_error( $phone ) ) {
				return $phone;
			}

			$existing = OTP_Service::find_user_id_by_phone_e164( $phone );
			if ( is_wp_error( $existing ) ) {
				return $existing;
			}

			$user_id = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : get_current_user_id(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( $existing > 0 && (int) $existing !== $user_id ) {
				return new \WP_Error(
					'phone_in_use',
					__( 'This phone number is already linked to another account.', 'change-wp-admin-login' )
				);
			}

			return $phone;
		}

		/**
		 * Resolve ISO from profile country dropdown (matches an allowed SMS country when possible).
		 *
		 * @param string $cc Dial code e.g. +1.
		 * @return string
		 */
		private function iso_from_country_post( $cc ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$posted_iso = isset( $_POST['aio_login_phone_country_iso'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['aio_login_phone_country_iso'] ) ) ) : '';

			$countries = OTP_Settings::get_country_codes();

			foreach ( $countries as $country ) {
				if ( $country['code'] !== $cc ) {
					continue;
				}
				if ( '' !== $posted_iso && $country['iso'] === $posted_iso ) {
					return $posted_iso;
				}
				if ( '' === $posted_iso ) {
					return $country['iso'];
				}
			}

			if ( '' !== $posted_iso ) {
				return $posted_iso;
			}

			$allowed = OTP_Settings::get_sms_allowed_country_isos();
			$cc_digits = preg_replace( '/\D+/', '', (string) $cc );
			$map       = OTP_Country_Codes::calling_code_map();
			if ( isset( $map[ $cc_digits ] ) ) {
				foreach ( $map[ $cc_digits ] as $iso ) {
					if ( empty( $allowed ) || in_array( $iso, $allowed, true ) ) {
						return $iso;
					}
				}
			}

			return '';
		}

		public function render_profile_save_notice() {
			if ( ! is_user_logged_in() ) {
				return;
			}

			$user_id = get_current_user_id();
			if ( ! get_transient( 'aio_login_phone_saved_' . $user_id ) ) {
				return;
			}

			delete_transient( 'aio_login_phone_saved_' . $user_id );

			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html__( 'SMS login phone number saved.', 'change-wp-admin-login' )
			);
		}

		public function maybe_dismiss_notice() {
			if ( ! is_user_logged_in() ) {
				return;
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( empty( $_GET['aio_login_dismiss_phone_notice'] ) ) {
				return;
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'aio_login_dismiss_phone_notice' ) ) {
				return;
			}

			delete_user_meta( get_current_user_id(), self::META_PROMPT );
			wp_safe_redirect( remove_query_arg( array( 'aio_login_dismiss_phone_notice', '_wpnonce' ) ) );
			exit;
		}

		public function render_add_phone_notice() {
			if ( ! is_user_logged_in() || ! is_admin() ) {
				return;
			}

			if ( ! OTP_Settings::is_sms_login_available() ) {
				return;
			}

			$user_id = get_current_user_id();
			if ( self::user_has_phone( $user_id ) ) {
				delete_user_meta( $user_id, self::META_PROMPT );
				return;
			}

			if ( '1' !== (string) get_user_meta( $user_id, self::META_PROMPT, true ) ) {
				return;
			}

			$profile_url = get_edit_profile_url( $user_id ) . '#aio-login-phone-section';
			$dismiss_url = wp_nonce_url(
				add_query_arg( 'aio_login_dismiss_phone_notice', '1' ),
				'aio_login_dismiss_phone_notice'
			);

			printf(
				'<div class="notice notice-info aio-login-otp-phone-notice"><p>%1$s <a href="%2$s">%3$s</a></p><p><a href="%4$s" class="button-link">%5$s</a></p></div>',
				esc_html__( 'To use Continue with SMS on the login page, add your mobile number to your account profile.', 'change-wp-admin-login' ),
				esc_url( $profile_url ),
				esc_html__( 'Add phone number', 'change-wp-admin-login' ),
				esc_url( $dismiss_url ),
				esc_html__( 'Dismiss', 'change-wp-admin-login' )
			);
		}
	}
}
