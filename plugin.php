<?php

/*
Plugin Name: Git Plugins
Plugin URI: http://github.com/brainstormmedia/git-plugins
Description: Search and install WordPress plugins from Github. <strong>Proof of concept. Not for production use.</strong>
Version: alpha.1
Author: Brainstorm Media
Author URI: http://brainstormmedia.com/
*/

/**
 * Copyright (c) 2013 Brainstorm Media. All rights reserved.
 *
 * Released under the GPL license
 * http://www.opensource.org/licenses/gpl-license.php
 *
 * This is an add-on for WordPress
 * http://wordpress.org/
 *
 * **********************************************************************
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * **********************************************************************
 */

include dirname( __FILE__ ) . '/git-plugin-install.php';
include dirname( __FILE__ ) . '/git-plugin-search.php';

add_action( 'admin_init', 'storm_init_git_plugin_search' );
function storm_init_git_plugin_search() {
	new Storm_Git_Plugin_Search();
	new Storm_Git_Plugin_Install();
}