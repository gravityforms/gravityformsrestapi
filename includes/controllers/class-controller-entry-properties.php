<?php

class GF_REST_Entry_Properties_Controller extends GF_REST_Form_Entries_Controller {

	public $rest_base = 'entries/(?P<entry_id>[\d]+)/properties';

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes() {

		$namespace = $this->namespace;

		$base = $this->rest_base;

		register_rest_route( $namespace, '/' . $base, array(
			array(
				'methods'         => WP_REST_Server::EDITABLE,
				'callback'        => array( $this, 'update_items' ),
				'permission_callback' => array( $this, 'update_item_permissions_check' ),
				'args'            => $this->get_endpoint_args_for_item_schema( true ),
			),
		) );

		register_rest_route( $namespace, '/' . $base . '/schema', array(
			'methods'         => WP_REST_Server::READABLE,
			'callback'        => array( $this, 'get_public_item_schema' ),
		) );
	}

	/**
	 * Update one item from the collection
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Request
	 */
	public function update_items( $request ) {
		$entry_id = $request['entry_id'];
		$key_value_pairs = $this->prepare_item_for_database( $request );

		if ( empty( $key_value_pairs ) ) {
			$message = __( 'No property values were found in the request body', 'gravityforms' );
			return new WP_REST_Response( $message, 400 );
		} elseif ( ! is_array( $key_value_pairs ) ) {
			$message = __( 'Property values should be sent as an array', 'gravityforms' );
			return new WP_REST_Response( $message, 400 );
		}

		$result = false;
		foreach ( $key_value_pairs as $key => $property_value ) {
			$result = GFAPI::update_entry_property( $entry_id, $key, $property_value );
			if ( is_wp_error( $result ) ) {
				break;
			}
		}

		if ( is_wp_error( $result ) ) {
			$status = $this->get_error_status( $result );
			return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => $status ) );
		}

		$message = __( 'Entry updated successfully', 'gravityforms' );

		return new WP_REST_Response( $message, 200 );
	}

	/**
	 * Check if a given request has access to update a specific item
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function update_item_permissions_check( $request ) {
		$capability = apply_filters( 'gform_web_api_capability_put_entries', 'gravityforms_edit_entries' );
		return GFAPI::current_user_can_any( $capability );
	}

	/**
	 * Prepare the item for create or update operation
	 *
	 * @param WP_REST_Request $request Request object
	 * @return WP_Error|array $prepared_item
	 */
	protected function prepare_item_for_database( $request ) {
		$properties = $request->get_body_params();
		return $properties;
	}

	/**
	 * Get the query params for collections
	 *
	 * @return array
	 */
	public function get_collection_params() {
		return array(
			'sorting'                   => array(
				'description'        => 'Current page of the collection.',
				'type'               => 'array',
				'sanitize_callback'  => 'is_array',
			),
			'paging'               => array(
				'description'        => 'Maximum number of items to be returned in result set.',
				'type'               => 'array',
				'sanitize_callback'  => 'is_array',
			),
			'search'                 => array(
				'description'        => 'The search criteria.',
				'type'               => 'string',
				'sanitize_callback'  => 'sanitize_text_field',
			),
		);
	}
}