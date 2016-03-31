<?php
/**
 * CMB2_Group_Post_Map_Get
 */
class CMB2_Group_Post_Map_Set {

	protected $group_field;
	protected $value;

	function __construct( CMB2_Field $group_field, $value ) {
		$this->group_field = $group_field;
		$this->value = $value;
	}

	public function save() {
		if ( empty( $this->value ) ) {
			return $this->remove_post_connections();
		}

		$original_post_id = $this->group_field->object_id;
		$original_post_status = get_post_status( $original_post_id );
		$field_id         = $this->group_field->id( true );
		$destination_cpt  = $this->group_field->args( 'post_type_map' );
		$fields           = array();
		$form_data        = isset( $_POST[ $field_id ] ) ? $_POST[ $field_id ] : array();

		$count = 0;
		foreach ( (array) $this->group_field->args( 'fields' ) as $field ) {
			$field = new CMB2_Field( array(
				'field_args'  => $field,
				'group_field' => $this->group_field,
			) );

			$fields[ $field->id( true ) ] = $field;
			$count++;
		}

		$posts = array();

		foreach ( $this->value as $index => $clean_array ) {

			$post_data = $this->get_post_data( $fields, $clean_array, $form_data[ $index ] );
			$post_data['post_type'] = $destination_cpt;
			$post_data['post_status'] = $original_post_status;
			$post_data['post_parent'] = $original_post_id;
			$post_data['ID'] = isset( $post_data['ID'] ) ? $post_data['ID'] : 0;

			if ( $post_data['ID'] && get_post( $post_data['ID'] ) ) {

				$posts[ $index ] = wp_update_post( $post_data );

			} else {
				if ( isset( $post_data['ID'] ) ) {
					unset( $post_data['ID'] );
				}

				$post_data = $this->get_post_data( $fields, $clean_array, $form_data[ $index ] );
				// wp_die( '<xmp>$post_data: '. print_r( $post_data, true ) .'</xmp>' );

				if ( ! empty( $post_data ) ) {
					$posts[ $index ] = wp_insert_post( $post_data );
				}
			}
		}

		do_action( 'cmb2_group_post_map_posts_updated', $posts, $original_post_id, $this->group_field );

	}

	protected function get_post_data( $fields, $clean_values, $unclean ) {
		$post_data = array();
		foreach ( $unclean as $sub_field_id => $unclean_value ) {
			if ( ! isset( $fields[ $sub_field_id ] ) ) {
				continue;
			}

			// Field object
			$field = $fields[ $sub_field_id ];
			$clean_value = false;

			if ( ! isset( $clean_values[ $sub_field_id ] ) ) {
				if ( $field->args( 'taxonomy' ) ) {
					$clean_value = is_array( $unclean_value ) ? array_map( 'sanitize_text_field', $unclean_value ) : sanitize_text_field( $unclean_value );
				} elseif ( 'ID' === $field->id( true ) ) {
					$post_data['ID'] = absint( $unclean_value );
				}
			} else {
				$clean_value = $clean_values[ $sub_field_id ];
			}

			if ( ! $clean_value ) {
				// Could not a find a clean value?!
				continue;
			}

			// If the field id matches a post field
			if ( in_array( $field->id( true ), CMB2_Group_Post_Map::$post_fields, 1 ) ) {

				// Then apply it directly.
				$post_data[ $field->id( true ) ] = $clean_value;
			}
			// If the field has a taxonomy parameter, then set value to that taxonomy
			elseif ( $field->args( 'taxonomy' ) ) {
				$post_data['tax_input'][ $field->args( 'taxonomy' ) ] = $clean_value;
			}
			// And finally, apply the data to the post as post meta.
			else {
				$post_data['meta_input'][ $field->id( true ) ] = $clean_value;
			}
		}

		return $post_data;
	}

	public function remove_post_connections() {
		throw new Exception( 'hey, fix this: '. __METHOD__ );
	}


}
