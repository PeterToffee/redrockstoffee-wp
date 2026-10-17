import { t } from './i18n.js';
/**
 * Tooltip content for AIO Login features.
 * Source: Tooltip Content for AIO Login Features.pdf
 */
export default {
	// Change WP-Admin Login URL
	changeLoginUrl: {
		content: t( '<p>Replace the default WordPress login URL to enhance security and prevent unauthorized access.</p>' ),
		helpingText: t( 'Enable to customize your admin login URL.' ),
	},

	// Limit Login Attempts
	limitLoginAttempts: {
		content: t( '<p>Restrict login attempts per IP to prevent brute-force attacks and block repeated failed login attempts.</p>' ),
		helpingText: t( 'Enable to restrict repeated login attempts per IP.' ),
	},

	// Login Redirection
	loginRedirection: {
		content: t( '<p>Define role-based or user-based destinations after login and logout to guide users to the right pages.</p>' ),
		helpingText: t( 'Enable custom redirection rules for login and logout actions.' ),
	},

	// Block IP Addresses
	blockIpAddresses: {
		content: t( '<p>Control site access by blocking or allowing specific IP addresses using whitelist or blacklist mode.</p>' ),
		helpingText: t( 'Enable to control access using whitelist or blacklist.' ),
	},

	// Disable Common Usernames
	disableCommonUsernames: {
		content: t( '<p>Block commonly used or custom usernames to prevent brute-force attacks and strengthen login security.</p>' ),
		helpingText: t( 'Enable to prevent use of common usernames.' ),
	},

	// Add CAPTCHA
	captcha: {
		content: t( '<p>Protect your login page from spam and bots using CAPTCHAs.</p>' ),
		helpingText: t( 'Enable CAPTCHAs to block bots and spam.' ),
	},

	// reCAPTCHA
	recaptcha: {
		title: t( 'reCAPTCHA' ),
		content: t( '<p>To protect forms from bots and spam by adding Captcha.</p>' ),
	},

	// WooCommerce Integration
	woocommerceIntegration: {
		title: t( 'WooCommerce Integration' ),
		content: t( '<p>Integrate AIO Login with WooCommerce to add CAPTCHA and Social Login to login, registration, and checkout pages.</p>' ),
		helpingText: t( 'Enable secure Captcha in WooCommerce with social login features.' ),
	},

	// Two-Factor Authentication
	twoFactorAuth: {
		content: t( '<p>Require a one-time password (OTP) from an authenticator app to secure admin logins.</p>' ),
		helpingText: t( 'Enable to add OTP verification for stronger login security.' ),
	},
	twoFactorMasterEnable: {
		content: t( '<p>Master switch for 2FA on this site. When it is on, both Email OTP and Authenticator App sections appear so you and other users can configure a method. Only one site-wide method can be active at a time (use the toggles below). Each user also uses only one method at a time. When it is off, methods are hidden here and non-administrator users do not see AIO Login in wp-admin.</p>' ),
	},
	twoFactorEmailOtp: {
		content: t( '<p>Enable this to send a one-time verification code via email when users log in. This adds an extra layer of security by requiring email verification.</p>' ),
	},
	twoFactorTotp: {
		content: t( '<p>Enable Time-based One-Time Password (TOTP) authentication. Users can use apps like Google Authenticator, Authy, or Microsoft Authenticator to generate verification codes.</p>' ),
	},
	twoFactorRememberDevice: {
		content: t( '<p>When enabled, users can mark their device as trusted after a successful OTP verification. They won\'t be prompted for OTP again on that device for the configured duration. Applies to both Email OTP and TOTP.</p>' ),
	},

	// Temporary Access
	temporaryAccess: {
		content: t( '<p>Provide passwordless temporary access link for short-term tasks or guest users without compromising site security.</p>' ),
		helpingText: t( 'Enable to allow users temporary admin access securely.' ),
	},

	// Password Strength Checker
	passwordStrengthChecker: {
		content: t( '<p>Enforce custom password rules for registrations and resets to ensure strong credentials.</p>' ),
		helpingText: t( 'Enable to set rules to improve user password security.' ),
	},

	// User Enumeration Protection
	userEnumerationProtection: {
		content: t( '<p>Prevent exposure of usernames to block brute-force and phishing attacks.</p>' ),
		helpingText: t( 'Enable to hide usernames to strengthen site security.' ),
	},

	// Login Attempt Logs (Lockouts)
	loginAttemptLogs: {
		content: t( '<p>Track users who reached the maximum login attempts and were locked out to monitor security threats.</p>' ),
		helpingText: t( 'Enable to view logs of locked-out users and attempts.' ),
	},

	// Failed Login Attempts
	failedLoginAttempts: {
		content: t( '<p>Monitor and log failed login attempts to detect suspicious activity and enhance site security.</p>' ),
		helpingText: t( 'Enable to track failed logins to spot potential threats.' ),
	},

	// User Enumeration Logs
	userEnumerationLogs: {
		content: t( '<p>Track attempts to fetch usernames or user IDs to monitor unauthorized enumeration activity.</p>' ),
		helpingText: t( 'Enable to monitor username discovery attempts securely.' ),
	},

	// Activity Log — Notifications
	notifications: {
		title: t( 'Notifications' ),
		content: t( '<p>Configure alerts for security events such as lockouts, failed logins, and other activity so you can respond quickly.</p>' ),
		helpingText: t( 'Set up notification channels for important login and security events.' ),
	},

	// Customizer - Logo
	logo: {
		content: t( '<p>Upload and display a custom logo on your WordPress login page.</p>' ),
		helpingText: t( 'Add a custom logo to your login page.' ),
	},

	// Customizer - Background
	background: {
		content: t( '<p>Customize the login page background with images, colors, or slideshows.</p>' ),
		helpingText: t( 'Set background images, colors, or slideshows.' ),
	},

	// Customizer - Custom CSS
	customCss: {
		content: t( '<p>Add custom CSS to fully style your login page according to your site\'s branding.</p>' ),
		helpingText: t( 'Apply custom CSS to style the login page.' ),
	},

	// Customizer - Template
	templates: {
		content: t( '<p>Choose from pre-designed login templates to quickly apply a professional look.</p>' ),
		helpingText: t( 'Select a ready-made template for your login page.' ),
	},

	// Social Login (generic - e.g. WooCommerce settings section)
	socialLogin: {
		title: t( 'Social Login' ),
		content: t( '<p>Allow users to log in with Google, Microsoft, Facebook, Line, GitHub, or Discord. Enable on WooCommerce login, registration, and checkout pages.</p>' ),
	},

	// Social Login providers
	googleSocialLogin: {
		content: t( '<p>Allow users to log in using their Google account for faster, secure access.</p>' ),
		helpingText: t( 'Enable one-click login via Google accounts.' ),
	},
	microsoftSocialLogin: {
		content: t( '<p>Enable users to log in using their Microsoft account for fast and secure authentication.</p>' ),
		helpingText: t( 'Enable one-click login via Microsoft accounts.' ),
	},
	facebookSocialLogin: {
		content: t( '<p>Let users log in with their Facebook account for a seamless and trusted login experience.</p>' ),
		helpingText: t( 'Enable one-click login via Facebook accounts.' ),
	},
	lineSocialLogin: {
		content: t( '<p>Allow users to log in using their Line account for quick and simple authentication.</p>' ),
		helpingText: t( 'Enable one-click login via Line accounts.' ),
	},
	githubSocialLogin: {
		content: t( '<p>Allow users to log in using their GitHub account for developer-friendly authentication.</p>' ),
		helpingText: t( 'Enable one-click login via GitHub.' ),
	},
	discordSocialLogin: {
		content: t( '<p>Allow users to log in using their Discord account for quick authentication.</p>' ),
		helpingText: t( 'Enable one-click login via Discord.' ),
	},

	// Recent Activity (dashboard section)
	recentActivity: {
		content: t( '<p>View lockouts and failed login attempts in one place. Switch between Login Attempt Logs and Failed Login Attempts to monitor security.</p>' ),
	},

	// Logging Settings (enumeration logs page)
	loggingSettings: {
		content: t( '<p>Enable to monitor username discovery attempts securely.</p>' ),
	},

	// User Enumeration - Enable Protection label
	userEnumerationProtectionEnable: {
		content: t( '<p>Enable to hide usernames to strengthen site security.</p>' ),
	},

	passwordlessOtpBlockDuration: {
		content: t( '<p>How long (in minutes) the IP address remains blocked after too many failed OTP attempts for this login method. Blocked IPs appear in Activity Log → Lockouts.</p>' ),
	},
	passwordlessOtpSkip2fa: {
		content: t( '<p>When enabled, a successful passwordless OTP login counts as completing AIO Login two-factor authentication, so users are not asked for 2FA again in the same session. Requires AIO Login Pro with 2FA enabled.</p>' ),
	},
	passwordlessEmailOtp: {
		content: t( '<p>Allow users to sign in securely without a password using a one-time code sent to their email address</p>' ),
	},
	passwordlessSmsOtp: {
		content: t( '<p>Allow users to sign in with an SMS code via Twilio. Requires AIO Login Pro and Twilio credentials.</p>' ),
	},
	passwordlessOtpLength: {
		content: t( '<p>Number of digits in the verification code shown to users.</p>' ),
	},
	passwordlessOtpExpiration: {
		content: t( '<p>How long the OTP remains valid before users must request a new code.</p>' ),
	},
	passwordlessOtpResend: {
		content: t( '<p>Minimum wait time before users can request another OTP.</p>' ),
	},
	passwordlessOtpRetries: {
		content: t( '<p>Maximum incorrect OTP attempts before the session is invalidated.</p>' ),
	},
	passwordlessTwilioToken: {
		content: t( '<p>Stored encrypted. Leave blank when saving other settings to keep the existing token.</p>' ),
	},
	magicLinkEnable: {
		content: t( '<p>When enabled, registered users can request a secure one-time sign-in link by email from the login page.</p>' ),
	},
	magicLinkValidity: {
		content: t( '<p>How long the magic link remains valid before it expires.</p>' ),
	},
	magicLinkRequests: {
		content: t( '<p>Maximum number of login link requests allowed per user within the validity window.</p>' ),
	},
	magicLinkSkip2fa: {
		content: t( '<p>Magic Link Login already acts as a possession-based authentication factor. Enabling this option skips the additional 2FA challenge.</p>' ),
	},
};
