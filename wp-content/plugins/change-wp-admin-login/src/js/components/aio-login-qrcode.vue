<template>
	<div></div>
</template>

<script>
export default {
	name: 'aio-login-qrcode',

	props: {
		render: {
			type: String,
			default: 'canvas',
		},
		width: {
			type: Number,
			default: 200,
		},
		height: {
			type: Number,
			default: 200,
		},
		typeNumber: {
			type: Number,
			default: -1,
		},
		correctLevel: {
			type: Number,
			default: 3,
		},
		background: {
			type: String,
			default: '#ffffff',
		},
		foreground: {
			type: String,
			default: '#000000',
		},
		src: {
			type: String,
			required: true,
		},
	},

	data: () => ( {
	} ),

	watch: {},

	computed: {},

	methods: {
		createCanvas() {
			var qrcode = new QRCode( this.typeNumber, this.correctLevel );
			qrcode.addData( this.src );
			qrcode.make();

			var canvas = document.createElement( 'canvas' );
			canvas.width = this.width;
			canvas.height = this.height;
			var ctx = canvas.getContext( '2d' );

			var tileW = this.width / qrcode.getModuleCount();
			var tileH = this.height / qrcode.getModuleCount();

			for( var row = 0; row < qrcode.getModuleCount(); row++ ){
				for( var col = 0; col < qrcode.getModuleCount(); col++ ){
					ctx.fillStyle = qrcode.isDark( row, col ) ? this.foreground : this.background;
					var w = ( Math.ceil( ( col + 1 ) * tileW ) - Math.floor( col * tileW ) );
					var h = ( Math.ceil( ( row + 1 ) * tileW ) - Math.floor( row * tileW ) );
					ctx.fillRect( Math.round( col * tileW ), Math.round( row * tileH ), w, h );
				}
			}

			this.$emit( 'canvas', canvas );

			return canvas;
		},

		createTable() {
			var qrcode = new QRCode( this.typeNumber, this.correctLevel );
			qrcode.addData( this.src );
			qrcode.make();

			var table = document.createElement( 'table' );
			table.style.width = this.width + 'px';
			table.style.height = this.height + 'px';
			table.style.border = '0px';
			table.style.borderCollapse = 'collapse';
			table.style.backgroundColor = this.background;

			var tileW = this.width / qrcode.getModuleCount();
			var tileH = this.height / qrcode.getModuleCount();

			for( var row = 0; row < qrcode.getModuleCount(); row++ ){
				var tr = document.createElement( 'tr' );
				tr.style.height = tileH + 'px';

				for( var col = 0; col < qrcode.getModuleCount(); col++ ){
					var td = document.createElement( 'td' );
					td.style.width = tileW + 'px';
					td.style.backgroundColor = qrcode.isDark( row, col ) ? this.foreground : this.background;
					tr.appendChild( td );
				}

				table.appendChild( tr );
			}

			this.$emit( 'table', table );

			return table;
		},
	},

	mounted() {
		if ( this.render === 'canvas' ) {
			this.$el.appendChild( this.createCanvas() );
		} else {
			this.$el.appendChild( this.createTable() );
		}

		this.$emit( 'rendered' );
	},
}
</script>

<style scoped>

</style>