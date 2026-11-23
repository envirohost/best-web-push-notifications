(function( $ ) {
	'use strict';
	const { __ } = wp.i18n;
	$( window ).on(
		'load',
		function(){
			$( ".bwpn-confirm-action" ).click(
				function(e){
					var page    = $( this ).data( "page" );
					var action  = $( this ).data( "action" );
					var id      = $( this ).data( "id" );
					var wpnonce = $( this ).data( "nonce" );
					if (confirm( __( 'Are you sure?', 'best-web-push-notifications' ) )) {
						var url              = "?page=" + page + "&action=" + action + "&id=" + id + "&_wpnonce=" + wpnonce;
						window.location.href = url;
					}
				}
			);

			// Send Notification > Select image
			$( "#bwpn-send-notification-image-custom" ).change(
				function(e){
					if (this.checked) {
						$( "#bwpn-send-notification-image" ).removeAttr( "disabled" );
						$( "#bwpn-send-notification-image-select" ).removeAttr( "disabled" );
					} else {
						$( "#bwpn-send-notification-image" ).attr( "disabled", "disabled" );
						$( "#bwpn-send-notification-image" ).val( "" );
						$( "#bwpn-send-notification-image-select" ).attr( "disabled", "disabled" );
						$( '.bwpn-send-notification-image-preview' ).attr( 'src', '' );
						$( '.bwpn-send-notification-image-preview-container' ).hide();
					}
				}
			);

			// Send Notification > Select image > Choose
			var bwpn_send_notification_choose;
			$( '#bwpn-send-notification-image-select' ).on(
				'click',
				function(event) {
					event.preventDefault();
					bwpn_send_notification_choose = wp.media(
						{
							title: 'Select image to upload',
							button: {
								text: 'Use this image',
							},
							multiple: false
						}
					);
					bwpn_send_notification_choose.on(
						'select',
						function() {
							let attachment = bwpn_send_notification_choose.state().get( 'selection' ).first().toJSON();
							$( '.bwpn-send-notification-image-preview-container' ).show();
							$( '.bwpn-send-notification-image-preview' ).attr( 'src', attachment.url );
							$( "#bwpn-send-notification-image" ).val( attachment.url );
						}
					);
					bwpn_send_notification_choose.open();
				}
			);
			if ($( '.bwpn-send-notification-image-preview' ).attr( 'src' ) !== "") {
				$( '.bwpn-send-notification-image-preview-container' ).show();
			}

			$( "#bwpn-send-notification-url-to-open-custom" ).change(
				function(e){
					if (this.checked) {
						$( "#bwpn-send-notification-url-to-open" ).removeAttr( "disabled" );
					} else {
						$( "#bwpn-send-notification-url-to-open" ).attr( "disabled", "disabled" );
					}
				}
			);

			$( "#form" ).submit(
				function(e){
					if ( $( "#bwpn-send-notification-schedule-notification" ).is( ":checked" ) ) {
						var scheduledTime      = new Date( $( "#bwpn-send-notification-scheduled-date" ).val() + 'T' + $( "#bwpn-send-notification-scheduled-time" ).val() );
						var scheduledTimeEpoch = Math.round( scheduledTime.getTime() / 1000 );
						var currentTimeEpoch   = Math.round( Date.now() / 1000 );
						var timeOffset         = scheduledTimeEpoch - currentTimeEpoch;
						$( "#bwpn-send-notification-timeoffset" ).val( timeOffset );
					}
				}
			);

			$( "#bwpn-send-notification-schedule-notification" ).change(
				function(e){
					if (this.checked) {
						$( "#bwpn-send-notification-scheduled-date" ).removeAttr( "disabled" );
						$( "#bwpn-send-notification-scheduled-time" ).removeAttr( "disabled" );

						var now        = new Date();
						var dateString = now.getFullYear().toString()
							+ '-' + ( now.getMonth() + 1 ).toString().padStart( 2, '0' )
							+ '-' + now.getDate().toString().padStart( 2, '0' );
						$( "#bwpn-send-notification-scheduled-date" ).attr( "min", dateString );
						$( "#bwpn-send-notification-scheduled-date" ).val( dateString );
						var timeString = now.getHours().toString().padStart( 2, '0' )
							+ ":" + now.getMinutes().toString().padStart( 2, '0' )
							+ ":" + now.getSeconds().toString().padStart( 2, '0' );
							$( "#bwpn-send-notification-scheduled-time" ).val( timeString );
					} else {
						$( "#bwpn-send-notification-scheduled-date" ).val( '' );
						$( "#bwpn-send-notification-scheduled-time" ).val( '' );
						$( "#bwpn-send-notification-scheduled-date" ).attr( "disabled", "disabled" );
						$( "#bwpn-send-notification-scheduled-time" ).attr( "disabled", "disabled" );
					}
				}
			);

			$( "#bwpn_settings\\[widget_ask_permissions_directly\\]" ).change(
				function(e){
					if (this.checked) {
						$( ".bwpn-options-dialog-group" ).closest( 'tr' ).hide();
					} else {
						$( ".bwpn-options-dialog-group" ).closest( 'tr' ).show();
					}
				}
			);
			if ($( "#bwpn_settings\\[widget_ask_permissions_directly\\]" ).is( ':checked' )) {
				$( ".bwpn-options-dialog-group" ).closest( 'tr' ).hide();
			}

			$( "#bwpn_settings\\[unregister_conflicts\\]" ).change(
				function(e){
					if (this.checked) {
						$( ".bwpn-options-unregister-conflicts-group" ).closest( 'tr' ).show();
					} else {
						$( ".bwpn-options-unregister-conflicts-group" ).closest( 'tr' ).hide();
					}
				}
			);
			if ( ! $( "#bwpn_settings\\[unregister_conflicts\\]" ).is( ':checked' )) {
				$( ".bwpn-options-unregister-conflicts-group" ).closest( 'tr' ).hide();
			}

			// Settings > Select default icon
			var bwpn_icon_choose;
			$( '#bwpn-default-icon-select' ).on(
				'click',
				function(event) {
					event.preventDefault();
					bwpn_icon_choose = wp.media(
						{
							title: 'Select image to upload',
							button: {
								text: 'Use this image',
							},
							multiple: false
						}
					);
					bwpn_icon_choose.on(
						'select',
						function() {
							let attachment = bwpn_icon_choose.state().get( 'selection' ).first().toJSON();
							$( '.bwpn-default-icon-preview-container' ).show();
							$( '.bwpn-default-icon-preview' ).attr( 'src', attachment.url );
							$( "#bwpn_settings\\[notifications_default_icon\\]" ).val( attachment.id );
						}
					);
					bwpn_icon_choose.open();
				}
			);
			if ($( '.bwpn-default-icon-preview' ).attr( 'src' ) !== "") {
				$( '.bwpn-default-icon-preview-container' ).show();
			}
			
			// Send on publish metabox
			var hide_custom_fields = function() {
				$("#bwpn_notification_custom_title").prop("disabled",true);
				$("#bwpn_notification_custom_title").hide();
				$("#bwpn_notification_custom_title_label").hide();
				$("#bwpn_notification_custom_body").prop("disabled",true);
				$("#bwpn_notification_custom_body").hide();
				$("#bwpn_notification_custom_body_label").hide();
			}
			
			var show_custom_fields = function() {
				$("#bwpn_notification_custom_title").show();
				$("#bwpn_notification_custom_title_label").show();
				$("#bwpn_notification_custom_title").prop("disabled",false);
				$("#bwpn_notification_custom_body").show();
				$("#bwpn_notification_custom_body_label").show();
				$("#bwpn_notification_custom_body").prop("disabled",false);
				
			}
			
			if ( $( "#bwpn_customize_notification" ).is(':checked') ){
				show_custom_fields();
			} else {
				hide_custom_fields();
				}
			$( "#bwpn_customize_notification" ).change(
				function(e){
					if (this.checked) {
						show_custom_fields();
					} else {
						hide_custom_fields();
					}
				}
			);
			
		}
	);
})( jQuery );
