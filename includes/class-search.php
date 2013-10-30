<?php

/**
 * Manage Github search API.
 */
class GHPS_Search {

	/**
	 * @var string URL for the Github search API
	 */
	var $git_base_url = 'https://api.github.com/search/';

	/**
	 * @var array Settings for the Github request. Filter with ghps_http_request_args.
	 */
	var $git_request_args = array(
		'headers' => array(
			// Enable Github search API preview
			'Accept' => 'application/vnd.github.preview.text-match+json',
		),
		'timeout' => 10,
		'sslverify' => false,
	);

	/**
	 * Override with filter 'ghps_minimum_star_count'
	 * 
	 * @var int Mininimum number of stars a repo must have to show up in search
	 **/
	var $minimum_star_count = 5;

	/**
	 * @var array Response from the Github search API
	 **/
	var $git_response;

	/**
	 * Instantiate the class. Add hooks.
	 */
	public function __construct() {
		add_filter( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
	
		add_filter( 'plugins_api', array( $this, 'plugins_api' ), 20, 3 );

		add_filter( 'ghps_http_request_args', array( $this, 'maybe_authenticate_http' ) );
	}

	/**
	 * WP_Plugin_Install_List_Table::display_rows() doesn't provide a column filter,
	 * so we have to edit columns with JavaScript.
	 * 
	 * @see http://core.trac.wordpress.org/ticket/25770
	 */
	public function admin_enqueue_scripts( $screen ) {
		if ( 'plugin-install.php' == $screen
			&& isset( $_GET['tab'] )
			&& 'search' == $_GET['tab']
		){

			wp_enqueue_script( 'ghps-plugin-install', plugins_url( 'js/plugin-install.js', GHPS_PLUGIN_FILE ), array('jquery'), GHPS_PLUGIN_VERSION, true );

			// Pass response to JavaScript for editing plugin listing table
			wp_localize_script( 'ghps-plugin-install', 'GHPSGitResponse', (array) $this->git_response );

			// Too short to merit its own file right now.
			?>
			<style>
				.column-name {min-width: 180px; }
				.column-author {min-width: 180px;}
				.column-author img {max-width: 60px; height: auto;}
			</style>
			<?php
		}
	}

	public function maybe_authenticate_http( $args ) {
		$plugin = GHPS_Controller::get_instance();

		$username = $plugin->get_option( 'username' );
		$password = $plugin->get_option( 'password' );

		if ( $username && $password ) {
			$args['headers']['Authorization'] = 'Basic ' . base64_encode( "$username:$password" );
		}

		return $args;
	}

	/**
	 * Override WordPress plugin search
	 * 
	 * @param bool|object         The result object. Default is false.
	 * @param string      $action The type of information being requested from the Plugin Install API.
	 * @param object      $args   Plugin API arguments.
	 * 
	 * @return object|bool plugins_api response object on success, WP_Error on failure.
	 */
	public function plugins_api( $false, $action, $args ) {
		// Prevent filter from effecting installs & upgrades
		if ( 'query_plugins' !== $action ) {
			return $false;
		}

		$this->git_response = $this->search( $args );

		if ( is_a( $this->git_response, 'WP_Error') || false === $this->git_response ) {
			return $this->git_response;
		}

		$this->git_response = $this->map_git_response_to_wp_response( $this->git_response );

		return $this->git_response;
	}

	/**
	 * Search all repos for "wordpress plugin" first,
	 * then searches files in those repos.
	 * 
	 * This roundabout method is necessary because the
	 * Github API doesn't allow global code search.
	 *
	 *  @param object $args WordPress Plugin API arguments.
	 */
	public function search( $args ) {
		// Get all repositories that match the raw search string
		$repo_response = $this->search_repositories( $args );

		$plugin_response = false;

		// Get all WordPress plugins within that set of repos
		if ( 0 < $repo_response->total_count ) {

			// Limit repos put in query to avoid API server error
			$repo_limit = 50;
			$repo_names = array_slice( wp_list_pluck( $repo_response->items, 'full_name' ), 0, $repo_limit );
			$repo_names = '@' . implode(' @', $repo_names );

			$plugin_response = $this->search_plugins( $repo_names );

			if ( class_exists( 'FB') ) { FB::log($plugin_response, '$plugin_response'); }
		}

		return $plugin_response;
	}

	/**
	 * Search Github for the query.
	 */
	public function search_query( $search_string, $search_type = 'repo' ) {
		if ( class_exists('FB') ) { FB::log($search_string, '$search_string'); }

		$transient_key = 'gp-' . $search_type . md5( $search_string );

		$response = get_transient( $transient_key );

		if ( false === $response ) {

			// No cache available
			if ( 'repo' == $search_type ) { $search_type = 'repositories'; }
			$search_string = str_replace( ' ', '+', $search_string );

			$github_query = add_query_arg( 'q', $search_string, $this->git_base_url . $search_type );
			$github_query = add_query_arg( 'per_page', 1000, $github_query );

			// Query the Github API
			$response = wp_remote_get( $github_query, apply_filters( 'ghps_http_request_args', $this->git_request_args ) );

			if ( is_a( $response, 'WP_Error') ) {
				return $response;
			}

			if ( class_exists('FB') ) { FB::log( $response['headers'], 'headers' ); }
			if ( class_exists('FB') ) { FB::log( $response['headers']['x-ratelimit-remaining'], 'rate-limit-remaining' ); }

			$response = json_decode( $response['body'] );

			// Cache the response
			set_transient( $transient_key, $response );

		}

		if ( class_exists('FB') ) { FB::log($response, '$response'); }

		return $response;
	}

	/**
	 * Search all github repos that mention "WordPress plugin" in the title or description
	 * 
	 * @param object $args WordPress Plugin API arguments.
	 */
	public function search_repositories( $args ) {
		$stars = apply_filters( 'ghps_minimum_star_count', $this->minimum_star_count );

		$search_string = $args->search . ' wordpress in:name,description,readme language:php fork:false stars:>' . $stars;

		$response = $this->search_query( $search_string, 'repo' );

		return $response;
	}

	/**
	 * Search given repositories for files that contain WordPress plugin headers
	 * 
	 * @param $repos string List of repo names in format @username/repo
	 */
	public function search_plugins( $repos ) {
		// Github search queries all words individually whether we use quotes or not.
		// e.g., "Plugin Description" is the same as Plugin Description
		$search_string = '"Plugin Name" AND "Plugin URI" extension:php in:file ' . $repos;

		$response = $this->search_query( $search_string, 'code' );

		$response = $this->filter_repos_that_are_not_plugins( $response );

		return $response;
	}

	/**
	 * @param object $response Github API JSON response
	 * @return object $response Filtered Github JSON response
	 */
	public function filter_repos_that_are_not_plugins( $response ) {
		if ( !is_object( $response ) || !is_array( $response->items ) ) {
			return false;
		}

		foreach ( (array) $response->items as $key => $plugin ) {

			// Remove if file not in repository root
			if ( false !== strpos( $plugin->path, '/' ) ) {
				unset( $response->items[ $key ] );
			}

			// Check first text fragment for WordPress plugin header
			$fragment = $plugin->text_matches[0]->fragment;
			if (
				false === strpos( $fragment, '<?php')
				|| false === strpos( $fragment, '/*')
				|| false === strpos( $fragment, 'Plugin Name:') // consider making case-insensitive
				|| false === strpos( $fragment, 'Plugin URI:') // consider making case-insensitive
			) {
				unset( $response->items[ $key ] );
			}
		}

		$response->total_count = count( $response->items );

		return $response;
	}

	/**
	 * Convert Github API JSON to WordPress plugin API format.
	 * 
	 * @param object $git_response Github API JSON response
	 * @return array $plugins Plugins array corresponding to $resource->plugins in WordPress search response object.
	 */
	public function map_git_response_to_wp_response( $git_response ) {
		$plugins = array();

		$wp_response = new stdClass();

		// Update total item count
		// Todo: Figure out why this doesn't effect paging
		$wp_response->info = array( 'results' => $git_response->total_count, 'page' => 1, 'pages' => 1, );

		// Build plugin list
		foreach ( (array) $git_response->items as $plugin ) {
			$wp_plugin = array(
				'name'              => $plugin->repository->name,
				'slug'              => $plugin->repository->html_url,
				'author'            => $plugin->repository->owner->login,
				'author_profile'    => $plugin->repository->owner->html_url,
				'author_gravatar'   => $plugin->repository->owner->avatar_url,
				'homepage'          => $plugin->html_url,
				'description'       => $plugin->repository->description,
				'short_description' => $plugin->repository->description,

				'version'           => 'Github',
				'contributors'      => array(), // See $plugin->collaborators_url
				'requires'          => null,
				'tested'            => null,
				'compatibility'     => array(),
				'rating'            => null,
				'num_ratings'       => null,

				''
			);


			$plugins[] = (object) $wp_plugin;
		}

		$wp_response->plugins = $plugins;

		return $wp_response;
	}

}