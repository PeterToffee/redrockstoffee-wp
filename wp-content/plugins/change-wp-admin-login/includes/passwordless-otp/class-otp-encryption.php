<?php
/**
 * Encrypt sensitive option values (e.g. Twilio auth token).
 *
 * @package AIO_Login
 */

namespace AIO_Login\Passwordless_Otp;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'AIO_Login\\Passwordless_Otp\\OTP_Encryption' ) ) {
	/**
	 * OTP_Encryption
	 */
	final class OTP_Encryption {

		/**
		 * Encrypt plaintext for storage.
		 *
		 * @param string $plaintext Plaintext.
		 * @return string Base64 payload or empty on failure.
		 */
		public static function encrypt( $plaintext ) {
			$plaintext = (string) $plaintext;
			if ( '' === $plaintext ) {
				return '';
			}

			if ( ! function_exists( 'openssl_encrypt' ) ) {
				return '';
			}

			$key    = self::get_key();
			$iv     = openssl_random_pseudo_bytes( 16 );
			$cipher = openssl_encrypt( $plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
			if ( false === $cipher ) {
				return '';
			}

			return base64_encode( $iv . $cipher ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		}

		/**
		 * Decrypt stored payload.
		 *
		 * @param string $encoded Encrypted payload.
		 * @return string
		 */
		public static function decrypt( $encoded ) {
			$encoded = (string) $encoded;
			if ( '' === $encoded || ! function_exists( 'openssl_decrypt' ) ) {
				return '';
			}

			$raw = base64_decode( $encoded, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			if ( false === $raw || strlen( $raw ) < 17 ) {
				return '';
			}

			$iv     = substr( $raw, 0, 16 );
			$cipher = substr( $raw, 16 );
			$key    = self::get_key();
			$plain  = openssl_decrypt( $cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

			return false === $plain ? '' : $plain;
		}

		/**
		 * Mask secret for API responses (never return full token).
		 *
		 * @param string $has_stored Whether a value exists.
		 * @return string
		 */
		public static function masked_placeholder( $has_stored ) {
			return $has_stored ? '••••••••••••' : '';
		}

		/**
		 * @return string 32-byte key.
		 */
		private static function get_key() {
			$material = wp_salt( 'auth' ) . wp_salt( 'secure_auth' ) . 'aio_login_otp_v1';
			return hash( 'sha256', $material, true );
		}
	}
}
