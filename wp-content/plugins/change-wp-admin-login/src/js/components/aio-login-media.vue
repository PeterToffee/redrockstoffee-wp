<template>
	<div class="aio-login__media-uploader" :style="backgroundImage">
		<button
			:id="id"
			type="button"
			@click="handleUploadImage"
			class="button button-secondary"
			:style="displayUploadedImage"
		>{{ title }}</button>

		<button
			type="button"
			:style="displayRemoveImage"
			@click="handleRemoveImage"
			class="button button-secondary"
		>{{ $t("Remove") }}</button>
	</div>
</template>

<script>
export default {
	name: 'aio-login-media',

	props: {
		title: {
			type: String,
			default: 'Upload Image'
		},

		defaultImage: {
			type: String,
			default: ''
		},

		image: {
			type: String,
			default: ''
		},

		id: {
			type: String,
			required: true
		}
	},

	data: ( vm ) => ( {
		media_uploader: null,
		image_url: vm.image,
		image_id: '',
	} ),

	methods: {
		handleUploadImage( e ) {
			e.preventDefault();

			if ( this.media_uploader ) {
				this.media_uploader.open();
				return;
			}

			this.media_uploader = wp.media.frames.file_frame = wp.media( {
				title: this.title,
				multiple: false,
				button: {
					text: 'Choose Picture',
				},
			} );

			this.media_uploader.on( 'select', () => {
				var attachment = this.media_uploader.state().get('selection').first().toJSON();
				this.image_url = attachment.url;
				this.image_id  = attachment.id;

				this.$emit( 'image-updated', attachment );
			} );

			this.media_uploader.open();
		},

		handleRemoveImage( e ) {
			e.preventDefault();

			this.image_url = this.defaultImage;
			this.image_id  = '';

			this.$emit( 'image-removed' );
		}
	},

	computed: {
		displayUploadedImage() {
			if ( this.image_url === this.defaultImage ) {
				return { display: 'block' }
			} else {
				return { display: 'none' }
			}
		},

		displayRemoveImage() {
			if ( this.image_url === this.defaultImage ) {
				return { display: 'none' }
			} else {
				return { display: 'block' }
			}
		},

		backgroundImage() {
			return {
				backgroundImage: 'url(' + this.image_url + ')'
			}
		}
	}
}
</script>

<style scoped>
.aio-login__media-uploader {
	width: 250px;
	height: 250px;
	display: flex;
	justify-content: center;
	align-items: center;
	background-repeat: no-repeat;
	background-position: center;
	background-size: cover;
	background-color: rgba(0,0,0,0.5);
	border-radius: 50%;
}
</style>