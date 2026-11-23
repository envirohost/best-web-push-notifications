<div class="wrap">
	<div class="icon32 icon32-posts-post" id="icon-edit"><br></div>
	<h1><?php printf( esc_html__( 'Notification job details', 'best-web-push-notifications' ) ); ?></h1>

	<tbody>
	<div class="formdata bwpn-view-form">
		<form>
			<div>
				<p>
					<label for="payload"><?php printf( esc_html__( 'Payload:', 'best-web-push-notifications' ) ); ?> </label>
					<br>
				<div class="bwpn-view-payload">
					<?php $payload = $item->payload; ?>
					<div><span><?php printf( esc_html__( 'Title:', 'best-web-push-notifications' ) ); ?></span> <?php echo esc_html( $payload->title ); ?></div>
					<div><span><?php printf( esc_html__( 'Body:', 'best-web-push-notifications' ) ); ?></span> <?php echo esc_html( $payload->body ); ?></div>
					<div>
						<span><?php printf( esc_html__( 'Url to open:', 'best-web-push-notifications' ) ); ?></span>
						<a href="<?php echo esc_html( $payload->extra->url_to_open ); ?>"
						   target="_blank"><?php echo esc_html( $payload->extra->url_to_open ); ?></a>
					</div>
					<div>
						<span><?php printf( esc_html__( 'Icon:', 'best-web-push-notifications' ) ); ?></span>
						<?php
						if ( empty( $payload->icon ) ) {
							printf( esc_html__( 'No', 'best-web-push-notifications' ) );
						} else {
							echo '<br/><img class="bwpn-view-payload-icon" src="' . esc_html( $payload->icon ) . '" alt="icon"/>';
						}
						?>
					</div>
					<div>
						<span><?php printf( esc_html__( 'Image:', 'best-web-push-notifications' ) ); ?></span>
						<?php
						if ( empty( $payload->image ) ) {
							printf( esc_html__( 'No', 'best-web-push-notifications' ) );
						} else {
							echo '<br /><img class="bwpn-view-payload-image" src="' . esc_html( $payload->image ) . '" alt="image"/>';
						}
						?>
					</div>
				</div>
				</p>
				<p>
					<label for="created_at"><?php printf( esc_html__( 'Date:', 'best-web-push-notifications' ) ); ?> </label>
					<br>
				<div><?php echo get_date_from_gmt( esc_html( $item->created_at ) ); ?></div>
				</p>
				<p>
					<label for="status"><?php printf( esc_html__( 'Status:', 'best-web-push-notifications' ) ); ?> </label>
					<br>
				<div>
				<?php
				if ( $item->status == BWPN_Lib_Db::NOTIFICATIONS_STATUS_SCHEDULED ) {
					echo esc_html( $item->status ) . ' ' . esc_html__( 'at', 'best-web-push-notifications' ) . ' ' . get_date_from_gmt( $item->scheduled_at );
				} else {
					echo esc_html( $item->status );
				}
				?>
				</div>
				</p>
				<p>
					<label for="stats"><?php printf( esc_html__( 'Stats:', 'best-web-push-notifications' ) ); ?> </label>
					<br>
				<div><?php printf( esc_html__( 'Total: %s', 'best-web-push-notifications' ), esc_html( $item->total ) ); ?></div>
				<div><?php printf( esc_html__( 'Succeeded: %s', 'best-web-push-notifications' ), esc_html( $item->succeeded ) ); ?></div> 
				<?php
				if ( $item->status != BWPN_Lib_Db::NOTIFICATIONS_STATUS_RUNNING ) {
					echo '<div>' . sprintf( esc_html__( 'Failed: %s', 'best-web-push-notifications' ), ( esc_html( $item->failed ) ) ) . '</div>';
				}
				?>
				</p>
				<p>
					<label for="progress"><?php printf( esc_html__( 'Execution details:', 'best-web-push-notifications' ) ); ?> </label>
					<br>
				<div>
					<?php
					if ( $item->status == BWPN_Lib_Db::NOTIFICATIONS_STATUS_RUNNING ) {
						printf( esc_html__( '%1$s sent out of %2$s', 'best-web-push-notifications' ), esc_html( $item->last_cursor ), esc_html( $item->total ) );
					} else {
						printf( esc_html__( 'Finished', 'best-web-push-notifications' ) );
					}
					?>
				</div>
				<div>
					<?php printf( esc_html__( 'Batch size: %s', 'best-web-push-notifications' ), esc_html( $item->batch_size ) ); ?>
				</div>
				<?php
				try {
					$finished_at = $item->finished_at ? new DateTime( $item->finished_at ) : new DateTime();
					$created_at  = new DateTime( $item->created_at );
					$diff        = $finished_at->diff( $created_at );
					?>
						<div><?php printf( esc_html__( 'Duration: %s', 'best-web-push-notifications' ), esc_html( $diff->format( '%Hh:%Im:%Ss' ) ) ); ?></div>
						<?php
				} catch ( Exception $ex ) {
					error_log( esc_html__( 'Could not calculate the duration:', 'best-web-push-notifications' ) . ' ' . $ex->getMessage() );
				}
				?>
				</p>
			</div>
		</form>
	</div>
	<a href="?page=<?php echo esc_html( $page ); ?>"><?php printf( esc_html__( 'Back', 'best-web-push-notifications' ) ); ?></a>
</div>
