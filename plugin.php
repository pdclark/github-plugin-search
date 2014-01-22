<?php
/*
Plugin Name: Github Plugin Search
Plugin URI: http://github.com/10up/github-plugin-search
Description: Search and install WordPress plugins from Github.
Version: 0.3
Author: Paul Clark, 10up
Author URI: http://pdclark.com
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

/**
 * Used for script cache busting.
 * @var string Version of this plugin.
 */
define( 'GHPS_PLUGIN_VERSION', '0.3' );

/**
 * Used for localization text-domain, which must match wp.org slug.
 * Used for wp-admin settings page slug.
 * 
 * @var string Slug of the plugin on wordpress.org.
 */
define( 'GHPS_PLUGIN_SLUG', 'github-plugin-search' );

/**
 * Used for error messages.
 * Used for settings page title.
 * 
 * @var string Nice name of the plugin.
 */
define( 'GHPS_PLUGIN_NAME', 'Github Plugin Search' );

/**
 * @var string Absolute path to this file.
 */
define( 'GHPS_PLUGIN_FILE', __FILE__ );

/**
 * @var string Absolute path to the root plugin directory
 */
define( 'GHPS_PLUGIN_DIR', dirname( __FILE__ ) );

/**
 * Loads plugin dependencies and instantiate the plugin.
 * Deactivates plugin and links to instructions if running PHP 4.
 */
function storm_github_plugin_search_init() {
	
	// PHP Version Check
	$php_is_outdated = version_compare( PHP_VERSION, '5.2', '<' );

	// Only exit and warn if on admin page
	$okay_to_exit = is_admin() && ( !defined('DOING_AJAX') || !DOING_AJAX );
	
	if ( $php_is_outdated ) {
		if ( $okay_to_exit ) {
			require_once ABSPATH . '/wp-admin/includes/plugin.php';
			deactivate_plugins( __FILE__ );
			wp_die( sprintf( __(
				'%s requires PHP 5.2 or higher, as does WordPress 3.2 and higher. The plugin has now disabled itself. For information on upgrading, %ssee this article%s.', GHPS_PLUGIN_SLUG ),
				GHPS_PLUGIN_NAME,
				'<a href="http://codex.wordpress.org/Switching_to_PHP5" target="_blank">',
				'</a>'
			) );
		} else {
			return;
		}
	}

	if ( is_admin() ) {

		require_once dirname( __FILE__ ) . '/includes/class-controller.php';
		require_once dirname( __FILE__ ) . '/includes/class-admin.php';
		require_once dirname( __FILE__ ) . '/includes/class-search.php';
		require_once dirname( __FILE__ ) . '/includes/class-install.php';
		
		GHPS_Controller::get_instance();

	}

}

add_action( 'plugins_loaded', 'storm_github_plugin_search_init' );
