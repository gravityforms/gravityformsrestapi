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
class Tests_GF_REST_API_Entry_Fields extends GF_UnitTestCase {

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

	function test_get_entry_fields() {
		$form_id = $this->get_form_id();
		$this->_create_entries();

		$entries = GFAPI::get_entries( $form_id );

		$entry = $entries[0];

		$entry_id = $entry['id'];

		$request = new WP_REST_Request( 'GET', $this->namespace . '/entries/' . $entry_id . '/fields/1;13.6;date_created' );

		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$verify_fields = $data[ $entry_id ];
		$this->assertEquals( $entry[1], $verify_fields[1] );
		$this->assertEquals( $entry['13.6'], $verify_fields['13.6'] );
		$this->assertEquals( $entry['date_created'], $verify_fields['date_created'] );
		$this->assertArrayNotHasKey( 'id', $verify_fields );
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
