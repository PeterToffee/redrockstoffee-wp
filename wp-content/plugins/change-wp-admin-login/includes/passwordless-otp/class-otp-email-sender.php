<?php
/**
 * Send passwordless OTP emails.
 *
 * @package AIO_Login
 */

namespace AIO_Login\Passwordless_Otp;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'AIO_Login\\Passwordless_Otp\\OTP_Email_Sender' ) ) {
	/**
	 * OTP_Email_Sender
	 */
	final class OTP_Email_Sender {

		/**
		 * @param int    $user_id User ID (0 if account not created yet).
		 * @param string $otp     Plain OTP (only in memory).
		 * @param string $email   Recipient when $user_id is 0.
		 * @return bool|\WP_Error
		 */
		public static function send( $user_id, $otp, $email = '' ) {
			$to_email      = '';
			$display_name  = '';

			if ( $user_id > 0 ) {
				$user = get_user_by( 'id', $user_id );
				if ( ! ( $user instanceof \WP_User ) ) {
					return new \WP_Error( 'invalid_user', __( 'Unable to send verification email.', 'change-wp-admin-login' ) );
				}
				$to_email     = $user->user_email;
				$display_name = $user->display_name ? $user->display_name : $user->user_login;
			} else {
				$to_email = sanitize_email( $email );
				if ( ! is_email( $to_email ) ) {
					return new \WP_Error( 'invalid_email', __( 'Unable to send verification email.', 'change-wp-admin-login' ) );
				}
				$display_name = $to_email;
			}

			$body_template = OTP_Settings::default_email_body();
			$expiration    = OTP_Settings::get_expiration_minutes( 'email' );

			$replacements = array(
				'{{display_name}}'    => $display_name,
				'{{otp_code}}'        => $otp,
				'{{expiration_time}}' => (string) $expiration,
				'{{device_info}}'     => OTP_Service::get_device_info(),
				'{{site_name}}'       => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
				'{{site_url}}'        => home_url(),
			);

			$message = str_replace( array_keys( $replacements ), array_values( $replacements ), $body_template );
			$subject = sprintf(
				/* translators: %s: site name */
				__( '[%s] Your login verification code', 'change-wp-admin-login' ),
				wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
			);

			$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
			$sent    = wp_mail( $to_email, $subject, $message, $headers );

			if ( ! $sent ) {
				return new \WP_Error(
					'email_failed',
					__( 'Unable to send verification email. Please try again later or contact the administrator.', 'change-wp-admin-login' )
				);
			}

			return true;
		}
	}
}
