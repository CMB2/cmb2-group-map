<?php
/**
 * CMB2_Group_Post_Map_Set
 */
class CMB2_Group_Post_Map_Set {

	/**
	 * CMB2_Field
	 *
	 * @var CMB2_Field
	 */
	protected $group_field;

	/**
	 * Group field value to save
	 *
	 * @var mixed
	 */
	protected $value;

	/**
	 * Constructor
	 *
	 * @since 0.1.0
	 *
	 * @param CMB2_Field $group_field
	 * @param mixed      $value       Value to save.
	 */
	function __construct( CMB2_Field $group_field, $value ) {
		$this->group_field = $group_field;
		$this->value = $value;
	}

	/**
	 * Handles saving the group field value out to individual CPT posts.
	 * Calls 'cmb2_group_post_map_posts_updated' hook.
	 *
	 * @since  0.1.0
	 */
	public function save() {
		$posts = array();

		if ( ! empty( $this->value ) ) {

			$group_field    = $this->group_field;
			$parent_post_id = $group_field->object_id;
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
				$data = $this->post_data( $fields, $clean, $form_data[ $index ] );

				if ( $post_id = $this->update_or_insert( $data ) ) {
					$posts[ $index ] = $post_id;
				}
			}
		}

		// Trigger an action after posts were updated/created
		do_action( 'cmb2_group_post_map_posts_updated', $posts, $parent_post_id, $group_field );
	}

	/**
	 * The post data for a post import.
	 *
	 * @since  0.1.0
	 *
	 * @param  array  $fields     Array of group sub-fields.
	 * @param  array  $clean_vals Array of CMB2-cleaned values for this post/group.
	 * @param  array  $unclean    Array of $_POST values for the group.
	 *
	 * @return array              Array of post data for the import
	 */
	protected function post_data( $fields, $clean_vals, $unclean ) {
		$post_data = array();

		foreach ( $fields as $sub_field_id => $field ) {
			$unclean_value = isset( $unclean[ $sub_field_id ] )
				? $unclean[ $sub_field_id ]
				: null;

			$post_data = $this->add_field_data(
				$post_data,
				$field,
				$clean_vals,
				$unclean_value
			);
		}

		// Set some default post data params
		if ( ! empty( $post_data ) ) {
			$post_data['post_type']   = $this->group_field->args( 'post_type_map' );
			$post_data['post_status'] = get_post_status( $this->group_field->object_id );
			$post_data['post_parent'] = $this->group_field->object_id;
			$post_data['ID']          = isset( $post_data['ID'] ) ? $post_data['ID'] : 0;
		}

		return $post_data;
	}

	/**
	 * Add data from the fields to the array of post data.
	 *
	 * @since 0.1.0
	 *
	 * @param array      $post_data  Array of post data for the import.
	 * @param CMB2_Field $field      Sub-field object.
	 * @param array      $clean_vals Array of CMB2-cleaned values for this post/group.
	 * @param mixed      $unclean    $_POST value for this field.
	 *
	 * @return array                 Maybe-modified Array of post data for the import.
	 */
	public function add_field_data( $post_data, $field, $clean_vals, $unclean ) {
		$clean_val = false;
		$field_id    = $field->id( true );
		$taxonomy    = $field->args( 'taxonomy' );

		if ( 'ID' === $field_id ) {

			// $unclean value is the post ID.
			$clean_val = absint( $unclean );

		} elseif ( $taxonomy ) {

			// Get taxonomy values from the $_POST data, and clean them.
			$clean_val = is_array( $unclean ) ? array_map( 'sanitize_text_field', $unclean ) : sanitize_text_field( $unclean );

		} elseif ( isset( $clean_vals[ $field_id ] ) ) {
			$clean_val = $clean_vals[ $field_id ];
		}

		if ( ! $clean_val ) {
			// Could not a find a clean value?!
			return $post_data;
		}

		// If the field id matches a post field
		if ( isset( CMB2_Group_Post_Map::$post_fields[ $field_id ] ) ) {

			// Then apply it directly.
			$post_data[ $field_id ] = $clean_val;
		} elseif ( $taxonomy ) {

			// If the field has a taxonomy parameter, then set value to that taxonomy
			$post_data['tax_input'][ $taxonomy ] = $clean_val;

		} else {

			// And finally, apply the rest as post meta.
			$post_data['meta_input'][ $field_id ] = $clean_val;
		}

		return $post_data;
	}

	/**
	 * If the post data array is not empty, either create or update a post
	 * in the mapped post-type.
	 *
	 * @since  0.1.0
	 *
	 * @param  array $data Array of post data for the import.
	 *
	 * @return mixed       Result of wp_update_post, wp_insert_post or is false if no data.
	 */
	protected function update_or_insert( $data ) {
		if ( empty( $data ) ) {
			return false;
		}

		// Update post?
		if ( isset( $data['ID'] ) && $data['ID'] && get_post( $data['ID'] ) ) {
			return wp_update_post( $data );
		}

		if ( isset( $data['ID'] ) ) {
			unset( $data['ID'] );
		}

		// Or create it?
		return wp_insert_post( $data );
	}

}
