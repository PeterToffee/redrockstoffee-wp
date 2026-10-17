<?php
/**
 * Send login link emails.
 *
 * @package AIO_Login
 */

namespace AIO_Login\Passwordless_Otp;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'AIO_Login\\Passwordless_Otp\\Magic_Link_Email_Sender' ) ) {
	/**
	 * Magic_Link_Email_Sender
	 */
	final class Magic_Link_Email_Sender {

		/**
		 * @param int    $user_id User ID.
		 * @param string $url     Login URL.
		 * @return true|\WP_Error
		 */
		public static function send( $user_id, $url ) {
			$user = get_user_by( 'id', (int) $user_id );
			if ( ! ( $user instanceof \WP_User ) ) {
				return new \WP_Error( 'invalid_user', __( 'Unable to send login link email.', 'change-wp-admin-login' ) );
			}

			$display_name = $user->display_name ? $user->display_name : $user->user_login;
			$site_name    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
			$validity     = Magic_Link_Service::get_validity_label();

			$message = sprintf(
				/* translators: 1: display name, 2: site name, 3: validity duration, 4: login URL */
				__(
					"Hi %1\$s,\n\nUse the link below to sign in to %2\$s. This link is valid for %3\$s and can only be used once.\n\n%4\$s\n\nIf you did not request this email, you can safely ignore it.\n",
					'change-wp-admin-login'
				),
				$display_name,
				$site_name,
				$validity,
				$url
			);

			$subject = sprintf(
				/* translators: %s: site name */
				__( '[%s] Your login link', 'change-wp-admin-login' ),
				$site_name
			);

			$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
			$sent    = wp_mail( $user->user_email, $subject, $message, $headers );

			if ( ! $sent ) {
				return new \WP_Error(
					'email_failed',
					__( 'Unable to send login link email. Please try again later or contact the administrator.', 'change-wp-admin-login' )
				);
			}

			return true;
		}
	}
}
