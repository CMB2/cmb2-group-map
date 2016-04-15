<?php
require_once CMB2_GROUP_POST_MAP_DIR . 'lib/base.php';
/**
 * CMB2_Group_Map_Ajax
 */
class CMB2_Group_Map_Ajax extends CMB2_Group_Map_Base {

	/**
	 * The $_POST data
	 *
	 * @var array
	 */
	protected $post_data = array();

	/**
	 * The array of group fields from CMB2_Group_Map
	 *
	 * @var array
	 */
	protected $group_fields = array();

	/**
	 * Host objects's ID
	 *
	 * @var integer
	 */
	protected $host_id = 0;

	/**
	 * ID of object to fetch or delete.
	 *
	 * @var integer
	 */
	protected $object_id = 0;

	/**
	 * The id of the group field which is requesting the
	 * fetch or the delete.
	 *
	 * @var string
	 */
	protected $field_id = '';

	/**
	 * Current group index, for selecting the right value.
	 *
	 * @var integer
	 */
	protected $index = 0;

	/**
	 * The id of the cmb instance for the current field.
	 *
	 * @var string
	 */
	protected $cmb_id = '';

	/**
	 * Constructor. Setup the ajax handler object.
	 *
	 * @since 0.1.0
	 */
	public function __construct( array $post_data, array $group_fields ) {
		if ( ! isset( $post_data['post_id'], $post_data['host_id'] ) ) {
			$this->throw_error( __LINE__, 'missing_required' );
		}

		$post = get_post( absint( $post_data['post_id'] ) );

		if ( ! $post ) {
			$this->throw_error( __LINE__, 'missing_required' );
		}

		$host = get_post( absint( $post_data['host_id'] ) );

		if ( ! $host ) {
			$this->throw_error( __LINE__, 'missing_required' );
		}

		$this->host_id      = $host->ID;
		$this->post_data    = $post_data;
		$this->object_id    = $post->ID;
		$this->group_fields = $group_fields;
	}

	/**
	 * Handles sending input html data back to Javascript
	 *
	 * @since  0.1.0
	 */
	public function send_input_data() {
		$this->init_send_args();
		$field = $this->find_group_field_array();

		// Need to override the group id fetching and splice in our own new post id.
		add_filter( 'cmb2_group_map_get_group_ids', array( $this, 'override_ids_get' ), 10, 2 );

		$group_field = new CMB2_Field( array(
			'field_args'  => $field,
			'object_type' => 'post',
			'object_id'   => $this->host_id,
		) );
		$group_field->index = $this->index;

		parent::__construct( $group_field );

		$inputs = array();
		foreach ( $group_field->fields() as $index => $field_args ) {
			$field = new CMB2_Field( array(
				'field_args'  => $field_args,
				'group_field' => $group_field,
			) );

			$field_type = new CMB2_Types( $field );

			ob_start();
			// Do html
			$field_type->render();
			// grab the data from the output buffer and add it to our variable
			$html = ob_get_clean();

			$inputs[ $index ] = array( 'html' => $html, 'type' => $field->type() );
		}

		// Send it to JS.
		wp_send_json_success( $inputs );
	}

	/**
	 * Handles deleting a post, as requested by Javascript handler.
	 *
	 * @since  0.1.0
	 */
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

		$group_field = new CMB2_Field( array(
			'field_args'  => $field,
			'object_type' => 'post',
			'object_id'   => $this->host_id,
		) );

		parent::__construct( $group_field );

		if ( $this->delete_object( $this->object_id ) ) {
			// Trigger an action after objects were updated/created
			do_action( 'cmb2_group_map_associated_object_deleted', $this->object_id, $this->host_id, $group_field );

			wp_send_json_success();
		}


		$this->throw_error( __LINE__, 'could_not_delete' );
	}

	/**
	 * Checks for correct data for fetching post data, and sets up variables.
	 *
	 * @since  0.1.0.
	 */
	protected function init_send_args() {
		if ( ! isset( $this->post_data['fieldName'] ) ) {
			$this->throw_error( __LINE__, 'missing_required' );
		}

		$parts          = explode( '[', sanitize_text_field( $this->post_data['fieldName'] ) );
		$this->field_id = array_shift( $parts );
		$index          = explode( ']', array_shift( $parts ) );
		$this->index    = array_shift( $index );
	}

	/**
	 * Get the group field config array from the list of field groups.
	 *
	 * @since  0.1.0
	 *
	 * @return array Group field config array.
	 */
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

	/**
	 * Handles overriding the group ids and splices in our own new post id.
	 *
	 * @since  0.1.0
	 *
	 * @param  array      $object_ids  Array of object ids.
	 * @param  CMB2_Field $group_field CMB2_Field group object.
	 *
	 * @return array                   Modified object ids.
	 */
	public function override_ids_get( $object_ids, CMB2_Field $group_field ) {
		$object_ids[ $this->index ] = $this->object_id;

		return $object_ids;
	}

	/**
	 * Helper method to throw an exception using one of the CMB2_Group_Map $strings.
	 *
	 * @since 0.1.0
	 *
	 * @param int    $line       Line number
	 * @param string $string_key The key to the string to use.
	 */
	protected function throw_error( $line, $string_key ) {
		throw new Exception( $line . ': '. CMB2_Group_Map::$strings[ $string_key ] );
	}

}
