<template>
	<div>
		<!-- Logging Settings Section -->
		<div class="aio-login-settings-section" style="margin-bottom: 30px;">
			<h3 style="margin-bottom: 20px; color: #333; font-size: 18px; font-weight: 600;">
				<span>{{ $t("Logging Settings") }}</span>
				<aio-login-tooltip
					:content="tooltipContent.loggingSettings.content"
					:title="tooltipContent.loggingSettings.title"
					placement="bottom"
				/>
			</h3>
			
			<div :class="{ 'aio-login-pro-feature': !has_pro }">
				<div :class="{ 'aio-login-pro-overlay': !has_pro }" @click="!has_pro ? handleProFeatureClick() : null">
					<table class="form-table" style="width: 100%; border-collapse: collapse;">
						<tr>
							<th style="width: 200px; text-align: left; padding: 12px 0; vertical-align: top;">
								<label for="log_enumeration_attempts">{{ $t("Log Enumeration Attempts") }}</label>
							</th>
							<td style="padding: 12px 0;">
								<aio-login-toggle
									id="log_enumeration_attempts"
									name="log_enumeration_attempts"
									:enabled="form_data.log_enumeration_attempts"
									v-on:toggle-input="handleLogEnumerationAttempts"
								/>
								<p class="description" style="margin: 8px 0 0 0; color: #666; font-size: 14px; font-weight: 600">{{ $t("Enable to monitor username discovery attempts securely.") }}</p>
							</td>
						</tr>

						<tr v-if="form_data.log_enumeration_attempts">
							<th style="width: 200px; text-align: left; padding: 12px 0; vertical-align: top;">
								<label for="log_enumeration_duration">{{ $t("Log Attempts Duration (Days)") }}</label>
							</th>
							<td style="padding: 12px 0;">
								<aio-login-text
									id="log_enumeration_duration"
									name="log_enumeration_duration"
									v-model="form_data.log_enumeration_duration"
									v-on:input="handleLogEnumerationDuration"
								/>
								<p class="description" style="margin: 8px 0 0 0; color: #666; font-size: 13px;">{{ $t("Number of days to keep enumeration attempt logs.") }}</p>
							</td>
						</tr>
					</table>

					<div style="margin-top: 20px;">
						<aio-login-submit-button @button-click="saveLoggingSettings" />
					</div>
				</div>
			</div>
		</div>

		<!-- Logs Display Section -->
		<div class="aio-login-logs-section">
			<h3 style="margin-bottom: 20px; color: #333; font-size: 18px; font-weight: 600;">
				<span>{{ $t("Enumeration Attempts Logs") }}</span>
				<aio-login-tooltip
					:content="tooltipContent.userEnumerationLogs.content"
					:title="tooltipContent.userEnumerationLogs.title"
					placement="bottom"
				/>
			</h3>
			
			<div :class="{ 'aio-login-pro-feature': !has_pro }">
				<div :class="{ 'aio-login-pro-overlay': !has_pro }" @click="!has_pro ? handleProFeatureClick() : null">
					<aio-login-datatable
						:headers="headers"
						:rows="data"
					></aio-login-datatable>
				</div>
			</div>
		</div>

		<!-- Delete Confirmation Popup -->
		<aio-login-popup 
			v-if="showDeletePopup" 
			width="500px" 
			height="auto" 
			content="content"
			@close-popup="closeDeletePopup"
		>
			<template #popup-title>
				<h3>{{ $t("Confirm Delete") }}</h3>
			</template>
			<template #popup-content>
				<div>
					<p style="margin: 0 0 8px 0; font-size: 16px; color: #333;">{{ $t("Are you sure you want to delete this log entry?") }}</p>
					<p style="margin: 0 0 20px 0; font-size: 14px; color: #dc3545; font-weight: 500;"><strong>{{ $t("This action cannot be undone.") }}</strong></p>
					
					<div v-if="deleteLogDetails" style="margin-top: 20px; padding: 20px; background: #f8f9fa; border-radius: 8px; border: 1px solid #e9ecef;">
					<h4 style="margin: 0 0 15px 0; color: #333; font-size: 16px; font-weight: 600;">{{ $t("Entry Details:") }}</h4>
					<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; font-size: 14px; line-height: 1.5;">
						<div style="padding: 8px 0; border-bottom: 1px solid #e9ecef;">
							<strong style="color: #495057;">{{ $t("ID:") }}</strong><br>
							<span style="color: #333;">{{ deleteLogDetails.id }}</span>
						</div>
						<div style="padding: 8px 0; border-bottom: 1px solid #e9ecef;">
							<strong style="color: #495057;">{{ $t("Username:") }}</strong><br>
							<span style="color: #333;">{{ deleteLogDetails.username || '-' }}</span>
						</div>
						<div style="padding: 8px 0; border-bottom: 1px solid #e9ecef;">
							<strong style="color: #495057;">{{ $t("IP Address:") }}</strong><br>
							<span style="color: #333;">{{ deleteLogDetails.ip_address }}</span>
						</div>
						<div style="padding: 8px 0; border-bottom: 1px solid #e9ecef;">
							<strong style="color: #495057;">{{ $t("Status:") }}</strong><br>
							<span style="color: #333;">{{ deleteLogDetails.status }}</span>
						</div>
						<div style="padding: 8px 0; border-bottom: 1px solid #e9ecef;">
							<strong style="color: #495057;">{{ $t("Date:") }}</strong><br>
							<span style="color: #333;">{{ deleteLogDetails.created_at }}</span>
						</div>
						<div style="padding: 8px 0; border-bottom: 1px solid #e9ecef;">
							<strong style="color: #495057;">{{ $t("Type:") }}</strong><br>
							<span style="color: #333;">{{ deleteLogDetails.type || '-' }}</span>
						</div>
					</div>
					<div style="margin-top: 15px; padding: 8px 0;">
						<strong style="color: #495057;">{{ $t("User Agent:") }}</strong><br>
						<span style="font-size: 13px; color: #666; word-break: break-all; line-height: 1.4; display: block; margin-top: 4px;">{{ deleteLogDetails.user_agent || '-' }}</span>
					</div>
				</div>
				</div>
			</template>
			<template #popup-footer>
				<div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 20px;">
					<button @click="closeDeletePopup" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500; transition: background-color 0.2s ease;">{{ $t("Cancel") }}</button>
					<button @click="confirmDelete" style="padding: 10px 20px; background: #dc3545; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500; transition: background-color 0.2s ease;">{{ $t("Delete") }}</button>
				</div>
			</template>
		</aio-login-popup>

		<!-- Success Notification -->
		<div v-if="showSuccessMessage" class="success-notification">
			{{ successMessage }}
		</div>

		<aio-login-snackbar
			:message="snackbar.message"
			v-if="snackbar.show"
			:duration="snackbar.timeout"
			v-on:close="handleSnackbarClose"
		/>
	</div>
</template>

<script>
import tooltipContent from '../tooltip-content.js';
import resolveParentCurrentIsPro from '../resolve-parent-current-is-pro.js';
import { t } from '../i18n.js';

export default {
	name: 'enumeration-protection-logs',

	data: () => ({
		tooltipContent,
		showDeletePopup: false,
		showSuccessMessage: false,
		successMessage: '',
		deleteLogId: null,
		deleteLogDetails: null,
		form_data: {
			log_enumeration_attempts: false,
			log_enumeration_duration: 30,
		},
		snackbar: {
			message: '',
			show: false,
			timeout: 3000,
		},
		headers: [
			{ value: t( 'ID' ), key: 'id' },
			{ value: t( 'User Login' ), key: 'user_login' },
			{ value: t( 'Date & Time' ), key: 'time' },
			{ value: t( 'Country' ), key: 'country' },
			{ value: t( 'City' ), key: 'city' },
			{ value: t( 'User Agent' ), key: 'user_agent' },
			{ value: t( 'IP Address' ), key: 'ip_address' },
			{ value: t( 'Status' ), key: 'status' },
			{ value: t( 'Blocked Until' ), key: 'blocked_until' },
			{ value: t( 'Actions' ), key: 'actions' },
		],

		data: [
			{
				id: '1',
				user_login: 'admin',
				time: '2024-01-15 14:30:25',
				country: 'United States',
				city: 'New York',
				user_agent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
				ip_address: '192.168.1.100',
				status: 'Blocked',
				blocked_until: '2024-01-16 14:30:25',
				actions: '<a href="#" class="action-btn unblock">Unblock</a><a href="#" class="action-btn delete">Delete</a>'
			},
			{
				id: '2',
				user_login: 'test',
				time: '2024-01-15 13:45:12',
				country: 'Germany',
				city: 'Berlin',
				user_agent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
				ip_address: '10.0.0.50',
				status: 'Blocked',
				blocked_until: '2024-01-16 13:45:12',
				actions: '<a href="#" class="action-btn unblock">Unblock</a><a href="#" class="action-btn delete">Delete</a>'
			},
			{
				id: '3',
				user_login: 'user123',
				time: '2024-01-15 12:20:45',
				country: 'United Kingdom',
				city: 'London',
				user_agent: 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36',
				ip_address: '172.16.0.25',
				status: 'Warning',
				blocked_until: '-',
				actions: '<a href="#" class="action-btn block">Block</a><a href="#" class="action-btn delete">Delete</a>'
			}
		],
	}),

	computed: {
		has_pro() {
			return resolveParentCurrentIsPro(this);
		},
	},

	mounted() {
		if (this.has_pro) {
			this.loadRealLogs();
		}
		this.loadLoggingSettings();
		
		// Add event delegation for button clicks
		this.$nextTick(() => {
			document.addEventListener('click', this.handleButtonClick.bind(this));
		});
	},

	beforeUnmount() {
		// Clean up event listener
		document.removeEventListener('click', this.handleButtonClick.bind(this));
	},

	methods: {
		loadLoggingSettings() {
			axios.get('aio-login/dashboard/activity-log-settings')
				.then(response => {
					if (response.data.success) {
						const data = response.data.data;
						this.form_data.log_enumeration_attempts = data.log_enumeration_attempts === 'on';
						this.form_data.log_enumeration_duration = parseInt(data.log_enumeration_duration) || 30;
					}
				})
				.catch(error => {
					console.error('Error loading logging settings:', error);
				});
		},

		handleLogEnumerationAttempts(value) {
			this.form_data.log_enumeration_attempts = value;
		},

		handleLogEnumerationDuration(value) {
			const actualValue = value.target ? value.target.value : value;
			
			// Allow empty field, only convert to number if there's a value
			if (actualValue === '' || actualValue === null || actualValue === undefined) {
				this.form_data.log_enumeration_duration = '';
			} else {
				const numValue = parseInt(actualValue);
				if (!isNaN(numValue) && numValue >= 1 && numValue <= 365) {
					this.form_data.log_enumeration_duration = numValue;
				} else if (numValue === 0) {
					// Show validation error for 0 and keep 0 in field
					this.showSnackbar('Duration must be between 1 and 365 days.');
					this.form_data.log_enumeration_duration = 0;
				}
				// If invalid, don't change the value
			}
		},

		saveLoggingSettings() {
			// Validate duration before saving
			const duration = this.form_data.log_enumeration_duration;
			if (duration === 0 || (duration !== '' && (duration < 1 || duration > 365))) {
				this.showSnackbar('Duration must be between 1 and 365 days.');
				return;
			}

			const settings = {
				log_enumeration_attempts: this.form_data.log_enumeration_attempts ? 'on' : 'off',
				log_enumeration_duration: this.form_data.log_enumeration_duration || 30,
			};

			axios.post('aio-login/dashboard/update/activity-log-settings', {
				settings: settings,
				_wpnonce: aio_login__app_object.nonce
			})
			.then(response => {
				if (response.data.success) {
					this.showSnackbar('Settings saved successfully!');
				} else {
					this.showSnackbar('Error saving settings.');
				}
			})
			.catch(error => {
				console.error('Error saving logging settings:', error);
				this.showSnackbar('Error saving settings.');
			});
		},

		showSnackbar(message) {
			this.snackbar.message = message;
			this.snackbar.show = true;
		},

		handleSnackbarClose() {
			this.snackbar.show = false;
		},

		loadRealLogs() {
			// Load real logs from pro plugin
			axios.get('aio-login-pro/v1/enumeration-logs')
				.then(response => {
					if (response.data.success) {
						this.data = response.data.data.map(log => {
							console.log('Processing log:', log.id, 'blocked_until:', log.blocked_until);
							
							return {
								id: log.id,
								user_login: log.username || '-',
								time: log.created_at,
								country: '-',
								city: '-',
								user_agent: log.user_agent || '-',
								ip_address: log.ip_address,
								status: this.getStatus(log.blocked_until),
								blocked_until: this.getBlockedUntilDisplay(log.blocked_until),
								actions: this.generateActions(log)
							};
						});
					}
				})
				.catch(error => {
					console.error('Error loading real logs:', error);
				});
		},

		getStatus(blockedUntil) {
			if (!blockedUntil || blockedUntil === 'null' || blockedUntil === null) {
				return 'Unblocked';
			}
			
			const blockedDate = new Date(blockedUntil);
			const now = new Date();
			
			if (blockedDate > now) {
				return 'Blocked';
			} else {
				return 'Unblocked';
			}
		},

		getBlockedUntilDisplay(blockedUntil) {
			if (!blockedUntil || blockedUntil === 'null' || blockedUntil === null) {
				return '-';
			}
			
			const blockedDate = new Date(blockedUntil);
			const now = new Date();
			
			if (blockedDate > now) {
				return blockedUntil;
			} else {
				return '-';
			}
		},

		generateActions(log) {
			let actions = '';
			
			// Check if IP is currently blocked
			const isBlocked = log.blocked_until && 
							 log.blocked_until !== 'null' && 
							 log.blocked_until !== null && 
							 log.blocked_until !== '-' &&
							 new Date(log.blocked_until) > new Date();
			
			console.log('Generating actions for log:', log.id, 'blocked_until:', log.blocked_until, 'isBlocked:', isBlocked);
			
			// Only show unblock button if IP is currently blocked
			// Don't show block button after unblocking
			if (isBlocked) {
				actions += `<a href="#" class="action-btn unblock" data-ip="${log.ip_address}" data-action="unblock">Unblock</a>`;
			}
			// If not blocked, don't show any block/unblock button
			
			// Add delete button
			actions += `<a href="#" class="action-btn delete" data-id="${log.id}" data-action="delete">Delete</a>`;
			
			return actions;
		},

		handleUnblockIP(ipAddress) {
			console.log('Unblock button clicked for IP:', ipAddress);
			
			if (!this.has_pro) {
				this.handleProFeatureClick();
				return;
			}

			axios.post('aio-login-pro/v1/unblock-ip', {
				ip_address: ipAddress
			})
			.then(response => {
				if (response.data.success) {
					// Show success message
					this.showSuccessNotification('IP address unblocked successfully!');
					// Reload the logs
					this.loadRealLogs();
				}
			})
			.catch(error => {
				console.error('Error unblocking IP:', error);
				this.showSuccessNotification('Error unblocking IP address.', 'error');
			});
		},

		handleBlockIP(ipAddress) {
			if (!this.has_pro) {
				this.handleProFeatureClick();
				return;
			}

			// For now, just show a message that blocking is not implemented
			this.showSuccessNotification('Block IP functionality will be implemented in the next update.', 'info');
		},

		handleDeleteLog(logId) {
			console.log('Delete button clicked for log ID:', logId);
			
			if (!this.has_pro) {
				this.handleProFeatureClick();
				return;
			}

			// Find the log details from the current data
			const logEntry = this.data.find(log => log.id == logId);
			if (logEntry) {
				// Get the original log data from the backend response
				axios.get('aio-login-pro/v1/enumeration-logs')
					.then(response => {
						if (response.data.success) {
							const originalLog = response.data.data.find(log => log.id == logId);
							if (originalLog) {
								this.deleteLogDetails = {
									id: originalLog.id,
									username: originalLog.username,
									ip_address: originalLog.ip_address,
									status: this.getStatus(originalLog.blocked_until),
									created_at: originalLog.created_at,
									type: originalLog.type,
									user_agent: originalLog.user_agent
								};
							}
						}
					})
					.catch(error => {
						console.error('Error fetching log details:', error);
					});
			}

			// Show delete confirmation popup
			this.deleteLogId = logId;
			this.showDeletePopup = true;
		},

		closeDeletePopup() {
			this.showDeletePopup = false;
			this.deleteLogId = null;
			this.deleteLogDetails = null;
		},

		confirmDelete() {
			if (!this.deleteLogId) return;

			axios.delete(`aio-login-pro/v1/enumeration-logs/${this.deleteLogId}`)
			.then(response => {
				if (response.data.success) {
					// Close popup
					this.closeDeletePopup();
					// Show success message
					this.showSuccessNotification('Log entry deleted successfully!');
					// Reload the logs
					this.loadRealLogs();
				}
			})
			.catch(error => {
				console.error('Error deleting log:', error);
				this.showSuccessNotification('Error deleting log entry.', 'error');
				this.closeDeletePopup();
			});
		},

		showSuccessNotification(message, type = 'success') {
			this.successMessage = message;
			this.showSuccessMessage = true;
			
			// Auto hide after 3 seconds
			setTimeout(() => {
				this.showSuccessMessage = false;
			}, 3000);
		},

		handleButtonClick(event) {
			// Check if clicked element is an action button
			if (event.target.classList.contains('action-btn')) {
				event.preventDefault();
				
				const action = event.target.getAttribute('data-action');
				const ipAddress = event.target.getAttribute('data-ip');
				const logId = event.target.getAttribute('data-id');
				
				console.log('Button clicked:', action, 'IP:', ipAddress, 'ID:', logId);
				
				switch (action) {
					case 'unblock':
						this.handleUnblockIP(ipAddress);
						break;
					case 'block':
						this.handleBlockIP(ipAddress);
						break;
					case 'delete':
						this.handleDeleteLog(logId);
						break;
				}
			}
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
		}
	}
}
</script>

<style scoped>
h2 {
	margin-top: 0;
	color: #333;
	font-size: 24px;
	margin-bottom: 20px;
}

.success-notification {
	position: fixed;
	top: 20px;
	right: 20px;
	background: #28a745;
	color: white;
	padding: 15px 20px;
	border-radius: 5px;
	box-shadow: 0 4px 12px rgba(0,0,0,0.15);
	z-index: 10000;
	animation: slideIn 0.3s ease-out;
}

.success-notification.error {
	background: #dc3545;
}

.success-notification.info {
	background: #17a2b8;
}

@keyframes slideIn {
	from {
		transform: translateX(100%);
		opacity: 0;
	}
	to {
		transform: translateX(0);
		opacity: 1;
	}
}
</style> 