<?php

namespace VIWEC\INC;

use WP_Error;

defined( 'ABSPATH' ) || exit;

class System_Status {

	public const PAGE_SLUG     = 'viwec-system-status';
	public const SUPPORT_EMAIL = 'support@villatheme.com';
	public const AJAX_ACTION   = 'viwec_system_status_report';
	public const RATE_OPTION   = 'viwec_system_status_report_sent';
	public const RATE_SECONDS  = 300;
	public const NONCE_ACTION  = 'viwec_system_status_report';

	protected static $instance = null;

	public static function init() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_menu' ], 35 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, [ $this, 'ajax_send_report' ] );
	}

	public function register_menu() {
		add_submenu_page(
			'edit.php?post_type=viwec_template',
			__( 'System Status', 'viwec-email-template-customizer' ),
			__( 'System Status', 'viwec-email-template-customizer' ),
			apply_filters( 'viwec_system_status_capability', 'manage_options' ),
			self::PAGE_SLUG,
			[ $this, 'render_page' ]
		);
	}

	public function enqueue_assets( $hook ) {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( self::PAGE_SLUG !== $page && false === strpos( (string) $hook, self::PAGE_SLUG ) ) {
			return;
		}

		wp_enqueue_style(
			VIWEC_SLUG . '-system-status',
			VIWEC_CSS . 'system-status.css',
			[],
			VIWEC_VER
		);

		wp_enqueue_script(
			VIWEC_SLUG . '-system-status',
			VIWEC_JS . 'system-status.js',
			[ 'jquery' ],
			VIWEC_VER,
			true
		);

		wp_localize_script(
			VIWEC_SLUG . '-system-status',
			'viwecSystemStatus',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => self::AJAX_ACTION,
				'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
				'i18n'    => [
					'sending' => __( 'Sending report…', 'viwec-email-template-customizer' ),
					'send'    => __( 'Send support request', 'viwec-email-template-customizer' ),
					'confirm' => __( 'Send a full system status report and your issue description to VillaTheme support (support@villatheme.com)?', 'viwec-email-template-customizer' ),
					'failed'  => __( 'Unable to send the report. Please try again.', 'viwec-email-template-customizer' ),
				],
			]
		);
	}

	public function render_page() {
		if ( ! current_user_can( apply_filters( 'viwec_system_status_capability', 'manage_options' ) ) ) {
			return;
		}

		$report = $this->collect_report();
		?>
		<div class="wrap viwec-system-status">
			<h1><?php esc_html_e( 'System Status', 'viwec-email-template-customizer' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Environment and Email Template Customizer configuration used when diagnosing issues. Use the form below to email this report to VillaTheme support.', 'viwec-email-template-customizer' ); ?>
			</p>

			<div class="viwec-system-status-report-box">
				<h2><?php esc_html_e( 'Request support', 'viwec-email-template-customizer' ); ?></h2>
				<p class="description">
					<?php
					printf(
						/* translators: %s: support email */
						esc_html__( 'Sends the full status report below to %s. Describe the problem so support can reproduce it.', 'viwec-email-template-customizer' ),
						esc_html( self::SUPPORT_EMAIL )
					);
					?>
				</p>
				<p>
					<label for="viwec-system-status-issue"><strong><?php esc_html_e( 'Issue description', 'viwec-email-template-customizer' ); ?></strong></label>
				</p>
				<textarea
					id="viwec-system-status-issue"
					class="large-text"
					rows="5"
					placeholder="<?php esc_attr_e( 'What went wrong? Steps to reproduce, expected vs actual, screenshots URL if any…', 'viwec-email-template-customizer' ); ?>"
				></textarea>
				<p>
					<button type="button" class="button button-primary" id="viwec-system-status-send">
						<?php esc_html_e( 'Send support request', 'viwec-email-template-customizer' ); ?>
					</button>
					<span class="viwec-system-status-send-status" id="viwec-system-status-send-status" aria-live="polite"></span>
				</p>
			</div>

			<?php
			$this->render_section( __( 'WordPress', 'viwec-email-template-customizer' ), $report['wordpress'] );
			$this->render_section( __( 'Server', 'viwec-email-template-customizer' ), $report['server'] );
			$this->render_section( __( 'Email Template Customizer', 'viwec-email-template-customizer' ), $report['plugin'] );
			$this->render_section( __( 'Dependencies', 'viwec-email-template-customizer' ), $report['dependencies'] );
			$this->render_section( __( 'Active theme & plugins', 'viwec-email-template-customizer' ), $report['software'] );
			?>
		</div>
		<?php
	}

	public function ajax_send_report() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( apply_filters( 'viwec_system_status_capability', 'manage_options' ) ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'viwec-email-template-customizer' ) ], 403 );
		}

		if ( (int) get_transient( self::RATE_OPTION ) > 0 ) {
			wp_send_json_error(
				[
					'message' => __( 'A report was sent recently. Please wait a few minutes before sending again.', 'viwec-email-template-customizer' ),
				],
				429
			);
		}

		$issue = isset( $_POST['issue'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['issue'] ) ) : '';
		$sent  = $this->send_report_email( $issue );

		if ( is_wp_error( $sent ) ) {
			wp_send_json_error( [ 'message' => $sent->get_error_message() ], 500 );
		}

		set_transient( self::RATE_OPTION, time(), self::RATE_SECONDS );

		wp_send_json_success(
			[
				'message' => sprintf(
					/* translators: %s: support email */
					__( 'Support request sent to %s.', 'viwec-email-template-customizer' ),
					self::SUPPORT_EMAIL
				),
			]
		);
	}

	public function collect_report() {
		$report = [
			'wordpress'    => $this->get_wordpress_rows(),
			'server'       => $this->get_server_rows(),
			'plugin'       => $this->get_plugin_rows(),
			'dependencies' => $this->get_dependency_rows(),
			'software'     => $this->get_software_rows(),
		];

		return apply_filters( 'viwec_system_status_report', $report );
	}

	private function get_wordpress_rows() {
		global $wp_version;

		$required_wp = '5.0';
		$wp_ok       = version_compare( (string) $wp_version, $required_wp, '>=' );
		$multisite   = is_multisite() ? __( 'Yes', 'viwec-email-template-customizer' ) : __( 'No', 'viwec-email-template-customizer' );
		$debug       = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? __( 'Enabled', 'viwec-email-template-customizer' ) : __( 'Disabled', 'viwec-email-template-customizer' );
		$debug_log   = ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) ? __( 'Enabled', 'viwec-email-template-customizer' ) : __( 'Disabled', 'viwec-email-template-customizer' );
		$cron        = ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) ? __( 'Disabled', 'viwec-email-template-customizer' ) : __( 'Enabled', 'viwec-email-template-customizer' );

		return [
			[ 'label' => __( 'Home URL', 'viwec-email-template-customizer' ), 'value' => home_url( '/' ) ],
			[ 'label' => __( 'Site URL', 'viwec-email-template-customizer' ), 'value' => site_url( '/' ) ],
			[
				'label'  => __( 'WordPress version', 'viwec-email-template-customizer' ),
				'value'  => (string) $wp_version . ( $wp_ok ? '' : ' (requires ' . $required_wp . '+)' ),
				'status' => $wp_ok ? 'ok' : 'error',
			],
			[ 'label' => __( 'Multisite', 'viwec-email-template-customizer' ), 'value' => $multisite ],
			[ 'label' => __( 'Language', 'viwec-email-template-customizer' ), 'value' => get_locale() ],
			[
				'label'  => __( 'Permalink structure', 'viwec-email-template-customizer' ),
				'value'  => (string) get_option( 'permalink_structure', '' ) ?: __( '(plain)', 'viwec-email-template-customizer' ),
				'status' => get_option( 'permalink_structure' ) ? 'ok' : 'warn',
			],
			[ 'label' => __( 'WP_DEBUG', 'viwec-email-template-customizer' ), 'value' => $debug, 'status' => ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? 'warn' : 'ok' ],
			[ 'label' => __( 'WP_DEBUG_LOG', 'viwec-email-template-customizer' ), 'value' => $debug_log ],
			[ 'label' => __( 'WP-Cron', 'viwec-email-template-customizer' ), 'value' => $cron, 'status' => ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) ? 'warn' : 'ok' ],
			[ 'label' => __( 'Timezone', 'viwec-email-template-customizer' ), 'value' => wp_timezone_string() ],
		];
	}

	private function get_server_rows() {
		global $wpdb;

		$mysql = '';
		if ( method_exists( $wpdb, 'db_server_info' ) ) {
			$mysql = (string) $wpdb->db_server_info();
		} elseif ( ! empty( $wpdb->db_version() ) ) {
			$mysql = (string) $wpdb->db_version();
		}

		$required_php = '7.4';
		$php_ok       = version_compare( PHP_VERSION, $required_php, '>=' );
		$memory       = (string) ini_get( 'memory_limit' );
		$wp_memory    = defined( 'WP_MEMORY_LIMIT' ) ? (string) WP_MEMORY_LIMIT : '';
		$max_exec     = (string) ini_get( 'max_execution_time' );
		$max_input    = (string) ini_get( 'max_input_vars' );
		$post_max     = (string) ini_get( 'post_max_size' );
		$upload_max   = (string) ini_get( 'upload_max_filesize' );
		$curl         = function_exists( 'curl_version' );
		$ssl          = is_ssl();
		$remote_get   = function_exists( 'wp_remote_get' );
		$mbstring     = extension_loaded( 'mbstring' );
		$json         = extension_loaded( 'json' );
		$gd           = extension_loaded( 'gd' ) || extension_loaded( 'imagick' );
		$yes          = __( 'Yes', 'viwec-email-template-customizer' );
		$no           = __( 'No', 'viwec-email-template-customizer' );

		return [
			[
				'label' => __( 'Server software', 'viwec-email-template-customizer' ),
				'value' => isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['SERVER_SOFTWARE'] ) ) : '—',
			],
			[
				'label'  => __( 'PHP version', 'viwec-email-template-customizer' ),
				'value'  => PHP_VERSION . ( $php_ok ? '' : ' (requires ' . $required_php . '+)' ),
				'status' => $php_ok ? 'ok' : 'error',
			],
			[ 'label' => __( 'MySQL version', 'viwec-email-template-customizer' ), 'value' => $mysql ?: '—' ],
			[ 'label' => __( 'PHP memory_limit', 'viwec-email-template-customizer' ), 'value' => $memory ],
			[ 'label' => __( 'WP_MEMORY_LIMIT', 'viwec-email-template-customizer' ), 'value' => $wp_memory ?: '—' ],
			[ 'label' => __( 'max_execution_time', 'viwec-email-template-customizer' ), 'value' => $max_exec ],
			[ 'label' => __( 'max_input_vars', 'viwec-email-template-customizer' ), 'value' => $max_input ],
			[ 'label' => __( 'post_max_size', 'viwec-email-template-customizer' ), 'value' => $post_max ],
			[ 'label' => __( 'upload_max_filesize', 'viwec-email-template-customizer' ), 'value' => $upload_max ],
			[
				'label'  => __( 'cURL', 'viwec-email-template-customizer' ),
				'value'  => $curl ? $yes : $no,
				'status' => $curl ? 'ok' : 'error',
			],
			[
				'label'  => __( 'mbstring', 'viwec-email-template-customizer' ),
				'value'  => $mbstring ? $yes : $no,
				'status' => $mbstring ? 'ok' : 'warn',
			],
			[
				'label'  => __( 'JSON', 'viwec-email-template-customizer' ),
				'value'  => $json ? $yes : $no,
				'status' => $json ? 'ok' : 'error',
			],
			[
				'label'  => __( 'GD / Imagick', 'viwec-email-template-customizer' ),
				'value'  => $gd ? $yes : $no,
				'status' => $gd ? 'ok' : 'warn',
			],
			[
				'label'  => __( 'HTTPS', 'viwec-email-template-customizer' ),
				'value'  => $ssl ? $yes : $no,
				'status' => $ssl ? 'ok' : 'warn',
			],
			[
				'label'  => __( 'wp_remote_get available', 'viwec-email-template-customizer' ),
				'value'  => $remote_get ? $yes : $no,
				'status' => $remote_get ? 'ok' : 'error',
			],
		];
	}

	private function get_plugin_rows() {
		$plugin_file = VIWEC_DIR . 'email-template-customizer-for-woo.php';
		$data        = function_exists( 'get_plugin_data' ) ? get_plugin_data( $plugin_file, false, false ) : [];
		$header_ver  = isset( $data['Version'] ) ? (string) $data['Version'] : '';
		$runtime_ver = defined( 'VIWEC_VER' ) ? (string) VIWEC_VER : '';
		$yes         = __( 'Yes', 'viwec-email-template-customizer' );
		$no          = __( 'No', 'viwec-email-template-customizer' );

		$templates = wp_count_posts( 'viwec_template' );
		$tpl_count = isset( $templates->publish ) ? (int) $templates->publish : 0;

		$hpos = false;
		if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
			$hpos = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		}

		return [
			[ 'label' => __( 'Plugin version (header)', 'viwec-email-template-customizer' ), 'value' => $header_ver ?: '—' ],
			[ 'label' => __( 'Runtime version constant', 'viwec-email-template-customizer' ), 'value' => $runtime_ver ?: '—' ],
			[ 'label' => __( 'Published email templates', 'viwec-email-template-customizer' ), 'value' => (string) $tpl_count ],
			[
				'label'  => __( 'HPOS (custom order tables)', 'viwec-email-template-customizer' ),
				'value'  => $hpos ? $yes : $no,
				'status' => $hpos ? 'ok' : 'ok',
			],
		];
	}

	

	private function get_dependency_rows() {
		$woo     = class_exists( 'WooCommerce' );
		$woo_ver = defined( 'WC_VERSION' ) ? WC_VERSION : '';
		$required_wc = '3.0';
		$wc_ok   = $woo && version_compare( $woo_ver, $required_wc, '>=' );

		return [
			[
				'label'  => __( 'WooCommerce', 'viwec-email-template-customizer' ),
				'value'  => $woo ? ( $woo_ver ? 'Active — ' . $woo_ver : __( 'Active', 'viwec-email-template-customizer' ) ) : __( 'Inactive (required)', 'viwec-email-template-customizer' ),
				'status' => $wc_ok ? 'ok' : 'error',
			],
		];
	}

	private function get_software_rows() {
		$theme = wp_get_theme();
		$rows  = [
			[
				'label' => __( 'Active theme', 'viwec-email-template-customizer' ),
				'value' => $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ),
			],
		];

		if ( $theme->parent() ) {
			$parent = $theme->parent();
			$rows[] = [
				'label' => __( 'Parent theme', 'viwec-email-template-customizer' ),
				'value' => $parent->get( 'Name' ) . ' ' . $parent->get( 'Version' ),
			];
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all    = get_plugins();
		$active = (array) get_option( 'active_plugins', [] );
		$names  = [];

		foreach ( $active as $plugin_file ) {
			if ( isset( $all[ $plugin_file ] ) ) {
				$names[] = $all[ $plugin_file ]['Name'] . ' ' . $all[ $plugin_file ]['Version'];
			} else {
				$names[] = $plugin_file;
			}
		}

		sort( $names );

		$rows[] = [
			'label' => __( 'Active plugins', 'viwec-email-template-customizer' ),
			'value' => count( $names ) ? implode( "\n", $names ) : '—',
		];

		return $rows;
	}

	private function render_section( $title, $rows ) {
		?>
		<h2><?php echo esc_html( $title ); ?></h2>
		<table class="widefat striped viwec-system-status-table">
			<tbody>
			<?php foreach ( $rows as $row ) : ?>
				<tr>
					<td class="viwec-system-status-label"><?php echo esc_html( (string) $row['label'] ); ?></td>
					<td class="viwec-system-status-value">
						<?php
						$status = isset( $row['status'] ) ? (string) $row['status'] : '';
						$value  = (string) $row['value'];

						if ( $status ) {
							echo '<span class="viwec-system-status-mark is-' . esc_attr( $status ) . '"></span> ';
						}

						if ( false !== strpos( $value, "\n" ) ) {
							echo '<pre class="viwec-system-status-pre">' . esc_html( $value ) . '</pre>';
						} else {
							echo esc_html( $value );
						}
						?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private function send_report_email( $issue ) {
		$report = $this->collect_report();
		$user   = wp_get_current_user();
		$host   = wp_parse_url( home_url(), PHP_URL_HOST );
		$subject = sprintf(
			'[Email Template Customizer] Support request — %s',
			$host ? $host : home_url()
		);

		$body  = "Email Template Customizer - WooCommerce Email Template Customizer — System Status Report\n";
		$body .= str_repeat( '=', 60 ) . "\n\n";
		$body .= 'Sent: ' . gmdate( 'c' ) . " UTC\n";
		$body .= 'Site: ' . home_url( '/' ) . "\n";
		$body .= 'Admin: ' . ( $user->user_email ?: '—' ) . ' (' . ( $user->display_name ?: $user->user_login ) . ")\n\n";

		if ( '' !== trim( (string) $issue ) ) {
			$body .= "Issue description\n";
			$body .= str_repeat( '-', 40 ) . "\n";
			$body .= $issue . "\n\n";
		} else {
			$body .= "Issue description\n";
			$body .= str_repeat( '-', 40 ) . "\n";
			$body .= "(none provided)\n\n";
		}

		$sections = [
			'WordPress'           => $report['wordpress'],
			'Server'              => $report['server'],
			'Email Template Customizer'            => $report['plugin'],
			'Dependencies'        => $report['dependencies'],
			'Theme & plugins'     => $report['software'],
		];

		foreach ( $sections as $title => $rows ) {
			$body .= $title . "\n";
			$body .= str_repeat( '-', 40 ) . "\n";

			foreach ( $rows as $row ) {
				$label = (string) $row['label'];
				$value = (string) $row['value'];
				$body .= $label . ': ' . str_replace( "\n", ' | ', $value ) . "\n";
			}

			$body .= "\n";
		}

		$headers = [ 'Content-Type: text/plain; charset=UTF-8' ];

		if ( is_email( $user->user_email ) ) {
			$headers[] = 'Reply-To: ' . $user->user_email;
		}

		$headers = apply_filters( 'viwec_system_status_report_headers', $headers, $issue, $report );

		$sent = wp_mail( self::SUPPORT_EMAIL, $subject, $body, $headers );

		if ( ! $sent ) {
			return new WP_Error(
				'viwec_mail_failed',
				__( 'wp_mail() failed. Check your site mail configuration.', 'viwec-email-template-customizer' )
			);
		}

		return true;
	}

	

	
}
