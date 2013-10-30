<?php

/**
 * Manage settings page and other WordPress admin user interface.
 */
class GHPS_Admin {

	/**
	 * @var array All sections
	 */
	var $sections;

	/**
	 * @var array All settings
	 */
	var $settings;
	
	function __construct() {

		$this->sections_init(); 
		$this->settings_init(); 
		
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		$menu_hook = is_multisite() ? 'network_admin_menu' : 'admin_menu';
		add_action( $menu_hook, array( $this, 'admin_menu' ) );

		// Save option in Network admin
		add_action( 'network_admin_edit_' . GHPS_Controller::OPTION_KEY, array( $this, 'update_network_setting' ) );

	}

	/**
	 * Populate $this->sections with all section arguements
	 * 
	 * @return void
	 */
	public function sections_init() {
		$this->sections = array(
			'login' => __( 'Github login credentials', GHPS_PLUGIN_SLUG ),
		);
	}

	/**
	 * Populate $this->settings with all settings arguements
	 * 
	 * @return void
	 */
	public function settings_init() {
		$this->settings = array(

			'username' => array(
				'title'       => __( 'Username', GHPS_PLUGIN_SLUG ),
				'description' => __( 'For example, <code>octocat</code>.', GHPS_PLUGIN_SLUG ),
				'default'     => '',
				'type'        => 'input',
				'section'     => 'login',
			),

			'password' => array(
				'title'       => __( 'Password', GHPS_PLUGIN_SLUG ),
				'description' => __( 'For example, <code>cats4eva!</code>', GHPS_PLUGIN_SLUG ),
				'default'     => '',
				'type'        => 'password',
				'section'     => 'login',
			),
		);
	}

	public function admin_menu() {
		
		add_submenu_page(
			'settings.php',                 // Parent menu item
			GHPS_PLUGIN_NAME,               // Page title
			GHPS_PLUGIN_NAME,               // Menu title
			'manage_options',               // Capability
			GHPS_PLUGIN_SLUG,               // Menu slug
			array( $this, 'admin_options' ) // Page display callback
		);

	}

	/**
	 * Output the options page view.
	 * 
	 * @return null Outputs views/licenses.php and exits.
	 */
	function admin_options() {
		$args = array(
			'action_url' => 'options.php',
		);

		if ( is_multisite() ) {
			$args['action_url'] = network_admin_url( 'edit.php?action=' . GHPS_Controller::OPTION_KEY );
		}

		GHPS_Controller::get_template( 'admin-options', $args );
	}

	/**
	* Register settings
	*/
	public function register_settings() {
		
		register_setting( GHPS_PLUGIN_SLUG, GHPS_Controller::OPTION_KEY, array ( $this, 'validate_settings' ) );
		
		foreach ( $this->sections as $slug => $title ) {
			add_settings_section(
				$slug,
				$title,
				null, // Section display callback
				GHPS_PLUGIN_SLUG
			);
		}
		
		foreach ( $this->settings as $id => $setting ) {
			$setting['id'] = $id;
			$this->create_setting( $setting );
		}
		
	}

	/**
	 * Create settings field
	 *
	 * @since 1.0
	 */
	public function create_setting( $args = array() ) {
		
		$defaults = array(
			'id'          => 'default_field',
			'title'       => __( 'Default Field', GHPS_PLUGIN_SLUG ),
			'description' => __( 'Default description.', GHPS_PLUGIN_SLUG ),
			'default'     => '',
			'type'        => 'text',
			'section'     => 'general',
			'choices'     => array(),
			'class'       => ''
		);
			
		extract( wp_parse_args( $args, $defaults ) );
		
		$field_args = array(
			'type'        => $type,
			'id'          => $id,
			'description' => $description,
			'default'     => $default,
			'choices'     => $choices,
			'label_for'   => $id,
			'class'       => $class
		);
		
		add_settings_field(
			$id,
			$title,
			array( $this, 'display_setting' ),
			GHPS_Controller::OPTION_KEY,
			$section,
			$field_args
		);
	}

	/**
	 * Load view for setting, passing arguments
	 */
	public function display_setting( $args = array() ) {
		
		$options = get_site_option( GHPS_Controller::OPTION_KEY );
		
		if ( !isset( $options[$id] ) ) {
			$options[$id] = $default;
		}

		$id = $args['id'];
		$args['option_value'] = $options[ $id ];
		$args['option_name'] = GHPS_Controller::OPTION_KEY . '[' . $id . ']';

		$template = 'setting-' . $args['type'];

		GHPS_Controller::get_template( $template, $args );
		
	}

	/**
	 * Update setting in multisite.
	 * @see http://wordpress.stackexchange.com/questions/64968/settings-api-in-multisite-missing-update-message
	 */
	public function update_network_setting() {
		update_site_option( GHPS_Controller::OPTION_KEY, $_POST[ GHPS_Controller::OPTION_KEY ] );

		$url = ( is_multisite() ) ? network_admin_url( 'settings.php' ) : admin_url( 'options.php' );

		$url = add_query_arg( array( 'page' => GHPS_PLUGIN_SLUG, 'updated' => 'true' ), $url );

		wp_redirect( $url );

		exit;
	}

	/**
	* Validate settings
	*/
	public function validate_settings( $input ) {
		if ( true ) {
			return $input;
		}

		return false;
	}

}