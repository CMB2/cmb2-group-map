<?php
require_once CMB2_GROUP_POST_MAP_DIR . 'lib/base.php';
/**
 * CMB2_Group_Map_Get
 */
class CMB2_Group_Map_Get extends CMB2_Group_Map_Base {

	/**
	 * A registry array for instances of this class.
	 *
	 * @var array
	 */
	protected static $getters = array();

	/**
	 * Array of mapped object ids for this object.
	 *
	 * @var array
	 */
	protected $object_ids = array();

	/**
	 * The current object id for term-checking.
	 *
	 * @var null
	 */
	protected $term_object_id = null;

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
	 * The current object object during object iteration.
	 *
	 * @var null
	 */
	protected $object = null;

	/**
	 * Get the array of values for a group field or the value for
	 * a group field's sub-fields. Data is retrieved from the mapped object.
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
			return apply_filters( 'cmb2_group_map_get_subfield_value', $value, $getter, $field );
		}

		if ( ! isset( self::$getters[ $field_id ] ) ) {
			self::$getters[ $field_id ] = new self( $field );
		}

		$getter = self::$getters[ $field_id ];
		$value  = $getter->group_field_value();

		// Return filtered value.
		return apply_filters( 'cmb2_group_map_get_group_field_value', $value, $getter );
	}

	/**
	 * Constructor. Setup the getter object.
	 *
	 * @since 0.1.0
	 *
	 * @param CMB2_Field $group_field Group field to get the values for.
	 */
	public function __construct( CMB2_Field $group_field ) {

		// Get meta before we change the group field's object type (in the parent constructor)
		$object_ids = CMB2_Group_Map::get_map_meta( $group_field );

		$object_ids = apply_filters( 'cmb2_group_map_get_group_ids', $object_ids, $group_field );
		if ( is_array( $object_ids ) ) {
			$object_ids = array_filter( $object_ids, 'get_post' );
			$this->object_ids = array_values( $object_ids );
		} else {
			$this->object_ids = array();
		}

		parent::__construct( $group_field );
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

		if ( empty( $this->object_ids ) ) {
			return $this->value;
		}

		$all_fields = $this->group_field->fields();
		$stored_id  = $this->group_field->object_id;

		foreach ( $this->object_ids as $this->group_field->index => $object_id ) {

			// Only proceed if there is an actual object by this id
			if ( $this->object = $this->get_object( $object_id ) ) {

				// Temp. set the group field's object id to this object id
				$this->set_group_field_object_id( $object_id );

				// initiate the cached values for this object id
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
		$this->set_group_field_object_id( $stored_id );

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
	 * @param int        $object_id  Object ID
	 * @param array      $args       Array of field arguments.
	 * @param CMB2_Field $subfield   Sub-field object.
	 *
	 * @return array                 Array of values for the group.
	 */
	public function set_sub_field_value( $nooverride, $object_id, $args, CMB2_Field $subfield ) {
		$field_id = $subfield->id( true );

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

		// If the field id matches a object field
		if ( $this->is_object_field( $field_id ) ) {
			$subfield_value = $this->object->{ $field_id };
		}

		// If the field has a taxonomy parameter, then get value from that taxonomy
		elseif ( $subfield->args( 'taxonomy' ) ) {
			$subfield_value = $this->get_value_from_taxonomy( $subfield );
		}

		// And finally, get the data from the object meta.
		else {
			$single = $subfield->args( 'repeatable' ) || ! $subfield->args( 'multiple' );

			$subfield_value = get_metadata(
				$this->object_type(),
				$this->object_id(),
				$field_id,
				$single
			);
		}

		$this->value[ $this->group_field->index ][ $field_id ] = $subfield_value;

		return $this->value;
	}

	/**
	 * Get the value from a taxonomy subfield
	 *
	 * @since  0.1.0
	 *
	 * @param  CMB2_Field $subfield Subfield object.
	 *
	 * @return mixed                Array of terms if successful.
	 */
	public function get_value_from_taxonomy( CMB2_Field $subfield ) {
		// No taxonomies for taxonomies
		if ( $this->object_type( 'term' ) ) {
			return null;
		}

		$taxonomy = $subfield->args( 'taxonomy' );
		$terms = get_the_terms( $this->object, $taxonomy );
		if ( ! $terms ) {
			$terms = array();
		}

		// Cache this taxonomy's terms against the object ID.
		$this->terms[ $this->object_id() ][ $taxonomy ] = $terms;

		if ( is_wp_error( $terms ) || empty( $terms ) ) {

			// Fallback to default (if it's set)
			$terms = $subfield->args( 'default' );

		} else {

			$terms = 'taxonomy_multicheck' === $subfield->type()
				? wp_list_pluck( $terms, 'slug' )
				: $terms[ key( $terms ) ]->slug;
		}

		return $terms ? $terms : array();
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

		// No taxonomies for taxonomies
		if ( $this->object_type( 'term' ) ) {
			return;
		}

		// If we're looking at a taxonomy field type, we have to do extra magic
		if ( $taxonomy = $subfield->args( 'taxonomy' ) ) {

			if ( ! isset( $this->object_ids[ $this->group_field->index ] ) ) {
				return;
			}

			// Get the next object id by removing it from the front of the object ids
			$this->term_object_id = $this->object_ids[ $this->group_field->index ];

			// If we have a cached value for this object id, then proceed
			if (
				isset( $this->terms[ $this->term_object_id ][ $taxonomy ] )
				&& $this->terms[ $this->term_object_id ][ $taxonomy ]
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
	 * @param  array  $terms     Array of term objects
	 * @param  int    $object_id Object ID.
	 * @param  string $taxonomy  Taxonomy slug.
	 *
	 * @return array             Array of term objects.
	 */
	public function override_term_get( $terms, $object_id, $taxonomy ) {
		// Final check if we do actually have the cached value
		if ( $this->term_object_id && isset( $this->terms[ $this->term_object_id ][ $taxonomy ] ) ) {
			// Ok we do, so let's return that instead.
			$terms = $this->terms[ $this->term_object_id ][ $taxonomy ];
		}

		return $terms;
	}

	/**
	 * The Group Field's object ID setter to make compatible w/ older CMB2.
	 *
	 * @since 0.1.0
	 *
	 * @param int $object_id The object ID to set.
	 */
	protected function set_group_field_object_id( $object_id ) {
		if ( class_exists( 'CMB2_Base' ) ) {
			$this->group_field->object_id( $object_id );
		} else {
			$this->group_field->object_id = $object_id;
		}
	}

}
