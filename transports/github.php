<?php

class Storm_Github_Updater {

	var $http_args = array();
	
	public function __construct( $args ){
		global $wp_version;

		$this->http_args = apply_filters( 'git_http_request_args', $this->http_args );

		$defaults = array(
			'name' => $args['Name'],
			'slug' => $args['slug'],
			'folder_name' => dirname( $args['slug'] ),
			'key' => dirname( $args['slug'] ),
			'host'  => $args['host'],
			'username' => $args['username'],
			'repository' => $args['repository'],
			'version' => $args['Version'],
			'author' => $args['Author'],
			'homepage' => $args['PluginURI'],
			'requires' => $wp_version,
			'tested' => $wp_version,
		);

		$args = wp_parse_args($args, $defaults);

		$this->api_url = "https://api.github.com/repos/{$args['username']}/{$args['repository']}";
		$this->tags_url = "https://api.github.com/repos/{$args['username']}/{$args['repository']}/tags";

		// TODO: Replace 'master' with master_branch or default_branch from Github API response
		$this->zip_url = "https://github.com/{$args['username']}/{$args['repository']}/archive/master.zip";
		
		foreach( $args as $key => $value ) {
			$this->$key = $value;
		}

		$this->set_new_version_and_zip_url();
		$this->set_last_updated();
		$this->set_description();

	}

	/**
	 * Get New Version from github
	 *
	 * @since 1.0
	 * @return void
	 */
	public function set_new_version_and_zip_url() {

		if ( false === get_site_transient( 'git_data_' . md5( $this->tags_url) ) ){
			$raw_response = wp_remote_get( $this->tags_url, $this->http_args );
			set_site_transient( 'git_data_' . md5( $this->tags_url), $raw_response );
		}else {
			$raw_response = get_site_transient( 'git_data_' . md5( $this->tags_url) );
		}

		$data = $this->get_github_data();

		if ( is_wp_error( $raw_response ) )
			return false;

		$tags = json_decode( $raw_response['body'] );
			
		$version = false;
		$zip_url = $this->zip_url;

		foreach ( (array) $tags as $tag ) {
			if ( version_compare($tag->name, $version, '>=') && !empty( $tag )) {
				$version = $tag->name;
				$zip_url = $tag->zipball_url;
			}
		}

		$this->new_version = $version;
		$this->zip_url = $zip_url;

	}

	/**
	 * Get GitHub Data from the specified repository
	 *
	 * @since 1.0
	 * @return array $github_data the data
	 */
	public function get_github_data() {
		if ( empty($this->github_data) && false === get_site_transient( 'git_data_' . md5( $this->api_url) ) ) {
			$data = wp_remote_get( $this->api_url, $this->http_args );

			if ( is_wp_error( $data ) )
				return false;

			$this->github_data = json_decode( $data['body'] );
			set_site_transient( 'git_data_' . md5( $this->api_url), $raw_response );
		}else {
			$data = get_site_transient( 'git_data_' . md5( $this->api_url) );
		}

		return $this->github_data;
	}


	/**
	 * Get update date
	 *
	 * @since 1.0
	 * @return string $date the date
	 */
	public function set_last_updated() {
		$_date = $this->get_github_data();
		return ( !empty($_date->updated_at) ) ? date( 'Y-m-d', strtotime( $_date->updated_at ) ) : false;
	}


	/**
	 * Get plugin description
	 *
	 * @since 1.0
	 * @return string $description the description
	 */
	public function set_description() {
		$_description = $this->get_github_data();
		return ( !empty($_description->description) ) ? $_description->description : false;
	}

}