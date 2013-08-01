<?php

class Storm_Git_Plugin_Search {

	/**
	 * URL for the Github search API
	 */
	var $git_base_url = 'https://api.github.com/search/code';

	/**
	 * Defaults for a search targeting WordPress plugins
	 */
	var $git_query_default = ' "Plugin Name:" "Description:" "Plugin URI:" language:php in:file';

	public function __construct() {
		add_filter( 'plugins_api_result', array( $this, 'plugins_api_result' ), 10, 3 );
	}

	public function plugins_api_result( $res, $action, $args ) {
		$github_query = $args->search . $this->git_query_default;
		$github_query = add_query_arg( 'q', $github_query, $this->git_base_url );

		$github_query = add_query_arg( array( 'page'=>$res->info['page'], 'per_page'=>20 ), $github_query );

		$http_request_args = apply_filters( 'git_http_request_args', array(
			'headers' => array(
				// Enable Github search API preview
				'Accept' => 'application/vnd.github.preview.text-match+json',
			),
			'sslverify' => false
		));

		$response = wp_remote_get( $github_query, $http_request_args );

		if ( is_a( $response, 'WP_Error') ) {
			return $res;
		}

		$json = json_decode( $response['body'] );

		$git_plugins = $this->map_git_repos_to_wp_plugins( $json, $response, $res );

		$res->plugins = $git_plugins;

		return $res;
	}

	public function map_git_repos_to_wp_plugins( $json ) {
		$plugins = array();

		foreach ( (array) $json->items as $plugin ) {
			// Skip found files that aren't in the root of their repository
			if ( false !== strpos( $plugin->path, '/' ) ) {
				continue;
			}

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

		return $plugins;
	}

}