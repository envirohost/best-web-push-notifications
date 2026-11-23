<script>
	window.onload = function () {
		const colors = {
			red: 'rgb(255, 99, 132)',
			blue: 'rgb(54, 162, 235)'
		};
		const config = {
			type: 'line',
			data: {
				datasets: [{
					label: '<?php printf( esc_html__( 'Failed', 'best-web-push-notifications' ) ); ?>',
					backgroundColor: colors.red,
					borderColor: colors.red,
					data: [
						<?php
						foreach ( $daily_stats as $item ) {
							echo "{ t: \"{$item->date}\",\n";
							echo "  y: {$item->failed} },\n";
						}
						?>
					],
					fill: false,
				}, {
					label: '<?php printf( esc_html__( 'Succeeded', 'best-web-push-notifications' ) ); ?>',
					fill: false,
					backgroundColor: colors.blue,
					borderColor: colors.blue,
					data: [
						<?php
						foreach ( $daily_stats as $item ) {
							echo "{ t: \"{$item->date}\",\n";
							echo "  y: {$item->succeeded} },\n";
						}
						?>
					],
				}]
			},
			options: {
				responsive: true,
				title: {
					display: true,
					text: '<?php printf( esc_html__( 'Daily Notifications', 'best-web-push-notifications' ) ); ?>'
				},
				tooltips: {
					mode: 'index',
					intersect: false,
				},
				hover: {
					mode: 'nearest',
					intersect: true
				},
				scales: {
					xAxes: [{
						type: 'time',
						display: true,
						scaleLabel: {
							display: true,
							labelString: '<?php printf( esc_html__( 'Date', 'best-web-push-notifications' ) ); ?>'
						},
						time: {
							unit: 'day'
						}
					}],
					yAxes: [{
						display: true,
						scaleLabel: {
							display: true,
							labelString: '<?php printf( esc_html__( 'Notifications', 'best-web-push-notifications' ) ); ?>'
						}
					}]
				}
			}
		};

		const ctx = document.getElementById('daily-notifications').getContext('2d');
		window.myLine = new Chart(ctx, config);
	}

</script>
<div class="wrap">
	<h1><?php printf( esc_html__( 'Best Web Push', 'best-web-push-notifications' ) ); ?></h1>
	<h2><?php printf( esc_html__( 'Plugin stats', 'best-web-push-notifications' ) ); ?></h2>
	<div class="notice notice-notice"><p>
	<?php
	printf(
		// translators: %1$s is the opening a tag
		// translators: %2$s is the closing a tag
		// translators: %3$s is the opening a tag
		// translators: %4$s is the closing a tag
		// translators: %5$s is the closing a tag
		// translators: %6$s is the closing a tag
		esc_html__( 'Welcome to Best Web Push. Start off by  reading the %1$sdocumentation%2$s, subscribe from the %3$sfront page%4$s and send your first %5$s notification%6$s', 'best-web-push-notifications' ),
		'<a href="https://docs.perfecty.org/" target="_blank">',
		'</a>',
		'<a href="' . esc_html( site_url() ) . '">',
		'</a>',
		'<a href="' . esc_html( admin_url( 'admin.php?page=bwpn-send-notification' ) ) . '">',
		'</a>'
	);
	?>
	!</p></div>
	<div style="width: 800px;">
		<canvas id="daily-notifications"></canvas>
	</div>
	<div class="bwpn-stats">
		<div>
			<h3><?php printf( esc_html__( 'Users', 'best-web-push-notifications' ) ); ?></h3>
			<div class="bwpn-stats-text">
				<span><?php printf( esc_html__( 'Total users:', 'best-web-push-notifications' ) ); ?> </span><span><?php echo esc_html( $users_stats['total'] ); ?></span>
			</div>
		</div>
		<div>
			<h3><?php printf( esc_html__( 'Notifications', 'best-web-push-notifications' ) ); ?></h3>
			<div class="bwpn-stats-text">
				<span><?php printf( esc_html__( 'Total notifications:', 'best-web-push-notifications' ) ); ?> </span><span><?php echo esc_html( $notifications_stats['total'] ); ?></span>
			</div>
			<div class="bwpn-stats-text">
				<span><?php printf( esc_html__( 'Succeeded:', 'best-web-push-notifications' ) ); ?> </span><span><?php echo esc_html( $notifications_stats['succeeded'] ); ?></span>
			</div>
			<div class="bwpn-stats-text">
				<span><?php printf( esc_html__( 'Failed:', 'best-web-push-notifications' ) ); ?> </span><span><?php echo esc_html( $notifications_stats['failed'] ); ?></span>
			</div>
		</div>
		<div>
			<h3><?php printf( esc_html__( 'Jobs', 'best-web-push-notifications' ) ); ?></h3>
			<div class="bwpn-stats-text">
				<span><?php printf( esc_html__( 'Completed:', 'best-web-push-notifications' ) ); ?> </span><span><?php echo esc_html( $jobs_stats['completed'] ); ?></span>
			</div>
			<div class="bwpn-stats-text">
				<span><?php printf( esc_html__( 'Running:', 'best-web-push-notifications' ) ); ?> </span><span><?php echo esc_html( $jobs_stats['running'] ); ?></span>
			</div>
			<div class="bwpn-stats-text">
				<span><?php printf( esc_html__( 'Scheduled:', 'best-web-push-notifications' ) ); ?> </span><span><?php echo esc_html( $jobs_stats['scheduled'] ); ?></span>
			</div>
			<div class="bwpn-stats-text">
				<span><?php printf( esc_html__( 'Canceled:', 'best-web-push-notifications' ) ); ?> </span><span><?php echo esc_html( $jobs_stats['canceled'] ); ?></span>
			</div>
			<div class="bwpn-stats-text">
				<span><?php printf( esc_html__( 'Failed:', 'best-web-push-notifications' ) ); ?> </span><span><?php echo esc_html( $jobs_stats['failed'] ); ?></span>
			</div>
		</div>
	</div>
</div>
