<?php
/**
 * CMB2_Group_Post_Map_Get
 */
class CMB2_Group_Post_Map_Get {

	/**
	 * A registry array for instances of this class.
	 *
	 * @var array
	 */
	protected static $getters = array();

	/**
	 * CMB2_Field
	 *
	 * @var CMB2_Field
	 */
	protected $group_field;

	/**
	 * Array of mapped post ids for this post.
	 *
	 * @var array
	 */
	protected $post_ids = array();

	/**
	 * Copy of $post_ids, manipulated for term-checking.
	 *
	 * @var array
	 */
	protected $term_post_ids = array();

	/**
	 * The current post id for term-checking.
	 *
	 * @var null
	 */
	protected $term_post_id = null;

	/**
	 * Retrieved value array.
	 *
	 * @var null
	 */
	protected $value = null;

	/**
	 * Retrieved term array.
	 *
	 * @var null
	 */
	protected $terms = null;

	/**
	 * The current post object during post iteration.
	 *
	 * @var null
	 */
	protected $post = null;

	/**
	 * Get the array of values for a group field or the value for
	 * a group field's sub-fields. Data is retrieved from the mapped post object.
	 *
	 * @since  0.1.0
	 *
	 * @param  CMB2_Field $field Group field, or group sub-field object.
	 *
	 * @return mixed             Group or sub-field's value.
	 */
	public static function get_value( CMB2_Field $field ) {
		$field_id = $field->group ? $field->group->id() : $field->id();

		if ( 'group' !== $field->type() ) {
			if ( ! $field->group ) {
				return $this->trigger_warning( __METHOD__ . ' only works with group fields.' );
			}

			if ( ! isset( self::$getters[ $field_id ] ) ) {
				return $this->trigger_warning( 'Something went wrong! The group field needs to have already set up the object.' );
			}

			$getter = self::$getters[ $field_id ];
			$value  = $getter->subfield_value( $field );

			// Return filtered value.
			return apply_filters( 'cmb2_group_post_map_get_subfield_value', $value, $getter, $field );
		}

		if ( ! isset( self::$getters[ $field_id ] ) ) {
			self::$getters[ $field_id ] = new self( $field );
		}

		$getter = self::$getters[ $field_id ];
		$value  = $getter->group_field_value();

		// Return filtered value.
		return apply_filters( 'cmb2_group_post_map_get_group_field_value', $value, $getter );
	}

	/**
	 * Constructor. Setup the getter object.
	 *
	 * @since 0.1.0
	 *
	 * @param CMB2_Field $group_field Group field to get the values for.
	 */
	function __construct( CMB2_Field $group_field ) {
		$this->group_field = $group_field;
		$this->post_ids    = get_post_meta( $group_field->object_id, $this->group_field->id( true ), 1 );
		// Need a separate post id array for taxonomy term value caching
		$this->term_post_ids = $this->post_ids;
	}

	/**
	 * Get the group field value
	 *
	 * @since  0.1.0
	 *
	 * @return array  Array of values for the group.
	 */
	public function group_field_value() {
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

	/**
	 * Gets value for subfield from the value for the whole group.
	 *
	 * @since  0.1.0
	 *
	 * @param  CMB2_Field $subfield CMB2_Field
	 *
	 * @return mixed                Value for subfield.
	 */
	public function subfield_value( CMB2_Field $subfield ) {
		$value = $this->group_field_value();

		// No sub-field?
		if ( empty( $value[ $this->group_field->index ] ) ) {
			return null;
		}

		$field_index = $this->group_field->index;
		$field_id    = $subfield->id( true );

		return isset( $value[ $field_index ][ $field_id ] )
			? $value[ $field_index ][ $field_id ]
			: null;
	}

	/**
	 * Hooks to 'cmb2_override_meta_value' and returns the value array we've created.
	 * Also, if field is a taxonomy field, filter 'get_the_terms' to return the cached value.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed      $nooverride Value w/o override.
	 * @param int        $post_id    Post ID
	 * @param array      $args       Array of field arguments.
	 * @param CMB2_Field $subfield   Sub-field object.
	 *
	 * @return array                 Array of values for the group.
	 */
	public function set_sub_field_value( $nooverride, $post_id, $args, CMB2_Field $subfield ) {
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
		if ( isset( CMB2_Group_Post_Map::$post_fields[ $field_id ] ) ) {
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

	/**
	 * Checks if subfield is a taxonomy field, and then filters 'get_the_terms'
	 * to return the cached value.
	 *
	 * @since 0.1.0
	 *
	 * @param CMB2_Field  $subfield Sub-field object
	 */
	public function check_taxonomy_cache( CMB2_Field $subfield ) {
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

	/**
	 * Hooked into 'get_the_terms', returns cached term array.
	 *
	 * @since  0.1.0
	 *
	 * @param  array  $terms    Array of term objects
	 * @param  int    $post_id  Post ID.
	 * @param  string $taxonomy Taxonomy slug.
	 *
	 * @return array            Array of term objects.
	 */
	public function override_term_get( $terms, $post_id, $taxonomy ) {
		// Final check if we do actually have the cached value
		if ( $this->term_post_id && isset( $this->terms[ $this->term_post_id ][ $taxonomy ] ) ) {
			// Ok we do, so let's return that instead.
			$terms = $this->terms[ $this->term_post_id ][ $taxonomy ];
		}

		return $terms;
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
