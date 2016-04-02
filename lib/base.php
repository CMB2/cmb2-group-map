<?php
/**
 * CMB2_Group_Map_Base
 */
abstract class CMB2_Group_Map_Base {

	/**
	 * CMB2_Field
	 *
	 * @var CMB2_Field
	 */
	protected $group_field;

	/**
	 * Group field value.
	 *
	 * @var null
	 */
	protected $value = null;

	/**
	 * Constructor. Setup the getter object.
	 *
	 * @since 0.1.0
	 *
	 * @param CMB2_Field $group_field Group field to get the values for.
	 */
	public function __construct( CMB2_Field $group_field ) {
		$group_field->args['original_object_type'] = $group_field->object_type;
		$group_field->object_type                  = $group_field->args( 'object_type_map' );
		$this->group_field                         = $group_field;
	}

	/**
	 * Get the group field's object ID.
	 *
	 * @since  0.1.0
	 *
	 * @return int  Object ID.
	 */
	public function object_id() {
		return $this->group_field->object_id;
	}

	/**
	 * Get this group field's object type.
	 *
	 * @since  0.1.0
	 *
	 * @param  string $check If set, checks if group field's object type matches value.
	 *
	 * @return string|bool   Object type. Can be 'user', 'comment', 'term', or the
	 *                       default 'post'. Or bool value from object type check.
	 */
	protected function object_type( $check = '' ) {
		$type = $this->group_field->object_type;
		if ( $check ) {
			return $check === $type;
		}
		return $type;
	}

	/**
	 * Get the object for our object type
	 *
	 * @since  0.1.0
	 *
	 * @param  int   $object_id Object ID
	 *
	 * @return mixed            Object instance if successful
	 */
	public function get_object( $object_id ) {
		switch ( $this->object_type() ) {
			case 'term':
				return get_term( $object_id, $this->group_field->args( 'taxonomy' ) );
			case 'comment':
				return get_comment( $object_id );
			case 'user':
				return get_user_by( 'id', $object_id );
			default:
				return get_post( $object_id );
		}
	}

	/**
	 * CMB2_Group_Map::object_id_key which gets the unique ID field key for the object type.
	 *
	 * @since  0.1.0
	 *
	 * @return string ID field key
	 */
	public function object_id_key() {
		return CMB2_Group_Map::object_id_key( $this->object_type() );
	}

	/**
	 * Chec if field id is one of the object type's default fields
	 *
	 * @since  0.1.0
	 *
	 * @param  string $field_id Field id
	 *
	 * @return boolean
	 */
	public function is_object_field( $field_id ) {
		switch ( $this->object_type() ) {
			case 'term':
				return isset( CMB2_Group_Map::$term_fields[ $field_id ] );
			case 'comment':
				return isset( CMB2_Group_Map::$comment_fields[ $field_id ] );
			case 'user':
				return isset( CMB2_Group_Map::$user_fields[ $field_id ] );
			default:
				return isset( CMB2_Group_Map::$post_fields[ $field_id ] );
		}
	}

	/**
	 * trigger_error wrapper which sets error level to E_USER_WARNING and returns empty string.
	 *
	 * @since  0.1.0
	 *
	 * @param  string $msg Error string
	 *
	 * @return string      Empty string for returning.
	 */
	protected function trigger_warning( $msg ) {
		trigger_error( $msg, E_USER_WARNING );
		return '';
	}

}
