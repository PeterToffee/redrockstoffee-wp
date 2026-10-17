<template>
	<section class="aio-login-dashboard-docs">
		<div class="aio-login-dashboard-docs__head">
			<h2>{{ $t("Guides and Documentation") }}</h2>
			<a :href="docsBaseUrl" target="_blank" rel="noopener noreferrer">{{ $t("View All") }}</a>
		</div>
		<div class="aio-login-dashboard-docs__grid">
			<article v-for="section in docSections" :key="section.title" class="aio-login-dashboard-docs__card">
				<h3>{{ $t(section.title) }}</h3>
				<ul>
					<li v-for="item in section.items" :key="item.label">
						<a :href="item.url" target="_blank" rel="noopener noreferrer">{{ $t(item.label) }}</a>
					</li>
				</ul>
				<a class="aio-login-dashboard-docs__more" :href="section.moreUrl" target="_blank" rel="noopener noreferrer">{{ $t("More →") }}</a>
			</article>
		</div>
	</section>
</template>

<script>
export default {
	name: 'aio-login-dashboard-docs',
	data: () => ( {
		docsBaseUrl: 'https://aiologin.com/docs/',
		docSections: [
			{
				title: 'Getting Started',
				moreUrl: 'https://aiologin.com/docs/getting-started/',
				items: [
					{ label: 'Installation and Activation Guide', url: 'https://aiologin.com/docs/getting-started/installation-and-activation-guide/' },
					{ label: 'AIO Login Pro Installation and Activation Guide', url: 'https://aiologin.com/docs/getting-started/aio-login-pro-installation-and-activation-guide/' },
					{ label: 'AIO Login Dashboard', url: 'https://aiologin.com/docs/getting-started/aio-login-dashboard/' },
				],
			},
			{
				title: 'WordPress Login Protection',
				moreUrl: 'https://aiologin.com/docs/wordpress-login-protection/',
				items: [
					{ label: 'Change your WP-Admin Login URL', url: 'https://aiologin.com/docs/wordpress-login-protection/change-your-wp-admin-login-url/' },
					{ label: 'Enabling Limit Login Attempts', url: 'https://aiologin.com/docs/wordpress-login-protection/enabling-limit-login-attempts/' },
					{ label: 'Blocking WordPress IP Addresses', url: 'https://aiologin.com/docs/wordpress-login-protection/blocking-wordpress-ip-addresses/' },
				],
			},
			{
				title: 'WordPress Login Security',
				moreUrl: 'https://aiologin.com/docs/wordpress-login-security/',
				items: [
					{ label: 'Setting Up Google reCAPTCHA', url: 'https://aiologin.com/docs/wordpress-login-security/setting-up-google-recaptcha/' },
					{ label: 'Two Factor Authentication', url: 'https://aiologin.com/docs/wordpress-login-security/two-factor-authentication/' },
					{ label: 'Temporary Access', url: 'https://aiologin.com/docs/wordpress-login-security/temporary-access/' },
				],
			},
			{
				title: 'Activity Logs',
				moreUrl: 'https://aiologin.com/docs/wordpress-login-page-activity-log/',
				items: [
					{ label: 'Lockout Users Activity Logs', url: 'https://aiologin.com/docs/wordpress-login-page-activity-log/activity-logs-for-lockout-users/' },
					{ label: 'Failed Login Attempts Logs', url: 'https://aiologin.com/docs/wordpress-login-page-activity-log/activity-logs-for-failed-login-attempts/' },
					{ label: 'User Enumeration Logs', url: 'https://aiologin.com/docs/wordpress-login-page-activity-log/logs-for-users-enumeration/' },
				],
			},
			{
				title: 'Social Login Apps',
				moreUrl: 'https://aiologin.com/docs/social-login-apps/',
				items: [
					{ label: 'Google Social Login App', url: 'https://aiologin.com/docs/social-login-apps/google-social-login-app/' },
					{ label: 'Microsoft Social Login App', url: 'https://aiologin.com/docs/social-login-apps/microsoft-social-login-app/' },
					{ label: 'Facebook Social Login App', url: 'https://aiologin.com/docs/social-login-apps/facebook-social-login-app/' },
				],
			},
			{
				title: 'Integrations',
				moreUrl: 'https://aiologin.com/docs/integrations/',
				items: [
					{ label: 'WooCommerce Integration', url: 'https://aiologin.com/docs/integrations/aiologin-woocommerce-integrations/' },
				],
			},
		],
	} ),
}
</script>

<style scoped>
.aio-login-dashboard-docs {
	width: 100%;
	box-sizing: border-box;
	overflow: hidden;
	border: 1px solid #dde3ee;
	border-radius: 10px;
	background: linear-gradient(180deg, #f8fbff 0%, #f4f8ff 100%);
	padding: 16px;
	margin-bottom: 24px;
}

.aio-login-dashboard-docs__head {
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin-bottom: 14px;
	padding: 0 4px;
}

.aio-login-dashboard-docs__head h2 {
	margin: 0;
	font-size: 19px;
	font-weight: 600;
	letter-spacing: 0.1px;
	color: #27364b;
}

.aio-login-dashboard-docs__head a {
	color: #445b7c;
	text-decoration: none;
	font-weight: 600;
	font-size: 12px;
}

.aio-login-dashboard-docs__head a:hover {
	color: #27364b;
	text-decoration: underline;
}

.aio-login-dashboard-docs__grid {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 12px;
}

.aio-login-dashboard-docs__card {
	background: #ffffff;
	border: 1px solid #e1e7f0;
	border-radius: 8px;
	padding: 13px 14px;
	min-height: 140px;
	box-sizing: border-box;
	transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
}

.aio-login-dashboard-docs__card:hover {
	border-color: #cfd9ea;
	box-shadow: 0 4px 14px rgba(39, 54, 75, 0.07);
	transform: translateY(-1px);
}

.aio-login-dashboard-docs__card h3 {
	margin: 0 0 9px;
	color: #2f425f;
	font-size: 15px;
	font-weight: 600;
}

.aio-login-dashboard-docs__card ul {
	margin: 0;
	padding: 0;
	list-style: none;
}

.aio-login-dashboard-docs__card li + li {
	margin-top: 7px;
}

.aio-login-dashboard-docs__card a {
	color: #4f617d;
	font-size: 12px;
	text-decoration: none;
	line-height: 1.45;
	word-break: break-word;
}

.aio-login-dashboard-docs__card a:hover {
	color: #27364b;
	text-decoration: underline;
}

.aio-login-dashboard-docs__more {
	display: inline-block;
	margin-top: 11px;
	color: #2d4568;
	font-weight: 600;
	font-size: 12px;
}

@media (max-width: 1200px) {
	.aio-login-dashboard-docs__grid {
		grid-template-columns: repeat(2, minmax(0, 1fr));
	}
}

@media (max-width: 782px) {
	.aio-login-dashboard-docs__grid {
		grid-template-columns: 1fr;
	}
}
</style>
