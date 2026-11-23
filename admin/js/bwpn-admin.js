/**
 * Best Web Push Notifications - admin helpers (no jQuery).
 */
( function () {
	'use strict';
	var __ = window.wp && window.wp.i18n ? window.wp.i18n.__ : function ( s ) { return s; };

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '.bwpn-media' );
		if ( button && window.wp && window.wp.media ) {
			event.preventDefault();
			var target = document.getElementById( button.dataset.target );
			var frame = window.wp.media( {
				title: __( 'Choose an image', 'best-web-push-notifications' ),
				library: { type: 'image' },
				multiple: false
			} );
			frame.on( 'select', function () {
				var item = frame.state().get( 'selection' ).first().toJSON();
				target.value = button.dataset.mode === 'id' ? item.id : item.url;
				var preview = document.getElementById( button.dataset.target + '-preview' );
				if ( preview ) {
					preview.src = ( item.sizes && item.sizes.thumbnail ) ? item.sizes.thumbnail.url : item.url;
					preview.hidden = false;
				}
			} );
			frame.open();
			return;
		}
		var clear = event.target.closest( '.bwpn-media-clear' );
		if ( clear ) {
			event.preventDefault();
			document.getElementById( clear.dataset.target ).value = '0';
			var img = document.getElementById( clear.dataset.target + '-preview' );
			if ( img ) {
				img.hidden = true;
				img.removeAttribute( 'src' );
			}
		}
	} );

	document.addEventListener( 'submit', function ( event ) {
		var form = event.target;
		if ( form.classList.contains( 'bwpn-confirm' ) && ! window.confirm( form.dataset.confirm ) ) {
			event.preventDefault();
			return;
		}
		// Bulk delete confirmation on list tables.
		var action = form.querySelector( 'select[name="action"]' );
		var action2 = form.querySelector( 'select[name="action2"]' );
		var chosen = ( action && action.value !== '-1' ) ? action.value : ( action2 ? action2.value : '' );
		if ( chosen === 'bwpn_delete' && ! window.confirm( __( 'Delete the selected items? This cannot be undone.', 'best-web-push-notifications' ) ) ) {
			event.preventDefault();
		}
	} );
}() );
