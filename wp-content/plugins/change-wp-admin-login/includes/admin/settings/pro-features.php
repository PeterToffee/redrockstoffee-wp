<?php
/**
 * AIO Login Pro Features
 *
 * @package AIO Login
 */

defined( 'ABSPATH' ) || exit;

$aio_login__pro_features = array(
	array(
		'title'       => __( 'Ban User / IP', 'aio-login' ),
		'icon'        => AIO_LOGIN__DIR_URL . 'assets/images/pro/block-user.png',
		'description' => esc_html__( 'Instantly ban WordPress user IP addresses to prevent unauthorized access', 'aio-login' ),
	),
	array(
		'title'       => __( 'App Based 2FA', 'aio-login' ),
		'icon'        => AIO_LOGIN__DIR_URL . 'assets/images/pro/app-based-2fa.png',
		'description' => esc_html__( 'Enable two-factor authentication via mobile apps for added security.', 'aio-login' ),
	),
	array(
		'title'       => __( 'Temp Access URL', 'aio-login' ),
		'icon'        => AIO_LOGIN__DIR_URL . 'assets/images/pro/temp-access-url.png',
		'description' => esc_html__( 'Generate WordPress temp access URL for specific users.', 'aio-login' ),
	),
	array(
		'title'       => __( 'Customize Design Pro', 'aio-login' ),
		'icon'        => AIO_LOGIN__DIR_URL . 'assets/images/pro/customize-design-pro.png',
		'description' => esc_html__( 'Take login page customization further with pre-built templates.', 'aio-login' ),
	),
	array(
		'title'       => __( 'Whitelist IP Addresses', 'aio-login' ),
		'icon'        => AIO_LOGIN__DIR_URL . 'assets/images/pro/wl-ip-addresses.png',
		'description' => esc_html__( 'Allow access to only whitelisted IP addresses to your WordPress login page.', 'aio-login' ),
	),
	array(
		'title'       => __( 'Block Login Attempts', 'aio-login' ),
		'icon'        => AIO_LOGIN__DIR_URL . 'assets/images/pro/block-login-attempts.png',
		'description' => esc_html__( 'You can block any user or IP address from accessing your wp-login page.', 'aio-login' ),
	),
);

?>

<div class="aio-login__pro-wrapper">
	<h2 class="aio-login__pro-heading"><?php esc_attr_e( 'Pro Features', 'aio-login' ); ?></h2>

	<div class="aio-login__grid_section">
		<?php foreach ( $aio_login__pro_features as $pro_feature ) : ?>
			<div class="aio-login__pro_features">
				<div class="aio-login__pro_features__icon">
					<img draggable="false" src="<?php echo esc_url( $pro_feature['icon'] ); ?>" alt="<?php echo esc_attr( $pro_feature['title'] ); ?>">
				</div>
				<div class="aio-login__pro_features__title">
					<h3><?php echo esc_attr( $pro_feature['title'] ); ?></h3>
				</div>

				<div class="aio-login__pro_features__description">
					<p>
						<?php echo esc_attr( $pro_feature['description'] ); ?>
					</p>
				</div>
			</div>
		<?php endforeach; ?>
	</div>

	<div class="aio-login__pro_features__cta">
		<a href="https://aiologin.com/pricing/?utm_source=plugin&utm_medium=get_pro_tab&utm_campaign=plugin" target="_blank" class="aio-login__button aio-login__pro"><?php esc_attr_e( 'Get AIO Login Pro', 'aio-login' ); ?></a>
</div>
