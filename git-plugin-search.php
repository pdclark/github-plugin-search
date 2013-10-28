<?php

class Storm_Git_Plugin_Search {

	/**
	 * @var string URL for the Github search API
	 */
	var $git_base_url = 'https://api.github.com/search/';

	/**
	 * @var array Settings for the Github request. Filter with git_http_request_args.
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
	 * Instantiate the class. Add hooks.
	 */
	public function __construct() {
		add_filter( 'plugins_api_result', array( $this, 'plugins_api_result' ), 10, 3 );

		add_filter( 'git_http_request_args', array( $this, 'maybe_authenticate_http' ) );
	}

	public function maybe_authenticate_http( $args ) {
		$username = apply_filters( 'git_plugins_api_username', false );
		$password = apply_filters( 'git_plugins_api_password', false );

		if ( $username && $password ) {
			$args['headers']['Authorization'] = 'Basic ' . base64_encode( "$username:$password" );
		}

		return $args;
	}

	/**
	 * Filter WordPress plugins API search result
	 * 
	 * @param object|WP_Error  $wp_response    Response object or WP_Error.
	 * @param string           $action The type of information being requested from the Plugin Install API.
	 * @param object           $args   Plugin API arguments.
	 * 
	 * @return object|WP_Error $wp_response    Response object or WP_Error.
	 */
	public function plugins_api_result( $wp_response, $action, $args ) {
		// Prevent filter from effecting installs & upgrades
		if ( 'query_plugins' !== $action ) {
			return $wp_response;
		}

		$git_response = $this->search( $args );

		if ( is_a( $git_response, 'WP_Error') || false === $git_response ) {
			return $git_response;
		}

		$git_response = $this->map_git_response_to_wp_response( $git_response );

		return $git_response;
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

			$repo_names = '@' . implode(' @', wp_list_pluck( $repo_response->items, 'full_name' ) );

			$plugin_response = $this->search_plugins( $repo_names );

			if ( class_exists( 'FB') ) { FB::log($plugin_response, '$plugin_response'); }
		}

		return $plugin_response;
	}

	/**
	 * Search Github for the query.
	 */
	public function search_query( $search_string, $search_type = 'repo' ) {
		$transient_key = 'gp-' . $search_type . md5( $search_string );

		$response = get_transient( $transient_key );

		if ( false === $response ) {

			// No cache available
			if ( 'repo' == $search_type ) { $search_type = 'repositories'; }
			$search_string = str_replace( ' ', '+', $search_string );

			$github_query = add_query_arg( 'q', $search_string, $this->git_base_url . $search_type );
			$github_query = add_query_arg( 'per_page', 1000, $github_query );

			// Query the Github API
			$response = wp_remote_get( $github_query, apply_filters( 'git_http_request_args', $this->git_request_args ) );

			if ( is_a( $response, 'WP_Error') ) {
				return $response;
			}

			if ( class_exists('FB') ) { FB::log( $response['headers']['x-ratelimit-remaining'], 'remaining' ); }

			$response = json_decode( $response['body'] );

			// Cache the response
			set_transient( $transient_key, $response );

		}


		return $response;
	}

	/**
	 * Search all github repos that mention "WordPress plugin" in the title or description
	 * 
	 * @param object $args WordPress Plugin API arguments.
	 */
	public function search_repositories( $args ) {
		$search_string = $args->search . ' in:name,description,readme fork:false';

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
		$search_string = 'plugin name extension:php in:file ' . $repos;

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
			$tmp = new StdClass;

			$tmp->name              = $plugin->repository->name;
			$tmp->slug              = $plugin->repository->html_url;
			$tmp->author            = $plugin->repository->owner->login;
			$tmp->author_profile    = $plugin->repository->owner->html_url;
			$tmp->homepage          = $plugin->html_url;
			$tmp->description       = $plugin->repository->description;
			$tmp->short_description = $plugin->repository->description;

			$tmp->version           = 'Github';
			$tmp->contributors      = array(); // See $plugin->collaborators_url
			$tmp->requires          = null;
			$tmp->tested            = null;
			$tmp->compatibility     = array();
			$tmp->rating            = null;
			$tmp->num_ratings       = null;


			$plugins[] = $tmp;
		}

		$wp_response->plugins = $plugins;

		return $wp_response;
	}

}