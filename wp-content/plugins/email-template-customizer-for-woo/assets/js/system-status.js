( function ( $ ) {
	'use strict';

	var cfg = window.viwecSystemStatus || {};
	var $btn = $( '#viwec-system-status-send' );
	var $issue = $( '#viwec-system-status-issue' );
	var $status = $( '#viwec-system-status-send-status' );

	if ( ! $btn.length ) {
		return;
	}

	function setStatus( message, type ) {
		$status.removeClass( 'is-success is-error' ).text( message || '' );
		if ( type ) {
			$status.addClass( 'is-' + type );
		}
	}

	$btn.on( 'click', function () {
		if ( $btn.prop( 'disabled' ) ) {
			return;
		}

		if ( ! window.confirm( ( cfg.i18n && cfg.i18n.confirm ) || 'Send report?' ) ) {
			return;
		}

		$btn.prop( 'disabled', true ).text( ( cfg.i18n && cfg.i18n.sending ) || 'Sending…' );
		setStatus( '', '' );

		$.post( cfg.ajaxUrl, {
			action: cfg.action,
			nonce: cfg.nonce,
			issue: $issue.val() || ''
		} ).done( function ( response ) {
			if ( ! response || ! response.success ) {
				var fail = ( cfg.i18n && cfg.i18n.failed ) || 'Failed';
				if ( response && response.data && response.data.message ) {
					fail = response.data.message;
				}
				setStatus( fail, 'error' );
				return;
			}

			setStatus( ( response.data && response.data.message ) || '', 'success' );
		} ).fail( function ( xhr ) {
			var message = ( cfg.i18n && cfg.i18n.failed ) || 'Failed';
			if ( xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ) {
				message = xhr.responseJSON.data.message;
			}
			setStatus( message, 'error' );
		} ).always( function () {
			$btn.prop( 'disabled', false ).text( ( cfg.i18n && cfg.i18n.send ) || 'Send support request' );
		} );
	} );
}( jQuery ) );
