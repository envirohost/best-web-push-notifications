<p><input type="checkbox"
	id="bwpn_send_on_publish"
	name="bwpn_send_on_publish"
	<?php if ( true === $send_notification || true === $check_send_on_publish ) {
		echo 'checked="checked"';} ?>/>
<label for="bwpn_send_on_publish" id="bwpn_send_on_publish_label"><?php printf( esc_html__( 'Send notification on publish', 'best-web-push-notifications' ) ); ?></label></p>

<p><input type="checkbox"
		id="bwpn_customize_notification"
		name="bwpn_customize_notification"
		<?php
		if ( true === $is_customized ) {
			echo 'checked="checked"'; }
		?>
		/>
<label for="bwpn_customize_notification" id="bwpn_customize_notification_label"><?php printf( esc_html__( 'Custom options', 'best-web-push-notifications' ) ); ?></label></p>
</p>
<p style="padding-top: 10px"><label for="bwpn_notification_custom_title" id="bwpn_notification_custom_title_label"><?php printf( esc_html__( 'Notification title', 'best-web-push-notifications' ) ); ?></label>
<input type="text" id="bwpn_notification_custom_title" name="bwpn_notification_custom_title" placeholder="<?php echo esc_html( get_bloginfo( 'name' ) ); ?>" value="<?php echo esc_attr( $notification_title ); ?>"></p>
<p style="padding-top: 10px"><label for="bwpn_notification_custom_body" id="bwpn_notification_custom_body_label"><?php printf( esc_html__( 'Notification text', 'best-web-push-notifications' ) ); ?></label>
<input type="text" id="bwpn_notification_custom_body" name="bwpn_notification_custom_body" placeholder="<?php printf( esc_html__( 'The Post\'s current title', 'best-web-push-notifications' ) ); ?>" value="<?php echo esc_attr( $notification_body ); ?>"></p>
