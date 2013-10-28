<div class="wrap">

	<?php screen_icon('options-general'); ?>
	<h2><?php _e( GHPS_PLUGIN_NAME, GHPS_PLUGIN_SLUG ); ?></h2>

	<form name="ghps_options" method="post" action="<?php echo $action_url ?>">

		<?php settings_fields( GHPS_PLUGIN_SLUG ); ?>
		<?php do_settings_sections( GHPS_PLUGIN_SLUG ); ?>
		<?php submit_button( 'Save' ); ?>
	
	</form>

</div>