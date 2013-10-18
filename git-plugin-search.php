<?php

class Storm_Git_Plugin_Search {

	/**
	 * @var string URL for the Github search API
	 */
	var $git_base_url = 'https://api.github.com/search/';

	/**
	 * @var string Defaults for a search targeting WordPress plugin repos
	 */
	var $search_users = ' wordpress plugin';

	/**
	 * @var string Defaults for a search targeting WordPress plugin files
	 */
	var $search_code = ' "Plugin Name:" "Description:" "Plugin URI:" language:php in:file';

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
		$git_response = $this->search_github_code( $args );

		if ( is_a( $git_response, 'WP_Error') ) { return $git_response; }

		$wp_response = $this->map_git_response_to_wp_response( $git_response );

		return $wp_response;
	}

	/**
	 * Search Github for the query.
	 * 
	 * Searches all repos for "wordpress plugin" first,
	 * then searches files in those repos.
	 * 
	 * This roundabout method is necessary because the
	 * Github API doesn't allow global code search.
	 *
	 *  @param object $args WordPress Plugin API arguments.
	 */
	public function search_github_code( $args ) {
		$transient_key = 'git-search-code' . md5( $args->search );

		$response = get_transient( $transient_key );

		if ( false === $response ) {
			// No cache -- query the Github API

			$search_string = $args->search . $this->search_code . $this->search_github_users( $args );

			$github_query = add_query_arg( 'q', $search_string, $this->git_base_url . 'code' );
			$github_query = add_query_arg( 'per_page', 1000, $github_query );

			$response = wp_remote_get( $github_query, apply_filters( 'git_http_request_args', $this->git_request_args ) );

			if ( is_a( $response, 'WP_Error') ) {
				return $res;
			}

			$response = json_decode( $response['body'] );

			$response = $this->filter_repos_that_are_not_plugins( $response );

			set_transient( $transient_key, $response );

		}

		return $response;
	}

	/**
	 * Search all github repos that mention "WordPress plugin" in the title or description
	 * 
	 * @param object $args WordPress Plugin API arguments.
	 */
	public function search_github_users( $args ) {
		$transient_key = 'git-search-users' . md5( $args->search );

		$users = get_transient( $transient_key );

		if ( false === $users ) {
			// No cache -- query the Github API

			$search_string = $args->search . $this->search_users;

			$github_query = add_query_arg( 'q', $search_string, $this->git_base_url . 'repositories' );
			$github_query = add_query_arg( 'per_page', 1000, $github_query );

			$response = wp_remote_get( $github_query, apply_filters( 'git_http_request_args', $this->git_request_args ) );

			if ( is_a( $response, 'WP_Error') ) {
				return $res;
			}

			$users = $this->extract_github_usernames( $response );

			set_transient( $transient_key, $users );
		}

		return $users;
	}

	/**
	 * Extract array of users from Github API response
	 * 
	 * @param $response WP_HTTP response. Body contains Github API JSON.
	 * @return string List of Github usernames
	 */
	public function extract_github_usernames( $response ) {
		$response = json_decode( $response['body'] );

		if ( !is_object( $response ) || !is_array( $response->items ) ) {
			return false;
		}

		$users = array();
		foreach( $response->items as $item ) {
			$users[] = $item->owner->login;
		}

		$users = array_unique( $users );

		// Convert list of usernames to @username format for search string
		$users = ' @' . implode( ' @', $users );

		return $users;
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

			// Skip found plugins that aren't in the root of their repository
			if ( false !== strpos( $plugin->path, '/' ) ) {
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