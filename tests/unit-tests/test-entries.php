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
class Tests_GF_REST_API_Entries extends GF_UnitTestCase {

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

	function test_get_entries() {
		$this->_create_entries();
		$request = new WP_REST_Request( 'GET', $this->namespace . '/entries' );

		$response = $this->server->dispatch( $request );
		$data = $response->get_data();

		$this->assertEquals( 50, $data['total_count'] );

		// Repeat the request with labels
		$request->set_query_params( array( 'labels' => 1 ) );

		$response = $this->server->dispatch( $request );
		$data = $response->get_data();

		// Double check results
		$this->assertEquals( 50, $data['total_count'] );

		// Entry level labels when no form is specified
		$this->assertArrayNotHasKey( 'labels', $data['entries'] );
		$this->assertArrayHasKey( 'labels', $data['entries'][0] );

	}

	function test_get_single_entry() {
		$this->_create_entries();

		$entries = GFAPI::get_entries( 0 );

		$first_entry_id = $entries[0]['id'];

		$request = new WP_REST_Request( 'GET', $this->namespace . '/entries/' . absint( $first_entry_id ) );

		$response = $this->server->dispatch( $request );
		$entry = $response->get_data();

		$this->assertEquals( $first_entry_id, $entry['id'] );
		$this->assertArrayNotHasKey( 'labels', $entry );

		// Repeat the request with labels
		$request->set_query_params( array( 'labels' => 1 ) );

		$response = $this->server->dispatch( $request );
		$entry = $response->get_data();

		$this->assertArrayHasKey( 'labels', $entry );
	}

	function test_get_entries_by_ids() {
		$this->_create_entries();

		$entries = GFAPI::get_entries( 0 );

		$first_entry_id = $entries[0]['id'];
		$third_entry_id = $entries[2]['id'];
		$tenth_entry_id = $entries[9]['id'];

		$url_param = sprintf( '%d;%d;%d', $first_entry_id, $third_entry_id, $tenth_entry_id );

		$request = new WP_REST_Request( 'GET', $this->namespace . '/entries/' . $url_param );

		$response = $this->server->dispatch( $request );
		$test_entries = $response->get_data();

		$keys = array_keys( $test_entries );

		$this->assertEquals( $first_entry_id, $keys[0] );
		$this->assertEquals( $third_entry_id, $keys[1] );
		$this->assertEquals( $tenth_entry_id, $keys[2] );

		$this->assertEquals( $first_entry_id, $test_entries[ $first_entry_id ]['id'] );
		$this->assertEquals( $third_entry_id, $test_entries[ $third_entry_id ]['id'] );
		$this->assertEquals( $tenth_entry_id, $test_entries[ $tenth_entry_id ]['id'] );

	}

	function test_get_entries_search() {

		$this->_create_entries();

		$search_criteria = array(
			'field_filters' => array(
				array(
					'key' => 5,
					'value' => 'Different text',
				),
			),
		);

		$search_criteria_json = json_encode( $search_criteria );

		$params = array(
			'search' => $search_criteria_json,
		);

		$request = new WP_REST_Request( 'GET', $this->namespace . '/entries' );
		$request->set_query_params( $params );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();

		$this->assertEquals( 10, $data['total_count'] );
		$this->assertEquals( 10, count( $data['entries'] ) );
	}

	function test_create_entry() {
		$form_id = $this->get_form_id();
		$entry = array( 'form_id' => $form_id, 'date_created' => '2016-07-19 11:00:00', '1' => 'Second Choice', '2.2' => 'Second Choice', '8' => '1', '13.6' => 'Spain' );

		$request = new WP_REST_Request( 'POST', $this->namespace . '/entries' );
		$request->set_body_params( $entry );
		$response = $this->server->dispatch( $request );
		$added_entry = $response->get_data();

		$this->assertEquals( '2016-07-19 11:00:00', $added_entry['date_created'] );
		$this->assertEquals( 'Second Choice', $added_entry['2.2'] );

		$entry_id = $added_entry['id'];
		$verify_entry = GFAPI::get_entry( $entry_id );

		$this->assertEquals( '2016-07-19 11:00:00', $verify_entry['date_created'] );
		$this->assertEquals( 'Second Choice', $verify_entry['2.2'] );
	}

	function test_update_entry() {
		$this->_create_entries();

		$entries = GFAPI::get_entries( 0 );

		$entry = $entries[0];
		$entry_id = $entry['id'];

		unset( $entry['id'] );

		$entry[1] = 'testing';

		$request = new WP_REST_Request( 'PUT', $this->namespace . '/entries/' . $entry_id );
		$request->set_body_params( $entry );
		$response = $this->server->dispatch( $request );
		$result = $response->get_data();

		// Successful update returns the updated entry.
		$this->assertEquals( 'testing', $result[1] );

		// Double check the entry was actually updated.
		$verify_entry = GFAPI::get_entry( $entry_id );

		$this->assertEquals( 'testing', $verify_entry[1] );
	}

	function test_delete_entry() {

		$request = new WP_REST_Request( 'DELETE', $this->namespace . '/entries/99999999' );
		$response = $this->server->dispatch( $request );
		$status = $response->get_status();
		$this->assertEquals( 404, $status );

		$this->_create_entries();

		$entries = GFAPI::get_entries( 0 );

		$entry = $entries[0];
		$entry_id = $entry['id'];

		$request = new WP_REST_Request( 'DELETE', $this->namespace . '/entries/' . $entry_id );
		$response = $this->server->dispatch( $request );
		$deleted_entry = $response->get_data();

		$this->assertEquals( 'trash', $deleted_entry['status'] );

		$verify_entry = GFAPI::get_entry( $entry_id );

		$this->assertEquals( 'trash', $verify_entry['status'] );

		// Repeating delete results in error

		$request = new WP_REST_Request( 'DELETE', $this->namespace . '/entries/' . $entry_id );
		$response = $this->server->dispatch( $request );
		$status = $response->get_status();

		$verify_entry = GFAPI::get_entry( $entry_id );

		$this->assertEquals( 'trash', $verify_entry['status'] );
		$this->assertEquals( 410, $status ); // 410 = gone

		// Test force delete
		$request = new WP_REST_Request( 'DELETE', $this->namespace . '/entries/' . $entry_id );
		$request->set_query_params( array( 'force' => 1 ) );
		$response = $this->server->dispatch( $request );
		$result = $response->get_data();

		$this->assertArrayHasKey( 'deleted', $result );
		$this->assertTrue( $result['deleted'] );
		$this->assertArrayHasKey( 'previous', $result );
		$deleted_entry = $result['previous'];
		$this->assertEquals( 'trash', $deleted_entry['status'] );

		$verify_entry = GFAPI::get_entry( $entry_id );

		$this->assertWPError( $verify_entry );
		$this->assertEquals( 'not_found', $verify_entry->get_error_code() );

		// Repeat delete = 404
		$request = new WP_REST_Request( 'DELETE', $this->namespace . '/entries/' . $entry_id );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 404, $response->get_status() );
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
