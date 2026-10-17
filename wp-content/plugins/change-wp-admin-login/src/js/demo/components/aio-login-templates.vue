<template>
	<div v-if="page_loaded" class="aio-login-t-wrapper">

		<div>
			<aio-login-form
				action="nonce"
			>
				<template v-slot:title>
					<span>Templates</span>
					<aio-login-tooltip
						:content="tooltipContent.templates.content"
						:title="tooltipContent.templates.title"
						placement="bottom"
					/>
				</template>

				<template v-slot:form-fields>
					
					<tr>
						<td>
							<div class="aio-login-pro__templates">

								<div v-for="template in templates" :key="template.id" class="aio-login-pro__template-item" :class="{ 'aio-login-pro__template-item--pro-locked': templateLocked(template) }">
									<label v-if="!templateLocked(template)" :for="template.id">
										<input type="radio" :id="template.id" name="template" :value="template.id" >
										<div class="template-image-container">
											<img :src="template.img" :alt="template.name">
											<div class="template-preview-eye" @click.stop.prevent="showPreview(template)">
												<span class="dashicons dashicons-visibility"></span>
											</div>
										</div>
										<span class="template-name">{{ template.name }}</span>
									</label>
									<button
										v-else
										type="button"
										class="aio-login-pro__template-pro-card"
										@click="openProPopup"
									>
										<div class="template-image-container">
											<img :src="template.img" :alt="template.name">
											<span class="aio-login-pro__template-pro-badge">PRO</span>
										</div>
										<span class="template-name">{{ template.name }}</span>
									</button>
								</div>
								
							</div>
						</td>
					</tr>
					
				</template>
			</aio-login-form>

			<!-- Template Preview Modal -->
			<div v-if="preview_modal.show" class="aio-login-preview-modal-overlay" @click="closePreview">
				<div class="aio-login-preview-modal-content" @click.stop>
					<div class="aio-login-preview-modal-header">
						<h3>{{ preview_modal.template.name }} Preview</h3>
						<button class="aio-login-preview-modal-close" @click="closePreview">&times;</button>
					</div>
					<div class="aio-login-preview-modal-body">
						<img :src="preview_modal.template.img" :alt="preview_modal.template.name">
					</div>
				</div>
			</div>
		</div>

	</div>
</template>

<script>
import tooltipContent from '../../tooltip-content.js';

export default {
	name: 'aio-login-templates',

	slug: 'templates',

	props: {
		hasPro: {
			type: Boolean,
			default: false,
		},
		premiumTemplatesUnlocked: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			tooltipContent,
			page_loaded: false,
			preview_modal: {
				show: false,
				template: null
			}
		}
	},

	computed: {
		assets_url() {
			return typeof aio_login__app_object !== 'undefined' ? aio_login__app_object.assets_url : '';
		},
		templates() {
			// Future Tech (template-8) is the free default; others require Pro.
			return [
				{
					id: 'template-8',
					img: this.assets_url + '/images/templates/template-08.png',
					name: 'Future Tech Split',
				},
				{
					id: 'default',
					img: this.assets_url + '/images/templates/default.jpg',
					name: 'Modern Center',
				},
				{
					id: 'template-1',
					img: this.assets_url + '/images/templates/template-01.jpg',
					name: 'Classic Bold',
				},
				{
					id: 'template-2',
					img: this.assets_url + '/images/templates/template-02.jpg',
					name: 'Midnight Dark',
				},
				{
					id: 'template-3',
					img: this.assets_url + '/images/templates/template-03.png',
					name: 'Dynamic Split',
				},
				{
					id: 'template-4',
					img: this.assets_url + '/images/templates/template-04.png',
					name: 'Deep Glass',
				},
				{
					id: 'template-5',
					img: this.assets_url + '/images/templates/template-05.png',
					name: 'Corporate Pro',
				},
				{
					id: 'template-6',
					img: this.assets_url + '/images/templates/template-06.png',
					name: 'Vibrant Duo',
				},
				{
					id: 'template-7',
					img: this.assets_url + '/images/templates/template-07.png',
					name: 'Elegant Frost',
				},
			];
		}
	},

	methods: {
		templateLocked(template) {
			const freeIds = ['default', 'template-8'];
			if (freeIds.includes(template.id)) {
				return false;
			}
			return !this.premiumTemplatesUnlocked;
		},

		openProPopup() {
			this.$parent.$parent.$parent.popup = true;
		},

		loadComponent() {
			this.$nextTick( () => {
				this.page_loaded = true;
			} );
		},

		showPreview(template) {
			this.preview_modal.template = template;
			this.preview_modal.show = true;
		},

		closePreview() {
			this.preview_modal.show = false;
			this.preview_modal.template = null;
		}
	},

	mounted() {
		if ( ! this.hasPro ) {
			this.page_loaded = true;
		} else {
			this.loadComponent();
		}
	}
}
</script>

<style scoped>
.aio-login-t-wrapper {
	position: relative;
}

.aio-login-pro__template-item--pro-locked .template-image-container {
	opacity: 0.92;
}

.aio-login-pro__template-pro-card {
	display: block;
	width: 100%;
	margin: 0;
	padding: 0;
	border: none;
	background: transparent;
	cursor: pointer;
	text-align: center;
	font: inherit;
	color: inherit;
}

.aio-login-pro__template-pro-card:focus-visible {
	outline: 2px solid #9416de;
	outline-offset: 2px;
}

.aio-login-pro__template-pro-badge {
	position: absolute;
	top: 8px;
	left: 8px;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-width: 40px;
	padding: 2px 6px;
	background: linear-gradient(180deg, #6e16df 0%, #510c79 121.05%);
	border-radius: 2px;
	color: #ffce50;
	font-size: 12px;
	font-weight: 600;
	text-transform: uppercase;
	z-index: 12;
}

.aio-login-pro__templates {
	display: flex;
	flex-wrap: wrap;
	gap: 30px;
	padding: 20px 0;
}

.aio-login-pro__template-item {
	width: 210px;
	text-align: center;
	margin-bottom: 20px;
}

.aio-login-pro__template-item label {
	cursor: pointer;
	display: block;
	position: relative;
}

.aio-login-pro__templates input[type="radio"] {
	display: none;
}

.template-image-container {
	position: relative;
	width: 200px;
	height: 130px;
	margin: 0 auto 10px auto;
}

.aio-login-pro__templates img {
	display: block;
	width: 100%;
	height: 100%;
	object-fit: cover;
	border: 2px solid #eee;
	border-radius: 8px;
	transition: all 0.3s ease;
}

.template-preview-eye {
	position: absolute;
	top: 8px;
	right: 8px;
	background: #ffffff;
	width: 32px;
	height: 32px;
	border-radius: 6px;
	display: flex;
	align-items: center;
	justify-content: center;
	box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
	transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
	z-index: 10;
	color: #64748b;
	opacity: 0;
	transform: translateY(5px);
}

.aio-login-pro__template-item:hover .template-preview-eye {
	opacity: 1;
	transform: translateY(0);
}

.template-preview-eye:hover {
	background: #6D16DF;
	color: #fff;
	transform: scale(1.1);
}

.aio-login-pro__template-item .template-name {
	display: block;
	font-size: 13px;
	font-weight: 600;
	color: #404280;
	margin-top: 5px;
}

.aio-login-pro__templates input:checked + .template-image-container img {
	border-color: #9416de;
	box-shadow: 0 4px 15px rgba(148, 22, 222, 0.2);
	transform: translateY(-2px);
}

.aio-login-pro__templates input:checked ~ .template-name {
	color: #9416de;
}

/* Modal Styling */
.aio-login-preview-modal-overlay {
	position: fixed;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	background: rgba(0, 0, 0, 0.75);
	display: flex;
	justify-content: center;
	align-items: center;
	z-index: 99999;
	backdrop-filter: blur(4px);
}

.aio-login-preview-modal-content {
	background: #fff;
	border-radius: 12px;
	width: 90%;
	max-width: 900px;
	max-height: 90vh;
	display: flex;
	flex-direction: column;
	overflow: hidden;
	box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
	animation: aioModalIn 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

@keyframes aioModalIn {
	from {
		opacity: 0;
		transform: scale(0.95) translateY(10px);
	}
	to {
		opacity: 1;
		transform: scale(1) translateY(0);
	}
}

.aio-login-preview-modal-header {
	padding: 15px 20px;
	border-bottom: 1px solid #eee;
	display: flex;
	justify-content: space-between;
	align-items: center;
	background: #fcfcfc;
}

.aio-login-preview-modal-header h3 {
	margin: 0 !important;
	font-size: 16px !important;
	color: #1e293b !important;
}

.aio-login-preview-modal-close {
	background: none;
	border: none;
	font-size: 24px;
	cursor: pointer;
	color: #64748b;
	line-height: 1;
	padding: 0;
}

.aio-login-preview-modal-close:hover {
	color: #ef4444;
}

.aio-login-preview-modal-body {
	padding: 20px;
	overflow-y: auto;
	text-align: center;
}

.aio-login-preview-modal-body img {
	max-width: 100%;
	height: auto;
	border-radius: 4px;
	box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
}
</style>