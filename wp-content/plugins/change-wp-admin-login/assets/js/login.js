jQuery( document ).ready( function( $ ) {
	$( 'input[name="aio_login__user_agent"]#aio_login__user_agent' ).val( aioLoginGetUserAgent() )
} );

let aioLoginGetUserAgent = () => {
	let data;
	var ua = detect.parse( navigator.userAgent );
	data = ua.browser.family + " " + ua.os.name + " " + ua.device.type;
	return data;
}