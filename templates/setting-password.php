<input class="regular-text <?php echo $class ?>" type="password" id="<?php echo $id ?>" name="<?php echo $option_name ?>" value="<?php esc_attr_e( $option_value ) ?>" />
				
<?php if ( $description ): ?>
	<br /><span class="description"><?php echo $description ?></span>
<?php endif; ?>