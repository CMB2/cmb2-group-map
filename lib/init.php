<?php

class CMB2_Group_Post_Map {

	protected static $single_instance = null;

	protected $ids = array();

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

	/**
	 * Creates or returns an instance of this class.
	 * @since  0.1.0
	 * @return CMB2_Group_Post_Map A single instance of this class.
	 */
	public static function get_instance() {
		if ( null === self::$single_instance ) {
			self::$single_instance = new self();
		}

		return self::$single_instance;
	}

	protected function __construct() {
		add_action( 'cmb2_after_init', array( $this, 'store_map_box_ids' ) );
		add_action( 'cmb2_group_post_map_posts_updated', array( $this, 'map_to_original_post' ), 10, 3 );

		// override get
		// override update
		// override remove
	}

	public function store_map_box_ids() {
		foreach ( CMB2_Boxes::get_all() as $cmb ) {
			foreach ( (array) $cmb->prop( 'fields' ) as $field ) {
				if (
					'group' === $field['type']
					&& isset( $field['post_type_map'] )
					&& post_type_exists( $field['post_type_map'] )
				) {

					$this->ids[ $cmb->object_type() ][ $cmb->cmb_id ][ $field['id'] ] = $field['post_type_map'];

					// Let's be sure not to stomp out any existing after_group parameter.
					if ( isset( $field['after_group'] ) ) {
						// Store it to another field property
						$cmb->update_field_property( $field['id'], 'cmb2_group_post_map_after_group', $field['after_group'] );
					}

					// Hook in our JS registration using after_group group field parameter.
					$cmb->update_field_property( $field['id'], 'after_group', array( $this, 'add_js' ) );
					$cmb->update_field_property( $field['id'], 'original_post_types', $cmb->prop( 'object_types' ) );

					// Add a hidden ID field to the group
					$cmb->add_group_field( $field['id'], array(
						'id'   => 'ID',
						'type' => 'hidden',
					) );

					add_filter( "cmb2_override_{$field['id']}_meta_save", array( $this, 'override_save' ), 10, 4 );
					add_filter( "cmb2_override_{$field['id']}_meta_remove", array( $this, 'override_remove' ), 10, 4 );
					add_filter( "cmb2_override_{$field['id']}_meta_value", array( $this, 'override_get' ), 10, 4 );

				}
			}
		}
	}

	public function add_js( $args, $field ) {
		// Check for stored 'after_group' parameter, and run that now.
		if ( $field->args( 'cmb2_group_post_map_after_group' ) ) {
			$field->peform_param_callback( 'cmb2_group_post_map_after_group' );
		}

		add_filter( 'cmb2_script_dependencies', array( $this, 'register_cmb2_group_post_map_js' ) );
	}

	public function register_cmb2_group_post_map_js( $dependencies ) {
		$dependencies['cmb2_group_post_map'] = 'cmb2_group_post_map';
		// wp_register_script( 'cmb2_group_post_map', $src, array( 'jquery' ), self::VERSION, 1 );
		return $dependencies;
	}

	public function override_save( $override, $a, $args, $field_group ) {
		require_once CMB2_GROUP_POST_MAP_DIR . 'lib/set.php';
		$setter = new CMB2_Group_Post_Map_Set( $field_group, $a['value'] );
		$setter->save();

		return true; // this shortcuts CMB2 save
	}

	public function override_get( $nooverride, $object_id, $a, $field ) {
		remove_filter( "cmb2_override_{$a['field_id']}_meta_value", array( $this, 'override_get' ), 10, 4 );

		require_once CMB2_GROUP_POST_MAP_DIR . 'lib/get.php';
		$value = CMB2_Group_Post_Map_Get::get_value( $field );

		return $value;
	}

	public function map_to_original_post( $posts, $original_post_id, $field ) {
		$post_ids = array();
		foreach ( $posts as $post_id ) {
			if ( ! is_wp_error( $post_id ) ) {
				$post_ids[] = $post_id;
			}
		}

		$meta_key = $field->id();

		update_post_meta( $original_post_id, $meta_key, $post_ids );
	}

	public function override_remove( $override, $a, $args, $field ) {
		throw new Exception( 'hey, fix this: '. __METHOD__ );
		return true; // this shortcuts CMB2 remove
	}

}
CMB2_Group_Post_Map::get_instance();
