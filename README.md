CMB2 Group Map
======================

CMB2 addon which allows you to use CMB2 group fields to manage custom post type entries. You will need to run `composer install` in this plugin's directory to get the required dependencies.

A few details:

* To specify the post type destination, add a `'post_type_map'` parameter to your group field parameters.
* To set the default WordPress post fields (e.g. the post content, title, etc), you need to set the `'id'` parameter to the same value as the `wp_posts` database column name. So for the content,  the `'id'` parameter would be `'post_content'`, or for the title, `'post_title'`.
* To set taxonomy terms for the connected post-type posts, use one of the `taxonomy_*` field types.

## Example

You need to include this library:
```php
require_once( 'cmb2-group-map/cmb2-group-map.php' );
```

Then setup a CMB2 metabox with a grouped field:
```php
// Standard CMB2 registration.
$cmb = new_cmb2_box( array(
	'id'           => 'footnotes',
	'title'        => 'Footnotes',
	'object_types' => array( 'post' ),
) );

// This is our group field registration. It's mostly standard.
$group_field_id = $cmb->add_field( array(
	'id'            => 'footnotes_ids',
	'type'          => 'group',
	// This is a custom property, and should specify
	// the destination post-type.
	'post_type_map' => 'footnotes',
	'description'   => 'Manage/add connected footnotes',
	'options'       => array(
		'group_title'   => 'Footnote {#}',
		'add_button'    => 'Add New Footnote',
		'remove_button' => 'Remove Footnote',
		'sortable'      => true,
	),
) );

// by using 'post_title' as the id, this will be the
// value stored to the destination post-type's title field.
$cmb->add_group_field( $group_field_id, array(
	'name' => 'Title',
	'id'   => 'post_title',
	'type' => 'text',
) );

// by using 'post_content' as the id, this will be the
// value stored to the destination post-type's content field.
$cmb->add_group_field( $group_field_id, array(
	'name' => 'Content',
	'id'   => 'post_content',
	'type' => 'textarea_small',
) );

// This field will be stored as post-meta against
// the destination post-type.
$cmb->add_group_field( $group_field_id, array(
	'name' => 'Color meta value',
	'id'   => 'footnote_color',
	'type' => 'colorpicker',
) );

// This field will be stored as category terms
// against the destination post-type.
$cmb->add_group_field( $group_field_id, array(
	'name'     => 'Categories',
	'id'       => 'category',
	'taxonomy' => 'category',
	'type'     => 'taxonomy_multicheck',
) );
```
