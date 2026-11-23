<div class="formdata">
	<div>
		<p>
			<label for="bwpn-send-notification-title"><?php printf( esc_html__( 'Title', 'best-web-push-notifications' ) ); ?></label>
			<br>
			<input id="bwpn-send-notification-title" name="bwpn-send-notification-title" type="text" value="<?php echo esc_attr( $item['bwpn-send-notification-title'] ); ?>" required>
		</p>
	</div>
	<div>
		<p>
			<label for="bwpn-send-notification-message"><?php printf( esc_html__( 'Message', 'best-web-push-notifications' ) ); ?></label>
			<br>
			<textarea id="bwpn-send-notification-message" name="bwpn-send-notification-message" cols="80" rows="4"
					  maxlength="1000"><?php echo esc_attr( stripslashes( $item['bwpn-send-notification-message'] ) ); ?></textarea>
		</p>
	</div>
	<div>
		<p>
			<label for="bwpn-send-notification-image-custom"><?php printf( esc_html__( 'Image URL', 'best-web-push-notifications' ) ); ?> <i><?php printf( esc_html__( '(default: no image)', 'best-web-push-notifications' ) ); ?></i></label>
			<br />
			<input id="bwpn-send-notification-image-custom" name="bwpn-send-notification-image-custom" type="checkbox"/>
			<input id="bwpn-send-notification-image" name="bwpn-send-notification-image" type="text" value="<?php echo esc_attr( $item['bwpn-send-notification-image'] ); ?>" disabled="disabled">
			<span> <?php printf( esc_html__( 'or', 'best-web-push-notifications' ) ); ?> </span>
			<input type="button" id="bwpn-send-notification-image-select" class="button" value="<?php printf( esc_html__( 'Select image', 'best-web-push-notifications' ) ); ?>" disabled="disabled"/>
			<div class="bwpn-send-notification-image-preview-container"><img src="" class="bwpn-send-notification-image-preview"/></div>
		</p>
	</div>
	<div>
		<p>
			<label for="bwpn-send-notification-url-to-open-custom"><?php printf( esc_html__( 'Url to open', 'best-web-push-notifications' ) ); ?> <i>
			  <?php printf( esc_html__( '(default: %s)', 'best-web-push-notifications' ), esc_html( site_url() ) ); ?></i></label>
			<br>
			<input id="bwpn-send-notification-url-to-open-custom" name="bwpn-send-notification-url-to-open-custom" type="checkbox"/>
			<input id="bwpn-send-notification-url-to-open" name="bwpn-send-notification-url-to-open" type="text" value="<?php echo esc_attr( $item['bwpn-send-notification-url-to-open'] ); ?>" disabled="disabled">
		</p>
	</div>
	<div>
		<span><?php printf( esc_html__( 'Icon:', 'best-web-push-notifications' ) ); ?></span><br />
		<?php
		if ( empty( $icon_url ) ) {
			echo '<i>' . esc_html__( 'Select icon in Best Web Push > Settings > Default Icon', 'best-web-push-notifications' ) . '</i>';
		} else {
			echo '<br/><img class="bwpn-default-icon-preview" src="' . esc_html( $icon_url ) . '" alt="icon"/>';
		}
		?>
	</div>
	<div>
		<p>
		<label for="bwpn-send-notification-schedule-notification"><?php printf( esc_html__( 'Schedule notification', 'best-web-push-notifications' ) ); ?> <i><?php printf( esc_html__( '(default: now)', 'best-web-push-notifications' ) ); ?></i></label>
		<br />
		<input id="bwpn-send-notification-schedule-notification" name="bwpn-send-notification-schedule-notification" type="checkbox"/>
		<input id="bwpn-send-notification-scheduled-date" name="bwpn-send-notification-scheduled-date" type="date" class="bwpn-notification-date" value="" pattern="[0-9]{4}-[0-9]{2}-[0-9]{2}" placeholder="2021-05-26" disabled="disabled"/>
		<input id="bwpn-send-notification-scheduled-time" name="bwpn-send-notification-scheduled-time" type="time" class="bwpn-notification-time" value="" pattern="[0-9]{2}:[0-9]{2}:[0-9]{2}" placeholder="00:00:00" step=1 disabled="disabled"/>
		<input id="bwpn-send-notification-timeoffset" name="bwpn-send-notification-timeoffset" type="hidden" value=""/>
		</p>
	</div>
</div>

