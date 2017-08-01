<?php

class GF_REST_Form_Entries_Controller extends GF_REST_Controller {

	/**
	 * @since  2.0-beta-1
	 * @access public
	 *
	 * @var string
	 */
	public $rest_base = 'forms/(?P<form_id>[\S]+)/entries';

	/**
	 * Register the routes for the objects of the controller.
	 *
	 * @since  2.0-beta-1
	 * @access public
	 */
	public function register_routes() {

		$namespace = $this->namespace;

		$base = $this->rest_base;

		register_rest_route( $namespace, '/' . $base, array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => $this->get_collection_params(),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_item' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
				'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
			),
		) );
	}

	/**
	 * Get a collection of entries
	 *
	 * @since  2.0-beta-1
	 * @access public
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_items( $request ) {

		$entry_ids = $request['include'];

		if ( ! empty( $entry_ids ) ) {
			if ( ! is_array( $entry_ids ) ) {
				$entry_ids = array( $entry_ids );
			}
			$entry_ids = array_map( 'absint', $entry_ids );
		}

		$field_ids = $request['_fields'];
		if ( ! empty( $field_ids ) ) {
			$field_ids = (array) explode( ',', $request['_fields'] );
			$field_ids = array_map( 'trim', $field_ids );
		}

		$labels = $request['_labels'];

		$data = array();
		if ( $entry_ids ) {
			foreach ( $entry_ids as $id ) {
				$result = GFAPI::get_entry( $id );
				if ( ! is_wp_error( $result ) ) {
					$entry = $this->maybe_json_encode_list_fields( $result );

					if ( ! empty( $field_ids ) && ( ! empty( $entry ) ) ) {
						$entry = $this->filter_entry_fields( $entry, $field_ids );
					}

					if ( $labels ) {
						$form = GFAPI::get_form( $entry['form_id'] );
						$entry['_labels'] = $this->get_entry_labels( $form );
					}

					$data[ $id ] = $entry;
				}
			}
		} else {
			$entry_search_params = $this->parse_entry_search_params( $request );

			$entry_count = 0;

			$form_ids = $this->maybe_explode_url_param( $request, 'form_id' );

			if ( empty( $form_ids ) ) {
				$form_ids = 0;
			}

			$entries = GFAPI::get_entries( $form_ids, $entry_search_params['search_criteria'], $entry_search_params['sorting'], $entry_search_params['paging'], $entry_count );

			$data = array();
			if ( ! is_wp_error( $entries ) ) {
				foreach ( $entries as &$entry ) {
					$entry = $this->maybe_json_encode_list_fields( $entry );
					if ( ! empty( $field_ids ) && ! empty( $entry ) ) {
						$entry = $this->filter_entry_fields( $entry, $field_ids );
					}
					if ( $labels && empty( $form_ids ) ) {
						$form = GFAPI::get_form( $entry['form_id'] );
						$entry['_labels'] = $this->get_entry_labels( $form );
					}
				}
				$data = array( 'total_count' => $entry_count, 'entries' => $entries );

				if ( $labels && ! empty( $form_ids ) && count( $form_ids ) == 1 ) {
					$form = GFAPI::get_form( $form_ids[0] );
					$data['_labels'] = $this->get_entry_labels( $form );
				}
			}
		}

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Create one item from the collection
	 *
	 * @since  2.0-beta-1
	 * @access public
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function create_item( $request ) {

		$entry = $this->prepare_item_for_database( $request );

		if ( is_wp_error( $entry ) ) {
			return $entry;
		}

		$entry_id = GFAPI::add_entry( $entry );

		if ( is_wp_error( $entry_id ) ) {
			$status = $this->get_error_status( $entry_id );
			return new WP_Error( $entry_id->get_error_code(), $entry_id->get_error_message(), array( 'status' => $status ) );
		}

		$entry['id'] = $entry_id;

		$response = rest_ensure_response( $entry );

		$response->set_status( 201 );
		$response->header( 'Location', rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $entry_id ) ) );

		return $response;
	}

	/**
	 * Check if a given request has access to get items
	 *
	 * @since  2.0-beta-1
	 * @access public
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|bool
	 */
	public function get_items_permissions_check( $request ) {

		/**
		 * Filters the capability required to get entries via the REST API.
		 *
		 * @since 2.0-beta-2
		 */
		$capability = apply_filters( 'gform_rest_api_capability_get_entries', 'gravityforms_view_entries', $request );

		return GFAPI::current_user_can_any( $capability );
	}

	/**
	 * Check if a given request has access to create items
	 *
	 * @since  2.0-beta-1
	 * @access public
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|bool
	 */
	public function create_item_permissions_check( $request ) {

		/**
		 * Filters the capability required to create entries via the REST API.
		 *
		 * @since 2.0-beta-2
		 */
		$capability = apply_filters( 'gform_rest_api_capability_post_entries', 'gravityforms_edit_entries' );

		return GFAPI::current_user_can_any( $capability );
	}

	/**
	 * Prepare the item for create or update operation
	 *
	 * @since  2.0-beta-1
	 * @access protected
	 *
	 * @param WP_REST_Request $request Request object
	 *
	 * @return WP_Error|array $prepared_item
	 */
	protected function prepare_item_for_database( $request ) {
		$entry = $request->get_json_params();

		if ( empty( $entry ) ) {
			return new WP_Error( 'missing_entry', __( 'Missing entry JSON', 'gravityforms' ) );
		}

		$form_id = $this->maybe_explode_url_param( $request, 'form_id' );

		if ( $form_id && ! is_array( $form_id ) ) {
			$entry['form_id'] = absint( $form_id );
		}

		$entry = $this->maybe_serialize_list_fields( $entry );

		return $entry;
	}

	/**
	 * Get the query params for collections
	 *
	 * @since  2.0-beta-1
	 * @access public
	 *
	 * @return array
	 */
	public function get_collection_params() {
		return array(
			'sorting' => array(
				'description'        => 'The sorting criteria.',
			),
			'paging' => array(
				'description'        => 'The paging criteria.',
			),
			'search' => array(
				'description'        => 'The search criteria.',
				'type'               => 'string',
			),
			'_fields' => array(
				'description'        => 'Comma separated list of fields to include in the response.',
				'type'               => 'string',
			),
			'_labels' => array(
				'description'        => 'Whether to include the labels in the response.',
				'type'               => 'integer',
			),
		);
	}

	/**
	 * Get the Entry schema, conforming to JSON Schema.
	 *
	 * @since  2.0-beta-1
	 * @access public
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'entry',
			'type'       => 'object',
			'properties' => array(
				'id' => array(
					'description' => __( 'Unique identifier for the resource.', 'gravityforms' ),
					'type'        => 'integer',
					'readonly'    => true,
				),
				'form_id' => array(
					'description' => __( 'The Form ID for the entry.', 'gravityforms' ),
					'type'        => 'integer',
					'required'    => true,
					'readonly'    => false,
				),
				'date_created' => array(
					'description' => __( 'The date the entry was created, in UTC.', 'gravityforms' ),
					'type'        => 'date-time',
					'readonly'    => false,
				),
				'is_starred' => array(
					'description' => __( 'Whether the entry is starred.', 'gravityforms' ),
					'type'        => 'integer',
					'readonly'    => false,
				),
				'is_read' => array(
					'description' => __( 'Whether the entry has been read.', 'gravityforms' ),
					'type'        => 'integer',
					'readonly'    => false,
				),
				'ip' => array(
					'description' => __( 'The IP address of the entry creator.', 'gravityforms' ),
					'type'        => 'string',
					'readonly'    => false,
				),
				'source_url' => array(
					'description' => __( 'The URL where the form was embedded.', 'gravityforms' ),
					'type'        => 'string',
					'readonly'    => false,
				),
				'user_agent' => array(
					'description' => __( 'The user agent string for the browser used to submit the entry.', 'gravityforms' ),
					'type'        => 'string',
					'readonly'    => false,
				),
				'payment_status' => array(
					'description' => __( 'The status of the payment, if applicable.', 'gravityforms' ),
					'type'        => 'string',
					'readonly'    => false,
				),
				'payment_date' => array(
					'description' => __( 'The date of the payment, if applicable.', 'gravityforms' ),
					'type'        => 'string',
					'readonly'    => false,
				),
				'payment_amount' => array(
					'description' => __( 'The amount of the payment, if applicable.', 'gravityforms' ),
					'type'        => 'string',
					'readonly'    => false,
				),
				'payment_method' => array(
					'description' => __( 'The payment method for the payment, if applicable.', 'gravityforms' ),
					'type'        => 'string',
					'readonly'    => false,
				),
				'transaction_id' => array(
					'description' => __( 'The transaction ID for the payment, if applicable.', 'gravityforms' ),
					'type'        => 'string',
					'readonly'    => false,
				),
				'is_fulfilled' => array(
					'description' => __( 'Whether the transaction has been fulfilled, if applicable.', 'gravityforms' ),
					'type'        => 'string',
					'readonly'    => false,
				),
				'created_by' => array(
					'description' => __( 'The user ID of the entry submitter.', 'gravityforms' ),
					'type'        => 'integer',
					'readonly'    => false,
				),
				'transaction_type' => array(
					'description' => __( 'The type of the transaction, if applicable.', 'gravityforms' ),
					'type'        => 'string',
					'readonly'    => false,
				),
				'status' => array(
					'description' => __( 'The status of the entry.', 'gravityforms' ),
					'type'        => 'string',
					'readonly'    => false,
				),
			),
		);
		return $schema;
	}

	/**
	 * @since  2.0-beta-1
	 * @access protected
	 *
	 * @param       $form
	 * @param array $args
	 *
	 * @return array
	 */
	protected function get_entry_labels( $form, $args = array() ) {
		$defaults = array(
			'field_ids' => false,
		);

		$args = wp_parse_args( $args, $defaults );

		$fields = $this->filter_fields( $form, $args['field_ids'] );

		$labels = array();

		// replace the values/ids with text labels
		foreach ( $fields as $field ) {
			/* @var GF_Field $field */
			$field_id = $field->id;
			$field = GFFormsModel::get_field( $form, $field_id );
			$input_type = $field->get_input_type();
			if ( in_array( $input_type , array( 'likert', 'rank', 'rating' ) ) ) {
				$label = array();
				$choice_labels = array();
				foreach ( $field->choices as $choice ) {
					$choice_labels[ $choice['value'] ] = $choice['text'];
				}
				if ( $input_type = 'likert' && $field->gsurveyLikertEnableMultipleRows ) {
					/* @var GF_Field_Likert $field  */
					$label = array(
						'label' => $field->label,
						'cols' => $choice_labels,
						'rows' => array(),
					);
					foreach ( $field->gsurveyLikertRows as $row ) {
						$label['rows'][ $row['value'] ] = $row['text'];
					}
				} else {
					$label['label'] = $field->label;
					$label['choices'] = $choice_labels;
				}
			} else {
				$inputs = $field->get_entry_inputs();

				if ( empty( $inputs ) ) {
					$label = $field->get_field_label( false, null );
				} else {
					$label = array();
					foreach ( $inputs as $input ) {
						$label[ (string) $input['id'] ] = $input['label'];
					}
				}
			}

			$labels[ $field->id ] = $label;
		}

		return $labels;
	}

	/**
	 * @since  2.0-beta-1
	 * @access private
	 *
	 * @param $form
	 * @param $field_ids
	 *
	 * @return array
	 */
	private function filter_fields( $form, $field_ids ) {
		$fields = $form['fields'];
		if ( is_array( $field_ids ) && ! empty( $field_ids ) ) {
			foreach ( $fields as $key => $field ) {
				if ( ! in_array( $field->id, $field_ids ) ) {
					unset( $fields[ $key ] );
				}
			}
			$fields = array_values( $fields );
		}
		return $fields;
	}
}
