<?php

/**
 * Manage plugin installation.
 */
class GHPS_Install {

	/**
	 * Whether to verify SSL for Git-related connections
	 * Override with <code> add_filter('git_sslverify', '__return_true' ); </code>
	 */
	var $ssl_verify = false;

	/**
	 * Class Constructor
	 *
	 * @since 1.0
	 * @param array $config configuration
	 * @return void
	 */
	public function __construct( $config = array() ) {

		$this->ssl_verify = apply_filters('git_sslverify', $this->ssl_verify);

		// Check for update from Git API
		// add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'api_check' ) );

		// Plugin details screen
		add_filter( 'plugins_api', array( $this, 'plugins_api' ), 99, 3 );

		// Cleanup and activate plugins after update
		// add_filter( 'upgrader_post_install', array( $this, 'upgrader_post_install' ), 10, 3 );

		// HTTP Timeout
		add_filter( 'http_request_timeout', array( $this, 'http_request_timeout' ) );

		// Maybe disable HTTP SSL Certificate Check for Git URLs
		// If statement can likely be removed.
		// @see https://github.com/jkudish/WordPress-GitHub-Plugin-Updater/issues/2#issuecomment-6654644
		if ( false === $this->ssl_verify ) {
			add_filter( 'http_request_args', array($this, 'disable_git_ssl_verify'), 10, 2 );
		}
	}

	/**
	 * Callback fn for the http_request_timeout filter
	 *
	 * @since 1.0
	 * @return int timeout value
	 */
	public function http_request_timeout() {
		return 2;
	}

	/**
	 * Disable SSL only for git repo URLs, but no other HTTP requests
	 *	Allows SSL to be disabled for zip are downloadeds outside plugin scope
	 *
	 * @return array $args http_request_args
	 */
	public function disable_git_ssl_verify($args, $url) {
		if ( false !== strpos( $url, 'github.com' ) ) {
			$args['sslverify'] = false; 
		}

		return $args;
	}


	/**
	 * Hook into the plugin update check and connect to github
	 *
	 * @since 1.0
	 * @param object $transient the plugin data transient
	 * @return object $transient updated plugin data transient
	 */
	public function api_check( $transient ) {

		// Check if the transient contains the 'checked' information
		// If not, just return its value without hacking it
		if ( empty( $transient->last_checked ) && empty( $transient->checked ) )
			return $transient;

		foreach( (array) $this->plugins as $plugin ) {
			// check the version and decide if it's new
			$update = version_compare( $plugin->new_version, $plugin->version );

			if ( 1 === $update ) {
				$response = new stdClass;
				$response->new_version = $plugin->new_version;
				$response->slug = $plugin->folder_name;
				$response->url = $plugin->homepage;
				$response->package = $plugin->zip_url;

				// If response is false, don't alter the transient
				if ( false !== $response )
					$transient->response[ $plugin->slug ] = $response;
			}
		}

		return $transient;
	}


	/**
	 * Get Plugin info
	 *
	 * @since 1.0
	 * @param bool $false always false
	 * @param string $action the API function being performed
	 * @param object $args plugin arguments
	 * @return object $response the plugin info
	 */
	public function plugins_api( $false, $action, $args ) {
		if ( 'install-plugin' != @$_GET['action'] ) {
			return $false;
		}

		$plugin = $this->get_repo_transport( $args->slug );

		if ( !$plugin ) {
			return false;
		}

		$args->slug = $plugin->slug;
		$args->plugin_name  = $plugin->name;
		$args->version = $plugin->new_version;
		$args->author = $plugin->author;
		$args->homepage = $plugin->homepage;
		$args->requires = $plugin->requires;
		$args->tested = $plugin->tested;
		$args->downloaded   = 0;
		$args->last_updated = $plugin->last_updated;
		$args->sections = array( 'description' => $plugin->description );
		$args->download_link = $plugin->zip_url;

		return $args;
	}


	/**
	 * Upgrader/Updater
	 * Move & activate the plugin, echo the update message
	 *
	 * @since 1.0
	 * @param boolean $true always true
	 * @param mixed $hook_extra not used
	 * @param array $result the result of the move
	 * @return array $result the result of the move
	 */
	public function upgrader_post_install( $true, $hook_extra, $result ) {

		global $wp_filesystem;

		$plugin = $this->plugins[ dirname($hook_extra['plugin']) ];


		// Move & Activate
		$proper_destination = WP_PLUGIN_DIR.'/'.$plugin->folder_name;
		$wp_filesystem->move( $result['destination'], $proper_destination );
		$result['destination'] = $proper_destination;
		$activate = activate_plugin( WP_PLUGIN_DIR.'/'.$plugin->slug );

		// Output the update message
		$fail		= __('The plugin has been updated, but could not be reactivated. Please reactivate it manually.', 'github_plugin_updater');
		$success	= __('Plugin reactivated successfully.', 'github_plugin_updater');
		echo is_wp_error( $activate ) ? $fail : $success;
		return $result;

	}

	/**
	 * Return appropriate repository handler based on URI
	 *
	 * Basically a copy of Storm_Git_Updater::get_repo_transport
	 * TODO: Merge these two into a helper function
	 *
	 * @return object
	 */
	public function get_repo_transport( $url ) {
		$parsed = parse_url( $url );
		$meta = array();

		$parsed['user'] = urldecode( $parsed['user'] );
		switch( $parsed['host'] ) {
			case 'github.com':
			case 'www.github.com':
				if ( !class_exists('Storm_Github_Updater') ) { include dirname( __FILE__ ) . '/transports/github.php'; }
				list( /*nothing*/, $username, $repository ) = explode('/', $parsed['path'] );
				return new Storm_Github_Updater( array_merge($meta, array( 'username' => $username, 'repository' => $repository, )) );
			break;
			case 'bitbucket.org':
			case 'www.bitbucket.org':
				if ( !class_exists('WordPress_Bitbucket_Updater') ) { include 'transports/bitbucket.php'; }
				list( /*nothing*/, $username, $repository ) = explode('/', $parsed['path'] );
				return new WordPress_Bitbucket_Updater( array_merge($meta, array( 'username' => $username, 'repository' => $repository, 'user' => $parsed['user'], 'pass' => $parsed['pass'] )) );
			break;
		}

		if ( '.git' == substr($parsed['path'], -4) ) {
			if ( !class_exists('WordPress_Gitweb_Updater') ) { include 'transports/gitweb.php'; }
			return new WordPress_Gitweb_Updater( array_merge( $meta, $parsed ) );
		}


		return false;
	}

}