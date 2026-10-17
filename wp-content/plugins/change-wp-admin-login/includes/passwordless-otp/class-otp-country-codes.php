<?php
/**
 * Country calling codes for passwordless SMS OTP (admin + login UI).
 *
 * @package AIO_Login
 */

namespace AIO_Login\Passwordless_Otp;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'AIO_Login\\Passwordless_Otp\\OTP_Country_Codes' ) ) {
	/**
	 * OTP_Country_Codes
	 */
	final class OTP_Country_Codes {

		/**
		 * @var array<int, array{code:string,label:string,iso:string}>|null
		 */
		private static $countries = null;

		/**
		 * @var array<string, array<int, string>>|null
		 */
		private static $calling_code_map = null;

		/**
		 * @return array<int, array{code:string,label:string,iso:string}>
		 */
		public static function all() {
			if ( null !== self::$countries ) {
				return self::$countries;
			}

			$path = __DIR__ . '/data/countries.json';
			if ( ! is_readable( $path ) ) {
				self::$countries = array();
				return self::$countries;
			}

			$raw = json_decode( (string) file_get_contents( $path ), true );
			if ( ! is_array( $raw ) ) {
				self::$countries = array();
				return self::$countries;
			}

			$countries = array();
			foreach ( $raw as $row ) {
				if ( empty( $row['iso'] ) || empty( $row['code'] ) ) {
					continue;
				}
				$iso  = strtoupper( sanitize_text_field( (string) $row['iso'] ) );
				$code = '+' . ltrim( preg_replace( '/\D+/', '', (string) $row['code'] ), '+' );
				if ( strlen( $iso ) !== 2 || '' === $code || '+' === $code ) {
					continue;
				}
				$label       = isset( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : $iso . ' (' . $code . ')';
				$countries[] = array(
					'iso'   => $iso,
					'code'  => $code,
					'label' => $label,
				);
			}

			usort(
				$countries,
				static function ( $a, $b ) {
					return strcasecmp( $a['label'], $b['label'] );
				}
			);

			self::$countries = $countries;

			return self::$countries;
		}

		/**
		 * Map numeric calling code (no +) to ISO 3166-1 alpha-2 codes.
		 *
		 * @return array<string, array<int, string>>
		 */
		public static function calling_code_map() {
			if ( null !== self::$calling_code_map ) {
				return self::$calling_code_map;
			}

			$map = array();
			foreach ( self::all() as $row ) {
				$prefix = ltrim( $row['code'], '+' );
				if ( ! isset( $map[ $prefix ] ) ) {
					$map[ $prefix ] = array();
				}
				if ( ! in_array( $row['iso'], $map[ $prefix ], true ) ) {
					$map[ $prefix ][] = $row['iso'];
				}
			}

			self::$calling_code_map = $map;

			return self::$calling_code_map;
		}
	}
}
