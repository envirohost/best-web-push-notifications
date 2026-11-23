<?php
$bwpn_dialog_title                    = ! empty( $options['dialog_title'] ) ? $options['dialog_title'] : BWPN_OPTIONS_DIALOG_TITLE;
$bwpn_dialog_submit                   = ! empty( $options['dialog_submit'] ) ? $options['dialog_submit'] : BWPN_OPTIONS_DIALOG_CONTINUE;
$bwpn_dialog_cancel                   = ! empty( $options['dialog_cancel'] ) ? $options['dialog_cancel'] : BWPN_OPTIONS_DIALOG_CANCEL;
$bwpn_settings_title                  = ! empty( $options['settings_title'] ) ? $options['settings_title'] : BWPN_OPTIONS_SETTINGS_TITLE;
$bwpn_settings_opt_in                 = ! empty( $options['settings_opt_in'] ) ? $options['settings_opt_in'] : BWPN_OPTIONS_SETTINGS_OPT_IN;
$bwpn_settings_update_error           = ! empty( $options['settings_update_error'] ) ? $options['settings_update_error'] : BWPN_OPTIONS_SETTINGS_UPDATE_ERROR;
$bwpn_nonce                           = wp_create_nonce( 'wp_rest' );
$bwpn_server_url                      = ! empty( $options['server_url'] ) ? $options['server_url'] : get_rest_url( null, 'bwpn' );
$bwpn_unregister_conflicts_expression = ! empty( $options['unregister_conflicts_expression'] ) ? $options['unregister_conflicts_expression'] : BWPN_UNREGISTER_CONFLICTS_EXPRESSION;
$bwpn_prompt_icon_url                 = isset( $options['notifications_default_icon'] ) && ! empty( $options['notifications_default_icon'] ) ? wp_get_attachment_url( $options['notifications_default_icon'] ) : '';
$bwpn_visits_to_display_prompt        = isset( $options['visits_to_display_prompt'] ) && $options['visits_to_display_prompt'] ? $options['visits_to_display_prompt'] : 0;

if ( BWPN_Lib_Utils::is_disabled() ||
	( ! isset( $options['widget_enabled'] ) || $options['widget_enabled'] == 0 ) ) {
	$bwpn_enabled = false;
} else {
	$bwpn_enabled = true;
}
if ( isset( $options['unregister_conflicts'] ) && $options['unregister_conflicts'] == 1 ) {
	$bwpn_unregister_conflicts = true;
} else {
	$bwpn_unregister_conflicts = false;
}
if ( isset( $options['widget_debugging_enabled'] ) && $options['widget_debugging_enabled'] == 1 ) {
	$bwpn_widget_debugging_enabled = true;
} else {
	$bwpn_widget_debugging_enabled = false;
}
if ( isset( $options['widget_hide_bell_after_subscribe'] ) && $options['widget_hide_bell_after_subscribe'] == 1 ) {
	$bwpn_widget_hide_bell_after_subscribe = true;
} else {
	$bwpn_widget_hide_bell_after_subscribe = false;
}
if ( isset( $options['widget_ask_permissions_directly'] ) && $options['widget_ask_permissions_directly'] == 1 ) {
	$bwpn_widget_ask_permissions_directly = true;
} else {
	$bwpn_widget_ask_permissions_directly = false;
}

?>
<script>
	window.BWPNOptions = {
		path: "<?php echo BWPN_JS_DIR; ?>",
		dialogTitle: "<?php echo $bwpn_dialog_title; ?>",
		dialogSubmit: "<?php echo $bwpn_dialog_submit; ?>",
		dialogCancel: "<?php echo $bwpn_dialog_cancel; ?>",
		settingsTitle: "<?php echo $bwpn_settings_title; ?>",
		settingsOptIn: "<?php echo $bwpn_settings_opt_in; ?>",
		settingsUpdateError: "<?php echo $bwpn_settings_update_error; ?>",
		serverUrl: "<?php echo $bwpn_server_url; ?>",
		vapidPublicKey: "<?php echo BWPN_VAPID_PUBLIC_KEY; ?>",
		token: "<?php echo $bwpn_nonce; ?>",
		tokenHeader: "X-WP-Nonce",
		enabled: <?php echo $bwpn_enabled ? 'true' : 'false'; ?>,
		unregisterConflicts: <?php echo $bwpn_unregister_conflicts ? 'true' : 'false'; ?>,
		serviceWorkerScope: "<?php echo BWPN_SERVICE_WORKER_SCOPE; ?>",
		loggerLevel: "<?php echo $bwpn_widget_debugging_enabled ? 'debug' : 'error'; ?>",
		loggerVerbose: <?php echo $bwpn_widget_debugging_enabled ? 'true' : 'false'; ?>,
		hideBellAfterSubscribe: <?php echo $bwpn_widget_hide_bell_after_subscribe ? 'true' : 'false'; ?>,
		askPermissionsDirectly: <?php echo $bwpn_widget_ask_permissions_directly ? 'true' : 'false'; ?>,
		unregisterConflictsExpression: "<?php echo $bwpn_unregister_conflicts_expression; ?>",
		promptIconUrl: "<?php echo $bwpn_prompt_icon_url; ?>",
		visitsToDisplayPrompt: <?php echo $bwpn_visits_to_display_prompt; ?>
	}
</script>
