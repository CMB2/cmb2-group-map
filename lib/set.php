<?php
require_once CMB2_GROUP_POST_MAP_DIR . 'lib/base.php';
/**
 * CMB2_Group_Map_Set
 */
class CMB2_Group_Map_Set extends CMB2_Group_Map_Base {

	/**
	 * Constructor
	 *
	 * @since 0.1.0
	 *
	 * @param CMB2_Field $group_field
	 * @param mixed      $value       Value to save.
	 */
	public function __construct( CMB2_Field $group_field, $value ) {
		parent::__construct( $group_field );

		$this->value = $value;
	}

	/**
	 * Handles saving the group field value out to individual mapped-type objects.
	 * Calls 'cmb2_group_map_updated' hook.
	 *
	 * @since  0.1.0
	 */
	public function save() {
		$updated = array();

		if ( ! empty( $this->value ) ) {

			$group_field    = $this->group_field;
			$parent_object_id = $group_field->object_id;
			$field_id       = $group_field->id( true );
			$fields         = array();
			$form_data      = isset( $_POST[ $field_id ] ) ? $_POST[ $field_id ] : array();

			$count = 0;
			foreach ( (array) $group_field->args( 'fields' ) as $field_args ) {
				$field = new CMB2_Field( array(
					'field_args'  => $field_args,
					'group_field' => $group_field,
				) );

				$fields[ $field->id( true ) ] = $field;
				$count++;
			}

			foreach ( $this->value as $index => $clean ) {
				$data = $this->object_data( $fields, $clean, $form_data[ $index ] );

				if ( $object_id = $this->update_or_insert( $data ) ) {
					$updated[ $index ] = $object_id;
				}
			}
		}

		// Trigger an action after objects were updated/created
		do_action( 'cmb2_group_map_updated', $updated, $parent_object_id, $group_field );
	}

	/**
	 * The object data for a object import.
	 *
	 * @since  0.1.0
	 *
	 * @param  array  $fields     Array of group sub-fields.
	 * @param  array  $clean_vals Array of CMB2-cleaned values for this object/group.
	 * @param  array  $unclean    Array of $_POST values for the group.
	 *
	 * @return array              Array of object data for the import
	 */
	protected function object_data( $fields, $clean_vals, $unclean ) {
		$data = array();

		foreach ( $fields as $sub_field_id => $field ) {
			$unclean_value = isset( $unclean[ $sub_field_id ] )
				? $unclean[ $sub_field_id ]
				: null;

			$data = $this->add_field_data(
				$data,
				$field,
				$clean_vals,
				$unclean_value
			);
		}

		// Set some default object data params
		if ( ! empty( $data ) ) {
			$data = $this->set_default_data( $data );
		}

		return $data;
	}

	/**
	 * Add data from the fields to the array of object data.
	 *
	 * @since 0.1.0
	 *
	 * @param array      $object_data  Array of object data for the import.
	 * @param CMB2_Field $field      Sub-field object.
	 * @param array      $clean_vals Array of CMB2-cleaned values for this object/group.
	 * @param mixed      $unclean    $_POST value for this field.
	 *
	 * @return array                 Maybe-modified Array of object data for the import.
	 */
	public function add_field_data( $object_data, $field, $clean_vals, $unclean ) {
		$clean_val = false;
		$field_id    = $field->id( true );
		$taxonomy    = $field->args( 'taxonomy' );

		if ( 'ID' === $field_id ) {

			// $unclean value is the object ID.
			$clean_val = absint( $unclean );

		} elseif ( $taxonomy ) {

			// Get taxonomy values from the $_POST data, and clean them.
			$clean_val = is_array( $unclean ) ? array_map( 'sanitize_text_field', $unclean ) : sanitize_text_field( $unclean );

		} elseif ( isset( $clean_vals[ $field_id ] ) ) {
			$clean_val = $clean_vals[ $field_id ];
		}

		if ( ! $clean_val ) {
			// Could not a find a clean value?!
			return $object_data;
		}

		// If the field id matches a object field
		if ( $this->is_object_field( $field_id ) ) {

			// Then apply it directly.
			$object_data[ $field_id ] = $clean_val;
		} elseif ( $taxonomy ) {

			// If the field has a taxonomy parameter, then set value to that taxonomy
			// @todo figure out why unchecking all terms does not remove them.
			$object_data['tax_input'][ $taxonomy ] = $clean_val;

		} else {

			// And finally, apply the rest as object meta.
			$object_data['meta_input'][ $field_id ] = $clean_val;
		}

		return $object_data;
	}

	/**
	 * If object data exists, then set some default values for the object
	 *
	 * @since 0.1.0
	 *
	 * @param array  $data Array of modified object data.
	 */
	public function set_default_data( $data ) {
		switch ( $this->object_type() ) {
			case 'user':
				$data['ID']   = isset( $data['ID'] ) ? $data['ID'] : 0;
				$data['role'] = isset( $data['role'] ) ? $data['role'] : 'subscriber';
				break;

			case 'comment':
				$data['comment_post_ID'] = $this->group_field->object_id;
				$data['user_id']         = isset( $data['user_id'] ) ? $data['user_id'] : get_current_user_id();
				$data['comment_ID']      = isset( $data['comment_ID'] ) ? $data['comment_ID'] : 0;
				break;

			case 'term':
				$data['taxonomy'] = $this->group_field->args( 'taxonomy' );
				$data['term_id']  = isset( $data['term_id'] ) ? $data['term_id'] : 0;
				$data['term']     = isset( $data['term'] ) ? $data['term'] : '';
				break;

			default:
				$data['post_type']   = $this->group_field->args( 'post_type_map' );
				$data['post_status'] = get_post_status( $this->group_field->object_id );
				$data['post_parent'] = $this->group_field->object_id;
				$data['ID']          = isset( $data['ID'] ) ? $data['ID'] : 0;
				break;
		}

		return $data;
	}

	/**
	 * If the object data array is not empty, either create or update a object
	 * in the mapped object-type.
	 *
	 * @since  0.1.0
	 *
	 * @param  array $data Array of object data for the import.
	 *
	 * @return mixed       Result of update, insert or is false if no data.
	 */
	protected function update_or_insert( $data ) {
		if ( empty( $data ) ) {
			return false;
		}

		// Update object?
		$updated = $this->update( $data );
		if ( 'no' !== $updated ) {
			return $updated;
		}

		// Or create it?
		return $this->insert( $data );
	}

	/**
	 * Possibly update an object.
	 *
	 * @since  0.1.0
	 *
	 * @param  array $data Array of object data for the update.
	 *
	 * @return mixed       Result of update, or 'no' if the checks failed.
	 */
	protected function update( $data ) {
		$updated = 'no';

		switch ( $this->object_type() ) {
			case 'user':
				if ( isset( $data['ID'] ) && $data['ID'] && get_user_by( 'id', $data['ID'] ) ) {
					$updated = wp_update_user( $data );
				}
				break;

			case 'comment':
				if ( isset( $data['comment_ID'] ) && $data['comment_ID'] && get_comment( $data['comment_ID'] ) ) {
					$updated = wp_update_post( $data );
				}
				break;

			case 'term':
				if ( isset( $data['term_id'] ) && $data['term_id'] && get_term( $data['term_id'], $data['taxonomy'] ) ) {
					$updated = wp_update_term( $data['term_id'], $data['taxonomy'], $data );
				}
				break;

			default:
				if ( isset( $data['ID'] ) && $data['ID'] && get_post( $data['ID'] ) ) {
					$updated = wp_update_post( $data );
				}
				break;
		}

		return $updated;
	}

	/**
	 * Insert an object.
	 *
	 * @since  0.1.0
	 *
	 * @param  array $data Array of object data for the insert.
	 *
	 * @return mixed       Result of insert.
	 */
	protected function insert( $data ) {
		switch ( $this->object_type() ) {
			case 'user':
				if ( isset( $data['ID'] ) ) {
					unset( $data['ID'] );
				}

				return wp_insert_user( $data );

			case 'comment':
				if ( isset( $data['comment_ID'] ) ) {
					unset( $data['comment_ID'] );
				}

				return wp_insert_comment( $data );

			case 'term':
				if ( isset( $data['term_id'] ) ) {
					unset( $data['term_id'] );
				}

				return wp_insert_term( $data['term'], $data['taxonomy'], $data );

			default:
				if ( isset( $data['ID'] ) ) {
					unset( $data['ID'] );
				}

				return wp_insert_post( $data );
		}
	}

}
