<textarea class="<?php echo $class ?>" id="<?php echo $id ?>" name="<?php echo $option_name ?>" placeholder="<?php echo $default ?>" rows="5" cols="30"><?php wp_htmledit_pre( $option_value ) ?></textarea>

<?php if ( $description ): ?>
	<br /><span class="description"><?php echo $description ?></span>
<?php endif; ?>