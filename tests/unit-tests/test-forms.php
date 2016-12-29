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
class Tests_GF_REST_API_Forms extends GF_UnitTestCase {

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

	function test_get_forms() {
		$request = new WP_REST_Request( 'GET', $this->namespace . '/forms' );

		$response = $this->server->dispatch( $request );
		$data = $response->get_data();

		$this->assertEquals( 1, count( $data ) );
	}

	function test_get_single_form() {
		$form_id = $this->get_form_id();
		$request = new WP_REST_Request( 'GET', $this->namespace . '/forms/' . absint( $form_id ) );

		$response = $this->server->dispatch( $request );
		$form = $response->get_data();

		$this->assertEquals( $form_id, $form['id'] );
	}

	function test_get_forms_by_ids() {
		$form_id = $this->get_form_id();
		$form = GFAPI::get_form( $form_id );

		$form['title'] = 'Second form';

		$form_id_2 = GFAPI::add_form( $form );

		$form['title'] = 'Third form';

		$form_id_3 = GFAPI::add_form( $form );

		$form['title'] = 'Fourth form';

		$form_id_4 = GFAPI::add_form( $form );

		$form['title'] = 'Fifth form';

		$form_id_5 = GFAPI::add_form( $form );

		$url_param = sprintf( '%d;%d;%d', $form_id_2, $form_id_3, $form_id_5 );

		$request = new WP_REST_Request( 'GET', $this->namespace . '/forms/' . $url_param );

		$response = $this->server->dispatch( $request );
		$forms = $response->get_data();

		$keys = array_keys( $forms );

		$this->assertEquals( $form_id_2, $keys[0] );
		$this->assertEquals( $form_id_3, $keys[1] );
		$this->assertEquals( $form_id_5, $keys[2] );

		$this->assertEquals( 'Second form', $forms[ $form_id_2 ]['title'] );
		$this->assertEquals( 'Third form', $forms[ $form_id_3 ]['title'] );
		$this->assertEquals( 'Fifth form', $forms[ $form_id_5 ]['title'] );

	}

	function test_create_form() {

		$request = new WP_REST_Request( 'POST', $this->namespace . '/forms' );
		$response = $this->server->dispatch( $request );
		$status = $response->get_status();
		$this->assertEquals( 400, $status );

		$form = GFAPI::get_form( $this->get_form_id() );
		$form['title'] = 'REST test';
		$request = new WP_REST_Request( 'POST', $this->namespace . '/forms' );
		$form_json = json_encode( $form );
		$request->set_body_params( $form_json );
		//$request->set_header( 'content-type', 'application/json' );

		$response = $this->server->dispatch( $request );
		$response_form = $response->get_data();
		$new_form_id = $response_form['id'];

		$verify_form = GFAPI::get_form( $new_form_id );

		$this->assertEquals( $new_form_id, $verify_form['id'] );
		$this->assertEquals( 'REST test', $verify_form['title'] );
	}

	function test_update_form() {
		$form_id = $this->get_form_id();
		$form = GFAPI::get_form( $form_id );
		$new_title = 'REST test';
		$form['title'] = $new_title;
		$request = new WP_REST_Request( 'PUT', $this->namespace . '/forms/' . absint( $form_id ) );
		$request->set_body_params( json_encode( $form ) );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$updated_form = $response->get_data();
		$updated_form_id = $updated_form['id'];

		$this->assertEquals( $new_title, $updated_form['title'] );
		$this->assertEquals( $form_id, $updated_form_id );
	}

	function test_delete_form() {
		$form_id = $this->get_form_id();
		$form = GFAPI::get_form( $form_id );
		$request = new WP_REST_Request( 'DELETE', $this->namespace . '/forms/' . absint( $form_id ) );
		$response = $this->server->dispatch( $request );
		$deleted_form = $response->get_data();

		$this->assertEquals( 1, $deleted_form['is_trash'] );

		$verify_entry = GFAPI::get_form( $form_id );

		$this->assertEquals( 1, $verify_entry['is_trash'] );

		$request = new WP_REST_Request( 'DELETE', $this->namespace . '/forms/' . absint( $form_id ) );
		$response = $this->server->dispatch( $request );
		$status = $response->get_status();

		$this->assertEquals( 410, $status ); // 410 = gone

		$verify_form = GFAPI::get_form( $form_id );
		$this->assertEquals( 1, $verify_form['is_trash'] );

		$request = new WP_REST_Request( 'DELETE', $this->namespace . '/forms/' . absint( $form_id ) );
		$request->set_query_params( array( 'force' => 1 ) );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$result = $response->get_data();

		$this->assertArrayHasKey( 'deleted', $result );
		$this->assertTrue( $result['deleted'] );
		$this->assertArrayHasKey( 'previous', $result );
		$deleted_form = $result['previous'];
		$this->assertEquals( 1, $deleted_form['is_trash'] );
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
