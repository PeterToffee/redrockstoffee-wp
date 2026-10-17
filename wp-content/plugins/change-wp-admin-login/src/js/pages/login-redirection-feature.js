import { t } from '../i18n.js';
export default {
	name: 'aio-login-login-redirection',
	slug: 'login-redirection',
	template: `<div v-if="page_loaded" class="aio-login-lr-wrapper">
		<p v-if="loadError" class="aio-login-lr-load-error" role="alert">{{ loadError }}</p>
		<aio-login-form :action="nonce" v-on:handle-submit="saveSettings">
			<template v-slot:title>
				<span>${t("Login Redirection")}</span>
				<aio-login-tooltip
					:content="featureTooltip"
					placement="bottom"
				/>
			</template>
			<template v-slot:form-fields>
				<tr>
					<th><label for="aio-login-redirection-enable">${t("Enable Login Redirection")}</label></th>
					<td>
						<aio-login-toggle
							id="aio-login-redirection-enable"
							name="aio-login-redirection-enable"
							:enabled="settings.enabled"
							v-on:toggle-input="toggleEnabled"
						/>
						<p class="desc"><strong>${t("Enable to apply login & logout redirect rules.")}</strong></p>
					</td>
				</tr>
			</template>
		</aio-login-form>

		<div v-if="savedRedirectionEnabled" class="aio-login-lr-rules">
			<div class="aio-login-lr-rules-head">
				<button type="button" class="button button-primary aio-login-lr-add-btn" @click="openModal">${t("+ Add New")}</button>
			</div>
			<div class="aio-login-lr-dt">
				<div class="aio-login-lr-dt-toolbar">
					<div class="aio-login-lr-dt-length">
						<select v-model.number="tablePageSize" class="aio-login-lr-dt-select" aria-label="Rows per page">
							<option v-for="n in tablePageSizeOptions" :key="n" :value="n">{{ n }}</option>
						</select>
						<span class="aio-login-lr-dt-length-label">${t("entries per page")}</span>
					</div>
					<div class="aio-login-lr-dt-search">
						<label for="aio-login-lr-search" class="aio-login-lr-dt-search-label">${t("Search:")}</label>
						<input id="aio-login-lr-search" type="search" class="aio-login-lr-dt-search-input" v-model.trim="tableSearch" autocomplete="off" />
					</div>
				</div>
				<div class="aio-login-lr-table-scroll">
					<table class="aio-login-lr-datatable-table">
						<colgroup>
							<col class="aio-login-lr-col aio-login-lr-col--url" />
							<col class="aio-login-lr-col aio-login-lr-col--url" />
							<col class="aio-login-lr-col aio-login-lr-col--narrow" />
							<col class="aio-login-lr-col aio-login-lr-col--value" />
							<col class="aio-login-lr-col aio-login-lr-col--order" />
							<col class="aio-login-lr-col aio-login-lr-col--actions" />
						</colgroup>
						<thead>
							<tr>
								<th>${t("Login URL")}</th>
								<th>${t("Logout URL")}</th>
								<th>${t("Condition")}</th>
								<th>${t("Condition Value")}</th>
								<th class="col-order">
									<span class="aio-login-lr-order-th">
										${t("Order")}
										<span v-if="!ruleOrderUnlocked" class="aio-login-lr-condtype-pro">PRO</span>
									</span>
								</th>
								<th class="col-actions">${t("Actions")}</th>
							</tr>
						</thead>
						<tbody>
							<tr v-if="!rules.length" class="aio-login-lr-table-empty">
								<td :colspan="tableColspan">${t("No rules found.")}</td>
							</tr>
							<tr v-else-if="!filteredRules.length" class="aio-login-lr-table-empty">
								<td :colspan="tableColspan">${t("No matching entries.")}</td>
							</tr>
							<tr v-for="rule in paginatedRules" :key="rule.id">
								<td class="cell-url"><span class="aio-login-lr-url-text">{{ renderTarget(rule.login_target_type, rule.login_target_value) }}</span></td>
								<td class="cell-url"><span class="aio-login-lr-url-text">{{ renderTarget(rule.logout_target_type, rule.logout_target_value) }}</span></td>
								<td class="cell-condition">
									<span class="aio-login-lr-pill aio-login-lr-pill--condition">{{ prettyCondition(rule.condition_type) }}</span>
								</td>
								<td class="cell-cond-value">
									<template v-if="rule.condition_type === 'all_users'">
										<span class="aio-login-lr-cond-plain">&mdash;</span>
									</template>
									<span v-else class="aio-login-lr-pill aio-login-lr-pill--value">{{ conditionValueLabel(rule) }}</span>
								</td>
								<td
									class="cell-order"
									:class="{ 'is-locked': !ruleOrderUnlocked }"
									:title="!ruleOrderUnlocked ? $t('Upgrade to Pro to use rule priority order') : ''"
									@click="!ruleOrderUnlocked && handleProFeatureClick()"
								>
									<template v-if="ruleOrderUnlocked">{{ rule.order ?? '-' }}</template>
									<span v-else class="aio-login-lr-order-locked-cell" aria-hidden="true">—</span>
								</td>
								<td class="cell-actions">
									<button v-if="advanced_conditions || rule.condition_type === 'all_users'" type="button" class="aio-login-lr-action-link aio-login-lr-action-edit" @click="editRule(rule)">
										<span class="dashicons dashicons-edit" aria-hidden="true"></span>
										${t("Edit")}
									</button>
									<button
										v-else
										type="button"
										class="aio-login-lr-action-link aio-login-lr-action-edit aio-login-lr-action-edit--locked"
										:title="$t('Upgrade to edit per-user or per-role rules')"
										@click="handleProFeatureClick"
									>
										<span class="dashicons dashicons-edit" aria-hidden="true"></span>
										${t("Edit")}
									</button>
									<span class="aio-login-lr-action-sep" aria-hidden="true">|</span>
									<button type="button" class="aio-login-lr-action-link aio-login-lr-action-delete" @click="openDeleteConfirm(rule.id)">
										<span class="dashicons dashicons-trash" aria-hidden="true"></span>
										${t("Delete")}
									</button>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
				<div class="aio-login-lr-dt-footer" v-if="rules.length">
					<p class="aio-login-lr-dt-info">${t("Showing")} {{ displayFrom }} ${t("to")} {{ displayTo }} ${t("of")} {{ totalFiltered }} ${t("entries")}</p>
					<div class="aio-login-lr-dt-pager" role="navigation" aria-label="${t('Table pagination')}">
						<button type="button" class="aio-login-lr-dt-pager-btn" :disabled="!totalFiltered || tablePage <= 1" @click="goTablePage(1)" aria-label="${t('First page')}">&laquo;</button>
						<button type="button" class="aio-login-lr-dt-pager-btn" :disabled="!totalFiltered || tablePage <= 1" @click="goTablePage(tablePage - 1)" aria-label="${t('Previous page')}">&lsaquo;</button>
						<button
							v-for="p in tablePageNumbers"
							:key="'p-' + p"
							type="button"
							class="aio-login-lr-dt-pager-btn"
							:class="{ 'is-active': p === tablePage }"
							:disabled="!totalFiltered"
							@click="goTablePage(p)"
						>{{ p }}</button>
						<button type="button" class="aio-login-lr-dt-pager-btn" :disabled="!totalFiltered || tablePage >= totalTablePages" @click="goTablePage(tablePage + 1)" aria-label="${t('Next page')}">&rsaquo;</button>
						<button type="button" class="aio-login-lr-dt-pager-btn" :disabled="!totalFiltered || tablePage >= totalTablePages" @click="goTablePage(totalTablePages)" aria-label="${t('Last page')}">&raquo;</button>
					</div>
				</div>
			</div>
		</div>

		<div v-if="savedRedirectionEnabled" class="aio-login-lr-fallback">
			<div class="aio-login-lr-fallback-head">
				<div class="aio-login-lr-fallback-titles">
					<h3 class="aio-login-lr-fallback-title">${t("Fallback Redirection")}</h3>
					<p class="aio-login-lr-fallback-desc">${t("Used when a rule&apos;s login/logout URL is invalid or unreachable. Not used when the rule has &quot;No logout redirect&quot; — WordPress default logout applies then.")}</p>
				</div>
				<aio-login-toggle
					id="aio-login-fallback-enable"
					name="aio-login-fallback-enable"
					:enabled="settings.fallback_enabled"
					v-on:toggle-input="onFallbackToggle"
				/>
			</div>
			<div v-if="settings.fallback_enabled" class="aio-login-lr-fallback-body">
				<label class="aio-login-lr-fallback-choice">
					<input
						class="aio-login-lr-fallback-radio"
						type="radio"
						name="aio-login-fallback-type"
						value="dashboard"
						v-model="settings.fallback_type"
						@change="onFallbackTypeRadiosChange"
					/>
					<span class="aio-login-lr-fallback-choice-text">
						<span class="aio-login-lr-fallback-choice-label">${t("Default Dashboard")}</span>
						<span class="aio-login-lr-fallback-choice-hint">{{ fallbackDashboardHint }}</span>
					</span>
				</label>
				<label class="aio-login-lr-fallback-choice">
					<input
						class="aio-login-lr-fallback-radio"
						type="radio"
						name="aio-login-fallback-type"
						value="custom"
						v-model="settings.fallback_type"
						@change="onFallbackTypeRadiosChange"
					/>
					<span class="aio-login-lr-fallback-choice-text">
						<span class="aio-login-lr-fallback-choice-label">${t("Custom URL")}</span>
					</span>
				</label>
				<div v-if="settings.fallback_type === 'custom'" class="aio-login-lr-fallback-custom">
					<input
						id="aio-login-fallback-custom"
						type="text"
						class="regular-text"
						v-model="settings.fallback_custom_url"
						placeholder="https://example.com/profile"
						autocomplete="off"
						@input="scheduleFallbackCustomPersist"
						@blur="persistFallbackSettingsDebouncedFlush"
					/>
				</div>
				<p v-if="persist_fallback_loading" class="aio-login-lr-fallback-saving">${t("Saving…")}</p>
			</div>
		</div>

		<div v-if="show_modal" class="aio-login-lr-modal-backdrop" @click.self="show_modal = false">
			<div class="aio-login-lr-modal" role="dialog" aria-modal="true" aria-label="Add New Redirect Rule">
				<div class="aio-login-lr-modal-header">
					<h3>{{ is_edit_mode ? 'Edit Redirect Rule' : 'Add New Redirect Rule' }}</h3>
					<button type="button" class="aio-login-lr-close" aria-label="Close" @click="show_modal = false">&times;</button>
				</div>
				<div class="aio-login-lr-modal-body" @click.capture="onRuleModalBodyCapture">
				<div class="aio-login-lr-grid">
					<label id="aio-login-lr-condtype-label">${t("Condition Type")}</label>
					<div
						ref="conditionTypeRoot"
						class="aio-login-lr-condtype"
						:class="{ 'is-open': condition_type_menu_open }"
					>
						<button
							type="button"
							id="aio-login-lr-condtype-trigger"
							class="aio-login-lr-condtype-trigger regular-text"
							aria-haspopup="listbox"
							:aria-expanded="condition_type_menu_open ? 'true' : 'false'"
							aria-labelledby="aio-login-lr-condtype-label aio-login-lr-condtype-trigger"
							@click.stop="toggleConditionTypeMenu"
						>
							<span class="aio-login-lr-condtype-trigger-label">{{ conditionTypeTriggerLabel }}</span>
							<span class="aio-login-lr-condtype-chevron dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
						</button>
						<div
							v-show="condition_type_menu_open"
							class="aio-login-lr-condtype-panel"
							role="listbox"
							aria-labelledby="aio-login-lr-condtype-label"
							@click.stop
						>
							<button type="button" class="aio-login-lr-condtype-item" role="option" @click="selectConditionType('all_users')">${t("All Users")}</button>
							<button
								type="button"
								class="aio-login-lr-condtype-item"
								:class="{ 'is-locked': !advanced_conditions }"
								role="option"
								:aria-disabled="!advanced_conditions ? 'true' : 'false'"
								@click.stop="onConditionTypeItemClick('user_role')"
							>
								<span>${t("User Role")}</span>
								<span v-if="!advanced_conditions" class="aio-login-lr-condtype-pro">PRO</span>
							</button>
							<button
								type="button"
								class="aio-login-lr-condtype-item"
								:class="{ 'is-locked': !advanced_conditions }"
								role="option"
								:aria-disabled="!advanced_conditions ? 'true' : 'false'"
								@click.stop="onConditionTypeItemClick('user')"
							>
								<span>${t("Specific user")}</span>
								<span v-if="!advanced_conditions" class="aio-login-lr-condtype-pro">PRO</span>
							</button>
						</div>
						<p class="aio-login-lr-help">${t("Choose what determines this redirect.")}</p>
					</div>

					<template v-if="draft.condition_type === 'user_role'">
						<label id="aio-login-lr-role-ms-label">${t("Select Roles")}</label>
						<div
							ref="rolePickerRoot"
							class="aio-login-lr-ms"
							:class="{ 'is-open': role_picker_open }"
						>
							<button
								type="button"
								id="aio-login-lr-role-ms-trigger"
								class="aio-login-lr-ms-trigger"
								:class="{ 'aio-login-lr-input-error': ruleFormErrors.role_condition }"
								aria-haspopup="listbox"
								:aria-expanded="role_picker_open ? 'true' : 'false'"
								aria-labelledby="aio-login-lr-role-ms-label aio-login-lr-role-ms-trigger"
								@click.stop="toggleRolePicker"
							>
								<div class="aio-login-lr-ms-pills">
									<template v-if="!(draft.condition_role_slugs || []).length">
										<span class="aio-login-lr-ms-trigger-placeholder">${t("Choose roles...")}</span>
									</template>
									<span
										v-for="slug in draft.condition_role_slugs"
										:key="'r-pill-' + slug"
										class="aio-login-lr-ms-pill"
									>
										{{ labelForRoleSlug(slug) }}
										<button
											type="button"
											class="aio-login-lr-ms-pill-remove"
											:aria-label="'Remove ' + labelForRoleSlug(slug)"
											@click.stop="removeRoleTag(slug)"
										>&times;</button>
									</span>
								</div>
								<span class="aio-login-lr-ms-chevron dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
							</button>
							<div
								v-show="role_picker_open"
								id="aio-login-lr-role-ms-panel"
								class="aio-login-lr-ms-panel"
								role="listbox"
								aria-multiselectable="true"
								@click.stop
							>
								<div class="aio-login-lr-ms-search">
									<span class="dashicons dashicons-search" aria-hidden="true"></span>
									<input
										type="search"
										v-model.trim="role_picker_search"
										class="aio-login-lr-ms-search-input"
										placeholder="Search roles..."
										autocomplete="off"
										@click.stop
										@keydown.esc.stop.prevent="role_picker_open = false"
									/>
								</div>
								<ul class="aio-login-lr-ms-list">
									<li v-for="role in filteredRolesForPicker" :key="role.value" class="aio-login-lr-ms-item">
										<label class="aio-login-lr-ms-option">
											<input
												type="checkbox"
												class="aio-login-lr-ms-check"
												:value="String(role.value)"
												v-model="draft.condition_role_slugs"
												@change="ruleFormErrors.role_condition = ''"
												@click.stop
											/>
											<span class="aio-login-lr-ms-option-label">{{ role.label }}</span>
										</label>
									</li>
									<li v-if="!filteredRolesForPicker.length" class="aio-login-lr-ms-empty">${t("No roles match your search.")}</li>
								</ul>
								<div class="aio-login-lr-ms-footer">
									<span class="aio-login-lr-ms-footer-count">{{ (draft.condition_role_slugs || []).length }} ${t("selected")}</span>
									<button type="button" class="aio-login-lr-ms-clear-all" @click.stop="clearAllRoles">${t("Clear all")}</button>
								</div>
							</div>
							<p class="aio-login-lr-help">${t("Rule applies when the user has any of the selected roles.")}</p>
							<p v-if="ruleFormErrors.role_condition" class="aio-login-lr-field-error">{{ ruleFormErrors.role_condition }}</p>
						</div>
					</template>
					<template v-if="draft.condition_type === 'user'">
						<label id="aio-login-lr-user-ms-label">${t("Select Users")}</label>
						<div
							ref="userPickerRoot"
							class="aio-login-lr-ms"
							:class="{ 'is-open': user_picker_open }"
						>
							<button
								type="button"
								id="aio-login-lr-user-ms-trigger"
								class="aio-login-lr-ms-trigger"
								:class="{ 'aio-login-lr-input-error': ruleFormErrors.user_condition }"
								aria-haspopup="listbox"
								:aria-expanded="user_picker_open ? 'true' : 'false'"
								aria-labelledby="aio-login-lr-user-ms-label aio-login-lr-user-ms-trigger"
								@click.stop="toggleUserPicker"
							>
								<div class="aio-login-lr-ms-pills">
									<template v-if="!(draft.condition_user_ids || []).length">
										<span class="aio-login-lr-ms-trigger-placeholder">${t("Choose users...")}</span>
									</template>
									<span
										v-for="uid in draft.condition_user_ids"
										:key="'u-pill-' + uid"
										class="aio-login-lr-ms-pill"
									>
										{{ labelForUserId(uid) }}
										<button
											type="button"
											class="aio-login-lr-ms-pill-remove"
											:aria-label="'Remove ' + labelForUserId(uid)"
											@click.stop="removeUserTag(uid)"
										>&times;</button>
									</span>
								</div>
								<span class="aio-login-lr-ms-chevron dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
							</button>
							<div
								v-show="user_picker_open"
								id="aio-login-lr-user-ms-panel"
								class="aio-login-lr-ms-panel"
								role="listbox"
								aria-multiselectable="true"
								@click.stop
							>
								<div class="aio-login-lr-ms-search">
									<span class="dashicons dashicons-search" aria-hidden="true"></span>
									<input
										type="search"
										v-model.trim="user_picker_search"
										class="aio-login-lr-ms-search-input"
										placeholder="Search users..."
										autocomplete="off"
										@click.stop
										@keydown.esc.stop.prevent="user_picker_open = false"
									/>
								</div>
								<ul class="aio-login-lr-ms-list">
									<li v-for="user in filteredUsersForPicker" :key="user.value" class="aio-login-lr-ms-item">
										<label class="aio-login-lr-ms-option">
											<input
												type="checkbox"
												class="aio-login-lr-ms-check"
												:value="String(user.value)"
												v-model="draft.condition_user_ids"
												@change="ruleFormErrors.user_condition = ''"
												@click.stop
											/>
											<span class="aio-login-lr-ms-option-label">{{ user.label }}</span>
										</label>
									</li>
									<li v-if="!filteredUsersForPicker.length" class="aio-login-lr-ms-empty">${t("No users match your search.")}</li>
								</ul>
								<div class="aio-login-lr-ms-footer">
									<span class="aio-login-lr-ms-footer-count">{{ (draft.condition_user_ids || []).length }} ${t("selected")}</span>
									<button type="button" class="aio-login-lr-ms-clear-all" @click.stop="clearAllUsers">${t("Clear all")}</button>
								</div>
							</div>
							<p class="aio-login-lr-help">${t("Rule applies when any of the selected users logs in or out.")}</p>
							<p v-if="ruleFormErrors.user_condition" class="aio-login-lr-field-error">{{ ruleFormErrors.user_condition }}</p>
						</div>
					</template>

					<label for="aio-login-lr-draft-login-target">${t("Login Redirect URL")}</label>
					<div>
						<select
							id="aio-login-lr-draft-login-target"
							class="regular-text"
							v-model="draft.login_target"
							:class="{ 'aio-login-lr-input-error': ruleFormErrors.login_destination }"
							@change="onLoginDestinationChange"
						>
							<option value="" disabled>${t("Choose a destination...")}</option>
							<option value="custom">${t("Custom URL")}</option>
							<option v-for="page in meta.pages" :key="'login-' + page.value" :value="'page:' + page.value">{{ page.label }}</option>
						</select>
						<p class="aio-login-lr-help">${t("Pick an internal page or choose Custom URL to enter any external link.")}</p>
						<p v-if="ruleFormErrors.login_destination" class="aio-login-lr-field-error">{{ ruleFormErrors.login_destination }}</p>
						<input
							v-if="draft.login_target === 'custom'"
							type="text"
							class="regular-text"
							:class="{ 'aio-login-lr-input-error': ruleFormErrors.login_custom }"
							v-model="draft.login_custom"
							placeholder="https://example.com/profile"
							:aria-invalid="ruleFormErrors.login_custom ? 'true' : 'false'"
							@input="ruleFormErrors.login_custom = ''"
						/>
						<p v-if="ruleFormErrors.login_custom" class="aio-login-lr-field-error">{{ ruleFormErrors.login_custom }}</p>
					</div>

					<label for="aio-login-lr-draft-logout-target">${t("Logout Redirect URL")} <span class="description">${t("(optional)")}</span></label>
					<div>
						<select
							id="aio-login-lr-draft-logout-target"
							class="regular-text"
							v-model="draft.logout_target"
							:class="{ 'aio-login-lr-input-error': ruleFormErrors.logout_destination }"
							@change="onLogoutDestinationChange"
						>
							<option value="">${t("No logout redirect")}</option>
							<option value="custom">${t("Custom URL")}</option>
							<option v-for="page in meta.pages" :key="'logout-' + page.value" :value="'page:' + page.value">{{ page.label }}</option>
						</select>
						<p class="aio-login-lr-help">${t("Leave as &quot;No logout redirect&quot; to only apply this rule on login, or pick a page / custom URL for logout.")}</p>
						<p v-if="ruleFormErrors.logout_destination" class="aio-login-lr-field-error">{{ ruleFormErrors.logout_destination }}</p>
						<input
							v-if="draft.logout_target === 'custom'"
							type="text"
							class="regular-text"
							:class="{ 'aio-login-lr-input-error': ruleFormErrors.logout_custom }"
							v-model="draft.logout_custom"
							placeholder="https://example.com/logout"
							:aria-invalid="ruleFormErrors.logout_custom ? 'true' : 'false'"
							@input="ruleFormErrors.logout_custom = ''"
						/>
						<p v-if="ruleFormErrors.logout_custom" class="aio-login-lr-field-error">{{ ruleFormErrors.logout_custom }}</p>
					</div>

					<label class="aio-login-lr-order-label">
						<span>${t("Order")}</span>
						<span v-if="!ruleOrderUnlocked" class="aio-login-lr-condtype-pro">PRO</span>
					</label>
					<div
						class="aio-login-lr-order-control"
						:class="{ 'is-locked': !ruleOrderUnlocked }"
						@click="onOrderFieldClick"
					>
						<input
							type="number"
							class="small-text aio-login-lr-order-input"
							v-model.number="draft.order"
							min="0"
							step="1"
							:disabled="!ruleOrderUnlocked"
							:readonly="!ruleOrderUnlocked"
							:tabindex="ruleOrderUnlocked ? 0 : -1"
							:aria-disabled="!ruleOrderUnlocked ? 'true' : 'false'"
							@blur="clampDraftOrder"
						/>
						<p class="aio-login-lr-help">{{ orderHelpText }}</p>
					</div>
				</div>
				</div>
				<div class="aio-login-lr-modal-footer">
					<button type="button" class="button aio-login-lr-btn-cancel" @click="show_modal = false">${t("Cancel")}</button>
					<button type="button" class="button button-primary aio-login-lr-btn-save" @click="saveRule">{{ is_edit_mode ? $t('Update Rule') : $t('Save Rule') }}</button>
				</div>
			</div>
		</div>

		<div v-if="show_delete_modal" class="aio-login-lr-modal-backdrop" @click.self="closeDeleteModal">
			<div class="aio-login-lr-modal aio-login-lr-delete-confirm-modal" role="dialog" aria-modal="true" aria-labelledby="aio-login-lr-delete-title">
				<div class="aio-login-lr-delete-confirm-body">
					<h3 id="aio-login-lr-delete-title" class="aio-login-lr-delete-confirm-title">${t("Delete this rule?")}</h3>
					<p class="aio-login-lr-delete-confirm-text">${t("This will permanently remove the redirect rule. This action cannot be undone.")}</p>
				</div>
				<div class="aio-login-lr-delete-confirm-footer">
					<button type="button" class="button aio-login-lr-delete-confirm-cancel" @click="closeDeleteModal">${t("Cancel")}</button>
					<button type="button" class="button aio-login-lr-delete-confirm-submit" @click="confirmDeleteRule">${t("Delete")}</button>
				</div>
			</div>
		</div>

		<aio-login-snackbar
			:message="snackbar.message"
			v-if="snackbar.show"
			:duration="snackbar.timeout"
			v-on:close="handleSnackbarClose"
		/>
	</div>`,
	data: () => ({
		featureTooltip: t( 'Login Redirection feature allows administrators to define simple, rule-based redirects for users on login and logout.' ),
		orderHelpText: t( 'Set the rule priority order. Lower numbers have higher priority (1, 2, 3, etc.), while 0 disables priority-based ordering.' ),
		nonce: '',
		page_loaded: false,
		loadError: '',
		savedRedirectionEnabled: false,
		advanced_conditions: false,
		rule_order_allowed: false,
		condition_type_menu_open: false,
		show_modal: false,
		show_delete_modal: false,
		pending_delete_id: '',
		placeholders_open: true,
		fallback_placeholders_open: true,
		is_edit_mode: false,
		settings: {
			enabled: false,
			fallback_enabled: false,
			fallback_type: 'dashboard',
			fallback_custom_url: '',
		},
		rules: [],
		meta: {
			pages: [],
			roles: [],
			users: [],
			fallback_dashboard_path: '/wp-admin/',
		},
		persist_fallback_loading: false,
		fallbackPersistTimer: null,
		draft: {
			id: '',
			condition_type: 'all_users',
			condition_value: '',
			condition_role_slugs: [],
			condition_user_ids: [],
			login_target: '',
			login_custom: '',
			logout_target: '',
			logout_custom: '',
			order: 0,
		},
		user_picker_open: false,
		user_picker_search: '',
		role_picker_open: false,
		role_picker_search: '',
		snackbar: {
			message: '',
			show: false,
			timeout: 6000,
		},
		tablePageSize: 10,
		tablePage: 1,
		tableSearch: '',
		tablePageSizeOptions: [10, 25, 50, 100],
		ruleFormErrors: {
			login_destination: '',
			logout_destination: '',
			login_custom: '',
			logout_custom: '',
			user_condition: '',
			role_condition: '',
		},
	}),
	computed: {
		filteredRules() {
			const q = (this.tableSearch || '').trim().toLowerCase();
			if (!q) {
				return this.rules;
			}
			return this.rules.filter((r) =>
				this.ruleRowSearchBlob(r).includes(q)
			);
		},
		ruleOrderUnlocked() {
			return !!this.rule_order_allowed;
		},
		tableColspan() {
			return 6;
		},
		totalFiltered() {
			return this.filteredRules.length;
		},
		totalTablePages() {
			const n = this.totalFiltered;
			if (n <= 0) {
				return 1;
			}
			return Math.ceil(n / this.tablePageSize);
		},
		paginatedRules() {
			const start = (this.tablePage - 1) * this.tablePageSize;
			return this.filteredRules.slice(
				start,
				start + this.tablePageSize
			);
		},
		displayFrom() {
			if (!this.totalFiltered) {
				return 0;
			}
			return (this.tablePage - 1) * this.tablePageSize + 1;
		},
		displayTo() {
			if (!this.totalFiltered) {
				return 0;
			}
			return Math.min(
				this.tablePage * this.tablePageSize,
				this.totalFiltered
			);
		},
		tablePageNumbers() {
			const total = this.totalTablePages;
			const cur = this.tablePage;
			if (total <= 7) {
				return Array.from({ length: total }, (_, i) => i + 1);
			}
			let end = Math.min(total, Math.max(cur + 2, 5));
			let start = Math.max(1, end - 4);
			if (start === 1) {
				end = Math.min(total, start + 4);
			}
			const out = [];
			for (let i = start; i <= end; i++) {
				out.push(i);
			}
			return out;
		},
		filteredUsersForPicker() {
			const q = (this.user_picker_search || '').trim().toLowerCase();
			const list = this.meta.users || [];
			if (!q) {
				return list;
			}
			return list.filter((u) => {
				const label = String(u.label || '').toLowerCase();
				const val = String(u.value || '');
				return label.includes(q) || val.includes(q);
			});
		},
		filteredRolesForPicker() {
			const q = (this.role_picker_search || '').trim().toLowerCase();
			const list = this.meta.roles || [];
			if (!q) {
				return list;
			}
			return list.filter((r) => {
				const label = String(r.label || '').toLowerCase();
				const val = String(r.value || '').toLowerCase();
				return label.includes(q) || val.includes(q);
			});
		},
		fallbackDashboardHint() {
			const p = this.meta.fallback_dashboard_path;
			return p && String(p).trim() ? String(p).trim() : '/wp-admin/';
		},
		conditionTypeTriggerLabel() {
			return this.prettyCondition(this.draft.condition_type);
		},
	},
	watch: {
		tableSearch() {
			this.tablePage = 1;
		},
		tablePageSize() {
			this.tablePage = 1;
		},
		show_modal(val) {
			if (!val) {
				this.user_picker_open = false;
				this.user_picker_search = '';
				this.role_picker_open = false;
				this.role_picker_search = '';
				this.condition_type_menu_open = false;
			}
		},
		'draft.condition_type'(val, prev) {
			if (val !== prev) {
				this.ruleFormErrors.user_condition = '';
				this.ruleFormErrors.role_condition = '';
			}
			if (val !== 'user') {
				this.user_picker_open = false;
				this.user_picker_search = '';
			}
			if (val !== 'user_role') {
				this.role_picker_open = false;
				this.role_picker_search = '';
			}
		},
	},
	methods: {
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
		onOrderFieldClick(event) {
			if (this.ruleOrderUnlocked) {
				return;
			}
			if (event && typeof event.preventDefault === 'function') {
				event.preventDefault();
			}
			if (event && typeof event.stopPropagation === 'function') {
				event.stopPropagation();
			}
			this.handleProFeatureClick();
		},
		/**
		 * Re-fetch plan meta (e.g. after Freemius activation) so User / User Role unlock without full page reload.
		 */
		refreshPlanMeta() {
			return axios
				.get('aio-login/login-redirection/get-settings')
				.then((response) => {
					const d = response.data || {};
					if (d.nonce) {
						this.nonce = d.nonce;
					}
					const meta = d.meta || {};
					this.advanced_conditions = !!meta.advanced_conditions;
					this.rule_order_allowed = !!meta.rule_order_allowed;
					const metaRest = { ...meta };
					delete metaRest.advanced_conditions;
					delete metaRest.rule_order_allowed;
					this.meta = Object.assign({}, this.meta, metaRest);
				})
				.catch(() => { });
		},
		toggleConditionTypeMenu() {
			const opening = !this.condition_type_menu_open;
			if (opening) {
				this.refreshPlanMeta();
			}
			this.condition_type_menu_open = opening;
			if (this.condition_type_menu_open) {
				this.user_picker_open = false;
				this.role_picker_open = false;
			}
		},
		selectConditionType(type) {
			this.draft.condition_type = type;
			this.condition_type_menu_open = false;
		},
		onConditionTypeItemClick(type) {
			if (!this.advanced_conditions && (type === 'user' || type === 'user_role')) {
				this.condition_type_menu_open = false;
				this.handleProFeatureClick();
				return;
			}
			this.selectConditionType(type);
		},
		normalizeOrderForSubmit(value) {
			const n = parseInt(value, 10);
			if (!Number.isFinite(n) || n < 0) {
				return 0;
			}
			return n;
		},
		clampDraftOrder() {
			this.draft.order = this.normalizeOrderForSubmit(this.draft.order);
		},
		ruleRowSearchBlob(rule) {
			const parts = [
				this.renderTarget(rule.login_target_type, rule.login_target_value),
				this.renderTarget(rule.logout_target_type, rule.logout_target_value),
				this.prettyCondition(rule.condition_type),
				rule.condition_type === 'all_users'
					? 'all users'
					: this.conditionValueLabel(rule),
			];
			if (this.ruleOrderUnlocked) {
				parts.push(String(rule.order ?? ''));
			}
			return parts.join(' ').toLowerCase();
		},
		conditionValueLabel(rule) {
			if (!rule.condition_value || rule.condition_type === 'all_users') {
				return '-';
			}
			if (rule.condition_type === 'user_role') {
				const slugs = this.parseRoleConditionSlugs(rule.condition_value);
				if (!slugs.length) {
					return '-';
				}
				return slugs
					.map((slug) => {
						const role = this.meta.roles.find(
							(x) => String(x.value) === String(slug)
						);
						return role ? role.label : slug;
					})
					.join(', ');
			}
			if (rule.condition_type === 'user') {
				const ids = this.parseUserConditionIds(rule.condition_value);
				if (!ids.length) {
					return '-';
				}
				return ids
					.map((id) => {
						const u = this.meta.users.find(
							(x) => String(x.value) === String(id)
						);
						return u ? u.label : id;
					})
					.join(', ');
			}
			return String(rule.condition_value);
		},
		goTablePage(p) {
			const t = this.totalTablePages;
			if (p < 1 || p > t) {
				return;
			}
			this.tablePage = p;
		},
		clampTablePage() {
			this.$nextTick(() => {
				if (!this.rules.length) {
					this.tablePage = 1;
					return;
				}
				if (!this.totalFiltered) {
					this.tablePage = 1;
					return;
				}
				const max = Math.max(
					1,
					Math.ceil(this.totalFiltered / this.tablePageSize)
				);
				if (this.tablePage > max) {
					this.tablePage = max;
				}
			});
		},
		toggleEnabled(value) {
			this.settings.enabled = value;
			if (!value) {
				this.savedRedirectionEnabled = false;
			}
		},
		onFallbackToggle(value) {
			this.settings.fallback_enabled = value;
			this.persistFallbackSettings(true);
		},
		onFallbackTypeRadiosChange() {
			this.persistFallbackSettings(true);
		},
		scheduleFallbackCustomPersist() {
			if (this.fallbackPersistTimer) {
				clearTimeout(this.fallbackPersistTimer);
			}
			this.fallbackPersistTimer = setTimeout(() => {
				this.fallbackPersistTimer = null;
				this.persistFallbackSettings(false);
			}, 550);
		},
		persistFallbackSettingsDebouncedFlush() {
			if (this.fallbackPersistTimer) {
				clearTimeout(this.fallbackPersistTimer);
				this.fallbackPersistTimer = null;
			}
			this.persistFallbackSettings(false);
		},
		persistFallbackSettings(showSnack) {
			this.persist_fallback_loading = true;
			axios
				.post('aio-login/login-redirection/save-settings', {
					_wpnonce: this.nonce,
					settings: this.settings,
				})
				.then((response) => {
					this.persist_fallback_loading = false;
					this.savedRedirectionEnabled = !!this.settings.enabled;
					if (showSnack) {
						this.snackbar.message =
							response.data.message ||
							'Fallback settings saved.';
						this.snackbar.show = true;
					}
				})
				.catch((err) => {
					this.persist_fallback_loading = false;
					const msg =
						err.response?.data?.message ||
						'Could not save fallback settings.';
					this.snackbar.message = msg;
					this.snackbar.show = true;
				});
		},
		prettyCondition(value) {
			if (value === 'all_users') return 'All Users';
			if (value === 'user_role') return 'User Role';
			if (value === 'user') return 'Specific user';
			return value;
		},
		renderTarget(type, value) {
			if (!value) return '-';
			if (type === 'page') {
				const page = this.meta.pages.find((p) => String(p.value) === String(value));
				return page ? page.url : value;
			}
			return value;
		},
		parseUserConditionIds(stored) {
			if (stored == null || stored === '') {
				return [];
			}
			if (Array.isArray(stored)) {
				return stored.map(String).filter(Boolean);
			}
			return String(stored)
				.split(/\s*,\s*/)
				.map((s) => s.trim())
				.filter(Boolean)
				.map(String);
		},
		parseRoleConditionSlugs(stored) {
			if (stored == null || stored === '') {
				return [];
			}
			if (Array.isArray(stored)) {
				return stored.map(String).map((s) => s.trim()).filter(Boolean);
			}
			return String(stored)
				.split(/\s*,\s*/)
				.map((s) => s.trim())
				.filter(Boolean)
				.map(String);
		},
		labelForUserId(uid) {
			const u = this.meta.users.find(
				(x) => String(x.value) === String(uid)
			);
			return u ? u.label : String(uid);
		},
		labelForRoleSlug(slug) {
			const r = this.meta.roles.find(
				(x) => String(x.value) === String(slug)
			);
			return r ? r.label : String(slug);
		},
		removeUserTag(uid) {
			const s = String(uid);
			this.draft.condition_user_ids = (
				this.draft.condition_user_ids || []
			).filter((x) => String(x) !== s);
			this.ruleFormErrors.user_condition = '';
		},
		removeRoleTag(slug) {
			const s = String(slug);
			this.draft.condition_role_slugs = (
				this.draft.condition_role_slugs || []
			).filter((x) => String(x) !== s);
			this.ruleFormErrors.role_condition = '';
		},
		clearAllUsers() {
			this.draft.condition_user_ids = [];
			this.ruleFormErrors.user_condition = '';
		},
		clearAllRoles() {
			this.draft.condition_role_slugs = [];
			this.ruleFormErrors.role_condition = '';
		},
		normalizeRoleSlugsForPayload(slugs) {
			const allowed = new Set((this.meta.roles || []).map((r) => String(r.value)));
			const out = [];
			const seen = new Set();
			for (const raw of slugs || []) {
				const key = String(raw || '').trim();
				if (!key || !allowed.has(key) || seen.has(key)) {
					continue;
				}
				seen.add(key);
				out.push(key);
			}
			return out;
		},
		normalizeUserIdsForPayload(ids) {
			const out = [];
			for (const raw of ids || []) {
				const n = parseInt(raw, 10);
				if (Number.isFinite(n) && n > 0) {
					out.push(n);
				}
			}
			return [...new Set(out)];
		},
		toggleUserPicker() {
			this.user_picker_open = !this.user_picker_open;
			if (this.user_picker_open) {
				this.role_picker_open = false;
				this.condition_type_menu_open = false;
				this.user_picker_search = '';
				this.ruleFormErrors.user_condition = '';
			}
		},
		toggleRolePicker() {
			this.role_picker_open = !this.role_picker_open;
			if (this.role_picker_open) {
				this.user_picker_open = false;
				this.condition_type_menu_open = false;
				this.role_picker_search = '';
				this.ruleFormErrors.role_condition = '';
			}
		},
		onRuleModalBodyCapture(ev) {
			const cr = this.$refs.conditionTypeRoot;
			if (this.condition_type_menu_open && cr && !cr.contains(ev.target)) {
				this.condition_type_menu_open = false;
			}
			const ur = this.$refs.userPickerRoot;
			if (this.user_picker_open && ur && !ur.contains(ev.target)) {
				this.user_picker_open = false;
			}
			const rr = this.$refs.rolePickerRoot;
			if (this.role_picker_open && rr && !rr.contains(ev.target)) {
				this.role_picker_open = false;
			}
		},
		clearRuleFormErrors() {
			this.ruleFormErrors.login_destination = '';
			this.ruleFormErrors.logout_destination = '';
			this.ruleFormErrors.login_custom = '';
			this.ruleFormErrors.logout_custom = '';
			this.ruleFormErrors.user_condition = '';
			this.ruleFormErrors.role_condition = '';
		},
		onLoginDestinationChange() {
			this.ruleFormErrors.login_destination = '';
			this.ruleFormErrors.login_custom = '';
			const loginDest = String(this.draft.login_target ?? '').trim();
			if (!loginDest || loginDest.startsWith('page:')) {
				this.draft.login_custom = '';
			}
		},
		onLogoutDestinationChange() {
			this.ruleFormErrors.logout_destination = '';
			this.ruleFormErrors.logout_custom = '';
			const dest = String(this.draft.logout_target ?? '').trim();
			if (!dest || dest.startsWith('page:')) {
				this.draft.logout_custom = '';
			}
		},
		buildLogoutRulePayload() {
			const dest = String(this.draft.logout_target ?? '').trim();
			if (!dest) {
				return { logout_target_type: 'custom', logout_target_value: '' };
			}
			if (dest.startsWith('page:')) {
				return {
					logout_target_type: 'page',
					logout_target_value: dest.replace(/^page:/, ''),
				};
			}
			if (dest === 'custom') {
				return {
					logout_target_type: 'custom',
					logout_target_value: String(this.draft.logout_custom ?? '').trim(),
				};
			}
			return { logout_target_type: 'custom', logout_target_value: '' };
		},
		validateRuleDraft() {
			this.clearRuleFormErrors();
			let ok = true;
			const loginDest = String(this.draft.login_target ?? '').trim();
			if (!loginDest) {
				this.ruleFormErrors.login_destination =
					'Please choose a login redirect destination.';
				ok = false;
			} else if (loginDest === 'custom') {
				const v = String(this.draft.login_custom || '').trim();
				if (!v) {
					this.ruleFormErrors.login_custom =
						'Please enter a login redirect URL when Custom URL is selected.';
					ok = false;
				}
			}
			if (this.draft.condition_type === 'user_role') {
				const rs = this.draft.condition_role_slugs || [];
				if (!rs.length) {
					this.ruleFormErrors.role_condition =
						'Please select at least one role.';
					ok = false;
				}
			}
			if (this.draft.condition_type === 'user') {
				const ids = this.draft.condition_user_ids || [];
				if (!ids.length) {
					this.ruleFormErrors.user_condition =
						'Please select at least one user.';
					ok = false;
				}
			}
			return ok;
		},
		openModal() {
			this.refreshPlanMeta().then(() => {
				this.draft = {
					id: '',
					condition_type: 'all_users',
					condition_value: '',
					condition_role_slugs: [],
					condition_user_ids: [],
					login_target: '',
					login_custom: '',
					logout_target: '',
					logout_custom: '',
					order: 0,
				};
				this.is_edit_mode = false;
				this.clearRuleFormErrors();
				this.show_modal = true;
			});
		},
		editRule(rule) {
			this.refreshPlanMeta().then(() => {
				if (
					!this.advanced_conditions &&
					(rule.condition_type === 'user' || rule.condition_type === 'user_role')
				) {
					this.handleProFeatureClick();
					return;
				}
				const loginIsPage = rule.login_target_type === 'page';
				const logoutIsPage = rule.logout_target_type === 'page';
				const logoutVal =
					rule.logout_target_value != null ? String(rule.logout_target_value) : '';
				const logoutHasValue = String(logoutVal).trim() !== '';
				let draftLogoutTarget = '';
				let draftLogoutCustom = '';
				if (logoutIsPage && logoutHasValue) {
					draftLogoutTarget = `page:${logoutVal}`;
				} else if (!logoutIsPage && logoutHasValue) {
					draftLogoutTarget = 'custom';
					draftLogoutCustom = logoutVal;
				}
				this.draft = {
					id: rule.id || '',
					condition_type: rule.condition_type || 'all_users',
					condition_value: rule.condition_value || '',
					condition_role_slugs:
						rule.condition_type === 'user_role'
							? this.parseRoleConditionSlugs(rule.condition_value)
							: [],
					condition_user_ids:
						rule.condition_type === 'user'
							? this.parseUserConditionIds(rule.condition_value)
							: [],
					login_target: loginIsPage ? `page:${rule.login_target_value}` : 'custom',
					login_custom: loginIsPage ? '' : (rule.login_target_value || ''),
					logout_target: draftLogoutTarget,
					logout_custom: draftLogoutCustom,
					order: this.normalizeOrderForSubmit(rule.order),
				};
				this.is_edit_mode = true;
				this.placeholders_open = true;
				this.clearRuleFormErrors();
				this.show_modal = true;
			});
		},
		saveRule() {
			if (!this.validateRuleDraft()) {
				return;
			}
			const conditionPayload =
				this.draft.condition_type === 'all_users'
					? ''
					: this.draft.condition_type === 'user_role'
						? this.normalizeRoleSlugsForPayload(
							this.draft.condition_role_slugs
						)
						: this.normalizeUserIdsForPayload(this.draft.condition_user_ids);
			const payload = {
				_wpnonce: this.nonce,
				id: this.draft.id || '',
				condition_type: this.draft.condition_type,
				condition_value: conditionPayload,
				login_target_type: String(this.draft.login_target).startsWith('page:') ? 'page' : 'custom',
				login_target_value: String(this.draft.login_target).startsWith('page:')
					? String(this.draft.login_target).replace(/^page:/, '')
					: this.draft.login_custom,
				...this.buildLogoutRulePayload(),
				order: this.ruleOrderUnlocked
					? this.normalizeOrderForSubmit(this.draft.order)
					: 0,
			};
			axios
				.post('aio-login/login-redirection/save-rule', payload)
				.then((response) => {
					this.rules = response.data.rules || [];
					this.clampTablePage();
					this.show_modal = false;
					this.is_edit_mode = false;
					this.clearRuleFormErrors();
					this.snackbar.message =
						response.data.message || 'Rule saved successfully.';
					this.snackbar.show = true;
				})
				.catch((err) => {
					const msg =
						err.response?.data?.message ||
						'Could not save the rule. Please check the form and try again.';
					this.snackbar.message = msg;
					this.snackbar.show = true;
				});
		},
		openDeleteConfirm(id) {
			this.pending_delete_id = id;
			this.show_delete_modal = true;
		},
		closeDeleteModal() {
			this.show_delete_modal = false;
			this.pending_delete_id = '';
		},
		confirmDeleteRule() {
			const id = this.pending_delete_id;
			if (!id) {
				return;
			}
			axios
				.post('aio-login/login-redirection/delete-rule', {
					_wpnonce: this.nonce,
					id,
				})
				.then((response) => {
					this.rules = response.data.rules || [];
					this.clampTablePage();
					this.closeDeleteModal();
					this.snackbar.message =
						response.data.message || 'Rule deleted successfully.';
					this.snackbar.show = true;
				});
		},
		saveSettings() {
			axios.post('aio-login/login-redirection/save-settings', {
				_wpnonce: this.nonce,
				settings: this.settings,
			}).then((response) => {
				this.savedRedirectionEnabled = !!this.settings.enabled;
				this.snackbar.message = response.data.message || this.$t( "Settings saved successfully." );
				this.snackbar.show = true;
			});
		},
		loadComponent() {
			axios.get('aio-login/login-redirection/get-settings').then((response) => {
				const data = (response && response.data) || {};
				this.nonce = data.nonce || '';
				const meta = data.meta || {};
				this.advanced_conditions = !!meta.advanced_conditions;
				this.rule_order_allowed = !!meta.rule_order_allowed;
				const metaRest = { ...meta };
				delete metaRest.advanced_conditions;
				delete metaRest.rule_order_allowed;
				this.settings = data.settings || this.settings;
				this.savedRedirectionEnabled = !!this.settings.enabled;
				this.rules = data.rules || [];
				this.meta = Object.assign({}, this.meta, metaRest);
				this.tablePage = 1;
				this.tableSearch = '';
				this.loadError = '';
				this.page_loaded = true;
			}).catch((error) => {
				const msg =
					(error && error.response && error.response.data && error.response.data.message) ||
					(error && error.message) ||
					'Unable to load Login Redirection settings. Please refresh the page.';
				this.loadError = msg;
				this.page_loaded = true;
			});
		},
		handleSnackbarClose() {
			this.snackbar.show = false;
		},
	},
	mounted() {
		this.loadComponent();
	},
	beforeDestroy() {
		if (this.fallbackPersistTimer) {
			clearTimeout(this.fallbackPersistTimer);
			this.fallbackPersistTimer = null;
		}
	},
}
