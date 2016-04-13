<?php
require_once CMB2_GROUP_POST_MAP_DIR . 'lib/base.php';
/**
 * CMB2_Group_Map_Ajax
 */
class CMB2_Group_Map_Ajax extends CMB2_Group_Map_Base {

	protected $post_data = array();
	protected $group_fields = array();
	protected $host_id = 0;
	protected $new_object_id = 0;
	protected $field_id = '';
	protected $index = 0;
	protected $cmb_id = '';

	/**
	 * Constructor. Setup the ajax handler object.
	 *
	 * @since 0.1.0
	 */
	public function __construct( array $post_data, array $group_fields ) {
		if ( ! isset( $post_data['post_id'] ) ) {
			$this->throw_error( __LINE__, 'missing_required' );
		}

		$post = get_post( absint( $post_data['post_id'] ) );

		if ( ! $post ) {
			$this->throw_error( __LINE__, 'missing_required' );
		}

		$this->post_data     = $post_data;
		$this->new_object_id = $post->ID;
		$this->group_fields  = $group_fields;
	}

	public function send_input_data() {
		$this->init_args();
		$field = $this->find_group_field_array();

		add_filter( 'cmb2_group_map_get_group_ids', array( $this, 'override_ids_get' ), 10, 2 );

		$field_group = new CMB2_Field( array(
			'field_args'  => $field,
			'object_type' => 'post',
			'object_id'   => $this->host_id,
		) );
		$field_group->index = $this->index;

		parent::__construct( $field_group );

		$inputs = array();
		foreach ( $field_group->fields() as $index => $field_args ) {
			$field = new CMB2_Field( array(
				'field_args'  => $field_args,
				'group_field' => $field_group,
			) );

			$field_type = new CMB2_Types( $field );

			ob_start();
			// Do html
			$field_type->render();
			// grab the data from the output buffer and add it to our variable
			$html = ob_get_clean();

			$inputs[ $index ] = array( 'html' => $html, 'type' => $field->type() );
		}

		// error_log( '$inputs: '. print_r( $inputs, true ) );
		wp_send_json_success( $inputs );
	}

	public function delete() {
		if ( ! isset( $this->post_data['nonce'] ) ) {
			$this->throw_error( __LINE__, 'missing_nonce' );
		}
		if ( ! isset( $this->post_data['group_id'] ) ) {
			$this->throw_error( __LINE__, 'missing_required' );
		}

		$this->field_id = sanitize_text_field( $this->post_data['group_id'] );

		$field = $this->find_group_field_array();

		if ( ! wp_verify_nonce( $this->post_data['nonce'], $field['id'] ) ) {
			$this->throw_error( __LINE__, 'missing_nonce' );
		}

		$field_group = new CMB2_Field( array(
			'field_args'  => $field,
			'object_type' => 'post',
		) );

		parent::__construct( $field_group );

		// wp_send_json_success( $this->get_object( $this->new_object_id ) );
		if ( $this->delete_object( $this->new_object_id ) ) {
			wp_send_json_success();
		}

		$this->throw_error( __LINE__, 'could_not_delete' );
	}

	protected function init_args() {
		if ( ! isset( $this->post_data['host_id'], $this->post_data['fieldName'] ) ) {
			$this->throw_error( __LINE__, 'missing_required' );
		}

		$host = get_post( absint( $this->post_data['host_id'] ) );

		if ( ! $host ) {
			$this->throw_error( __LINE__, 'missing_required' );
		}

		$this->host_id  = $host->ID;
		$parts          = explode( '[', sanitize_text_field( $this->post_data['fieldName'] ) );
		$this->field_id = array_shift( $parts );
		$index          = explode( ']', array_shift( $parts ) );
		$this->index    = array_shift( $index );
	}

	protected function find_group_field_array() {
		foreach ( $this->group_fields as $cmb_id => $fields ) {
			foreach ( $fields as $_field_id => $field ) {
				if ( $_field_id === $this->field_id ) {
					$this->cmb_id = $cmb_id;
					return $field;
				}
			}
		}

		$this->throw_error( __LINE__, 'missing_required' );
	}

	public function override_ids_get( $object_ids, $group_field ) {
		$object_ids[ $this->index ] = $this->new_object_id;

		return $object_ids;
	}

	protected function throw_error( $line, $string_key ) {
		throw new Exception( $line . ': '. CMB2_Group_Map::$strings[ $string_key ] );
	}

}
