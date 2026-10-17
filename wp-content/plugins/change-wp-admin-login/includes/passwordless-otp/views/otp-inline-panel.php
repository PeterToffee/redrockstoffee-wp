<?php
/**
 * Inline passwordless OTP panel (replaces login form area, not a modal).
 *
 * @package AIO_Login
 */

defined( 'ABSPATH' ) || exit;
?>
<div id="aio-login-otp-panel" class="aio-login-otp-panel" hidden aria-hidden="true">
	<p class="aio-login-otp-panel__back-wrap">
		<button type="button" class="aio-login-otp-back-to-login" data-otp-back-to-login>
			<span class="aio-login-otp-back-to-login__icon" aria-hidden="true">←</span>
			<?php esc_html_e( 'Back to login', 'change-wp-admin-login' ); ?>
		</button>
	</p>

	<div class="aio-login-otp-step aio-login-otp-step--contact" data-step="contact">
		<h2 id="aio-login-otp-panel-title" class="aio-login-otp-panel__title"></h2>
		<p class="aio-login-otp-panel__error" role="alert" hidden></p>

		<div class="aio-login-otp-field aio-login-otp-field--email" hidden>
			<label for="aio-login-otp-email"><?php esc_html_e( 'Email address', 'change-wp-admin-login' ); ?></label>
			<input type="email" id="aio-login-otp-email" name="email" autocomplete="email" class="input" />
		</div>

		<div class="aio-login-otp-field aio-login-otp-field--sms" hidden>
			<label for="aio-login-otp-country"><?php esc_html_e( 'Country code', 'change-wp-admin-login' ); ?></label>
			<select id="aio-login-otp-country" name="country_code" class="input" aria-label="<?php esc_attr_e( 'Country code', 'change-wp-admin-login' ); ?>"></select>
			<label for="aio-login-otp-phone"><?php esc_html_e( 'Phone number', 'change-wp-admin-login' ); ?></label>
			<div class="aio-login-otp-phone-field">
				<span class="aio-login-otp-phone-field__dial" id="aio-login-otp-phone-dial" aria-hidden="true"></span>
				<input type="tel" id="aio-login-otp-phone" name="phone" inputmode="numeric" autocomplete="tel-national" class="input" />
			</div>
			<p class="aio-login-otp-phone-field__hint"><?php esc_html_e( 'The country code is shown automatically and cannot be edited here. Enter the rest of your mobile number.', 'change-wp-admin-login' ); ?></p>
		</div>

		<div class="aio-login-otp-captcha aio-login-passwordless-captcha" hidden></div>

		<div class="aio-login-otp-actions aio-login-otp-actions--contact">
			<button type="button" class="button button-primary button-large aio-login-otp-send" disabled><?php esc_html_e( 'Send Code', 'change-wp-admin-login' ); ?></button>
		</div>
	</div>

	<div class="aio-login-otp-step aio-login-otp-step--verify" data-step="verify" hidden>
		<h2 class="aio-login-otp-panel__title"><?php esc_html_e( 'Enter verification code', 'change-wp-admin-login' ); ?></h2>
		<p class="aio-login-otp-panel__error" role="alert" hidden></p>
		<div class="aio-login-otp-field aio-login-otp-field--code">
			<label for="aio-login-otp-code"><?php esc_html_e( 'Verification code', 'change-wp-admin-login' ); ?></label>
			<input
				type="text"
				id="aio-login-otp-code"
				name="otp"
				class="input aio-login-otp-code-input"
				inputmode="numeric"
				pattern="[0-9]*"
				autocomplete="one-time-code"
				maxlength="8"
			/>
		</div>
		<p class="aio-login-otp-resend-wrap">
			<button type="button" class="aio-login-otp-resend" disabled hidden><?php esc_html_e( 'Resend code', 'change-wp-admin-login' ); ?></button>
			<span class="aio-login-otp-resend-timer" hidden></span>
		</p>
		<div class="aio-login-otp-actions">
			<button type="button" class="button aio-login-otp-back" data-otp-back><?php esc_html_e( 'Back', 'change-wp-admin-login' ); ?></button>
			<button type="button" class="button button-primary button-large aio-login-otp-verify" disabled><?php esc_html_e( 'Verify', 'change-wp-admin-login' ); ?></button>
		</div>
	</div>

	<div class="aio-login-otp-step aio-login-otp-step--success" data-step="success" hidden>
		<div class="aio-login-otp-success-icon" aria-hidden="true">✓</div>
		<h2 class="aio-login-otp-panel__title"><?php esc_html_e( "You're signed in", 'change-wp-admin-login' ); ?></h2>
		<p class="aio-login-otp-success-message"><?php esc_html_e( "You're signed in. Redirecting you to your dashboard…", 'change-wp-admin-login' ); ?></p>
		<button type="button" class="button button-primary button-large aio-login-otp-continue"><?php esc_html_e( 'Continue to Dashboard', 'change-wp-admin-login' ); ?></button>
	</div>
</div>
