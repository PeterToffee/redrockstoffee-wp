<?php
/**
 * Template dashboard
 *
 * @package AIO Login
 */

defined( 'ABSPATH' ) || exit;

?>



<div class="aio-login__cards-container">

	<aio-login-card
			type="success"
			title="Success"
			:data="{
				title: success_login.title,
				count: success_login.count,
			}"
	></aio-login-card>

	<aio-login-card
			type="failed"
			title="Failed"
			:data="{
				title: failed_login.title,
				count: failed_login.count,
			}"
	></aio-login-card>

	<aio-login-card
			type="lockout"
			title="Lockouts"
			:data="{
				title: lockout.title,
				count: lockout.count,
			}"
	></aio-login-card>

	<?php
	$url = add_query_arg(
		array(
			'page' => 'aio-login',
			'tab'  => 'customization',
		),
		admin_url( 'admin.php' )
	);
	?>
	<aio-login-customization-card href="<?php echo esc_attr( $url ); ?>"></aio-login-customization-card>
</div>

<div class="aio-login__meta-container">

	<aio-login-meta :is-pro="false" :is-left="true">
		<template v-slot:title>
			<?php esc_attr_e( 'Custom Login URL', 'aio-login' ); ?>
		</template>
		<template v-slot:description>
			<?php esc_attr_e( 'Attackers often try exploits on /wp-login or /wp-admin as a default login URL for WordPress. Change it to avoid these attacks and have an easily memorizable login URL.', 'aio-login' ); ?>
		</template>
		<template v-slot:configuration>
			<a href="
			<?php
			echo esc_url(
				add_query_arg(
					array(
						'page'    => 'aio-login',
						'tab'     => 'login-protection',
                        'sub-tab' => 'change-login-url',
					),
					admin_url( 'admin.php' )
				)
			);
			?>
			" class="aio-login__button aio-login__pull-right"><?php esc_attr_e( 'Configure', 'aio-login' ); ?></a>
		</template>
	</aio-login-meta>

	<aio-login-meta :is-pro="false" :is-left="false">
		<template v-slot:title>
			<?php esc_attr_e( 'Limit Login Attempts', 'aio-login' ); ?>
		</template>
		<template v-slot:description>
			<?php esc_attr_e( 'Limit the number of times a user IP can attempt to log in to your wp-admin with incorrect credentials. Once the login attempt limit is reached, the IP from which the attempts have originated will be blocked for default period of time.', 'aio-login' ); ?>
		</template>
		<template v-slot:configuration>
			<select @change="toggle_limit_login_attempts" id="aio-login--lla-toggle" class="aio-login__configure aio-login__pull-right">
				<?php $selected = get_option( 'aio_login_limit_attempts_enable', 'off' ); ?>
				<option <?php selected( $selected, 'on', true ); ?> value="on"><?php esc_attr_e( 'On', 'aio-login' ); ?></option>
				<option <?php selected( $selected, 'off', true ); ?> value="off"><?php esc_attr_e( 'Off', 'aio-login' ); ?></option>
			</select>
		</template>
	</aio-login-meta>

	<aio-login-meta :is-pro="<?php echo ! \AIO_Login\AIO_Login::has_pro() ? 'true' : 'false'; ?>" :is-left="true">
		<template v-slot:title>
			<?php esc_attr_e( 'Two Factor Authentication', 'aio-login' ); ?>
		</template>
		<template v-slot:description>
			<?php esc_attr_e( 'Two-factor authentication forces admin users to login only after providing a token, generated from the Authenticator applications. When you enable this option, all admin users will be asked to configure their two-factor authentication in the Authenticator app on their next login.', 'aio-login' ); ?>
		</template>
		<template v-slot:configuration>
			<?php if ( ! \AIO_Login\AIO_Login::has_pro() ) : ?>
				<div class="aio-login__toggle-switch-wrapper aio-login__pull-right mt-20 aio-login__disabled aio-login__toggle-pro" @click="open_pro_popup">
					<input disabled type="checkbox" class="aio-login__toggle-field" id="enable-tfa-disabled">
					<label class="aio-login__toggle-switch" for="enable-tfa-disabled">
						<span class="aio-login__toggle-indicator"></span>
					</label>
				</div>
			<?php else : ?>
				<div class="aio-login__toggle-switch-wrapper aio-login__pull-right mt-20">
					<input type="checkbox" id="enable_2fa" class="aio-login__toggle-field" value="on" <?php checked( get_option( 'aio_login_pro__two_factor_auth_enable' ), 'on', true ); ?> @click="e => update_pro_settings( e.target.checked, 'enable_2fa' )">
					<label class="aio-login__toggle-switch" for="enable_2fa">
						<span class="aio-login__toggle-indicator"></span>
					</label>
				</div>
			<?php endif; ?>
		</template>
	</aio-login-meta>

	<aio-login-meta :is-pro="<?php echo ! \AIO_Login\AIO_Login::has_pro() ? 'true' : 'false'; ?>" :is-left="false">
		<template v-slot:title>
			<?php esc_attr_e( 'Block IP Address', 'aio-login' ); ?>
		</template>
		<template v-slot:description>
			<?php esc_attr_e( 'By default your WordPress login can be accessed by any IP or user. You can use this feature to allow login only for specific IPs or users in order to prevent brute-force attacks or malicious login attempts.', 'aio-login' ); ?>
		</template>
		<template v-slot:configuration>
			<?php if ( ! \AIO_Login\AIO_Login::has_pro() ) : ?>
				<div class="aio-login__toggle-switch-wrapper aio-login__pull-right mt-20 aio-login__disabled aio-login__toggle-pro" @click="open_pro_popup">
					<input disabled type="checkbox" class="aio-login__toggle-field" id="enable-ip-block-disabled">
					<label class="aio-login__toggle-switch" for="enable-ip-block-disabled">
						<span class="aio-login__toggle-indicator"></span>
					</label>
				</div>
			<?php else : ?>
				<div class="aio-login__toggle-switch-wrapper aio-login__pull-right mt-20">
					<input type="checkbox" id="enable_bipa" class="aio-login__toggle-field" value="on" <?php checked( get_option( 'aio_login_block_ip_address_enable' ), 'on', true ); ?> @click="e => update_pro_settings( e.target.checked, 'enable_block_ip_address' )">
					<label class="aio-login__toggle-switch" for="enable_bipa">
						<span class="aio-login__toggle-indicator"></span>
					</label>
				</div>
			<?php endif; ?>
		</template>
	</aio-login-meta>

</div>

<?php if ( ! AIO_Login\AIO_Login::has_pro() ) : ?>
	<div class="aio-login__pro-banner-wrapper">
		<div
            style="display: flex;justify-content: space-between;align-items: center;width: 100%;height: 100%;"
		>
			<div class="aio-login__pro-content">
				<h2><?php esc_attr_e( 'Get the Most Powerful WordPress Login Plugin Today', 'aio-login' ); ?></h2>
				<p>
					<?php esc_attr_e( 'Join over 90K smart website owners who use AIO Login to improve their WordPress admin login security and customization', 'aio-login' ); ?>
				</p>
			</div>

			<div class="aio-login__pro-action">
				<a class="aio-login__button aio-login__pro" href="https://aiologin.com/pricing/?utm_source=plugin&utm_medium=dashboard_pro_banner&utm_campaign=plugin"><?php esc_attr_e( 'Get AIO Login Pro', 'aio-login' ); ?></a>
			</div>
		</div>
	</div>
<?php endif; ?>

<?php

add_action(
	'aio_login__footer',
	function () {
		?>
		<div class="aio-login__content-wrapper">
			<h2 class="aio-login__recent-activity"><?php esc_attr_e( 'Recent Activity', 'aio-login' ); ?></h2>

			<div class="aio-login__table-wrapper">
				<div class="aio-login__table-nav">
					<div class="aio-login__table-nav-item active">
						<a @click="e => change_activity_table( e, '#aio-login--lockouts-table' )" class="aio-login--activate" href="#"><?php esc_attr_e( 'Lockouts', 'aio-login' ); ?></a>
					</div>
					<div class="aio-login__table-nav-item">
						<a @click="e => change_activity_table( e, '#aio-login--failed-login-table' )" class="aio-login--activate" href="#"><?php esc_attr_e( 'Failed Logins', 'aio-login' ); ?></a>
					</div>
				</div>

				<aio-login-table
						id="aio-login--lockouts-table"
						type="lockout_activity_logs"
						:headers="[
							{ key: 'time', value: '<?php esc_attr_e( 'Date & Time', 'aio-login' ); ?>' },
							{ key: 'country', value: '<?php esc_attr_e( 'Country', 'aio-login' ); ?>' },
							{ key: 'city', value: '<?php esc_attr_e( 'City', 'aio-login' ); ?>' },
							{ key: 'user_agent', value: '<?php esc_attr_e( 'User Agent', 'aio-login' ); ?>' },
							{ key: 'ip_address', value: '<?php esc_attr_e( 'IP Address', 'aio-login' ); ?>' },
						]"
				></aio-login-table>

				<aio-login-table
						id="aio-login--failed-login-table"
						style="display: none;"
						type="failed_login_activity_logs"
						:headers="[
							{ key: 'id', value: '<?php esc_attr_e( 'ID', 'aio-login' ); ?>' },
							{ key: 'user_login', value: '<?php esc_attr_e( 'User Login', 'aio-login' ); ?>' },
							{ key: 'time', value: '<?php esc_attr_e( 'Date & Time', 'aio-login' ); ?>' },
							{ key: 'country', value: '<?php esc_attr_e( 'Country', 'aio-login' ); ?>' },
							{ key: 'city', value: '<?php esc_attr_e( 'City', 'aio-login' ); ?>' },
							{ key: 'user_agent', value: '<?php esc_attr_e( 'User Agent', 'aio-login' ); ?>' },
							{ key: 'ip_address', value: '<?php esc_attr_e( 'IP Address', 'aio-login' ); ?>' },
						]"
				></aio-login-table>

			</div>

			<?php
			$lockouts_url = add_query_arg(
				array(
					'page'    => 'aio-login',
					'tab'     => 'activity-log',
					'sub-tab' => 'lockouts',
				),
				admin_url( 'admin.php' )
			);
			$failed_url   = add_query_arg(
				array(
					'page'    => 'aio-login',
					'tab'     => 'activity-log',
					'sub-tab' => 'failed-logins',
				),
				admin_url( 'admin.php' )
			);
			?>
			<div class="aio-login__view-all-activity">
				<a id="aio_login__view-all-activity" aio-login-lockout-url="<?php echo esc_url( $lockouts_url ); ?>" aio-login-failed-url="<?php echo esc_url( $failed_url ); ?>" href="<?php echo esc_url( $lockouts_url ); ?>" class="aio-login__button">
					<?php esc_attr_e( 'View All Activity', 'aio-login' ); ?>
				</a>
			</div>
		</div>
		<?php
	}
);
?>
