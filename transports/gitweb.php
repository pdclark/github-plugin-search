<?php

class WordPress_Gitweb_Updater {
	
	public function __construct( $args ){

		global $wp_version;
		
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

		foreach( $args as $key => $value ) {
			$this->$key = $value;
		}

		if ( $this->user && $this->pass ) {
			$this->url = $this->scheme.'://'.$this->user.':'.$this->pass.'@'.$this->host.'/'.$this->path;
		}else {
			$this->url = $this->scheme.'://'.$this->host.'/'.$this->path;
		}

		$this->get_data();

	}

	public function get_data() {

		if ( !class_exists('phpQuery') ) {
			include dirname(__FILE__).'/phpQuery.php';
		}
		
		$response = wp_remote_get( $this->url.'/tags' );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		phpQuery::newDocument( $response['body'] );

		$version = false;
		$zip_url = false;
		foreach( pq('table.tags a.name') as $tag ) {
			$tag = pq($tag);
			if ( version_compare($tag->text(), $version, '>=') ) {

				$href = $tag->attr('href');
				$commit = substr( $href, strrpos( $href, '/' )+1 );

				$zip_url = $this->url.'/snapshot/'.$commit.'.zip';
				$version = $tag->text();
				$updated_at = $tag->parent()->prev()->text();
			}
		}

		$this->new_version = $version;
		$this->zip_url = $zip_url;
		$this->updated_at = date( 'Y-m-d', strtotime( $updated_at ) );
		$this->description = pq('div.page_footer_text')->text();

	}

}