<?php
/**
 * CMB2_Group_Map
 *
 * @todo Make this work for other destination object types
 * @todo Document this file/methods, etc
 * @todo Add README.md
 * @todo Add JS which adds button for removing the mapped object from DB
 * @todo Add JS button for selecting/searching existing content (but only w/o post_parent)
 * @todo Hook up removal
 */
class CMB2_Group_Map {

	protected static $single_instance = null;
	protected $allowed_object_types = array( 'post', 'user', 'comment', 'term' );
	protected $group_fields = array();

	public static $post_fields = array(
		'ID'                    => '',
		'post_author'           => '',
		'post_date'             => '',
		'post_date_gmt'         => '',
		'post_content'          => '',
		'post_content_filtered' => '',
		'post_title'            => '',
		'post_excerpt'          => '',
		'post_status'           => '',
		'post_type'             => '',
		'comment_status'        => '',
		'ping_status'           => '',
		'post_password'         => '',
		'post_name'             => '',
		'to_ping'               => '',
		'pinged'                => '',
		'post_modified'         => '',
		'post_modified_gmt'     => '',
		'post_parent'           => '',
		'menu_order'            => '',
		'post_mime_type'        => '',
		'guid'                  => '',
		'tax_input'             => '',
		'meta_input'            => '',
	);

	public static $user_fields = array(
		'ID'                   => '',
		'user_pass'            => '',
		'user_login'           => '',
		'user_nicename'        => '',
		'user_url'             => '',
		'user_email'           => '',
		'display_name'         => '',
		'nickname'             => '',
		'first_name'           => '',
		'last_name'            => '',
		'description'          => '',
		'rich_editing'         => '',
		'comment_shortcuts'    => '',
		'admin_color'          => '',
		'use_ssl'              => '',
		'user_registered'      => '',
		'show_admin_bar_front' => '',
		'role'                 => '',
	);

	public static $comment_fields = array(
		'comment_agent'        => '',
		'comment_approved'     => '',
		'comment_author'       => '',
		'comment_author_email' => '',
		'comment_author_IP'    => '',
		'comment_author_url'   => '',
		'comment_content'      => '',
		'comment_date'         => '',
		'comment_date_gmt'     => '',
		'comment_karma'        => '',
		'comment_parent'       => '',
		'comment_post_ID'      => '',
		'comment_type'         => '',
		'comment_meta'         => '',
		'user_id'              => '',
	);

	public static $term_fields = array(
		'term'        => '',
		'taxonomy'    => '',
		'alias_of'    => '',
		'description' => '',
		'parent'      => '',
		'slug'        => '',
	);

	/**
	 * Creates or returns an instance of this class.
	 * @since  0.1.0
	 * @return CMB2_Group_Map A single instance of this class.
	 */
	public static function get_instance() {
		if ( null === self::$single_instance ) {
			self::$single_instance = new self();
		}

		return self::$single_instance;
	}

	protected function __construct() {
		add_action( 'cmb2_after_init', array( $this, 'setup_mapped_group_fields' ) );
		add_action( 'cmb2_group_map_updated', array( $this, 'map_to_original_object' ), 10, 3 );
	}

	public function setup_mapped_group_fields() {
		foreach ( CMB2_Boxes::get_all() as $cmb ) {
			foreach ( (array) $cmb->prop( 'fields' ) as $field ) {
				if (
					'group' === $field['type']
					&& (
						isset( $field['post_type_map'] )
						&& post_type_exists( $field['post_type_map'] )
					)
					|| isset( $field['object_type_map'] )
				) {
					$this->setup_mapped_group_field( $cmb, $field );
				}
			}
		}
	}

	protected function setup_mapped_group_field( CMB2 $cmb, array $field ) {
		$field = $this->set_object_type( $cmb, $field );

		$this->set_after_group_js_hook( $cmb, $field );

		$cmb->update_field_property( $field['id'], 'original_object_types', $cmb->prop( 'object_types' ) );

		// Add a hidden ID field to the group to store the referenced object id.
		$cmb->add_group_field( $field['id'], array(
			'id'   => self::object_id_key( $field['object_type_map'] ),
			'type' => 'hidden',
		) );

		$this->hook_cmb2_overrides( $field['id'] );

		// Store fields to object property for retrieval (if necessary)
		$this->group_fields[ $cmb->cmb_id ][ $field['id'] ] = $field;
	}

	protected function set_object_type( CMB2 $cmb, array $field ) {
		// Set object type
		if ( ! isset( $field['object_type_map'] ) || ! in_array( $field['object_type_map'], $this->allowed_object_types, 1 ) ) {
			$field['object_type_map'] = 'post';
		}

		$cmb->update_field_property( $field['id'], 'object_type_map', $field['object_type_map'] );

		if ( 'term' === $field['object_type_map'] && ( ! isset( $field['taxonomy'] ) || ! taxonomy_exists( $field['taxonomy'] ) ) ) {
			wp_die( 'Using "term" for the "object_type_map" parameter requires a "taxonomy" parameter to also be set.' );
		}

		return $field;
	}

	protected function set_after_group_js_hook( CMB2 $cmb, array $field ) {
		// Let's be sure not to stomp out any existing after_group parameter.
		if ( isset( $field['after_group'] ) ) {
			// Store it to another field property
			$cmb->update_field_property( $field['id'], 'cmb2_group_map_after_group', $field['after_group'] );
		}

		// Hook in our JS registration using after_group group field parameter.
		// This ensures the enqueueing/registering only occurs if the field is displayed.
		$cmb->update_field_property( $field['id'], 'after_group', array( $this, 'filter_cmb2_js_dependencies' ) );
	}

	public function filter_cmb2_js_dependencies( $args, $field ) {
		// Check for stored 'after_group' parameter, and run that now.
		if ( $field->args( 'cmb2_group_map_after_group' ) ) {
			$field->peform_param_callback( 'cmb2_group_map_after_group' );
		}

		// Register our JS with the 'cmb2_script_dependencies' filter.
		add_filter( 'cmb2_script_dependencies', array( $this, 'register_js' ) );
	}

	public function register_js( $dependencies ) {
		// $dependencies['cmb2_group_map'] = 'cmb2_group_map';
		// wp_register_script( 'cmb2_group_map', $src, array( 'jquery' ), self::VERSION, 1 );
		return $dependencies;
	}

	public static function object_id_key( $object_type ) {
		switch ( $object_type ) {
			case 'comment':
				return 'comment_ID';

			case 'term':
				return 'term_id';

			case 'user':
			default:
				return 'ID';
		}
	}

	protected function hook_cmb2_overrides( $field_id ) {
		add_filter( "cmb2_override_{$field_id}_meta_save", array( $this, 'do_save' ), 10, 4 );
		add_filter( "cmb2_override_{$field_id}_meta_value", array( $this, 'do_get' ), 10, 4 );
		add_filter( "cmb2_override_{$field_id}_meta_remove", array( $this, 'do_remove' ), 10, 4 );
	}

	public function do_save( $override, $a, $args, $field_group ) {
		require_once CMB2_GROUP_POST_MAP_DIR . 'lib/set.php';
		$setter = new CMB2_Group_Map_Set( $field_group, $a['value'] );
		$setter->save();

		return true; // this shortcuts CMB2 save
	}

	public function do_get( $nooverride, $object_id, $a, $field ) {
		remove_filter( "cmb2_override_{$a['field_id']}_meta_value", array( $this, 'do_get' ), 10, 4 );

		require_once CMB2_GROUP_POST_MAP_DIR . 'lib/get.php';
		$value = CMB2_Group_Map_Get::get_value( $field );

		return $value; // this shortcuts CMB2 get
	}

	public function do_remove( $override, $a, $args, $field ) {
		throw new Exception( 'hey, fix this: '. __METHOD__ );
		return true; // this shortcuts CMB2 remove
	}

	public function map_to_original_object( $updated, $original_object_id, $field ) {
		$updated    = is_array( $updated ) ? $updated : array();
		$object_ids = array();

		foreach ( $updated as $object_id ) {
			if ( ! is_wp_error( $object_id ) ) {
				$object_ids[] = $object_id;
			}
		}

		$meta_key    = $field->id();
		$object_type = $field->args( 'original_object_type' );

		if ( empty( $object_ids ) ) {
			delete_metadata( $object_type, $original_object_id, $meta_key );
		} else {
			update_metadata( $object_type, $original_object_id, $meta_key, $object_ids );
		}
	}

}
CMB2_Group_Map::get_instance();
