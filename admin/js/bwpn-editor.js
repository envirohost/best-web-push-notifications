/**
 * Best Web Push Notifications - block editor panel.
 *
 * Shows "Send a notification when published" in the document sidebar. The
 * option is only offered before the first publish, and the server clears it
 * after sending, so updating a published post never sends again.
 */
( function ( wp, config ) {
	if ( ! wp || ! wp.plugins || ! config ) {
		return;
	}
	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var useSelect = wp.data.useSelect;
	var useDispatch = wp.data.useDispatch;
	var editor = wp.editor;
	var Panel = editor.PluginDocumentSettingPanel || ( wp.editPost && wp.editPost.PluginDocumentSettingPanel );
	var components = wp.components;

	function BWPNPanel() {
		var state = useSelect( function ( select ) {
			var store = select( 'core/editor' );
			return {
				meta: store.getEditedPostAttribute( 'meta' ) || {},
				status: store.getCurrentPostAttribute( 'status' )
			};
		}, [] );
		var editPost = useDispatch( 'core/editor' ).editPost;
		var published = state.status === 'publish';

		function setMeta( key, value ) {
			var meta = {};
			meta[ key ] = value;
			editPost( { meta: meta } );
		}

		var children = [];
		if ( ! config.hasKeys ) {
			children.push( el( components.Notice, { status: 'warning', isDismissible: false, key: 'keys' }, __( 'VAPID keys are missing; notifications cannot be sent.', 'best-web-push-notifications' ) ) );
		}
		if ( published ) {
			children.push( el( 'p', { key: 'pub' }, __( 'This post is published. Updating it does not send a notification. Use Push Notifications > Send to notify again.', 'best-web-push-notifications' ) ) );
		} else {
			children.push( el( components.CheckboxControl, {
				key: 'send',
				label: __( 'Send a notification when published', 'best-web-push-notifications' ),
				checked: !! state.meta[ config.metaSend ],
				onChange: function ( value ) { setMeta( config.metaSend, value ); }
			} ) );
			children.push( el( components.TextControl, {
				key: 'title',
				label: __( 'Notification title (optional)', 'best-web-push-notifications' ),
				help: __( 'Defaults to the site name. The post title is the message.', 'best-web-push-notifications' ),
				value: state.meta[ config.metaTitle ] || '',
				onChange: function ( value ) { setMeta( config.metaTitle, value ); },
				__nextHasNoMarginBottom: true
			} ) );
		}

		return el( Panel, { name: 'bwpn-panel', title: __( 'Push notification', 'best-web-push-notifications' ) }, children );
	}

	wp.plugins.registerPlugin( 'best-web-push-notifications', { render: BWPNPanel } );
} )( window.wp, window.BWPNEditor );
