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
class Tests_GF_REST_API_Form_Submissions extends GF_UnitTestCase {

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

	function test_post_submission() {
		$form_id = $this->get_form_id();

		$initial_count = GFAPI::count_entries( $form_id );
		$this->assertEquals( 0, $initial_count );

		$request = new WP_REST_Request( 'POST', $this->namespace . '/forms/' . $form_id . '/submissions' );

		$body = array(
			'input_1' => 'First Choice',
			'input_2_2' => 'Second Choice',
			'input_5' => 'Testing the submissions endpoint',
			'input_8' => '9',
			'input_11' => '11:10',
			'field_values' => '',
			'target_page'  => 0,
			'source_page'  => 1,
		);

		// Both $request and $_POST need the input values to simulate the request.
		$request->set_body_params( $body );
		$_POST = $body;

		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 1, $data['is_valid'] );

		$count = GFAPI::count_entries( $form_id );

		$this->assertEquals( 1, $count );
	}

	function test_redirect_confirmation() {

		if ( version_compare( GFForms::$version, '2.3-dev-1', '<' ) ) {
			$this->markTestSkipped();
			return;
		}

		$form_id = $this->get_form_id();

		$form = GFAPI::get_form( $form_id );

		$form['confirmations'] = array(
			'5964d794e1606' =>
				array(
					'id'                => '5964d794e1606',
					'name'              => 'Default Confirmation',
					'isDefault'         => true,
					'type'              => 'redirect',
					'message'           => '',
					'url'               => 'https://google.com',
					'pageId'            => 0,
					'queryString'       => '',
					'disableAutoformat' => false,
					'conditionalLogic'  => array(),
				),
		);

		GFAPI::update_form( $form );

		$initial_count = GFAPI::count_entries( $form_id );
		$this->assertEquals( 0, $initial_count );

		$request = new WP_REST_Request( 'POST', $this->namespace . '/forms/' . $form_id . '/submissions' );

		$body = array(
			'input_1' => 'First Choice',
			'input_2_2' => 'Second Choice',
			'input_5' => 'Testing the submissions endpoint',
			'input_8' => '9',
			'input_11' => '11:10',
			'field_values' => '',
			'target_page'  => 0,
			'source_page'  => 1,
		);

		// Both $request and $_POST need the input values to simulate the request.
		$request->set_body_params( $body );
		$_POST = $body;


		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 1, $data['is_valid'] );

		$count = GFAPI::count_entries( $form_id );

		$this->assertEquals( 1, $count );

		$this->assertEquals( 'redirect', $data['confirmation_type'] );
		$this->assertEquals( 'https://google.com', $data['confirmation_redirect'] );

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
