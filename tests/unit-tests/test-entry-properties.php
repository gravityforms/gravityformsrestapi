<?php

/**
 * Testing the Gravity Forms REST API.
 *
 * Note: all the database operations are wrapped in a transaction and rolled back at teh end of each test.
 * So when debugging it's best not to stop the execution completely - best to let the tests run till the end.
 * This also means that if you check the database directly in the middle of debugging a test you won't see any changes - it'll appear empty.
 *
 * @group testsuite
 */
class Tests_GF_REST_API_Entry_Properties extends GF_UnitTestCase {

	/**
	 * @var GF_UnitTest_Factory
	 */
	protected $factory;

	/**
	 * @var int
	 */
	protected $form_id;

	/**
	 * @var Spy_REST_Server
	 */
	protected $server;

	/**
	 * @var $namespace string
	 */
	protected $namespace = '/gf/v2';

	function setUp() {
		parent::setUp();

		// Assume the administrator user
		wp_set_current_user( 1 );

		$this->form_id = $this->factory->form->create();

		/** @var WP_REST_Server $wp_rest_server */
		global $wp_rest_server;
		$this->server = $wp_rest_server = new Spy_REST_Server;
		do_action( 'rest_api_init' );

	}

	function tearDown() {
		parent::tearDown();

		/** @var WP_REST_Server $wp_rest_server */
		global $wp_rest_server;
		$wp_rest_server = null;
	}

	function test_the_tests() {

		$t = 1;
		$this->assertEquals( 1, $t );
	}

	function test_update_entry_property() {
		$this->_create_entries();

		$entries = GFAPI::get_entries( 0 );

		$entry = $entries[0];

		$this->assertEmpty( $entry['is_starred'] );
		$this->assertEmpty( $entry['is_read'] );

		$entry_id = $entry['id'];

		$properties['is_starred'] = true;
		$properties['is_read'] = true;

		$request = new WP_REST_Request( 'PUT', $this->namespace . '/entries/' . $entry_id . '/properties' );
		$response = $this->server->dispatch( $request );
		$status = $response->get_status();
		$this->assertEquals( 400, $status );

		$request = new WP_REST_Request( 'PUT', $this->namespace . '/entries/' . $entry_id . '/properties' );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( json_encode( $properties ) );
		$response = $this->server->dispatch( $request );
		$result = $response->get_data();

		$verify_entry = GFAPI::get_entry( $entry_id );

		$this->assertEquals( 1, $verify_entry['is_starred'] );
		$this->assertEquals( 1, $verify_entry['is_read'] );
	}

	/* HELPERS */
	function get_form_id() {
		return $this->form_id;
	}

	function _create_entries() {
		$form_id = $this->get_form_id();
		$this->factory->entry->create_many( 10, array( 'form_id' => $form_id, 'date_created' => '2013-11-28 11:00', '1' => 'Second Choice', '2.2' => 'Second Choice', '8' => '1', '13.6' => 'Spain' ) );
		$this->factory->entry->create_many( 10, array( 'form_id' => $form_id, 'date_created' => '2013-11-28 11:15', '1' => 'First Choice', '2.2' => 'Second Choice', '2.3' => 'Third Choice', '8' => '2', '13.6' => 'Brazil' ) );
		$this->factory->entry->create_many( 10, array( 'form_id' => $form_id, 'date_created' => '2013-11-29 12:00', '1' => 'Second Choice', '2.1' => 'First Choice', '8' => '3', '13.6' => 'United Kingdom' ) );
		$this->factory->entry->create_many( 10, array( 'form_id' => $form_id, 'date_created' => '2013-11-29 12:00', '1' => 'Second Choice', '2.1' => 'First Choice', '2.2' => 'Second Choice', '5' => 'My text', '8' => '4', '13.6' => 'United States' ) );
		$this->factory->entry->create_many( 10, array( 'form_id' => $form_id, 'date_created' => '2013-11-29 13:00', '1' => 'Second Choice', '5' => 'Different text', '8' => '5', '13.6' => 'Canada' ) );
	}
}
