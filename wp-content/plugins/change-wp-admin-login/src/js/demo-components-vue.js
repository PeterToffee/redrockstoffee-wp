import aioLoginBlockIpAddress from './demo/components/aio-login-ip-address.vue'
import aioLoginDisableCommonUsernames from './demo/components/aio-login-disable-common-usernames.vue';
import aioLoginPasswordStrenghtChecker from './demo/components/aio-login-password-strenght-checker.vue';
import aioLoginTemporaryAccess from './demo/components/aio-login-temporary-access.vue';
import aioLoginLoginLink from './demo/components/aio-login-login-link.vue';
import aioLoginTemplates from './demo/components/aio-login-templates.vue';
import aioLoginTwoFactorAuthentication from './demo/components/aio-login-two-factor-authentication.vue';
import aioSocialLogin from './demo/components/aio-login-social-login.vue';
import aioLoginCaptcha from './demo/components/aio-login-captcha.vue';

window.aio_login__mount_helper_instance.mount( '#aio-login__app', {
	'aio-login-block-ip-address': aioLoginBlockIpAddress,
	'aio-login-disable-common-usernames': aioLoginDisableCommonUsernames,
	'aio-login-password-strenght-checker': aioLoginPasswordStrenghtChecker,
	'aio-login-temporary-access': aioLoginTemporaryAccess,
	'aio-login-login-link': aioLoginLoginLink,
	'aio-login-templates': aioLoginTemplates,
	'aio-login-two-factor-authentication': aioLoginTwoFactorAuthentication,
	'aio-login-social-login': aioSocialLogin,
	'aio-login-captcha': aioLoginCaptcha,
} );
