<?php
/*
Plugin Name: Gravity Forms REST API
Plugin URI: http://www.gravityforms.com
Description: Gravity Forms REST API Feature Add-On.
Version: 2.0-rc-1
Author: Rocketgenius
Author URI: http://www.gravityforms.com
License: GPL-3.0+
Text Domain: gravityformsrestapi
Domain Path: /languages

------------------------------------------------------------------------
Copyright 2016 Rocketgenius

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see http://www.gnu.org/licenses.
*/

// Defines the current version of the REST API add-on
define( 'GF_REST_API_VERSION', '2.0-rc-1' );

define( 'GF_REST_API_MIN_GF_VERSION', '2.0' );

// After GF is loaded, load the add-on
add_action( 'gform_loaded', array( 'GF_REST_API_Bootstrap', 'load_addon' ), 1 );


/**
 * Loads the Gravity Forms REST API add-on.
 *
 * Includes the main class, registers it with GFAddOn, and initialises.
 *
 * @since 2.0-beta-1
 */
class GF_REST_API_Bootstrap {

	/**
	 * Loads the required files.
	 *
	 * @since 1.0-beta-1
	 * @access public
	 */
	public static function load_addon() {

		$dir = plugin_dir_path( __FILE__ );

		// Requires the class file
		require_once( $dir . '/class-gf-rest-api.php' );

		require_once( $dir . '/includes/class-results-cache.php' );

		// Registers the class name with GFAddOn
		GFAddOn::register( 'GF_REST_API' );

		if ( ! class_exists( 'WP_REST_Controller' ) ) {
			require_once( $dir . '/includes/controllers/class-wp-rest-controller.php' );
		}

		require_once( $dir . '/includes/controllers/class-gf-rest-controller.php' );

		require_once( $dir . '/includes/controllers/class-controller-form-entries.php' );
		require_once( $dir . '/includes/controllers/class-controller-form-results.php' );
		require_once( $dir . '/includes/controllers/class-controller-form-submissions.php' );
		require_once( $dir . '/includes/controllers/class-controller-entries.php' );
		require_once( $dir . '/includes/controllers/class-controller-entry-notifications.php' );
		require_once( $dir . '/includes/controllers/class-controller-entry-properties.php' );
		require_once( $dir . '/includes/controllers/class-controller-forms.php' );

		gf_rest_api();
	}
}

/**
 * Returns an instance of the GF_REST_API class
 *
 * @since  2.0-beta-1
 * @access public
 *
 * @return GF_REST_API An instance of the GF_REST_API class
 */
function gf_rest_api() {
	return GF_REST_API::get_instance();
}

