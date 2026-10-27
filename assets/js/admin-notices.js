/**
 * OhMyLMS Admin Notices JavaScript
 *
 * Handles dismissible admin notices
 */
( function ( $ ) {
	'use strict';

	$( document ).ready( function () {
		// Handle dismissible notice clicks
		$( document ).on(
			'click',
			'.ohmylms-notice .notice-dismiss',
			function () {
				var $notice = $( this ).closest( '.ohmylms-notice' );
				var noticeType = $notice.data( 'notice' );

				if ( noticeType === 'pro-version' ) {
					// Send AJAX request to dismiss pro version notice
					$.ajax( {
						url: ohmylms_notices.ajax_url,
						type: 'POST',
						data: {
							action: 'ohmylms_dismiss_pro_version_notice',
							nonce: ohmylms_notices.nonce,
						},
						success: function ( response ) {
							if ( response.success ) {
								$notice.fadeOut();
							}
						},
						error: function () {
							console.error(
								'Failed to dismiss OhMyLMS Pro version notice'
							);
						},
					} );
				}
			}
		);
	} );
} )( jQuery );
