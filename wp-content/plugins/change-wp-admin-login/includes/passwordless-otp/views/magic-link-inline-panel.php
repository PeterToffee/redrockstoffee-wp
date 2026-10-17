<?php
/**
 * Inline login link panel (replaces login form area).
 *
 * @package AIO_Login
 */

defined( 'ABSPATH' ) || exit;
?>
<div id="aio-login-magic-link-panel" class="aio-login-magic-link-panel" hidden aria-hidden="true">
	<p class="aio-login-otp-panel__back-wrap">
		<button type="button" class="aio-login-otp-back-to-login" data-ml-back-to-login>
			<span class="aio-login-otp-back-to-login__icon" aria-hidden="true">←</span>
			<?php esc_html_e( 'Back to login', 'change-wp-admin-login' ); ?>
		</button>
	</p>

	<div class="aio-login-magic-link-step aio-login-magic-link-step--contact" data-ml-step="contact">
		<h2 class="aio-login-otp-panel__title"><?php esc_html_e( 'Email Me a Login Link', 'change-wp-admin-login' ); ?></h2>
		<p class="aio-login-otp-panel__error" role="alert" hidden></p>

		<div class="aio-login-otp-field aio-login-otp-field--email">
			<label for="aio-login-magic-link-email"><?php esc_html_e( 'Email address', 'change-wp-admin-login' ); ?></label>
			<input type="email" id="aio-login-magic-link-email" name="email" autocomplete="email" class="input" />
		</div>

		<div class="aio-login-otp-captcha aio-login-magic-link-captcha aio-login-passwordless-captcha" hidden></div>

		<div class="aio-login-otp-actions aio-login-otp-actions--contact">
			<button type="button" class="button button-primary button-large aio-login-magic-link-send" disabled><?php esc_html_e( 'Send Link', 'change-wp-admin-login' ); ?></button>
		</div>
	</div>

	<div class="aio-login-magic-link-step aio-login-magic-link-step--sent" data-ml-step="sent" hidden>
		<div class="aio-login-otp-success-icon" aria-hidden="true">✓</div>
		<h2 class="aio-login-otp-panel__title"><?php esc_html_e( 'Check your email', 'change-wp-admin-login' ); ?></h2>
		<p class="aio-login-magic-link-sent-message"><?php esc_html_e( 'We sent a secure login link to your email. Open the link to sign in.', 'change-wp-admin-login' ); ?></p>
	</div>
</div>
