<?php

class WordPress_Bitbucket_Updater {
	
	public function __construct( $args ){

		global $wp_version;

		$defaults = array(
			'name' => $args['Name'],
			'slug' => $args['slug'],
			'folder_name' => dirname( $args['slug'] ),
			'key' => dirname( $args['slug'] ),
			'host'  => $args['host'],
			'username' => $args['username'],
			'user' => $args['user'],
			'pass' => $args['pass'],
			'repository' => $args['repository'],
			'version' => $args['Version'],
			'author' => $args['Author'],
			'homepage' => $args['PluginURI'],
			'requires' => $wp_version,
			'tested' => $wp_version,
		);

		$args = wp_parse_args($args, $defaults);

		$args['repository'] = preg_replace( '/\.git$/', '', $args['repository'] );

		$this->api_url = "https://api.bitbucket.org/1.0/repositories/{$args['username']}/{$args['repository']}";
		$this->tags_url = "https://api.bitbucket.org/1.0/repositories/{$args['username']}/{$args['repository']}/tags";
		$this->zip_url = "https://bitbucket.org/{$args['username']}/{$args['repository']}/get/";

		foreach( $args as $key => $value ) {
			$this->$key = $value;
		}

		add_filter( 'http_request_args', array( $this, 'http_request_args' ), 10, 2 );

		$this->set_new_version_and_zip_url();
		$this->set_last_updated();
		$this->set_description();

	}

	public function http_request_args($r, $url) {
		if ( 
			$url == $this->api_url 
			|| $url == $this->tags_url 
			|| $url == $this->zip_url 
		) {
			$r['headers'] = wp_parse_args( array('Authorization' => 'Basic ' . base64_encode( $this->user . ':' . $this->pass ) ), $r );
		}

		return $r;
	}
	/**
	 * Get New Version from github
	 *
	 * @since 1.0
	 * @return void
	 */
	public function set_new_version_and_zip_url() {

		$raw_response = wp_remote_get( $this->tags_url, $this->request_args );

		if ( is_wp_error( $raw_response ) )
			return false;

		$tags = json_decode( $raw_response['body'] );
		
		if ( !is_array( $tags ) )
			return false;
			
		$version = false;
		$zip_url = false;
		foreach ( (array) $tags as $name => $tag ) {
			if ( version_compare($name, $version, '>=') ) {
				$version = $name;
				$timestamp = $tag->timestamp;
			}
		}

		$this->new_version = $version;
		$this->timestamp = $timestamp;
		$this->zip_url .= $version . '.zip';

	}

	/**
	 * Get GitHub Data from the specified repository
	 *
	 * @since 1.0
	 * @return array $repo_data the data
	 */
	public function get_repo_data() {

		if ( empty($this->repo_data) ) {
			$data = wp_remote_get( $this->api_url, $this->request_args );

			if ( is_wp_error( $data ) )
				return false;

			$this->repo_data = json_decode( $data['body'] );
		}

		return $this->repo_data;
	}


	/**
	 * Get update date
	 *
	 * @since 1.0
	 * @return string $date the date
	 */
	public function set_last_updated() {
		return ( !empty( $this->timestamp ) ) ? date( 'Y-m-d', strtotime( $this->timestamp ) ) : false;
	}


	/**
	 * Get plugin description
	 *
	 * @since 1.0
	 * @return string $description the description
	 */
	public function set_description() {
		$_description = $this->get_repo_data();
		return ( !empty($_description->description) ) ? $_description->description : false;
	}

}