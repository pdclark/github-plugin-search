<input class="regular-text <?php echo $class ?>" type="text" id="<?php echo $id ?>" name="<?php echo $option_name ?>" placeholder="<?php echo $default ?>" value="<?php esc_attr_e( $option_value ) ?>" />

<?php if ( $description ): ?>
	<br /><span class="description"><?php echo $description ?></span>
<?php endif; ?>