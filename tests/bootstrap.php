<?php

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = dirname( dirname( __FILE__ ) ) . '/tmp/wordpress-tests-lib';
}

require_once $_tests_dir . '/includes/functions.php';

function _manually_load_plugin() {
	if ( ! class_exists( 'RGForms' ) ) {
		require dirname( dirname( __FILE__ ) ) . '/tmp/gravityforms/gravityforms.php';
	}
	require dirname( dirname( __FILE__ ) ) . '/restapi.php';
	//GFForms::setup( true );
	gf_upgrade()->install();

	/*
	add_filter( 'determine_current_user', function( $user_id ) {
		return 1;
	}, 15 ); // Priority 15 - after cookie auth check.
*/
	GFForms::loaded();
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';

require dirname( __FILE__ ) . '/gravityforms-factory.php';
require dirname( __FILE__ ) . '/gravityforms-testcase.php';

if ( ! class_exists( 'Spy_REST_Server' ) ) {
	require dirname( __FILE__ ) . '/spy-rest-server.php';
}
