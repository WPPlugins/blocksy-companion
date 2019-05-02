<h1><?php echo __('Instructions', 'blc'); ?></h1>

<p>
	<?php echo __('After installing and activating the Cookies Consent extension you will be able to configure it from this location:', 'blc') ?>
</p>

<ul>
	<li>
		<b>
			<?php echo __('Customizer', 'blc') ?>
		</b>
		<i>
		<?php
			echo sprintf(
				__('Navigate to %s and customize the notification to meet your needs.', 'blc'),
				sprintf(
					'<code>%s</code>',
					__('Customizer ➝ Cookie Consent', 'blc')
				)
			);
		?>
		</i>
	</li>
</ul>


