<?php
/**
 * CMB2_Group_Post_Map_Get
 */
class CMB2_Group_Post_Map_Get {

	protected static $getters = array();

	protected $group_field;
	protected $post_ids = array();
	protected $term_post_ids = array();
	protected $term_post_id = null;
	public $value = null;
	protected $terms = null;
	protected $key = 0;
	protected $post = null;

	public static function get_value( $field ) {

		$field_id = $field->group ? $field->group->id() : $field->id();

		if ( 'group' !== $field->type() ) {

			if ( ! $field->group ) {
				trigger_error( __METHOD__ . ' only works with group fields.' );
				return '';
			}


			if ( ! isset( self::$getters[ $field_id ] ) ) {
				trigger_error( 'something went wrong! The group field should have already setup the object.' );
				return '';
			}

			return self::$getters[ $field_id ]->get_subfield_value( $field );
		}

		if ( ! isset( self::$getters[ $field_id ] ) ) {
			self::$getters[ $field_id ] = new CMB2_Group_Post_Map_Get( $field );
		}

		return self::$getters[ $field_id ]->get_group_field_value();
	}

	function __construct( CMB2_Field $group_field ) {
		$this->group_field   = $group_field;
		$this->post_ids      = get_post_meta( $group_field->object_id, $this->group_field->id( true ), 1 );
		// Need a separate post id array for taxonomy term value caching
		$this->term_post_ids = $this->post_ids;
	}

	public function get_group_field_value() {
		if ( null !== $this->value ) {
			return $this->value;
		}

		$this->value = array();

		if ( empty( $this->post_ids ) ) {
			return $this->value;
		}

		$all_fields = $this->group_field->fields();
		$stored_id = $this->group_field->object_id;

		foreach ( $this->post_ids as $this->group_field->index => $post_id ) {

			// Only proceed if there is an actual post by this id
			if ( $this->post = get_post( $post_id ) ) {

				// Temp. set the group field's object id to this post id
				$this->group_field->object_id = $post_id;

				// initiate the cached values for this post id
				$this->value[ $this->group_field->index ] = array();

				// loop the group field's sub-fields
				foreach ( $all_fields as $field_id => $field_args ) {

					// And set the override filter for value-getting
					add_filter( 'cmb2_override_meta_value', array( $this, 'set_sub_field_value' ), 9, 4 );

					// Then init our field object, which will fetch the value
					// (and cache it for future initiations/lookups)
					$subfield = new CMB2_Field( array(
						'field_args'  => $field_args,
						'group_field' => $this->group_field,
					) );
				}
			}
		}

		// Restore the group field object id
		$this->group_field->object_id = $stored_id;

		// Return the full value array
		return $this->value;
	}

	public function get_subfield_value( $subfield ) {
		$value = $this->get_group_field_value();

		// No sub-field?
		if ( empty( $value[ $this->group_field->index ] ) ) {
			return null;
		}

		return isset( $value[ $this->group_field->index ][ $subfield->id( true ) ] )
			? $value[ $this->group_field->index ][ $subfield->id( true ) ]
			: null;
	}

	public function set_sub_field_value( $nooverride, $post_id, $args, $subfield ) {
		$field_id = $subfield->id( true );
		$taxonomy = $subfield->args( 'taxonomy' );

		if (
			// If we already have this value
			isset( $this->value[ $this->group_field->index ][ $field_id ] )
			// And this is def. a subfield
			&& $subfield->group instanceof CMB2_Field
			// And the parent/group field's ID matches the one on our object
			&& $this->group_field->id() === $subfield->group->id()
		) {

			// Maybe do extra magic to get taxonomy term cached values
			$this->check_taxonomy_cache( $subfield );

			// Then return the already existing value
			return $this->value;
		}

		$subfield_value = null;

		// If the field id matches a post field
		if ( in_array( $field_id, CMB2_Group_Post_Map::$post_fields, 1 ) ) {
			$subfield_value = $this->post->{ $field_id };
		}

		// If the field has a taxonomy parameter, then get value from that taxonomy
		elseif ( $taxonomy ) {
			$subfield_value = get_the_terms( $this->post, $taxonomy );

			$this->terms[ $this->post->ID ][ $taxonomy ] = $subfield_value;

			if ( is_wp_error( $subfield_value ) || empty( $subfield_value ) ) {
				$subfield_value = $subfield->args( 'default' );

			} else {

				$subfield_value = 'taxonomy_multicheck' === $subfield->type()
					? wp_list_pluck( $subfield_value, 'slug' )
					: $subfield_value[ key( $subfield_value ) ]->slug;
			}
		}

		// And finally, get the data from the post meta.
		else {
			$single = $subfield->args( 'repeatable' ) || ! $subfield->args( 'multiple' );
			$subfield_value = get_post_meta( $this->post->ID, $field_id, $single );
		}

		$this->value[ $this->group_field->index ][ $field_id ] = $subfield_value;

		return $this->value;
	}

	public function check_taxonomy_cache( $subfield ) {
		// If we're looking at a taxonomy field type, we have to do extra magic
		if ( $taxonomy = $subfield->args( 'taxonomy' ) ) {
			// Get the next post id by removing it from the front of the post ids
			$this->term_post_id = array_shift( $this->term_post_ids );

			// If we have a cached value for this post id, then proceed
			if (
				isset( $this->terms[ $this->term_post_id ][ $taxonomy ] )
				&& $this->terms[ $this->term_post_id ][ $taxonomy ]
			) {
				// We have a cached value, so we'll filter get_the_terms to return the cached value.
				add_filter( 'get_the_terms', array( $this, 'override_term_get' ), 10, 3 );
			}
		}
	}

	public function override_term_get( $terms, $post_id, $taxonomy ) {
		// Final check if we do actually have the cached value
		if ( $this->term_post_id && isset( $this->terms[ $this->term_post_id ][ $taxonomy ] ) ) {
			// Ok we do, so let's return that instead.
			$terms = $this->terms[ $this->term_post_id ][ $taxonomy ];
		}

		return $terms;
	}

}
