<?php

/**
 * The main plugin wrapper
 * Sets up hooks, manages options, loads templates, instantiates other classes.
 * 
 * @author Paul Clark <http://pdclark.com>
 */
class GHPS_Controller {

	/**
	 * @var GHPS_Controller Instance of this class.
	 */
	private static $instance = false;

	/**
	 * @var string Key for plugin options in wp_options table
	 */
	const OPTION_KEY = GHPS_PLUGIN_SLUG;

	/**
	 * @var int How often should transients be updated, in seconds.
	 */
	protected $update_interval;

	/**
	 * @var array Options from wp_options
	 */
	protected $options;

	/**
	 * @var GHPS_Admin Admin object
	 */
	protected $admin;

	/**
	 * @var GHPS_Search Search object
	 */
	protected $search;

	/**
	 * @var GHPS_Install Install & updates object
	 */
	protected $install;
	
	/**
	 * Don't use this. Use ::get_instance() instead.
	 */
	public function __construct() {
		if ( !self::$instance ) {
			$message = '<code>' . __CLASS__ . '</code> is a singleton.<br/> Please get an instantiate it with <code>' . __CLASS__ . '::get_instance();</code>';
			wp_die( $message );
		}       
	}

	/**
	 * If a variable is accessed from outside the class,
	 * return a value from method get_$var()
	 * 
	 * For example, $inbox->unread_count returns $inbox->get_unread_count()
	 * 
	 * @return pretty-much-anything
	 */
	public function __get( $var ) {
		$method = 'get_' . $var;

		if ( method_exists( $this, $method ) ) {
			return $this->$method();
		}else {
			return $this->$var;
		}
	}
	
	public static function get_instance() {
		if ( !is_a( self::$instance, __CLASS__ ) ) {
			self::$instance = true;
			self::$instance = new self();
			self::$instance->init();
		}
		return self::$instance;
	}
	
	/**
	 * Initial setup. Called by get_instance.
	 */
	protected function init() {

		$this->options = get_site_option( self::OPTION_KEY );

		// Filter allows search results to be updated more or less frequently.
		// Default is 15 minutes
		$this->update_interval = apply_filters( 'github_search_update_interval', 60*15 );

		$this->search  = new GHPS_Search();
		$this->install = new GHPS_Install();
		$this->admin   = new GHPS_Admin();

	}

	public function get_option( $key ) {
		if ( isset( $this->options[ $key ] ) ) {
			return $this->options[ $key ];
		}else {
			return false;
		}
	}

	/**
	 * Load HTML template from templates directory.
	 * Contents of $args are turned into variables for use in the template.
	 * 
	 * For example, $args = array( 'foo' => 'bar' );
	 *   becomes variable $foo with value 'bar'
	 */
	public static function get_template( $file, $args = array() ) {
		extract( $args );

		include GHPS_PLUGIN_DIR . "/templates/$file.php";

	}

	/**
	 * @return bool Whether username and password have been filled out in settings.
	 */
	public function have_credentials() {
		// Todo: Add notice linking to settings requesting setup.

		if ( false === $this->get_option('username') || false === $this->get_option('password') ) {
			return false;
		}

		return true;
	}

}