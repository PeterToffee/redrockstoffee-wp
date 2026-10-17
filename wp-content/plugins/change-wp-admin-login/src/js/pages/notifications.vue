<template>
	<div>
		<div class="aio-login-t-wrapper">
			<div :class="{ 'aio-login-locked-content': !has_pro }">
			<div v-if="!showSettings">
				<div>
					<h1>
						<span>{{ $t("Notifications") }}</span>
						<aio-login-tooltip
							:content="tooltipContent.notifications.content"
							:title="tooltipContent.notifications.title"
							placement="bottom"
						/>
					</h1>
					<p class="desc">
						{{ $t("Configure how you want to be notified about login activity and security-related events.") }}
					</p>
				</div>
				<div class="aio-login-pro__social-login">
					<aio-login-notification-channel-card
						channel="slack"
						:has-pro="has_pro"
						:enabled="slackEnabled"
						:loading="isLoadingSettings"
						:config-data="slackConfigData"
						@toggle-integration="handleSlackToggle"
						@configure-integration="showSlackSettings"
					/>
					<aio-login-notification-channel-card
						channel="webhook"
						:has-pro="has_pro"
						:enabled="webhookEnabled"
						:loading="isLoadingSettings"
						:config-data="webhookConfigData"
						@toggle-integration="handleWebhookToggle"
						@configure-integration="showWebhookSettings"
					/>
				</div>
			</div>

			<div v-else class="notifications-settings-view">
				<div class="notifications-settings-header">
					<div class="notifications-settings-header-brand">
						<img
							:src="settingsChannelIcon"
							:alt="settingsChannelTitle"
							class="channel-settings-icon"
						/>
						<div class="notifications-settings-header-text">
							<h2>{{ settingsPanelHeading }}</h2>
							<p class="notifications-settings-subtitle">
								{{ $t("Configure how you want to receive alerts about security events on your website.") }}
							</p>
						</div>
					</div>
					<button
						type="button"
						class="notifications-back-btn"
						@click="goBack"
					>
						{{ $t("← Back") }}
					</button>
				</div>

				<div class="notifications-settings-body">
					<template v-if="'slack' === settingsChannel">
						<template v-if="isLoadingSettings">
							<div class="notifications-form-row">
								<div class="notifications-form-label">
									<span class="notifications-skeleton notifications-skeleton--label"></span>
								</div>
								<div class="notifications-form-field">
									<div class="notifications-skeleton notifications-skeleton--toggle"></div>
									<div class="notifications-skeleton notifications-skeleton--hint"></div>
								</div>
							</div>
							<div class="notifications-form-row">
								<div class="notifications-form-label">
									<span class="notifications-skeleton notifications-skeleton--label"></span>
								</div>
								<div class="notifications-form-field">
									<div class="notifications-skeleton notifications-skeleton--input"></div>
									<div class="notifications-skeleton notifications-skeleton--hint notifications-skeleton--hint-wide"></div>
								</div>
							</div>
							<div class="notifications-form-row notifications-form-row--events">
								<div class="notifications-form-label">
									<span class="notifications-skeleton notifications-skeleton--label"></span>
								</div>
								<div class="notifications-form-field notifications-events-list">
									<div class="notifications-skeleton notifications-skeleton--checkbox"></div>
									<div class="notifications-skeleton notifications-skeleton--checkbox"></div>
									<div class="notifications-skeleton notifications-skeleton--checkbox"></div>
								</div>
							</div>
						</template>
						<template v-else>
						<div class="notifications-form-row">
							<div class="notifications-form-label">
								<span>{{ $t("Slack Notifications") }}</span>
								<aio-login-tooltip
									title="Slack Notifications"
									content="Turn on to send security notifications to your Slack workspace using an incoming webhook."
									placement="top"
								/>
							</div>
							<div class="notifications-form-field">
								<label class="toggle-switch" :class="{ 'disabled': !has_pro }">
									<aio-login-toggle
										id="notification-settings-slack-main"
										name="notification-settings-slack-main"
										v-on:toggle-input="handleSettingsToggle"
										:enabled="slackEnabled"
										:disabled="!has_pro"
									/>
								</label>
								<p class="notifications-field-hint">{{ $t("Receive real-time security alerts directly in your Slack workspace.") }}</p>
							</div>
						</div>

						<template v-if="slackEnabled && has_pro">
							<div class="notifications-form-row">
								<div class="notifications-form-label">
									<span>{{ $t("Slack Webhook URL") }}</span>
									<aio-login-tooltip
										title="Incoming webhook"
										content="Create an incoming webhook in your Slack app settings and paste the URL here."
										placement="top"
									/>
								</div>
								<div class="notifications-form-field">
									<div class="notifications-slack-url-row">
										<aio-login-text
											id="aio-login-notification-url-slack"
											name="aio-login-notification-url-slack"
											type="url"
											v-model="settingsUrlDraft"
											placeholder="https://hooks.slack.com/services/..."
											:readonly="true"
											:disabled="!has_pro"
										/>
										<button
											type="button"
											class="button notifications-connect-slack"
											:disabled="!has_pro || !slackConnectUrl"
											@click="connectWithSlack"
										>{{ $t("Connect with Slack") }}</button>
									</div>
									<p class="notifications-guide-line">
										{{ $t("Need help setting up?") }}
										<a
											:href="slackGuideUrl"
											class="notifications-guide-link"
											target="_blank"
											rel="noopener noreferrer"
										>
											{{ $t("View configuration guide") }}
											<span class="notifications-external-icon" aria-hidden="true">↗</span>
										</a>
									</p>
								</div>
							</div>

							<div class="notifications-form-row notifications-form-row--events">
								<div class="notifications-form-label">
									<span>{{ $t("Event Settings") }}</span>
								</div>
								<div class="notifications-form-field notifications-events-list">
									<label class="notifications-checkbox-row">
										<input
											type="checkbox"
											checked
											disabled
											class="notifications-checkbox notifications-checkbox--locked"
										/>
										<span>{{ $t("Lockout Attempts") }}</span>
										<aio-login-tooltip
											title="Lockout Attempts"
											content="Always included. Alerts when an IP or user is locked out after failed logins."
											placement="top"
										/>
									</label>
									<label class="notifications-checkbox-row">
										<input
											type="checkbox"
											v-model="slackEvents.failed_login"
											class="notifications-checkbox"
											:disabled="!has_pro"
										/>
										<span>{{ $t("Failed Login Attempts") }}</span>
										<aio-login-tooltip
											title="Failed Login Attempts"
											content="Notify when failed login attempts are recorded according to your security settings."
											placement="top"
										/>
									</label>
									<label class="notifications-checkbox-row">
										<input
											type="checkbox"
											v-model="slackEvents.user_enumeration"
											class="notifications-checkbox"
											:disabled="!has_pro"
										/>
										<span>{{ $t("User Enumeration Attempts") }}</span>
										<aio-login-tooltip
											title="User Enumeration Attempts"
											content="Notify when username or author enumeration is detected, if logging is enabled."
											placement="top"
										/>
									</label>
								</div>
							</div>
						</template>
						</template>
					</template>

					<template v-else>
						<template v-if="isLoadingSettings">
							<div class="notifications-form-row">
								<div class="notifications-form-label">
									<span class="notifications-skeleton notifications-skeleton--label"></span>
								</div>
								<div class="notifications-form-field">
									<div class="notifications-skeleton notifications-skeleton--toggle"></div>
									<div class="notifications-skeleton notifications-skeleton--hint"></div>
								</div>
							</div>
							<div class="notifications-form-row">
								<div class="notifications-form-label">
									<span class="notifications-skeleton notifications-skeleton--label"></span>
								</div>
								<div class="notifications-form-field">
									<div class="notifications-skeleton notifications-skeleton--input"></div>
									<div class="notifications-skeleton notifications-skeleton--hint notifications-skeleton--hint-wide"></div>
								</div>
							</div>
							<div class="notifications-form-row notifications-form-row--events">
								<div class="notifications-form-label">
									<span class="notifications-skeleton notifications-skeleton--label"></span>
								</div>
								<div class="notifications-form-field notifications-events-list">
									<div class="notifications-skeleton notifications-skeleton--checkbox"></div>
									<div class="notifications-skeleton notifications-skeleton--checkbox"></div>
									<div class="notifications-skeleton notifications-skeleton--checkbox"></div>
								</div>
							</div>
						</template>
						<template v-else>
						<div class="notifications-form-row">
							<div class="notifications-form-label">
								<span>{{ $t("Webhook Notifications") }}</span>
								<aio-login-tooltip
									title="Webhook Notifications"
									content="Send security events to your own HTTPS endpoint as JSON POST requests."
									placement="top"
								/>
							</div>
							<div class="notifications-form-field">
								<label class="toggle-switch" :class="{ 'disabled': !has_pro }">
									<aio-login-toggle
										id="notification-settings-webhook-main"
										name="notification-settings-webhook-main"
										v-on:toggle-input="handleSettingsToggle"
										:enabled="webhookEnabled"
										:disabled="!has_pro"
									/>
								</label>
								<p class="notifications-field-hint">{{ $t("Send security events to your custom endpoint for integration with other tools.") }}</p>
							</div>
						</div>

						<template v-if="webhookEnabled && has_pro">
							<div class="notifications-form-row">
								<div class="notifications-form-label">
									<span>{{ $t("Custom Webhook URL") }}</span>
								</div>
								<div class="notifications-form-field">
									<aio-login-text
										id="aio-login-notification-url-webhook"
										name="aio-login-notification-url-webhook"
										type="url"
										v-model="settingsUrlDraft"
										placeholder="https://example.com/webhook"
										:disabled="!has_pro"
									/>
									<p class="notifications-field-hint">{{ $t("Enter the endpoint URL where you want to receive security notifications. We'll send a POST request with event details.") }}</p>
								</div>
							</div>

							<div class="notifications-form-row notifications-form-row--events">
								<div class="notifications-form-label">
									<span>{{ $t("Event Settings") }}</span>
								</div>
								<div class="notifications-form-field notifications-events-list">
									<label class="notifications-checkbox-row">
										<input
											type="checkbox"
											checked
											disabled
											class="notifications-checkbox notifications-checkbox--locked"
										/>
										<span>{{ $t("Lockout Attempts") }}</span>
										<aio-login-tooltip
											title="Lockout Attempts"
											content="Always included. Alerts when an IP or user is locked out after failed logins."
											placement="top"
										/>
									</label>
									<label class="notifications-checkbox-row">
										<input
											type="checkbox"
											v-model="webhookEvents.failed_login"
											class="notifications-checkbox"
											:disabled="!has_pro"
										/>
										<span>{{ $t("Failed Login Attempts") }}</span>
										<aio-login-tooltip
											title="Failed Login Attempts"
											content="Notify when failed login attempts are recorded according to your security settings."
											placement="top"
										/>
									</label>
									<label class="notifications-checkbox-row">
										<input
											type="checkbox"
											v-model="webhookEvents.user_enumeration"
											class="notifications-checkbox"
											:disabled="!has_pro"
										/>
										<span>{{ $t("User Enumeration Attempts") }}</span>
										<aio-login-tooltip
											title="User Enumeration Attempts"
											content="Notify when username or author enumeration is detected, if logging is enabled."
											placement="top"
										/>
									</label>
								</div>
							</div>
						</template>
						</template>
					</template>

					<div class="submit">
						<button
							type="button"
							class="button aio-login__primary notifications-save-btn"
							:disabled="!has_pro || saving"
							@click="saveAllNotificationSettings"
						>
							{{ saving ? $t('Saving…') : $t('Save Changes') }}
						</button>
					</div>
				</div>
			</div>
			</div>
			<div v-if="!has_pro" class="aio-login-t-content-overflow" @click="handleProFeatureClick"></div>
		</div>

		<aio-login-snackbar
			:message="snackbar.message"
			v-if="snackbar.show"
			:duration="snackbar.duration"
			v-on:close="handleSnackbarClose"
		/>
	</div>
</template>

<script>
import tooltipContent from '../tooltip-content.js';
import resolveParentCurrentIsPro from '../resolve-parent-current-is-pro.js';
import { t } from '../i18n.js';

// Final desired URL examples:
// .../admin.php?page=aio-login&tab=activity-log#/notifications#/notifications-slack-settings
// .../admin.php?page=aio-login&tab=activity-log#/notifications#/notifications-webhook-settings
const HASH_SLACK = '#/notifications#/notifications-slack-settings';
const HASH_WEBHOOK = '#/notifications#/notifications-webhook-settings';
const SLACK_GUIDE_URL = 'https://slack.aiologin.com/';

function defaultEvents() {
	return {
		lockout: true,
		failed_login: false,
		user_enumeration: false,
	};
}

export default {
	name: 'notifications-page',

	slug: 'notifications',

	data: () => ( {
		tooltipContent,
		slackGuideUrl: SLACK_GUIDE_URL,
		assetsUrl: aio_login__app_object.assets_url,
		assetsVer: aio_login__app_object.version || '',
		showSettings: false,
		settingsChannel: 'slack',
		slackEnabled: false,
		webhookEnabled: false,
		slackConfigData: { url: '' },
		webhookConfigData: { url: '' },
		slackEvents: defaultEvents(),
		webhookEvents: defaultEvents(),
		settingsUrlDraft: '',
		saving: false,
		pendingSlackSuccessMessage: false,
		pendingSlackErrorMessage: false,
		snackbar: {
			message: '',
			show: false,
			duration: 4000,
		},
		isLoadingSettings: true,
	} ),

	computed: {
		has_pro() {
			return resolveParentCurrentIsPro(this);
		},

		settingsChannelTitle() {
			return 'slack' === this.settingsChannel ? 'Slack' : 'Webhook';
		},

		settingsPanelHeading() {
			return 'slack' === this.settingsChannel
				? 'Slack Notification'
				: 'Webhook Notification';
		},

		settingsChannelIcon() {
			const base = this.assetsUrl + 'images/icons/notification-' + this.settingsChannel + '.svg';
			return this.assetsVer ? base + '?ver=' + encodeURIComponent( this.assetsVer ) : base;
		},

		slackConnectUrl() {
			const u = aio_login__app_object.slack_connect_url;
			return typeof u === 'string' && u.trim().length > 0 ? u.trim() : '';
		},
	},

	mounted() {
		window.addEventListener( 'hashchange', this.checkUrlHash );
		this.processSlackOAuthReturnFlags();
		this.checkUrlHash();
		this.loadNotificationSettings().then( () => {
			if ( this.pendingSlackSuccessMessage ) {
				this.pendingSlackSuccessMessage = false;
				this.slackEnabled = true;
				this.syncSettingsUrlDraft();
				this.snackbar.message = 'Connected with Slack successfully.';
				this.snackbar.show = true;
			}
			if ( this.pendingSlackErrorMessage ) {
				this.pendingSlackErrorMessage = false;
				this.snackbar.message = 'Invalid Slack webhook. Please try Connect with Slack again.';
				this.snackbar.show = true;
			}
		} );
	},
	beforeUnmount() {
		window.removeEventListener( 'hashchange', this.checkUrlHash );
	},

	methods: {
		processSlackOAuthReturnFlags() {
			try {
				const u = new URL( window.location.href );
				const saved = u.searchParams.get( 'aio_slack_saved' );
				const err = u.searchParams.get( 'aio_slack_error' );
				if ( saved !== '1' && err !== '1' ) {
					return;
				}
				u.searchParams.delete( 'aio_slack_saved' );
				u.searchParams.delete( 'aio_slack_error' );
				const qs = u.searchParams.toString();
				const newBase = u.pathname + ( qs ? '?' + qs : '' );
				history.replaceState( null, '', newBase + HASH_SLACK );
				// Make sure UI opens the Slack settings panel immediately.
				this.showSettings = true;
				this.settingsChannel = 'slack';
				this.syncSettingsUrlDraft();

				if ( saved === '1' ) {
					this.pendingSlackSuccessMessage = true;
				}
				if ( err === '1' ) {
					this.pendingSlackErrorMessage = true;
				}
				// Ensure hash-dependent UI updates for any edge cases.
				window.dispatchEvent( new HashChangeEvent( 'hashchange' ) );
			} catch ( e ) {
				// ignore invalid URL
			}
		},

		normalizeEvents( ev ) {
			return {
				lockout: true,
				failed_login: !!( ev && ev.failed_login ),
				user_enumeration: !!( ev && ev.user_enumeration ),
			};
		},

		checkUrlHash() {
			const hash = window.location.hash || '';
			// Be tolerant: Vue-router may append / transform the hash.
			// We only care that the inner page is Slack/Webhook settings.
			const isSlack = hash.includes( 'notifications-slack-settings' );
			const isWebhook = hash.includes( 'notifications-webhook-settings' );
			if ( isSlack ) {
				this.showSettings = true;
				this.settingsChannel = 'slack';
				this.syncSettingsUrlDraft();
			} else if ( isWebhook ) {
				this.showSettings = true;
				this.settingsChannel = 'webhook';
				this.syncSettingsUrlDraft();
			} else {
				this.showSettings = false;
				if ( hash === '' || hash === '#' ) {
					const baseUrl = window.location.href.split( '#' )[0] + '#/notifications';
					if ( history.pushState ) {
						history.replaceState( null, null, baseUrl );
					}
				}
			}
		},

		updateUrlHash( hash ) {
			if ( history.pushState ) {
				const newHash = hash.startsWith( '#' ) ? hash : '#' + hash;
				const newUrl = window.location.href.split( '#' )[0] + newHash;
				history.pushState( null, null, newUrl );
				window.dispatchEvent( new HashChangeEvent( 'hashchange' ) );
			} else {
				window.location.hash = hash;
			}
		},

		goBack() {
			this.showSettings = false;
			const target = window.location.href.split( '#' )[0] + '#/notifications';
			if ( history.pushState ) {
				history.pushState( null, null, target );
				window.dispatchEvent( new HashChangeEvent( 'hashchange' ) );
			} else {
				window.location.hash = '#/notifications';
			}
		},

		syncSettingsUrlDraft() {
			const url = 'slack' === this.settingsChannel
				? ( this.slackConfigData.url || '' )
				: ( this.webhookConfigData.url || '' );
			this.settingsUrlDraft = url;
		},

		applyUrlDraftToConfig() {
			const url = String( this.settingsUrlDraft || '' ).trim();
			if ( 'slack' === this.settingsChannel ) {
				this.slackConfigData = { ...this.slackConfigData, url };
			} else {
				this.webhookConfigData = { ...this.webhookConfigData, url };
			}
		},

		async loadNotificationSettings() {
			this.isLoadingSettings = true;
			if ( ! this.has_pro ) {
				this.isLoadingSettings = false;
				return;
			}
			try {
				const response = await axios.get( 'aio-login-pro/notifications/get-settings' );
				if ( response.data && response.data.success && response.data.data ) {
					const d = response.data.data;
					if ( d.slack ) {
						this.slackEnabled = !! d.slack.enabled;
						this.slackConfigData = { url: d.slack.url || '' };
						if ( d.slack.events ) {
							this.slackEvents = this.normalizeEvents( d.slack.events );
						}
					}
					if ( d.webhook ) {
						this.webhookEnabled = !! d.webhook.enabled;
						this.webhookConfigData = { url: d.webhook.url || '' };
						if ( d.webhook.events ) {
							this.webhookEvents = this.normalizeEvents( d.webhook.events );
						}
					}
					if ( this.showSettings ) {
						this.syncSettingsUrlDraft();
					}
				}
			} catch ( e ) {
				console.error( 'Error loading notification settings:', e );
			} finally {
				this.isLoadingSettings = false;
			}
		},

		payloadFromState() {
			return {
				slack: {
					enabled: this.slackEnabled,
					url: this.slackConfigData.url || '',
					events: {
						lockout: true,
						failed_login: !! this.slackEvents.failed_login,
						user_enumeration: !! this.slackEvents.user_enumeration,
					},
				},
				webhook: {
					enabled: this.webhookEnabled,
					url: this.webhookConfigData.url || '',
					events: {
						lockout: true,
						failed_login: !! this.webhookEvents.failed_login,
						user_enumeration: !! this.webhookEvents.user_enumeration,
					},
				},
			};
		},

		async persistNotifications( message ) {
			try {
				await axios.post( 'aio-login-pro/notifications/save-settings', this.payloadFromState() );
				if ( message ) {
					this.snackbar.message = message;
					this.snackbar.show = true;
				}
			} catch ( e ) {
				console.error( 'Error saving notification settings:', e );
				this.snackbar.message = 'Could not save settings. Please try again.';
				this.snackbar.show = true;
			}
		},

		handleSlackToggle( on ) {
			if ( ! this.has_pro || this.isLoadingSettings ) {
				return;
			}
			this.slackEnabled = on;
			this.persistNotifications( on ? 'Slack notifications enabled.' : 'Slack notifications disabled.' );
		},

		handleWebhookToggle( on ) {
			if ( ! this.has_pro || this.isLoadingSettings ) {
				return;
			}
			this.webhookEnabled = on;
			this.persistNotifications( on ? 'Webhook notifications enabled.' : 'Webhook notifications disabled.' );
		},

		showSlackSettings() {
			if ( ! this.has_pro ) {
				return;
			}
			this.settingsChannel = 'slack';
			this.showSettings = true;
			this.syncSettingsUrlDraft();
			this.updateUrlHash( HASH_SLACK );
		},

		showWebhookSettings() {
			if ( ! this.has_pro ) {
				return;
			}
			this.settingsChannel = 'webhook';
			this.showSettings = true;
			this.syncSettingsUrlDraft();
			this.updateUrlHash( HASH_WEBHOOK );
		},

		handleSettingsToggle( on ) {
			if ( ! this.has_pro ) {
				return;
			}
			if ( 'slack' === this.settingsChannel ) {
				this.slackEnabled = on;
			} else {
				this.webhookEnabled = on;
			}
		},

		connectWithSlack() {
			if ( ! this.has_pro ) {
				return;
			}
			if ( ! this.slackConnectUrl ) {
				this.snackbar.message = 'Slack connection is not available. Open the configuration guide or contact support.';
				this.snackbar.show = true;
				return;
			}
			window.location.href = this.slackConnectUrl;
		},

		async saveAllNotificationSettings() {
			if ( ! this.has_pro ) {
				return;
			}
			this.applyUrlDraftToConfig();
			this.saving = true;
			try {
				const response = await axios.post( 'aio-login-pro/notifications/save-settings', this.payloadFromState() );
				const msg = ( response.data && response.data.message )
					? response.data.message
					: t( 'Settings saved successfully.' );
				this.snackbar.message = msg;
				this.snackbar.show = true;
			} catch ( e ) {
				console.error( 'Error saving notification settings:', e );
				this.snackbar.message = 'Could not save settings. Please try again.';
				this.snackbar.show = true;
			} finally {
				this.saving = false;
			}
		},

		handleSnackbarClose() {
			this.snackbar.show = false;
		},
		handleProFeatureClick() {
			let p = this.$parent;
			while (p) {
				if ('popup' in p && typeof p.popup === 'boolean') {
					p.popup = true;
					return;
				}
				p = p.$parent;
			}
		},
	},
}
</script>

<style scoped>
.notifications-intro {
	color: #4a5568;
	font-size: 15px;
	line-height: 1.5;
	margin: 0 0 20px;
	max-width: 640px;
}

.aio-login-pro__social-login {
	display: flex;
	flex-wrap: wrap;
	gap: 20px;
}

.aio-login-locked-content {
	filter: blur(1px);
	pointer-events: none;
	user-select: none;
}

.aio-login-t-content-overflow {
	position: absolute;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;
	background: rgba(255, 255, 255, 0.35);
	backdrop-filter: blur(0.5px);
	z-index: 100;
	cursor: pointer;
}

.notifications-settings-view {
	width: 100%;
	max-width: none;
}

.notifications-settings-header {
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
	gap: 24px;
	margin-bottom: 8px;
	padding-bottom: 24px;
	border-bottom: 1px solid #ebe8eb;
}

.notifications-settings-header-brand {
	display: flex;
	align-items: flex-start;
	gap: 16px;
	min-width: 0;
}

.channel-settings-icon {
	width: 48px;
	height: 48px;
	flex-shrink: 0;
}

.notifications-settings-header-text h2 {
	margin: 0 0 8px;
	font-size: 24px;
	font-weight: 600;
	color: #404280;
}

.notifications-settings-subtitle {
	margin: 0;
	font-size: 14px;
	line-height: 1.5;
	color: #606c80;
	max-width: 520px;
}

.notifications-back-btn {
	flex-shrink: 0;
	padding: 8px 16px;
	font-size: 14px;
	font-weight: 500;
	color: #404280;
	background: #fff;
	border: 1px solid #d1d5db;
	border-radius: 6px;
	cursor: pointer;
	transition: background 0.15s, border-color 0.15s;
}

.notifications-back-btn:hover {
	background: #f9fafb;
	border-color: #9ca3af;
}

.notifications-settings-body {
	padding-top: 8px;
}

.notifications-form-row {
	display: grid;
	grid-template-columns: minmax(180px, 260px) 1fr;
	gap: 16px 40px;
	align-items: start;
	padding: 22px 0;
	border-bottom: 1px solid #f0eef0;
}

.notifications-form-row--events {
	align-items: flex-start;
}

.notifications-form-label {
	display: flex;
	align-items: center;
	gap: 4px;
	font-size: 14px;
	font-weight: 600;
	color: #404280;
	padding-top: 6px;
}

.notifications-form-field {
	min-width: 0;
}

.notifications-field-hint {
	margin: 10px 0 0;
	font-size: 13px;
	line-height: 1.5;
	color: #606c80;
	max-width: 640px;
}

.notifications-slack-url-row {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 12px;
	max-width: 720px;
}

.notifications-slack-url-row :deep(.regular-text) {
	flex: 1 1 220px;
	min-width: 180px;
	max-width: 100%;
}

.notifications-connect-slack {
	border-color: #9516df !important;
	background-color: #9416de !important;
	color: #fff !important;
	padding: 6px 16px !important;
	height: auto !important;
	line-height: 1.5 !important;
	border-radius: 4px !important;
	cursor: pointer;
	white-space: nowrap;
}

.notifications-connect-slack:hover:not(:disabled) {
	filter: brightness(1.05);
}

.notifications-connect-slack:disabled {
	opacity: 0.6;
	cursor: not-allowed;
}

.notifications-guide-line {
	margin: 12px 0 0;
	font-size: 13px;
	color: #606c80;
}

.notifications-guide-link {
	color: #6e16df;
	text-decoration: none;
	font-weight: 500;
	margin-left: 4px;
}

.notifications-guide-link:hover {
	text-decoration: underline;
}

.notifications-external-icon {
	font-size: 12px;
	margin-left: 2px;
	opacity: 0.85;
}

.notifications-events-list {
	display: flex;
	flex-direction: column;
	gap: 14px;
}

.notifications-checkbox-row {
	display: flex;
	align-items: center;
	gap: 8px;
	font-size: 14px;
	color: #404280;
	cursor: pointer;
	margin: 0;
}

.notifications-checkbox {
	width: 18px;
	height: 18px;
	appearance: none;
	-webkit-appearance: none;
	-moz-appearance: none;
	border: 1.5px solid #b8c1d1;
	border-radius: 4px;
	background: #fff;
	background-image: none !important;
	cursor: pointer;
	flex-shrink: 0;
	display: inline-block;
	position: relative;
	transition: background-color 0.2s ease, border-color 0.2s ease;
	vertical-align: middle;
	outline: none;
	box-shadow: none;
}

.notifications-checkbox::before,
.notifications-checkbox:checked::before,
.notifications-checkbox:disabled::before {
	content: none !important;
	display: none !important;
	background: none !important;
}

.notifications-checkbox:checked {
	background: #9516df;
	border-color: #9516df;
}

.notifications-checkbox:checked::after {
	content: '';
	position: absolute;
	left: 5px;
	top: 1px;
	width: 4px;
	height: 9px;
	border: solid #fff;
	border-width: 0 2px 2px 0;
	transform: rotate(45deg);
	pointer-events: none;
}

.notifications-checkbox:disabled {
	cursor: not-allowed;
	opacity: 1;
}

.notifications-checkbox--locked {
	opacity: 0.85;
}

.toggle-switch.disabled {
	opacity: 0.6;
	cursor: not-allowed;
	pointer-events: none;
}

.submit {
	margin-top: 32px;
	padding-top: 24px;
	border-top: 1px solid #ebe8eb;
}

.notifications-save-btn {
	padding: 10px 22px !important;
	font-size: 14px !important;
	line-height: 1.5 !important;
	height: auto !important;
	min-width: 160px;
}

.submit .button.aio-login__primary {
	border-color: #9516df;
	background-color: #9416de;
	color: #fff;
}

.submit .button.aio-login__primary:hover:not(:disabled) {
	background-color: #7a12c4;
	color: #fff;
}

.submit .button:disabled {
	opacity: 0.65;
	cursor: not-allowed;
}

.notifications-skeleton {
	display: block;
	border-radius: 6px;
	background: linear-gradient( 90deg, #f2f4f8 25%, #e7ebf3 50%, #f2f4f8 75% );
	background-size: 200% 100%;
	animation: notifications-skeleton-shimmer 1.3s ease-in-out infinite;
}

.notifications-skeleton--label {
	width: 150px;
	height: 16px;
}

.notifications-skeleton--toggle {
	width: 52px;
	height: 28px;
}

.notifications-skeleton--hint {
	margin-top: 12px;
	width: 320px;
	max-width: 100%;
	height: 12px;
}

.notifications-skeleton--hint-wide {
	width: 440px;
}

.notifications-skeleton--input {
	width: 100%;
	max-width: 520px;
	height: 40px;
}

.notifications-skeleton--checkbox {
	width: 260px;
	max-width: 100%;
	height: 18px;
}

@keyframes notifications-skeleton-shimmer {
	0% {
		background-position: 200% 0;
	}
	100% {
		background-position: -200% 0;
	}
}

@media (max-width: 600px) {
	.notifications-form-row {
		grid-template-columns: 1fr;
		gap: 8px;
	}

	.notifications-settings-header {
		flex-direction: column;
		align-items: stretch;
	}

	.notifications-back-btn {
		align-self: flex-start;
	}
}
</style>
