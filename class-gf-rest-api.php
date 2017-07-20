<?php

// Include the Gravity Forms add-on framework
GFForms::include_addon_framework();

class GF_REST_API extends GFAddOn {
	/**
	 * Contains an instance of this class, if available.
	 *
	 * @since  2.0-beta-1
	 * @access private
	 *
	 * @var object $_instance If available, contains an instance of this class
	 */
	private static $_instance = null;

	/**
	 * Defines the version of the REST API add-on.
	 *
	 * @since  2.0-beta-1
	 * @access protected
	 *
	 * @var string $_version Contains the version, defined from restapi.php
	 */
	protected $_version = GF_REST_API_VERSION;

	/**
	 * Defines the minimum Gravity Forms version required.
	 *
	 * @since  2.0-beta-1
	 * @access protected
	 *
	 * @var string $_min_gravityforms_version The minimum version required.
	 */
	protected $_min_gravityforms_version = GF_REST_API_MIN_GF_VERSION;

	/**
	 * Defines the plugin slug.
	 *
	 * @since  2.0-beta-1
	 * @access protected
	 *
	 * @var string $_slug The slug used for this plugin.
	 */
	protected $_slug = 'gravityformsrestapi';

	/**
	 * Defines the main plugin file.
	 *
	 * @since  2.0-beta-1
	 * @access protected
	 *
	 * @var string $_path The path to the main plugin file, relative to the plugins folder.
	 */
	protected $_path = 'gravityformsrestapi/restapi.php';

	/**
	 * Defines the full path to this class file.
	 *
	 * @since  2.0-beta-1
	 * @access protected
	 *
	 * @var string $_full_path The full path.
	 */
	protected $_full_path = __FILE__;
	/**
	 * Defines the URL where this add-on can be found.
	 *
	 * @since  2.0-beta-1
	 * @access protected
	 *
	 * @var string $_url The add-on URL.
	 */
	protected $_url = 'http://www.gravityforms.com';
	/**
	 * Defines the title of this add-on.
	 *
	 * @since 1.0-beta-1
	 * @access protected
	 *
	 * @var string $_title The title of the add-on.
	 */
	protected $_title = 'Gravity Forms REST API Add-On';
	/**
	 * Defines the short title of the add-on.
	 *
	 * @since 1.0-beta-1
	 * @access protected
	 *
	 * @var string $_short_title The short title.
	 */
	protected $_short_title = 'REST API';

	/**
	 * Returns an instance of this class, and stores it in the $_instance property.
	 *
	 * @since  2.0-beta-1
	 * @access public
	 *
	 * @return GF_REST_API $_instance An instance of the GF_REST_API class
	 */
	public static function get_instance() {
		if ( self::$_instance == null ) {
			self::$_instance = new GF_REST_API();
		}

		return self::$_instance;
	}

	/**
	 * @since  2.0-beta-1
	 * @access private
	 */
	private function __clone() {
	} /* do nothing */

	/**
	 * GF_REST_API constructor.
	 *
	 * @since  2.0-beta-1
	 * @access public
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * @since  2.0-beta-1
	 * @access public
	 */
	public function register_rest_routes() {
		$controllers = array(
			'GF_REST_Entries_Controller',
			'GF_REST_Entry_Properties_Controller',
			'GF_REST_Entry_Notifications_Controller',
			'GF_REST_Form_Entries_Controller',
			'GF_REST_Form_Results_Controller',
			'GF_REST_Form_Submissions_Controller',
			'GF_REST_Forms_Controller',
		);

		foreach ( $controllers as $controller ) {
			$controller_obj = new $controller();
			$controller_obj->register_routes();
		}
	}
}



