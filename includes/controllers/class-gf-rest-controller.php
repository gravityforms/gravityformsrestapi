<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract Rest Controller Class
 *
 * @author   Rocketgenius
 * @category API
 * @package  Rocketgenius/Abstracts
 * @extends  WP_REST_Controller
 */
abstract class GF_REST_Controller extends WP_REST_Controller {
	/**
	 * Endpoint namespace.
	 *
	 * @since  2.0-beta-1
	 * @access protected
	 *
	 * @var string
	 */
	protected $namespace = 'gf/v2';

	/**
	 * Route base.
	 *
	 * @since  2.0-beta-1
	 * @access protected
	 *
	 * @var string
	 */
	protected $rest_base = '';

	/**
	 *
	 * @since  2.0-beta-1
	 * @access public
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return array
	 */
	public function parse_entry_search_params( $request ) {

		// Sorting parameters
		$sorting_param = $request->get_param( 'sorting' );
		$sort_key = isset( $sorting_param['key'] ) && ! empty( $sorting_param['key'] ) ? $sorting_param['key'] : 'id';
		$sort_dir = isset( $sorting_param['direction'] ) && ! empty( $sorting_param['direction'] ) ? $sorting_param['direction'] : 'DESC';
		$sorting  = array( 'key' => $sort_key, 'direction' => $sort_dir );
		if ( isset( $sorting_param['is_numeric'] ) ) {
			$sorting['is_numeric'] = $sorting_param['is_numeric'];
		}

		// paging parameters
		$paging_param = $request->get_param( 'paging' );
		$page_size = isset( $paging_param['page_size'] ) ? intval( $paging_param['page_size'] ) : 10;
		if ( isset( $paging_param['current_page'] ) ) {
			$current_page = intval( $paging_param['current_page'] );
			$offset       = $page_size * ( $current_page - 1 );
		} else {
			$offset = isset( $paging_param['offset'] ) ? intval( $paging_param['offset'] ) : 0;
		}

		$paging = array( 'offset' => $offset, 'page_size' => $page_size );

		$search = $request->get_param( 'search' );
		if ( isset( $search ) ) {
			if ( ! is_array( $search ) ) {
				$search = urldecode( ( stripslashes( $search ) ) );
				$search = json_decode( $search, true );
			}
		} else {
			$search = array();
		}

		$params = array(
			'search_criteria' => $search,
			'paging'          => $paging,
			'sorting'         => $sorting,
		);

		return $params;
	}

	/**
	 * @since  2.0-beta-1
	 * @access public
	 *
	 * @param $entry
	 *
	 * @return mixed
	 */
	public function maybe_json_encode_list_fields( $entry ) {
		$form_id = $entry['form_id'];
		$form    = GFAPI::get_form( $form_id );
		if ( ! empty( $form['fields'] ) && is_array( $form['fields'] ) ) {
			foreach ( $form['fields'] as $field ) {
				/* @var GF_Field $field */
				if ( $field->get_input_type() == 'list' ) {
					$new_value = maybe_unserialize( $entry[ $field->id ] );

					if ( ! $this->is_json( $new_value ) ) {
						$new_value = json_encode( $new_value );
					}

					$entry[ $field->id ] = $new_value;
				}
			}
		}

		return $entry;
	}

	/**
	 * @since  2.0-beta-1
	 * @access public
	 *
	 * @param $value
	 *
	 * @return bool
	 */
	public static function is_json( $value ) {
		if ( is_string( $value ) && in_array( substr( $value, 0, 1 ), array( '{', '[' ) ) && is_array( json_decode( $value, ARRAY_A ) ) ) {
			return true;
		}

		return false;
	}

	/**
	 * @since  2.0-beta-1
	 * @access public
	 *
	 * @param $entry
	 * @param $field_ids
	 *
	 * @return array
	 */
	public static function filter_entry_fields( $entry, $field_ids ) {

		if ( ! is_array( $field_ids ) ) {
			$field_ids = array( $field_ids );
		}
		$new_entry = array();
		foreach ( $entry as $key => $val ) {
			if ( in_array( $key, $field_ids ) || ( is_numeric( $key ) && in_array( intval( $key ), $field_ids ) ) ) {
				$new_entry[ $key ] = $val;
			}
		}

		return $new_entry;
	}

	/**
	 * Parses a url parameter from the request object. If the string contains semicolons it is split up.
	 *
	 * Returns an array of positive integers.
	 *
	 * @since  2.0-beta-1
	 * @access public
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @param string $param_key
	 *
	 * @return array
	 */
	public function maybe_explode_url_param( $request, $param_key ) {
		if ( empty( $param_key ) ) {
			return false;
		}
		$param = $request->get_param( $param_key );
		if ( empty( $param ) ) {
			return false;
		}
		if ( strpos( $param, ';' ) !== false ) {
			$params = explode( ';', $param );
			foreach ( $params as &$id ) {
				$id = sanitize_text_field( $id );
			}
			$return = $params;
		} else {
			$return = array( sanitize_text_field( $param ) );
		}

		return $return;
	}

	/**
	 * @since  2.0-beta-1
	 * @access public
	 *
	 * @param      $entry
	 * @param null $form_id
	 *
	 * @return mixed
	 */
	public function maybe_serialize_list_fields( $entry, $form_id = null ) {
		if ( empty( $form_id ) ) {
			$form_id = $entry['form_id'];
		}
		$form = GFAPI::get_form( $form_id );
		if ( ! empty( $form['fields'] ) && is_array( $form['fields'] ) ) {
			foreach ( $form['fields'] as $field ) {
				/* @var GF_Field $field */
				if ( $field->get_input_type() == 'list' && isset( $entry[ $field->id ] ) ) {
					$new_list_value = self::maybe_decode_json( $entry[ $field->id ] );
					if ( ! is_serialized( $new_list_value ) ) {
						$new_list_value = serialize( $new_list_value );
					}
					$entry[ $field->id ] = $new_list_value;
				}
			}
		}

		return $entry;
	}

	/**
	 * @since  2.0-beta-1
	 * @access public
	 *
	 * @param $value
	 *
	 * @return array|mixed|object
	 */
	public static function maybe_decode_json( $value ) {
		if ( self::is_json( $value ) ) {
			return json_decode( $value, ARRAY_A );
		}

		return $value;
	}

	/**
	 * @since  2.0-beta-1
	 * @access public
	 *
	 * @param $wp_error
	 *
	 * @return int|mixed
	 */
	public function get_error_status( $wp_error ) {
		$error_code = $wp_error->get_error_code();
		$mappings   = array(
			'not_found'   => 404,
			'not_allowed' => 401,
		);
		$http_code  = isset( $mappings[ $error_code ] ) ? $mappings[ $error_code ] : 400;

		return $http_code;
	}

	/**
	 * @since  2.0-beta-1
	 * @access public
	 *
	 * @param $message
	 */
	public function log_debug( $message ) {
		GFCommon::log_debug( $message );
	}
}
