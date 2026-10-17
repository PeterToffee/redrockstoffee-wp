<?php
/**
 * Login / logout redirection (free): rules, REST, runtime filters.
 * Per-user and per-role conditions require Pro plan feature {@see login_redirection_advanced}.
 * Unreachable login URL on the highest-priority rule that defines one uses fallback (when enabled), not a lower-order rule.
 * Fallback applies to logout only when the top matching rule defines a logout URL that is unreachable (404 / HEAD failure).
 * No logout URL on the rule ("No logout redirect") uses WordPress default logout — never fallback.
 * Logout URL comes only from the top-priority matching rule (no fall-through to lower-order rules).
 * Order 0 = no priority; order-0 rules apply only when no rule with order 1+ exists on the site.
 *
 * @package AIO_Login
 */

namespace AIO_Login\Login_Controller;

use AIO_Login\Helper\Helper;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'AIO_Login\\Login_Controller\\Login_Redirection' ) ) {
	/**
	 * Login_Redirection
	 */
	final class Login_Redirection {

		/**
		 * Nonce action for REST mutations.
		 */
		private const NONCE_ACTION = 'aio-login-login-redirection';

		/**
		 * @var self|null
		 */
		private static $instance = null;

		/**
		 * Login_Redirection constructor.
		 */
		private function __construct() {
			add_filter( 'login_redirect', array( $this, 'apply_login_redirection' ), 100, 3 );
			add_filter( 'logout_redirect', array( $this, 'apply_logout_redirection' ), 100, 3 );
			add_action( 'rest_api_init', array( $this, 'rest_api_init' ) );
		}

		/**
		 * @return self
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Whether per-user / per-role rule conditions are allowed for this site license.
		 *
		 * @return bool
		 */
		public static function advanced_conditions_allowed() {
			return class_exists( '\AIO_Login_Pro\Plan\Plan_Features' )
				&& \AIO_Login_Pro\Plan\Plan_Features::can( 'login_redirection_advanced' );
		}

		/**
		 * Whether rule priority order (1, 2, …) is allowed for this site license.
		 *
		 * @return bool
		 */
		public static function rule_order_allowed() {
			if ( ! \AIO_Login\AIO_Login::has_pro() ) {
				return false;
			}
			if ( function_exists( 'aiologin_pro_can_use_premium_code' ) && ! aiologin_pro_can_use_premium_code() ) {
				return false;
			}
			if ( class_exists( '\AIO_Login_Pro\Plan\Plan_Features' ) ) {
				return \AIO_Login_Pro\Plan\Plan_Features::can( 'login_redirection' );
			}
			return true;
		}

		/**
		 * Register REST routes (aio-login namespace).
		 */
		public function rest_api_init() {
			register_rest_route(
				'aio-login/login-redirection',
				'/get-settings',
				array(
					'methods'             => 'GET',
					'permission_callback' => array( Helper::class, 'get_api_permission' ),
					'callback'            => array( $this, 'get_login_redirection_settings' ),
				)
			);

			register_rest_route(
				'aio-login/login-redirection',
				'/save-settings',
				array(
					'methods'             => 'POST',
					'permission_callback' => array( Helper::class, 'get_api_permission' ),
					'callback'            => array( $this, 'save_login_redirection_settings' ),
				)
			);

			register_rest_route(
				'aio-login/login-redirection',
				'/save-rule',
				array(
					'methods'             => 'POST',
					'permission_callback' => array( Helper::class, 'get_api_permission' ),
					'callback'            => array( $this, 'save_login_redirection_rule' ),
				)
			);

			register_rest_route(
				'aio-login/login-redirection',
				'/delete-rule',
				array(
					'methods'             => 'POST',
					'permission_callback' => array( Helper::class, 'get_api_permission' ),
					'callback'            => array( $this, 'delete_login_redirection_rule' ),
				)
			);
		}

		/**
		 * @return bool
		 */
		private function is_login_redirection_enabled() {
			return 'on' === get_option( 'aio_login_pro_login_redirection_enabled', 'off' );
		}

		/**
		 * @return bool
		 */
		private function is_login_redirection_fallback_enabled() {
			return 'on' === get_option( 'aio_login_pro_login_redirection_fallback_enabled', 'off' );
		}

		/**
		 * @return array<int, array<string, mixed>>
		 */
		private function get_login_redirection_rules() {
			$rules = get_option( 'aio_login_pro_login_redirection_rules', array() );
			if ( ! is_array( $rules ) ) {
				return array();
			}
			return array_map( array( $this, 'normalize_login_redirection_rule_row' ), $rules );
		}

		/**
		 * Fix stored page targets (e.g. page ID passed through esc_url_raw as "http://2").
		 *
		 * @param array<string, mixed> $rule Rule row.
		 * @return array<string, mixed>
		 */
		private function normalize_login_redirection_rule_row( $rule ) {
			if ( ! is_array( $rule ) ) {
				return array();
			}
			foreach ( array( 'login', 'logout' ) as $event ) {
				$type_key  = $event . '_target_type';
				$value_key = $event . '_target_value';
				if ( ! isset( $rule[ $type_key ] ) || 'page' !== $rule[ $type_key ] ) {
					continue;
				}
				$page_id = $this->normalize_stored_page_target_value( $rule[ $value_key ] ?? '' );
				$rule[ $value_key ] = $page_id > 0 ? (string) $page_id : '';
			}
			return $rule;
		}

		/**
		 * Rules returned to the admin UI / REST. When advanced conditions are not allowed,
		 * omit user / user_role rows (they stay in the DB for if Pro returns).
		 *
		 * @param array<int, array<string, mixed>>|null $rules Full list or null to load from options.
		 * @return array<int, array<string, mixed>>
		 */
		private function get_visible_login_redirection_rules( $rules = null ) {
			if ( null === $rules ) {
				$rules = $this->get_login_redirection_rules();
			}
			if ( ! is_array( $rules ) ) {
				return array();
			}
			if ( self::advanced_conditions_allowed() ) {
				return $rules;
			}
			return array_values(
				array_filter(
					$rules,
					static function( $rule ) {
						$t = isset( $rule['condition_type'] ) ? (string) $rule['condition_type'] : '';
						return 'user' !== $t && 'user_role' !== $t;
					}
				)
			);
		}

		/**
		 * @param mixed $raw Stored condition_value or request body fragment.
		 * @return int[]
		 */
		private function login_redirection_parse_condition_user_ids( $raw ) {
			if ( is_array( $raw ) ) {
				return array_values(
					array_unique(
						array_filter(
							array_map( 'absint', $raw )
						)
					)
				);
			}
			if ( is_numeric( $raw ) ) {
				$n = absint( $raw );
				return $n ? array( $n ) : array();
			}
			$s = (string) $raw;
			if ( '' === trim( $s ) ) {
				return array();
			}
			return array_values(
				array_unique(
					array_filter(
						array_map( 'absint', preg_split( '/\s*,\s*/', $s, -1, PREG_SPLIT_NO_EMPTY ) )
					)
				)
			);
		}

		/**
		 * @param int    $user_id WordPress user ID.
		 * @param string $stored  Saved condition_value string.
		 */
		private function login_redirection_user_matches_rule( $user_id, $stored ) {
			$user_id = absint( $user_id );
			if ( ! $user_id ) {
				return false;
			}
			$allowed = $this->login_redirection_parse_condition_user_ids( $stored );
			return ! empty( $allowed ) && in_array( $user_id, $allowed, true );
		}

		/**
		 * @param mixed $raw Stored condition_value or request body fragment.
		 * @return string[]
		 */
		private function login_redirection_parse_condition_role_slugs( $raw ) {
			global $wp_roles;

			if ( ! isset( $wp_roles->roles ) || ! is_array( $wp_roles->roles ) ) {
				return array();
			}

			$valid_keys = array_keys( $wp_roles->roles );
			$pieces     = array();

			if ( is_array( $raw ) ) {
				$pieces = $raw;
			} else {
				$s = (string) $raw;
				if ( '' === trim( $s ) ) {
					return array();
				}
				$pieces = preg_split( '/\s*,\s*/', $s, -1, PREG_SPLIT_NO_EMPTY );
			}

			$out = array();
			foreach ( $pieces as $piece ) {
				$key = sanitize_key( (string) $piece );
				if ( '' !== $key && in_array( $key, $valid_keys, true ) ) {
					$out[] = $key;
				}
			}

			return array_values( array_unique( $out ) );
		}

		/**
		 * Normalize condition value for duplicate-rule comparison (order-insensitive lists).
		 *
		 * @param string $type   all_users|user_role|user.
		 * @param string $stored Raw stored condition_value.
		 */
		private function login_redirection_normalize_condition_value_for_compare( $type, $stored ) {
			$type = (string) $type;
			if ( 'all_users' === $type ) {
				return '';
			}
			if ( 'user_role' === $type ) {
				$slugs = $this->login_redirection_parse_condition_role_slugs( $stored );
				sort( $slugs, SORT_STRING );
				return implode( ',', $slugs );
			}
			if ( 'user' === $type ) {
				$ids = $this->login_redirection_parse_condition_user_ids( $stored );
				sort( $ids, SORT_NUMERIC );
				return implode( ',', array_map( 'strval', $ids ) );
			}
			return (string) $stored;
		}

		/**
		 * @param \WP_User $user   User.
		 * @param string   $stored Comma-separated role keys (or legacy single role).
		 */
		private function login_redirection_user_role_matches_rule( $user, $stored ) {
			if ( ! $user instanceof \WP_User ) {
				return false;
			}

			$wanted = $this->login_redirection_parse_condition_role_slugs( $stored );
			if ( empty( $wanted ) ) {
				return false;
			}

			$overlap = array_intersect( $wanted, array_values( array_unique( (array) $user->roles ) ) );
			return ! empty( $overlap );
		}

		/**
		 * Sort key for rule "order": lower = higher priority. 0 or missing = no explicit priority (last among numbered orders).
		 *
		 * @param array<string, mixed> $rule Rule row.
		 * @return int
		 */
		private function login_redirection_rule_sort_priority( $rule ) {
			if ( ! is_array( $rule ) ) {
				return 999999;
			}
			if ( ! isset( $rule['order'] ) || '' === trim( (string) $rule['order'] ) ) {
				return 999999;
			}
			$n = (int) $rule['order'];
			if ( $n < 1 ) {
				return 999999;
			}
			return $n;
		}

		/**
		 * Whether the rule uses an explicit order (1, 2, …). Order 0 means no priority.
		 *
		 * @param array<string, mixed> $rule Rule row.
		 */
		private function login_redirection_rule_has_numbered_order( $rule ) {
			if ( ! is_array( $rule ) || ! isset( $rule['order'] ) ) {
				return false;
			}
			if ( '' === trim( (string) $rule['order'] ) ) {
				return false;
			}
			return (int) $rule['order'] >= 1;
		}

		/**
		 * Whether any saved rule uses explicit order (1+). When true, order-0 rules are ignored site-wide.
		 *
		 * @param array<int, array<string, mixed>> $rules All redirection rules.
		 */
		private function site_has_numbered_order_rules( $rules ) {
			foreach ( $rules as $rule ) {
				if ( $this->login_redirection_rule_has_numbered_order( $rule ) ) {
					return true;
				}
			}
			return false;
		}

		/**
		 * Rules eligible for this request after order policy:
		 * - If any rule on the site has order 1+, only matching numbered rules are used (order 0 never runs).
		 * - If no numbered rules exist site-wide, only matching order-0 / unset rules are used.
		 *
		 * @param array<int, array<string, mixed>> $matching Rules that match the current user.
		 * @param array<int, array<string, mixed>> $all_rules All saved rules.
		 * @return array<int, array<string, mixed>>
		 */
		private function resolve_matching_rules_for_order_pool( $matching, $all_rules ) {
			$use_numbered_pool = $this->site_has_numbered_order_rules( $all_rules );
			$pool              = array();

			foreach ( $matching as $rule ) {
				$is_numbered = $this->login_redirection_rule_has_numbered_order( $rule );
				if ( $use_numbered_pool && ! $is_numbered ) {
					continue;
				}
				if ( ! $use_numbered_pool && $is_numbered ) {
					continue;
				}
				$pool[] = $rule;
			}

			return $this->sort_matching_rules( $pool );
		}

		/**
		 * @param array<int, array<string, mixed>> $rules Matching rules.
		 * @return array<int, array<string, mixed>>
		 */
		private function sort_matching_rules( $rules ) {
			$type_rank = static function( $type ) {
				if ( 'user' === $type ) {
					return 0;
				}
				if ( 'user_role' === $type ) {
					return 1;
				}
				if ( 'all_users' === $type ) {
					return 2;
				}
				return 9;
			};

			usort(
				$rules,
				function( $a, $b ) use ( $type_rank ) {
					$a_order = $this->login_redirection_rule_sort_priority( $a );
					$b_order = $this->login_redirection_rule_sort_priority( $b );
					if ( $a_order !== $b_order ) {
						return $a_order <=> $b_order;
					}
					$ta = isset( $a['condition_type'] ) ? $type_rank( (string) $a['condition_type'] ) : 9;
					$tb = isset( $b['condition_type'] ) ? $type_rank( (string) $b['condition_type'] ) : 9;
					if ( $ta !== $tb ) {
						return $ta <=> $tb;
					}
					$a_created = isset( $a['created_at'] ) ? (int) $a['created_at'] : 0;
					$b_created = isset( $b['created_at'] ) ? (int) $b['created_at'] : 0;
					return $a_created <=> $b_created;
				}
			);
			return $rules;
		}

		/**
		 * @param string   $url  URL with placeholders.
		 * @param \WP_User $user User.
		 */
		private function replace_placeholders( $url, $user ) {
			$user_id   = $user instanceof \WP_User ? $user->ID : 0;
			$username  = $user instanceof \WP_User ? $user->user_login : '';
			$user_slug = $user instanceof \WP_User ? $user->user_nicename : '';

			return strtr(
				(string) $url,
				array(
					'{{user_id}}'     => (string) $user_id,
					'{{username}}'    => (string) $username,
					'{{user_slug}}'   => (string) $user_slug,
					'{{website_url}}' => (string) home_url(),
				)
			);
		}

		/**
		 * Page targets are stored as post IDs; recover IDs wrongly saved via esc_url_raw (e.g. "http://2").
		 *
		 * @param mixed $value Stored target value.
		 */
		private function normalize_stored_page_target_value( $value ) {
			if ( is_numeric( $value ) ) {
				return absint( $value );
			}
			$value = trim( (string) $value );
			if ( '' === $value ) {
				return 0;
			}
			if ( preg_match( '#^https?://(\d+)/?$#i', $value, $matches ) ) {
				return absint( $matches[1] );
			}
			return absint( $value );
		}

		/**
		 * @param string $type  page|custom.
		 * @param mixed  $value Raw request value.
		 */
		private function sanitize_rule_target_value_for_storage( $type, $value ) {
			if ( 'page' === $type ) {
				$page_id = $this->normalize_stored_page_target_value( $value );
				return $page_id > 0 ? (string) $page_id : '';
			}
			return esc_url_raw( (string) $value );
		}

		/**
		 * @param array<string, mixed> $rule  Rule row.
		 * @param string               $event login|logout.
		 * @param \WP_User             $user User.
		 */
		private function resolve_rule_target_url( $rule, $event, $user ) {
			$type_key  = 'login' === $event ? 'login_target_type' : 'logout_target_type';
			$value_key = 'login' === $event ? 'login_target_value' : 'logout_target_value';
			$type      = isset( $rule[ $type_key ] ) ? sanitize_text_field( $rule[ $type_key ] ) : 'custom';
			$value     = isset( $rule[ $value_key ] ) ? $rule[ $value_key ] : '';

			if ( empty( $value ) ) {
				return '';
			}

			if ( 'page' === $type ) {
				$page_id = $this->normalize_stored_page_target_value( $value );
				if ( ! $page_id ) {
					return '';
				}
				$url = get_permalink( $page_id );
				return is_string( $url ) ? $url : '';
			}

			return $this->replace_placeholders( (string) $value, $user );
		}

		/**
		 * Whether a rule target URL is considered usable (not 404 / unreachable).
		 * When false on the top-priority rule that defines a login URL, login fallback may apply (not a lower-order rule).
		 *
		 * @param string               $resolved_url Full URL after {@see resolve_rule_target_url()}.
		 * @param array<string, mixed> $rule         Rule row.
		 * @param string               $event      login|logout.
		 * @param \WP_User               $user       User.
		 */
		private function is_login_redirection_target_reachable( $resolved_url, $rule, $event, $user ) {
			$resolved_url = trim( (string) $resolved_url );
			if ( '' === $resolved_url ) {
				return false;
			}

			$filtered = apply_filters( 'aio_login_login_redirection_rule_target_reachable', null, $resolved_url, $rule, $event, $user );
			if ( null !== $filtered ) {
				return (bool) $filtered;
			}

			$type_key  = 'login' === $event ? 'login_target_type' : 'logout_target_type';
			$value_key = 'login' === $event ? 'login_target_value' : 'logout_target_value';
			$type      = isset( $rule[ $type_key ] ) ? sanitize_text_field( $rule[ $type_key ] ) : 'custom';

			if ( 'page' === $type ) {
				$pid = absint( isset( $rule[ $value_key ] ) ? $rule[ $value_key ] : 0 );
				return $this->is_login_redirection_page_target_reachable( $pid, $user );
			}

			$post_id = url_to_postid( $resolved_url );
			if ( $post_id > 0 ) {
				return $this->is_login_redirection_page_target_reachable( $post_id, $user );
			}

			// wp-admin (and similar) often fail server-side HEAD; trust them so valid rules are not skipped.
			if ( $this->is_login_redirection_trusted_same_site_url( $resolved_url ) ) {
				return true;
			}

			// Other same-site URLs: when fallback is off, use the rule URL even if it 404s.
			// When fallback is on, run HEAD so bad paths (e.g. /w) can use login fallback instead of a lower-order rule.
			if ( $this->is_login_redirection_same_site_url( $resolved_url ) && ! $this->is_login_redirection_fallback_enabled() ) {
				return true;
			}

			if ( apply_filters( 'aio_login_login_redirection_skip_custom_url_head_check', false, $resolved_url, $rule, $event, $user ) ) {
				return true;
			}

			return $this->is_login_redirection_url_reachable_via_http( $resolved_url );
		}

		/**
		 * Whether the URL belongs to this WordPress site (same host as home_url).
		 *
		 * @param string $url Candidate redirect URL.
		 */
		private function is_login_redirection_same_site_url( $url ) {
			$url  = trim( (string) $url );
			$home = home_url( '/' );
			if ( '' === $url || '' === $home ) {
				return false;
			}

			$url_host  = wp_parse_url( $url, PHP_URL_HOST );
			$home_host = wp_parse_url( $home, PHP_URL_HOST );
			if ( ! is_string( $url_host ) || '' === $url_host || ! is_string( $home_host ) || '' === $home_host ) {
				return false;
			}

			return strtolower( $url_host ) === strtolower( $home_host );
		}

		/**
		 * Same-site URLs that should skip HTTP HEAD (false negatives on wp-admin, etc.).
		 *
		 * @param string $url Candidate redirect URL.
		 */
		private function is_login_redirection_trusted_same_site_url( $url ) {
			if ( ! $this->is_login_redirection_same_site_url( $url ) ) {
				return false;
			}

			$path = wp_parse_url( $url, PHP_URL_PATH );
			if ( ! is_string( $path ) || '' === $path ) {
				return false;
			}
			$path = trailingslashit( $path );

			$admin_path = wp_parse_url( admin_url(), PHP_URL_PATH );
			if ( is_string( $admin_path ) && '' !== $admin_path ) {
				$admin_path = trailingslashit( $admin_path );
				if ( 0 === strpos( $path, $admin_path ) ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * @param int      $page_id Page (or post) ID stored on the rule.
		 * @param \WP_User $user    User being redirected.
		 */
		private function is_login_redirection_page_target_reachable( $page_id, $user ) {
			$page_id = absint( $page_id );
			if ( ! $page_id ) {
				return false;
			}
			$post = get_post( $page_id );
			if ( ! ( $post instanceof \WP_Post ) ) {
				return false;
			}
			if ( 'trash' === $post->post_status ) {
				return false;
			}
			if ( is_post_status_viewable( $post ) ) {
				return true;
			}
			return $user instanceof \WP_User && user_can( $user, 'read_post', $post->ID );
		}

		/**
		 * Lightweight HTTP check for custom URLs (404 / connection failure → unreachable).
		 *
		 * @param string $url Full URL.
		 */
		private function is_login_redirection_url_reachable_via_http( $url ) {
			$url = trim( (string) $url );
			if ( '' === $url ) {
				return false;
			}
			$url = preg_replace( '/#.*$/', '', $url );

			$cache_ttl = (int) apply_filters( 'aio_login_login_redirection_url_check_cache_ttl', 120 );
			$cache_key = 'aio_lr_url_ok_' . md5( $url );
			if ( $cache_ttl > 0 ) {
				$cached = get_transient( $cache_key );
				if ( false !== $cached ) {
					return (bool) $cached;
				}
			}

			$timeout = (int) apply_filters( 'aio_login_login_redirection_url_check_timeout', 3 );
			$timeout = max( 1, min( 15, $timeout ) );

			$response = wp_remote_head(
				$url,
				array(
					'timeout'            => $timeout,
					'redirection'        => 5,
					'sslverify'          => apply_filters( 'https_local_ssl_verify', false ),
					'reject_unsafe_urls' => true,
				)
			);

			$ok = false;
			if ( is_wp_error( $response ) ) {
				$ok = false;
			} else {
				$code = (int) wp_remote_retrieve_response_code( $response );
				if ( 405 === $code ) {
					$ok = true;
				} elseif ( $code >= 200 && $code < 400 ) {
					$ok = true;
				}
			}

			if ( $cache_ttl > 0 ) {
				set_transient( $cache_key, $ok ? 1 : 0, $cache_ttl );
			}

			return $ok;
		}

		/**
		 * Rules matching the user, sorted by order policy.
		 *
		 * @param \WP_User $user User.
		 * @return array<int, array<string, mixed>>
		 */
		private function collect_matching_rules_for_user( $user ) {
			if ( ! ( $user instanceof \WP_User ) ) {
				return array();
			}

			$rules = $this->get_login_redirection_rules();
			if ( empty( $rules ) ) {
				return array();
			}

			$advanced = self::advanced_conditions_allowed();
			$matching = array();
			foreach ( $rules as $rule ) {
				if ( ! isset( $rule['condition_type'] ) ) {
					continue;
				}
				$condition_type = (string) $rule['condition_type'];
				if ( ! $advanced && ( 'user' === $condition_type || 'user_role' === $condition_type ) ) {
					continue;
				}
				$value = isset( $rule['condition_value'] ) ? (string) $rule['condition_value'] : '';

				if ( 'all_users' === $condition_type ) {
					$matching[] = $rule;
				} elseif ( 'user_role' === $condition_type && $this->login_redirection_user_role_matches_rule( $user, $value ) ) {
					$matching[] = $rule;
				} elseif ( 'user' === $condition_type && $this->login_redirection_user_matches_rule( (int) $user->ID, $value ) ) {
					$matching[] = $rule;
				}
			}

			if ( empty( $matching ) ) {
				return array();
			}

			return $this->resolve_matching_rules_for_order_pool( $matching, $rules );
		}

		/**
		 * Whether the rule row stores a logout destination (not "No logout redirect").
		 *
		 * @param array<string, mixed> $rule Rule row.
		 */
		private function rule_has_logout_url_configured( $rule ) {
			if ( ! is_array( $rule ) ) {
				return false;
			}
			$value = isset( $rule['logout_target_value'] ) ? trim( (string) $rule['logout_target_value'] ) : '';
			return '' !== $value;
		}

		/**
		 * Top-priority matching rule defines a logout URL (empty value = WordPress default logout, no fallback).
		 *
		 * @param \WP_User $user User.
		 */
		private function top_matching_rule_has_logout_url_configured( $user ) {
			$matching = $this->collect_matching_rules_for_user( $user );
			if ( empty( $matching ) ) {
				return false;
			}
			return $this->rule_has_logout_url_configured( $matching[0] );
		}

		/**
		 * @param \WP_User $user  User.
		 * @param string   $event login|logout.
		 */
		private function find_matching_rule_url( $user, $event ) {
			if ( ! ( $user instanceof \WP_User ) ) {
				return '';
			}

			$matching = $this->collect_matching_rules_for_user( $user );
			if ( empty( $matching ) ) {
				return '';
			}

			// Logout uses only the top-priority matching rule. If that rule has no logout URL, do not
			// fall through to a lower-priority rule (e.g. login from order 1, logout from order 0).
			if ( 'logout' === $event ) {
				return $this->resolve_logout_url_for_top_matching_rule( $matching, $user );
			}

			$fallback_on = $this->is_login_redirection_fallback_enabled();
			foreach ( $matching as $rule ) {
				$url = $this->resolve_rule_target_url( $rule, $event, $user );
				if ( '' === trim( (string) $url ) ) {
					continue;
				}
				// First matching rule (by order) that defines a login URL — do not fall through to lower-order rules.
				$reachable = $this->is_login_redirection_target_reachable( $url, $rule, $event, $user );
				if ( ! $reachable && $fallback_on ) {
					return '';
				}
				return $url;
			}

			return '';
		}

		/**
		 * Logout redirect from the single highest-priority rule that matches this user.
		 *
		 * @param array<int, array<string, mixed>> $matching Sorted matching rules.
		 * @param \WP_User                         $user     User.
		 * @return string
		 */
		private function resolve_logout_url_for_top_matching_rule( $matching, $user ) {
			if ( empty( $matching ) || ! ( $user instanceof \WP_User ) ) {
				return '';
			}

			$rule = $matching[0];
			$url  = $this->resolve_rule_target_url( $rule, 'logout', $user );
			if ( '' === trim( (string) $url ) ) {
				return '';
			}

			if ( ! $this->is_login_redirection_target_reachable( $url, $rule, 'logout', $user ) ) {
				if ( $this->is_login_redirection_fallback_enabled() ) {
					return '';
				}
			}

			return $url;
		}

		/**
		 * Resolve redirect for a matched rule URL. When fallback is off, prefer the rule URL even if
		 * wp_validate_redirect() would otherwise cause WordPress to fall back to wp-admin.
		 *
		 * @param string $rule_url Resolved rule target.
		 * @param string $event    login|logout.
		 * @return string Empty if rule URL is empty.
		 */
		private function get_rule_redirect_url( $rule_url, $event = 'login' ) {
			$rule_url = trim( (string) $rule_url );
			if ( '' === $rule_url ) {
				return '';
			}

			$safe = $this->get_safe_redirect_url( $rule_url, '' );
			if ( '' !== $safe ) {
				return $safe;
			}

			// Matched rule exists — do not drop to login fallback for trusted / same-site targets (see reachability).
			if ( $this->is_login_redirection_trusted_same_site_url( $rule_url )
				|| ( $this->is_login_redirection_same_site_url( $rule_url ) && ! $this->is_login_redirection_fallback_enabled() ) ) {
				$this->register_allowed_redirect_host_for_url( $rule_url );
				$validated = wp_validate_redirect( $rule_url, '' );
				return '' !== $validated ? $validated : $rule_url;
			}

			// Allow fallback when wp_validate_redirect rejects the rule URL (login or configured logout).
			if ( $this->is_login_redirection_fallback_enabled() ) {
				return '';
			}

			$this->register_allowed_redirect_host_for_url( $rule_url );
			$validated = wp_validate_redirect( $rule_url, '' );
			return '' !== $validated ? $validated : $rule_url;
		}

		/**
		 * @param \WP_User $user                    User.
		 * @param string   $requested_redirect_to Requested redirect.
		 */
		private function get_fallback_url( $user, $requested_redirect_to = '' ) {
			if ( ! $this->is_login_redirection_fallback_enabled() ) {
				return '';
			}

			$type = get_option( 'aio_login_pro_login_redirection_fallback_type', 'dashboard' );
			if ( 'previous' === $type ) {
				$type = 'dashboard';
			}

			if ( 'custom' === $type ) {
				$custom = get_option( 'aio_login_pro_login_redirection_fallback_custom_url', '' );
				return $this->replace_placeholders( (string) $custom, $user );
			}

			if ( is_string( $requested_redirect_to ) && '' !== trim( $requested_redirect_to ) ) {
				return $requested_redirect_to;
			}
			return admin_url();
		}

		/**
		 * Allow this request's wp_safe_redirect() to accept an off-site URL set in a rule
		 * (wp_validate_redirect only allows the site host unless the host is listed here).
		 *
		 * @param string $url Full redirect URL (after placeholders).
		 */
		private function register_allowed_redirect_host_for_url( $url ) {
			$url = trim( (string) $url );
			if ( '' === $url ) {
				return;
			}
			$parsed = wp_parse_url( $url );
			if ( empty( $parsed['host'] ) ) {
				return;
			}
			if ( isset( $parsed['scheme'] ) && 'http' !== $parsed['scheme'] && 'https' !== $parsed['scheme'] ) {
				return;
			}
			$host = (string) $parsed['host'];
			add_filter(
				'allowed_redirect_hosts',
				static function ( $hosts ) use ( $host ) {
					$hosts   = is_array( $hosts ) ? $hosts : array();
					$hosts[] = $host;
					return array_values( array_unique( $hosts ) );
				}
			);
		}

		/**
		 * @param string $url     Candidate URL.
		 * @param string $default Default if invalid.
		 */
		private function get_safe_redirect_url( $url, $default ) {
			$url = trim( (string) $url );
			if ( '' === $url ) {
				return $default;
			}
			$this->register_allowed_redirect_host_for_url( $url );
			$validated = wp_validate_redirect( $url, '' );
			if ( '' === $validated ) {
				return $default;
			}
			return $validated;
		}

		/**
		 * @param string         $redirect_to           Default redirect.
		 * @param string         $requested_redirect_to Requested redirect.
		 * @param \WP_User|\WP_Error $user              User.
		 * @return string
		 */
		public function apply_login_redirection( $redirect_to, $requested_redirect_to, $user ) {
			if ( ! $this->is_login_redirection_enabled() || ! ( $user instanceof \WP_User ) ) {
				return $redirect_to;
			}

			$rule_url = $this->find_matching_rule_url( $user, 'login' );
			if ( ! empty( $rule_url ) ) {
				$rule_redirect = $this->get_rule_redirect_url( $rule_url );
				if ( '' !== $rule_redirect ) {
					return $rule_redirect;
				}
			}

			$fallback = $this->get_fallback_url( $user, $requested_redirect_to );
			if ( ! empty( $fallback ) ) {
				return $this->get_safe_redirect_url( $fallback, admin_url() );
			}

			return $redirect_to;
		}

		/**
		 * @param string         $redirect_to           Default redirect.
		 * @param string         $requested_redirect_to Requested redirect.
		 * @param \WP_User|\WP_Error $user              User.
		 * @return string
		 */
		public function apply_logout_redirection( $redirect_to, $requested_redirect_to, $user ) {
			if ( ! $this->is_login_redirection_enabled() || ! ( $user instanceof \WP_User ) ) {
				return $redirect_to;
			}

			$rule_defines_logout = $this->top_matching_rule_has_logout_url_configured( $user );
			$rule_url            = $this->find_matching_rule_url( $user, 'logout' );
			if ( ! empty( $rule_url ) ) {
				$rule_redirect = $this->get_rule_redirect_url( $rule_url, 'logout' );
				if ( '' !== $rule_redirect ) {
					return $rule_redirect;
				}
			}

			// Fallback when the rule has a logout URL but it is unreachable/invalid — not when "No logout redirect".
			if ( $rule_defines_logout && $this->is_login_redirection_fallback_enabled() ) {
				$fallback = $this->get_fallback_url( $user, $requested_redirect_to );
				if ( ! empty( $fallback ) ) {
					return $this->get_safe_redirect_url( $fallback, $redirect_to );
				}
			}

			return $redirect_to;
		}

		/**
		 * @param \WP_REST_Request $request Request.
		 * @return \WP_REST_Response|\WP_Error
		 */
		public function get_login_redirection_settings( $request ) {
			unset( $request );

			$pages = get_pages(
				array(
					'sort_column' => 'post_title',
					'sort_order'  => 'ASC',
				)
			);
			$page_options = array();
			foreach ( (array) $pages as $page ) {
				$page_options[] = array(
					'value' => (string) $page->ID,
					'label' => $page->post_title . ' (#' . $page->ID . ')',
					'url'   => get_permalink( $page->ID ),
				);
			}

			global $wp_roles;
			$role_options = array();
			if ( $wp_roles && isset( $wp_roles->roles ) ) {
				foreach ( $wp_roles->roles as $role_key => $role_data ) {
					$role_options[] = array(
						'value' => (string) $role_key,
						'label' => isset( $role_data['name'] ) ? (string) $role_data['name'] : (string) $role_key,
					);
				}
			}

			$user_options = array();
			$users        = get_users(
				array(
					'number'  => 200,
					'orderby' => 'login',
					'order'   => 'ASC',
					'fields'  => array( 'ID', 'user_login' ),
				)
			);
			foreach ( (array) $users as $u ) {
				$user_options[] = array(
					'value' => (string) $u->ID,
					'label' => (string) $u->user_login,
				);
			}

			$admin_path = wp_parse_url( admin_url(), PHP_URL_PATH );
			if ( ! is_string( $admin_path ) || '' === $admin_path ) {
				$admin_path = '/wp-admin/';
			}
			$admin_path = trailingslashit( $admin_path );

			$fallback_type = get_option( 'aio_login_pro_login_redirection_fallback_type', 'dashboard' );
			if ( 'previous' === $fallback_type ) {
				$fallback_type = 'dashboard';
				update_option( 'aio_login_pro_login_redirection_fallback_type', 'dashboard' );
			}

			return rest_ensure_response(
				array(
					'settings' => array(
						'enabled'               => 'on' === get_option( 'aio_login_pro_login_redirection_enabled', 'off' ),
						'fallback_enabled'      => 'on' === get_option( 'aio_login_pro_login_redirection_fallback_enabled', 'off' ),
						'fallback_type'         => $fallback_type,
						'fallback_custom_url'   => get_option( 'aio_login_pro_login_redirection_fallback_custom_url', '' ),
					),
					'rules'    => $this->get_visible_login_redirection_rules(),
					'meta'     => array(
						'pages'                 => $page_options,
						'roles'                 => $role_options,
						'users'                 => $user_options,
						'fallback_dashboard_path' => $admin_path,
						'advanced_conditions'   => self::advanced_conditions_allowed(),
						'rule_order_allowed'    => self::rule_order_allowed(),
					),
					'nonce'    => wp_create_nonce( self::NONCE_ACTION ),
				)
			);
		}

		/**
		 * @param \WP_REST_Request $request Request.
		 * @return \WP_REST_Response|\WP_Error
		 */
		public function save_login_redirection_settings( $request ) {
			$params = $request->get_params();
			if ( ! isset( $params['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $params['_wpnonce'] ) ), self::NONCE_ACTION ) ) {
				return new \WP_Error( 'invalid_nonce', __( 'Invalid nonce', 'change-wp-admin-login' ), array( 'status' => 403 ) );
			}

			$settings = isset( $params['settings'] ) && is_array( $params['settings'] ) ? $params['settings'] : array();
			update_option( 'aio_login_pro_login_redirection_enabled', ! empty( $settings['enabled'] ) ? 'on' : 'off' );
			update_option( 'aio_login_pro_login_redirection_fallback_enabled', ! empty( $settings['fallback_enabled'] ) ? 'on' : 'off' );

			$fallback_type = isset( $settings['fallback_type'] ) ? sanitize_text_field( $settings['fallback_type'] ) : 'dashboard';
			if ( 'previous' === $fallback_type ) {
				$fallback_type = 'dashboard';
			}
			if ( ! in_array( $fallback_type, array( 'dashboard', 'custom' ), true ) ) {
				$fallback_type = 'dashboard';
			}
			update_option( 'aio_login_pro_login_redirection_fallback_type', $fallback_type );
			update_option( 'aio_login_pro_login_redirection_fallback_custom_url', isset( $settings['fallback_custom_url'] ) ? esc_url_raw( $settings['fallback_custom_url'] ) : '' );

			return rest_ensure_response( array( 'message' => esc_html__( 'Settings saved successfully.', 'change-wp-admin-login' ) ) );
		}

		/**
		 * @param \WP_REST_Request $request Request.
		 * @return \WP_REST_Response|\WP_Error
		 */
		public function save_login_redirection_rule( $request ) {
			$params = $request->get_params();
			if ( ! isset( $params['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $params['_wpnonce'] ) ), self::NONCE_ACTION ) ) {
				return new \WP_Error( 'invalid_nonce', __( 'Invalid nonce', 'change-wp-admin-login' ), array( 'status' => 403 ) );
			}

			$condition_type = isset( $params['condition_type'] ) ? sanitize_text_field( $params['condition_type'] ) : 'all_users';
			if ( ! in_array( $condition_type, array( 'all_users', 'user_role', 'user' ), true ) ) {
				return new \WP_Error( 'invalid_condition', __( 'Invalid condition type.', 'change-wp-admin-login' ), array( 'status' => 400 ) );
			}

			if ( ( 'user' === $condition_type || 'user_role' === $condition_type ) && ! self::advanced_conditions_allowed() ) {
				return new \WP_Error(
					'plan_restricted',
					__( 'User and role-based redirect rules require an eligible Pro plan.', 'change-wp-admin-login' ),
					array( 'status' => 403 )
				);
			}

			$existing_id = isset( $params['id'] ) ? sanitize_text_field( $params['id'] ) : '';
			$rule_id     = '' !== $existing_id ? $existing_id : 'rule_' . wp_generate_password( 12, false, false ) . '_' . time();

			$condition_value_stored = '';
			if ( 'all_users' === $condition_type ) {
				$condition_value_stored = '';
			} elseif ( 'user_role' === $condition_type ) {
				$role_slugs = $this->login_redirection_parse_condition_role_slugs(
					isset( $params['condition_value'] ) ? $params['condition_value'] : array()
				);
				if ( empty( $role_slugs ) ) {
					return new \WP_Error(
						'empty_role_condition',
						__( 'Please select at least one role.', 'change-wp-admin-login' ),
						array( 'status' => 400 )
					);
				}
				$condition_value_stored = implode( ',', $role_slugs );
			} elseif ( 'user' === $condition_type ) {
				$user_ids = $this->login_redirection_parse_condition_user_ids( isset( $params['condition_value'] ) ? $params['condition_value'] : array() );
				if ( empty( $user_ids ) ) {
					return new \WP_Error(
						'empty_user_condition',
						__( 'Please select at least one user.', 'change-wp-admin-login' ),
						array( 'status' => 400 )
					);
				}
				$condition_value_stored = implode( ',', $user_ids );
			}

			$login_target_type  = isset( $params['login_target_type'] ) && 'page' === $params['login_target_type'] ? 'page' : 'custom';
			$logout_target_type = isset( $params['logout_target_type'] ) && 'page' === $params['logout_target_type'] ? 'page' : 'custom';

			$rule = array(
				'id'                  => $rule_id,
				'condition_type'      => $condition_type,
				'condition_value'     => $condition_value_stored,
				'login_target_type'   => $login_target_type,
				'login_target_value'  => $this->sanitize_rule_target_value_for_storage(
					$login_target_type,
					isset( $params['login_target_value'] ) ? $params['login_target_value'] : ''
				),
				'logout_target_type'  => $logout_target_type,
				'logout_target_value' => $this->sanitize_rule_target_value_for_storage(
					$logout_target_type,
					isset( $params['logout_target_value'] ) ? $params['logout_target_value'] : ''
				),
				'order'               => max( 0, absint( isset( $params['order'] ) ? $params['order'] : 0 ) ),
				'created_at'          => time(),
			);

			if ( ! self::rule_order_allowed() ) {
				$rule['order'] = 0;
			}

			if ( 'custom' === $rule['login_target_type'] && '' === trim( (string) $rule['login_target_value'] ) ) {
				return new \WP_Error(
					'empty_login_url',
					__( 'Please enter a login redirect URL when Custom URL is selected.', 'change-wp-admin-login' ),
					array( 'status' => 400 )
				);
			}
			if ( 'page' === $rule['login_target_type'] && '' === trim( (string) $rule['login_target_value'] ) ) {
				return new \WP_Error(
					'empty_login_page',
					__( 'Please choose a login redirect destination.', 'change-wp-admin-login' ),
					array( 'status' => 400 )
				);
			}

			$rules = $this->get_login_redirection_rules();
			$new_norm = $this->login_redirection_normalize_condition_value_for_compare( $condition_type, $condition_value_stored );
			$order_cmp = (int) $rule['order'];
			foreach ( $rules as $existing_rule ) {
				if ( isset( $existing_rule['id'] ) && $existing_rule['id'] === $rule_id ) {
					continue;
				}
				$ex_type = isset( $existing_rule['condition_type'] ) ? (string) $existing_rule['condition_type'] : '';
				if ( $ex_type !== $condition_type ) {
					continue;
				}
				$ex_order = isset( $existing_rule['order'] ) ? max( 0, absint( $existing_rule['order'] ) ) : 0;
				if ( $ex_order !== $order_cmp ) {
					continue;
				}
				$ex_val = isset( $existing_rule['condition_value'] ) ? (string) $existing_rule['condition_value'] : '';
				$ex_norm = $this->login_redirection_normalize_condition_value_for_compare( $ex_type, $ex_val );
				if ( $ex_norm === $new_norm ) {
					return new \WP_Error(
						'duplicate_rule',
						__( 'This rule already exists. Please update the existing rule or remove it before creating a new one.', 'change-wp-admin-login' ),
						array( 'status' => 409 )
					);
				}
			}

			$updated = false;
			foreach ( $rules as $index => $existing_rule ) {
				if ( isset( $existing_rule['id'] ) && $existing_rule['id'] === $rule_id ) {
					$rule['created_at'] = isset( $existing_rule['created_at'] ) ? intval( $existing_rule['created_at'] ) : $rule['created_at'];
					$rules[ $index ]    = $rule;
					$updated            = true;
					break;
				}
			}
			if ( ! $updated ) {
				$rules[] = $rule;
			}
			update_option( 'aio_login_pro_login_redirection_rules', $rules );

			return rest_ensure_response(
				array(
					'message' => $updated ? esc_html__( 'Rule updated successfully.', 'change-wp-admin-login' ) : esc_html__( 'Rule saved successfully.', 'change-wp-admin-login' ),
					'rules'   => $this->get_visible_login_redirection_rules( $rules ),
				)
			);
		}

		/**
		 * @param \WP_REST_Request $request Request.
		 * @return \WP_REST_Response|\WP_Error
		 */
		public function delete_login_redirection_rule( $request ) {
			$params = $request->get_params();
			if ( ! isset( $params['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $params['_wpnonce'] ) ), self::NONCE_ACTION ) ) {
				return new \WP_Error( 'invalid_nonce', __( 'Invalid nonce', 'change-wp-admin-login' ), array( 'status' => 403 ) );
			}

			$delete_id = isset( $params['id'] ) ? sanitize_text_field( $params['id'] ) : '';
			$rules     = array_values(
				array_filter(
					$this->get_login_redirection_rules(),
					function( $rule ) use ( $delete_id ) {
						return ! isset( $rule['id'] ) || $rule['id'] !== $delete_id;
					}
				)
			);
			update_option( 'aio_login_pro_login_redirection_rules', $rules );

			return rest_ensure_response(
				array(
					'message' => esc_html__( 'Rule deleted successfully.', 'change-wp-admin-login' ),
					'rules'   => $this->get_visible_login_redirection_rules( $rules ),
				)
			);
		}
	}
}
