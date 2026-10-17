<?php
/**
 * Sends Activity Log notifications to Slack and custom webhooks when events occur.
 *
 * @package AIO_Login
 */

namespace AIO_Login\Notifications;

defined( 'ABSPATH' ) || exit;

/**
 * Class Notification_Sender
 */
class Notification_Sender {

	/**
	 * Register hooks (called from plugin bootstrap).
	 */
	public static function boot() {
		add_action( 'aio_login_after_failed_login', array( __CLASS__, 'on_failed_login' ), 10, 1 );
		add_action( 'aio_login_after_lockout', array( __CLASS__, 'on_lockout' ), 10, 1 );
		add_action( 'aio_login_enumeration_logged', array( __CLASS__, 'on_enumeration' ), 10, 3 );
	}

	/**
	 * @param array $details Row fields from failed login insert.
	 */
	public static function on_failed_login( $details ) {
		if ( ! is_array( $details ) ) {
			return;
		}
		self::dispatch( 'failed_login', $details );
	}

	/**
	 * @param array $data Lockout row (ip_address, country, city, time, user_agent).
	 */
	public static function on_lockout( $data ) {
		if ( ! is_array( $data ) ) {
			return;
		}
		self::dispatch( 'lockout', $data );
	}

	/**
	 * @param string $type Enumeration log type.
	 * @param string $username Username if any.
	 * @param string $ip_address IP.
	 */
	public static function on_enumeration( $type, $username, $ip_address ) {
		$details = array(
			'type'       => sanitize_text_field( (string) $type ),
			'username'   => sanitize_text_field( (string) $username ),
			'ip_address' => sanitize_text_field( (string) $ip_address ),
		);
		self::dispatch( 'user_enumeration', $details );
	}

	/**
	 * @param string $event_key lockout|failed_login|user_enumeration
	 * @param array  $context   Context for message / JSON body.
	 */
	private static function dispatch( $event_key, array $context ) {
		if ( ! \AIO_Login\AIO_Login::has_pro() ) {
			return;
		}

		$context = apply_filters( 'aio_login_notification_context', $context, $event_key );

		if ( self::should_send_slack( $event_key ) ) {
			$url = get_option( 'aio_login_notifications_slack_url', '' );
			if ( $url ) {
				$slack = self::build_slack_message( $event_key, $context );
				self::post_slack( $url, $slack );
			}
		}

		if ( self::should_send_webhook( $event_key ) ) {
			$url = get_option( 'aio_login_notifications_webhook_url', '' );
			if ( $url ) {
				$payload = self::build_webhook_payload( $event_key, $context );
				self::post_json( $url, $payload );
			}
		}
	}

	/**
	 * @param string $event_key Event key.
	 */
	private static function should_send_slack( $event_key ) {
		if ( 'on' !== get_option( 'aio_login_notifications_slack_enabled', 'off' ) ) {
			return false;
		}
		return self::event_enabled_for_channel( 'aio_login_notifications_slack_events', $event_key );
	}

	/**
	 * @param string $event_key Event key.
	 */
	private static function should_send_webhook( $event_key ) {
		if ( 'on' !== get_option( 'aio_login_notifications_webhook_enabled', 'off' ) ) {
			return false;
		}
		return self::event_enabled_for_channel( 'aio_login_notifications_webhook_events', $event_key );
	}

	/**
	 * @param string $option_name JSON option name.
	 * @param string $event_key   lockout|failed_login|user_enumeration
	 */
	private static function event_enabled_for_channel( $option_name, $event_key ) {
		$raw = get_option( $option_name, '' );
		$decoded = is_string( $raw ) ? json_decode( $raw, true ) : array();
		if ( ! is_array( $decoded ) ) {
			$decoded = array();
		}
		if ( 'lockout' === $event_key ) {
			return ! isset( $decoded['lockout'] ) || ! empty( $decoded['lockout'] );
		}
		if ( 'failed_login' === $event_key ) {
			return ! empty( $decoded['failed_login'] );
		}
		if ( 'user_enumeration' === $event_key ) {
			return ! empty( $decoded['user_enumeration'] );
		}
		return false;
	}

	/**
	 * Build Slack Block Kit payload (readable layout) + fallback text for notifications.
	 *
	 * @param string $event_key Event key.
	 * @param array  $context   Context.
	 * @return array{text:string,blocks:array}
	 */
	private static function build_slack_message( $event_key, array $context ) {
		$site     = get_bloginfo( 'name' );
		$site_url = home_url();
		$when     = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
		if ( isset( $context['time'] ) && is_numeric( $context['time'] ) ) {
			$when = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $context['time'] );
		}

		$blocks = array();

		switch ( $event_key ) {
			case 'lockout':
				$blocks = self::slack_blocks_lockout( $site, $site_url, $when, $context );
				$text   = sprintf(
					'[%s] %s — IP %s',
					$site,
					__( 'Lockout', 'change-wp-admin-login' ),
					self::slack_plain( $context['ip_address'] ?? '' )
				);
				break;
			case 'failed_login':
				$blocks = self::slack_blocks_failed_login( $site, $site_url, $when, $context );
				$text   = sprintf(
					'[%s] %s — %s',
					$site,
					__( 'Failed login', 'change-wp-admin-login' ),
					self::slack_plain( $context['user_login'] ?? '' )
				);
				break;
			case 'user_enumeration':
				$blocks = self::slack_blocks_enumeration( $site, $site_url, $when, $context );
				$text   = sprintf(
					'[%s] %s — %s',
					$site,
					__( 'User enumeration', 'change-wp-admin-login' ),
					self::slack_plain( $context['type'] ?? '' )
				);
				break;
			default:
				$text   = '[' . $site . '] ' . wp_json_encode( $context );
				$blocks = array(
					array(
						'type' => 'section',
						'text' => array(
							'type' => 'mrkdwn',
							'text' => self::slack_mrkdwn_value( $text ),
						),
					),
				);
		}

		$payload = array(
			'text'   => $text,
			'blocks' => $blocks,
		);

		return apply_filters( 'aio_login_slack_notification_payload', $payload, $event_key, $context );
	}

	/**
	 * Safe plain snippet for fallback / logs.
	 *
	 * @param string $str String.
	 */
	private static function slack_plain( $str ) {
		$s = wp_strip_all_tags( (string) $str );
		if ( strlen( $s ) > 500 ) {
			$s = substr( $s, 0, 497 ) . '…';
		}
		return $s;
	}

	/**
	 * Escape text used inside Slack mrkdwn sections.
	 *
	 * @param string $str String.
	 */
	private static function slack_mrkdwn_value( $str ) {
		$s = (string) $str;
		$s = str_replace( array( '&', '<' ), array( '&amp;', '&lt;' ), $s );
		if ( strlen( $s ) > 2800 ) {
			$s = substr( $s, 0, 2797 ) . '…';
		}
		return $s;
	}

	/**
	 * One mrkdwn field line: *Label*\nvalue
	 *
	 * @param string $label Label.
	 * @param string $value Value.
	 */
	private static function slack_field( $label, $value ) {
		$raw = trim( (string) $value );
		$v   = '' === $raw ? '—' : self::slack_mrkdwn_value( $raw );

		return array(
			'type' => 'mrkdwn',
			'text' => '*' . self::slack_mrkdwn_value( $label ) . "*\n" . $v,
		);
	}

	/**
	 * @param string $site     Site name.
	 * @param string $site_url Home URL.
	 * @param string $when     Formatted time.
	 * @param array  $context  Context.
	 */
	private static function slack_blocks_lockout( $site, $site_url, $when, array $context ) {
		$loc = trim( ( $context['country'] ?? '' ) . ', ' . ( $context['city'] ?? '' ), " ,\t\n\r\0\x0B" );

		return array(
			array(
				'type' => 'header',
				'text' => array(
					'type'  => 'plain_text',
					'text'  => '🔒 ' . __( 'IP lockout', 'change-wp-admin-login' ),
					'emoji' => true,
				),
			),
			array(
				'type'   => 'section',
				'fields' => array(
					self::slack_field( __( 'Site', 'change-wp-admin-login' ), $site ),
					self::slack_field( __( 'Time', 'change-wp-admin-login' ), $when ),
					self::slack_field( __( 'IP address', 'change-wp-admin-login' ), $context['ip_address'] ?? '' ),
					self::slack_field( __( 'Location', 'change-wp-admin-login' ), $loc !== '' ? $loc : '—' ),
				),
			),
			array(
				'type' => 'context',
				'elements' => array(
					array(
						'type' => 'mrkdwn',
						'text' => sprintf(
							/* translators: %s: site URL */
							__( 'AIO Login • <%s|Open site>', 'change-wp-admin-login' ),
							esc_url( $site_url )
						),
					),
				),
			),
		);
	}

	/**
	 * @param string $site     Site name.
	 * @param string $site_url Home URL.
	 * @param string $when     Formatted time.
	 * @param array  $context  Context.
	 */
	private static function slack_blocks_failed_login( $site, $site_url, $when, array $context ) {
		$loc = trim( ( $context['country'] ?? '' ) . ', ' . ( $context['city'] ?? '' ), " ,\t\n\r\0\x0B" );
		$ua  = isset( $context['user_agent'] ) ? self::slack_plain( $context['user_agent'] ) : '';

		$blocks = array(
			array(
				'type' => 'header',
				'text' => array(
					'type'  => 'plain_text',
					'text'  => '⚠️ ' . __( 'Failed login', 'change-wp-admin-login' ),
					'emoji' => true,
				),
			),
			array(
				'type'   => 'section',
				'fields' => array(
					self::slack_field( __( 'Site', 'change-wp-admin-login' ), $site ),
					self::slack_field( __( 'Time', 'change-wp-admin-login' ), $when ),
					self::slack_field( __( 'Username', 'change-wp-admin-login' ), $context['user_login'] ?? '' ),
					self::slack_field( __( 'IP address', 'change-wp-admin-login' ), $context['ip_address'] ?? '' ),
					self::slack_field( __( 'Location', 'change-wp-admin-login' ), $loc !== '' ? $loc : '—' ),
				),
			),
		);

		if ( $ua !== '' && $ua !== 'UNKNOWN' ) {
			$blocks[] = array(
				'type' => 'section',
				'text' => array(
					'type' => 'mrkdwn',
					'text' => '*' . self::slack_mrkdwn_value( __( 'User agent', 'change-wp-admin-login' ) ) . "*\n```" . self::slack_mrkdwn_value( $ua ) . '```',
				),
			);
		}

		$blocks[] = array(
			'type' => 'context',
			'elements' => array(
				array(
					'type' => 'mrkdwn',
					'text' => sprintf(
						/* translators: %s: site URL */
						__( 'AIO Login • <%s|Open site>', 'change-wp-admin-login' ),
						esc_url( $site_url )
					),
				),
			),
		);

		return $blocks;
	}

	/**
	 * @param string $site     Site name.
	 * @param string $site_url Home URL.
	 * @param string $when     Formatted time.
	 * @param array  $context  Context.
	 */
	private static function slack_blocks_enumeration( $site, $site_url, $when, array $context ) {
		$user = (string) ( $context['username'] ?? '' );

		return array(
			array(
				'type' => 'header',
				'text' => array(
					'type'  => 'plain_text',
					'text'  => '👁️ ' . __( 'User enumeration', 'change-wp-admin-login' ),
					'emoji' => true,
				),
			),
			array(
				'type'   => 'section',
				'fields' => array(
					self::slack_field( __( 'Site', 'change-wp-admin-login' ), $site ),
					self::slack_field( __( 'Time', 'change-wp-admin-login' ), $when ),
					self::slack_field( __( 'Event type', 'change-wp-admin-login' ), $context['type'] ?? '' ),
					self::slack_field( __( 'IP address', 'change-wp-admin-login' ), $context['ip_address'] ?? '' ),
					self::slack_field( __( 'Username', 'change-wp-admin-login' ), $user !== '' ? $user : '—' ),
				),
			),
			array(
				'type' => 'context',
				'elements' => array(
					array(
						'type' => 'mrkdwn',
						'text' => sprintf(
							/* translators: %s: site URL */
							__( 'AIO Login • <%s|Open site>', 'change-wp-admin-login' ),
							esc_url( $site_url )
						),
					),
				),
			),
		);
	}

	/**
	 * @param string $event_key Event key.
	 * @param array  $context   Context.
	 */
	private static function build_webhook_payload( $event_key, array $context ) {
		return apply_filters(
			'aio_login_webhook_notification_payload',
			array(
				'source'  => 'aio-login',
				'version' => defined( 'AIO_LOGIN__VERSION' ) ? AIO_LOGIN__VERSION : '',
				'event'   => $event_key,
				'time'    => gmdate( 'c' ),
				'site'    => array(
					'name' => get_bloginfo( 'name' ),
					'url'  => home_url(),
				),
				'context' => $context,
			),
			$event_key,
			$context
		);
	}

	/**
	 * @param string $url     Slack incoming webhook URL.
	 * @param array  $payload Must include `text` (fallback); optional `blocks` for Block Kit layout.
	 */
	private static function post_slack( $url, array $payload ) {
		$body = array(
			'text' => isset( $payload['text'] ) ? (string) $payload['text'] : '',
		);
		if ( ! empty( $payload['blocks'] ) && is_array( $payload['blocks'] ) ) {
			$body['blocks'] = $payload['blocks'];
		}

		$body = apply_filters( 'aio_login_slack_webhook_body', $body, $payload );

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 10,
				'headers' => array(
					'Content-Type' => 'application/json; charset=utf-8',
				),
				'body'    => wp_json_encode( $body ),
			)
		);
		if ( is_wp_error( $response ) ) {
			do_action( 'aio_login_slack_notification_error', $response, $url );
		}
	}

	/**
	 * @param string $url     Endpoint URL.
	 * @param array  $payload JSON-serializable payload.
	 */
	private static function post_json( $url, array $payload ) {
		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 10,
				'headers' => array(
					'Content-Type' => 'application/json; charset=utf-8',
				),
				'body'    => wp_json_encode( $payload ),
			)
		);
		if ( is_wp_error( $response ) ) {
			do_action( 'aio_login_webhook_notification_error', $response, $url );
		}
	}
}
