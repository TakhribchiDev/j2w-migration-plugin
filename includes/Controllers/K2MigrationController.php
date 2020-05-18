<?php
/**
 * @package  J2WMigration
 */

namespace Includes\Controllers;

use Exception;
use MongoDB\Driver\Exception\ExecutionTimeoutException;

/**
 *
 */
class K2MigrationController extends BaseController
{
    public $wpdb_src;

    public function register()
    {
        // Set the source DB
        $this->setSourceDB();

        // Ajax needed hooks
        add_action('admin_enqueue_scripts', function() {
            wp_enqueue_script('k2-migrate-ajax-script', $this->plugin_url . '/assets/k2_migrate_ajax.js');

            $category_ids = null;
            $k2_posts_ids = null;

            if (isset($this->wpdb_src)) {
            	$category_ids = $this->wpdb_src->get_col('select id from nagsh_k2_categories where parent = 10');

	            foreach ($category_ids as $category_id) {

		            $sub_category_ids = $this->wpdb_src->get_col( 'select * from nagsh_k2_categories where parent = ' . strval( $category_id ) );

		            if ( $sub_category_ids ) {
			            $category_ids = array_merge( $category_ids, $sub_category_ids );
		            }
	            }

            	$ids_string = implode(',', $category_ids);
            	$ids_string = '('. $ids_string . ')';
                $k2_posts_ids = $this->wpdb_src->get_col('select id from nagsh_k2_items where catid in ' . $ids_string);
            }

            wp_localize_script('k2-migrate-ajax-script', 'k2_ajax_obj',
                [
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'posts_ids' => $k2_posts_ids,
	                'category_ids' => $category_ids
                ] );
        });

        // Hook migrate function to wp_ajax_migrate action
        add_action('wp_ajax_k2_migrate', [$this, 'migrate']);
    }

    public function setSourceDB() {
        $db_options = get_option('j2w_db');

        if (!$db_options) return;

        $db_port = isset($db_options['db_port']) ? ':' . $db_options['db_port'] : '';

        $this->wpdb_src = new \wpdb($db_options['db_user'], $db_options['db_pass'], $db_options['db_name'], $db_options['db_host'] . $db_port);
    }

    // Migration specific functions
    public function migrate()
    {
        try {
            if (isset($_POST['k2_empty_posts_categories'])) {
                $this->truncateTermsAndTermTaxonomy();
                $this->truncatePostsAndPostmeta();
            }

            if (isset($_POST['k2_migrate_categories'])) {
                $this->migrateCategories();
            }

            if (isset($_POST['k2_migrate_posts'])) {
                $this->migratePosts($_POST['posts_ids']);
            }

	        if (isset($_POST['k2_migrate_extra_fields'])) {
		        $this->migrateExtraFields();
	        }

        } catch (Exception $e) {
            $message = $e->getMessage();
        } finally {

            // Send success in response
            echo isset($message) ? $message : "Success!";
            wp_die();
        }
    }

	/**
	 * This function returns category ids related to nagsh downloads
	 */
	public function getCategoryIds() {
		$category_ids = array();

		if (isset($this->wpdb_src)) {
			$category_ids = $this->wpdb_src->get_col( 'select id from nagsh_k2_categories where parent = 10' );

			foreach ( $category_ids as $category_id ) {

				$sub_category_ids = $this->wpdb_src->get_col( 'select * from nagsh_k2_categories where parent = ' . strval( $category_id ) );

				if ( $sub_category_ids ) {
					$category_ids = array_merge( $category_ids, $sub_category_ids );
				}
			}
		}

		return $category_ids;
    }

    public function migrateCategories()
    {
        $categories_list = array();

        if (isset($this->wpdb_src)) {
            // Query categories from the source database and save them in $categories_list array
            $categories_list = $this->wpdb_src->get_results('select * from  nagsh_k2_categories where parent = 10');
        }

	    foreach ($categories_list as $category) {

		    $sub_categories = $this->wpdb_src->get_results( 'select * from nagsh_k2_categories where parent = ' . strval( $category->id ) );

		    if ( $sub_categories ) {
		    	$categories_list = array_merge( $categories_list, $sub_categories );
		    }
	    }

        foreach ($categories_list as $category) {
	        // Find parent of each category
	        $old_parent = null;
	        $new_parent = null;
	        if ($category->parent) {
		        // Get category parent from source database
		        $filtered_array = array_filter($categories_list, function ($result) use ($category) {
			        return $result->id == $category->parent;
		        });
		        $old_parent = array_pop($filtered_array);
		        // Find category term new parent from wordpress database
		        $new_parent = get_term_by('name', $old_parent ? $old_parent->name : '', 'product_cat');
	        }

            // Insert new term to the database
            wp_insert_term($category->name, 'product_cat',
                [
                    'slug' => $category->alias,
	                'description' => strip_tags($category->description),
	                'parent' => ($new_parent ? $new_parent->term_id : 0)
                ]
            );

        }
    }

    public function truncateTermsAndTermTaxonomy() {
        global $wpdb;

        $wpdb->query('TRUNCATE `wp_terms`;');
        $wpdb->query('TRUNCATE `wp_term_taxonomy`;');
    }

    public function truncatePostsAndPostmeta() {
        global $wpdb;

        $wpdb->query('TRUNCATE `wp_posts`;');
        $wpdb->query('TRUNCATE `wp_postmeta`;');
        $wpdb->query('TRUNCATE `wp_term_relationships`;');
    }

    public function migratePosts($posts_ids_json)
    {
    	$posts_ids = array_map('intval', json_decode(stripslashes($posts_ids_json)));

		$first_id = $posts_ids[0];
		$last_id = $posts_ids[sizeof($posts_ids) - 1];

        if (!isset($this->wpdb_src)) return;

        // Query posts from the source database and save them in $k2_posts array
        $k2_posts = $this->wpdb_src->get_results("select k2_download.*,  (nagsh_k2_rating.rating_sum div nagsh_k2_rating.rating_count) as rating, item_price, special_price , count( nagsh_k2store_orderitems.order_id ) as sales
            from ( select * from  nagsh_k2_items where id >= $first_id and id <= $last_id ) as k2_download
            left join nagsh_k2_rating on k2_download.id = nagsh_k2_rating.itemID
            left join nagsh_k2store_products on k2_download.id = nagsh_k2store_products.product_id
            left join nagsh_k2store_orderitems on k2_download.id = nagsh_k2store_orderitems.product_id group by k2_download.id");

        foreach ( $posts_ids as $id ) {

        	// Find the post with the id in $posts_ids from db fetched posts
            $filtered_posts = array_filter( $k2_posts, function ($post) use ($id) {
                return $post->id == $id;
            } );
            $k2_post = array_pop( $filtered_posts );

			$post_id = wp_insert_post([
				'post_author' => get_current_user_id(),
				'post_date' => current_time('mysql'),
				'post_date_gmt' => current_time('mysql', 1),
				'post_content' => '<p style=\"text-align:right\">' . preg_replace('/{k2storecart\s+\d+}/i', '', strip_tags($k2_post->fulltext) ) . '</p>' . '[nagshdl-show-acf-fields]',
				'post_title' => $k2_post->title,
				'post_excerpt' => preg_replace('/{k2storecart\s+\d+}/i', '', strip_tags($k2_post->introtext) ),
				'post_status' => 'publish',
				'comment_status' => 'open',
				'ping_status' => 'open',
				'post_password' => '',
				'post_name' => $k2_post->alias,
				'to_ping' => '',
				'pinged' => '',
				'post_modified' => current_time('mysql'),
				'post_modified_gmt' => current_time('mysql', 1),
				'post_content_filtered' => '',
				'post_parent' => 0,
				'guid' => '',
				'menu_order' => $k2_post->ordering,
				'post_type' => 'product',
				'post_mime_type' => '',
			]);

	        // Generate post meta for this post
	        $download_links = $this->getDownloadLinks( $k2_post );
			$thumbnail_id = $this->insertPostImage($post_id, $k2_post);
			$product_image_gallery = $this->allocateImageGallery($post_id, $k2_post);

	        $post_meta = [
		        '_edit_last' => get_current_user_id(),
		        'slide_template' => 'default',
		        '_thumbnail_id' => $thumbnail_id,
		        '_regular_price' => ( is_null( $k2_post->item_price) ? '0' : intval( $k2_post->item_price ) ),
		        'total_sales' => $k2_post->sales,
		        '_tax_status' => 'taxable',
		        '_tax_class' => '',
		        '_manage_stock' => 'no',
		        '_backorders' => 'no',
		        '_sold_individually' => 'no',
		        '_virtual' => 'yes',
		        '_downloadable' => 'yes',
		        '_download_limit' => -1,
		        '_download_expiry' => -1,
		        '_stock' => '',
		        '_stock_status' => 'instock',
		        '_wc_average_rating' => $k2_post->rating,
		        '_wc_review_count' => 0,
		        '_downloadable_files' => $download_links,
		        '_product_version' => '1.0',
		        '_price' => ( is_null( $k2_post->special_price) ? '0' : intval( $k2_post->special_price ) ),
		        '_product_image_gallery' => $product_image_gallery
	        ];
	        $this->insertPostMeta($post_id, $post_meta);

            // Advanced Custom Fields Values as post meta
            $acf_meta = $this->getCustomFieldsValues( $k2_post->extra_fields );
			$this->insertPostMeta($post_id, $acf_meta );

			// Set post's category
            $k2_category_alias = $this->wpdb_src->get_var('select alias from nagsh_k2_categories where id =' . strval($k2_post->catid));
            $term = get_term_by('slug', $k2_category_alias,'product_cat');
            wp_set_post_terms($post_id, $term->term_id, 'product_cat');

            // Set post tags
            $post_tags = $this->wpdb_src->get_col( "select name
                                                    from  nagsh_k2_tags_xref inner join nagsh_k2_tags on nagsh_k2_tags_xref.tagID = nagsh_k2_tags.id
                                                    where itemID = $k2_post->id" );
            wp_set_post_terms($post_id, $post_tags, 'product_tag');

        } // End of foreach
    } // End of function migratePosts

	/**
	 * This function migrates k2 extra fields to wordpress as ACF(Advanced Custom Fields) fields
	 * This is a test version for http://download.nagsh.ir
	 *
	 */
	public function migrateExtraFields() {
		// Get category ids from db
		$category_ids = $this->getCategoryIds();
		$ids_string = $this->array2String($category_ids);

		// Get extra fields groups ids of categories from db
		$extra_fields_groups_ids = $this->wpdb_src->get_col('select distinct extraFieldsGroup from nagsh_k2_categories where id in ' . $ids_string);
		$efg_ids_string = $this->array2String($extra_fields_groups_ids);

		// Get extra fields groups from db
		$extra_fields_groups = $this->wpdb_src->get_results('select * from nagsh_k2_extra_fields_groups where id in ' . $efg_ids_string);

		foreach ($extra_fields_groups as $efg) {
			// Insert extra field group to db
			$efg_id = $this->insertExtraFieldGroup($efg);

			// Get extra fields related to each group
			$extra_fields = $this->wpdb_src->get_results( 'select * from nagsh_k2_extra_fields where nagsh_k2_extra_fields.group  = ' . strval($efg->id) );

			// Insert each group's extra fields
			foreach ( $extra_fields as $ef ) {
				$this->insertExtraField($ef, $efg_id, $efg->name);
			}
		}
	}

	/**
	 * This function is used to insert a k2 extra fields group
	 * as an advanced custom fields group
	 *
	 * @param object $efg
	 *
	 * @return int|\WP_Error
	 */
	public function insertExtraFieldGroup( $efg ) {
		$acf_group_post_id = wp_insert_post([
			'post_author' => get_current_user_id(),
			'post_date' => current_time('mysql'),
			'post_date_gmt' => current_time('mysql', 1),
			'post_content' => maybe_serialize([
				'location' => [[[
					'param' => 'post_type',
					'operator' => '==',
					'value' => 'product'
				]]],
				'position' => 'side',
				'style' => 'default',
				'label_placement' => 'left',
				'instruction_placement' => 'label',
				'hide_on_screen' => '',
				'description' => '' ]),
			'post_title' => $efg->name,
			'post_excerpt' => sanitize_title($efg->name),
			'post_status' => 'publish',
			'comment_status' => 'closed',
			'ping_status' => 'closed',
			'post_password' => '',
			'post_name' => uniqid('group_'),
			'to_ping' => '',
			'pinged' => '',
			'post_modified' => current_time('mysql'),
			'post_modified_gmt' => current_time('mysql', 1),
			'post_content_filtered' => '',
			'post_parent' => 0,
			'guid' => '',
			'menu_order' => 0,
			'post_type' => 'acf-field-group',
			'post_mime_type' => '',
			'meta_input' => [
				'_edit_last' => get_current_user_id(),
				'slide_template' => 'default',
			]
		]);

		return $acf_group_post_id;
	}

    /**
     * This function is used to insert a k2 extra field
     * as an advanced custom field
     *
     * @param $ef object -- Extra field object
     * @param $efg_id integer -- Extra field group id
     * @param $efg_name
     * @return int|\WP_Error
     */
	public function insertExtraField( $ef, $efg_id, $efg_name ) {

		$ef_values = json_decode($ef->value);
		$post_content = null;

		// Determine advanced custom fields type
        if ( $ef->type == 'select' ) {
            $choices = array();

            foreach ( $ef_values as $value ) {
                $choices[$value->value] = $value->name;
            }

            $post_content = serialize( [
                'type'              => 'select',
                'instructions'      => '',
                'required'          => 0,
                'conditional_logic' => 0,
                'wrapper'           =>
                    [
                        'width' => '',
                        'class' => '',
                        'id'    => '',
                    ],
                'choices'           => $choices,
                'default_value'     =>
                    [
                        0 => $ef_values[0]->name,
                    ],
                'allow_null' => 0,
                'multiple' => 0,
                'ui' => 0,
                'return_format' => 'value',
                'ajax' => 0,
                'placeholder' => '',
            ] );

        } else if ( $ef->type == 'multipleSelect' ) {
			$choices = array();

			foreach ( $ef_values as $value ) {
				$choices[$value->value] = $value->name;
			}

			$post_content = serialize( [
				'type'              => 'checkbox',
				'instructions'      => '',
				'required'          => 0,
				'conditional_logic' => 0,
				'wrapper'           =>
					[
						'width' => '',
						'class' => '',
						'id'    => '',
					],
				'choices'           => $choices,
				'allow_custom'      => 0,
				'default_value'     =>
					[
						0 => $ef_values[0]->name,
					],
				'layout'            => 'vertical',
				'toggle'            => 0,
				'return_format'     => 'value',
				'save_custom'       => 0,
			] );

		} elseif ( $ef->type == 'textfield' ) {
			$post_content = serialize( [
					'type' => 'text',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' =>
						[
							'width' => '',
							'class' => '',
							'id' => '',
						],
					'default_value' => $ef_values[0]->value,
					'placeholder' => '',
					'prepend' => '',
					'append' => '',
					'maxlength' => '',
				] );
		}

		$acf_field_post_id = wp_insert_post([
			'post_author' => get_current_user_id(),
			'post_date' => current_time('mysql'),
			'post_date_gmt' => current_time('mysql', 1),
			'post_content' => $post_content,
			'post_title' => $ef->name,
			'post_excerpt' => preg_replace("/[\s]/", "_", $efg_name . '_' . $ef->name),
			'post_status' => 'publish',
			'comment_status' => 'closed',
			'ping_status' => 'closed',
			'post_password' => '',
			'post_name' => uniqid('field_'),
			'to_ping' => '',
			'pinged' => '',
			'post_modified' => current_time('mysql'),
			'post_modified_gmt' => current_time('mysql', 1),
			'post_content_filtered' => '',
			'post_parent' => $efg_id,
			'guid' => '',
			'menu_order' => $ef->ordering,
			'post_type' => 'acf-field',
			'post_mime_type' => ''
		]);

		return $acf_field_post_id;
	}

	public function getCustomFieldsValues( $extra_fields ) {
        $acf_map = [];

        if ( empty( $extra_fields ) ) return $acf_map;

        // Decode k2 item extra fields mapping
	    $efmap = json_decode( $extra_fields );

	    // Get extra fields list from source db
	    $src_efs = $this->wpdb_src->get_results( 'select nagsh_k2_extra_fields.id as id, nagsh_k2_extra_fields.name as name, value, type, nagsh_k2_extra_fields_groups.name as \'group\' from nagsh_k2_extra_fields inner join nagsh_k2_extra_fields_groups on nagsh_k2_extra_fields.group = nagsh_k2_extra_fields_groups.id order by nagsh_k2_extra_fields.id asc' );

	    foreach ( $efmap as $pair ) {
	         $filtered_efs = array_filter( $src_efs, function ( $ef ) use ( $pair ) {
                return $ef->id == $pair->id;
            } );
            $src_ef = array_pop( $filtered_efs );

            // Initialize variables
            if( $src_ef->type == 'select' or $src_ef->type == 'multipleSelect' ) {
                $src_ef_val = [];
                $acf_value = [];
            }
            else if ( $src_ef->type == 'textfield' ) {
                $src_ef_val = '';
                $acf_value = '';
            }

            if ( $src_ef->type == 'select' ) {
                $src_ef_vals = json_decode( $src_ef->value );
                $src_ef_val = $src_ef_vals[ intval( $pair->value ) - 1 ]->value;
                $acf_value[] =  $src_ef_val;
            }
            else if ( $src_ef->type == 'multipleSelect' ) {
                $src_ef_vals = json_decode( $src_ef->value );
                foreach ( $pair->value as $val ) {
                    $src_ef_val[] = $src_ef_vals[ intval( $val ) - 1 ]->value;
                }

                $acf_value = $src_ef_val;
            }
            else if ( $src_ef->type == 'textfield' ) {
                $src_ef_val = $pair->value;

                $acf_value = $src_ef_val;
            }

            global $wpdb;
            $result = $wpdb->get_col( 'Select post_name from wp_posts where post_type = \'acf-field\' and post_excerpt = \'' . preg_replace("/[\s]/", "_", $src_ef->group . '_' . $src_ef->name) . '\'' );
            $acf_name = $result[0];
            $meta_key = preg_replace("/[\s]/", "_", $src_ef->group . '_' . $src_ef->name);

            $acf_map[ '_' . $meta_key ] = $acf_name;
            $acf_map[ $meta_key ] = $acf_value;
        }

	    return $acf_map;
    }

	public function insertPostImage($post_id, $k2_post) {

        $image_info = array();

        try {
            $image_info = $this->getImageInfo( dirname( ABSPATH )  . '/media/k2/items/cache/' ,  md5('Image' . $k2_post->id) . '_XL');
        }
        catch ( Exception $e ) {
            print_r ( 'Error: ' . $e->getMessage() );
        }
        finally {
            if ( ! $image_info ) return '';
        }

		$image_sizes = $this->generateImageSizes( $image_info['dirname'] . '/' . $image_info['basename']);

		$attachment_id = wp_insert_post([
			'post_author' => get_current_user_id(),
			'post_date' => current_time('mysql'),
			'post_date_gmt' => current_time('mysql', 1),
			'post_content' => $k2_post->title,
			'post_title' => $image_info['filename'],
			'post_excerpt' => $k2_post->title,
			'post_status' => 'inherit',
			'comment_status' => 'open',
			'ping_status' => 'closed',
			'post_password' => '',
			'post_name' => urlencode($image_info['filename']),
			'to_ping' => '',
			'pinged' => '',
			'post_modified' => current_time('mysql'),
			'post_modified_gmt' => current_time('mysql', 1),
			'post_content_filtered' => '',
			'post_parent' => $post_id,
			'guid' => wp_get_upload_dir()['url'] . $image_sizes['original']['file'],
			'menu_order' => 0,
			'post_type' => 'attachment',
			'post_mime_type' => $image_sizes['original']['mime-type'],
			'comment_count' => 0,
			'meta_input' => [
				'_wp_attached_file' => ltrim( wp_get_upload_dir()['subdir'] , '/' ) . '/' . $image_sizes['original']['file'],
				'_wp_attachment_metadata' => [
					'width' => $image_sizes['original']['width'],
					'height' => $image_sizes['original']['height'],
					'file' => ltrim( wp_get_upload_dir()['subdir'] , '/' ) . '/' . $image_sizes['original']['file'],
					'sizes' => [
						'thumbnail' => [
							'file' => $image_sizes['150x150']['file'],
							'width' => $image_sizes['150x150']['width'],
							'height' => $image_sizes['150x150']['height'],
							'mime-type' => $image_sizes['150x150']['mime-type'],
						],
						'post-thumbnail' => [
							'file' => $image_sizes['300x300_uncropped']['file'],
							'width' => $image_sizes['300x300_uncropped']['width'],
							'height' => $image_sizes['300x300_uncropped']['height'],
							'mime-type' => $image_sizes['300x300_uncropped']['mime-type'],
						],
						'woocommerce_thumbnail' => [
							'file' => $image_sizes['300x300']['file'],
							'width' => $image_sizes['300x300']['width'],
							'height' => $image_sizes['300x300']['height'],
							'mime-type' => $image_sizes['300x300']['mime-type'],
							'uncropped' => 'false'
						],
						'woocommerce_gallery_thumbnail' => [
							'file' => $image_sizes['300x300']['file'],
							'width' => $image_sizes['300x300']['width'],
							'height' => $image_sizes['300x300']['height'],
							'mime-type' => $image_sizes['300x300']['mime-type']
						],
						'shop_catalog' => [
							'file' => $image_sizes['300x300']['file'],
							'width' => $image_sizes['300x300']['width'],
							'height' => $image_sizes['300x300']['height'],
							'mime-type' => $image_sizes['300x300']['mime-type']
						],
					],
					'image-meta' => [
						'aperture' => '0',
						'credit' => '',
						'camera' => '',
						'caption' => '',
						'created_timestamp' => '',
						'copyright' => '',
						'focal_length' => '0',
						'iso' => '0',
						'shutter_speed' => '0',
						'title' => '',
						'orientation' => '0',
						'keywords' => []
					]
				],
				'_wp_attachment_image_alt' => $k2_post->title,
			]
		]);

		return $attachment_id;
	}

	public function generateImageSizes($image_path) {
	    $resizes = array();

		// Save Image related filesystem information
		$image_info = pathinfo($image_path);

		if ( ! $image_info ) return $resizes;

		// Copy image to its new location
		if (getimagesize($image_path)) {
			copy($image_path, wp_get_upload_dir()['path'] . '/' . $image_info['basename']);
		}

		// Get image new location info
		$image_new_path = wp_get_upload_dir()['path'] . '/' . $image_info['basename'];
		$image_info = pathinfo($image_new_path);

		// Original image size information
		$original_size = getimagesize($image_new_path);
		$resizes['original'] = [
			'path' => $image_new_path,
			'file' => $image_info['basename'],
			'width' => $original_size[0],
			'height' => $original_size[1],
			'mime-type'=> $original_size['mime']
		];

		// Sizes needed for each image
		$sizes = [
			'300x300' => [
				'width' => 300,
				'height' => 300,
				'crop' => true
			],
			'150x150' => [
				'width' => 150,
				'height' => 150,
				'crop' => true
			],
			'300x300_uncropped' => [
				'width' => 300,
				'height' => 300,
				'crop' => false
			],
		];

		foreach ($sizes as $key => $value) {
			// Generate multiple sizes in $sizes array
			// Get an image editor
			$image_editor = wp_get_image_editor($image_new_path);
			$image_editor->resize($value['width'], $value['height'], $value['crop']);

			$resizes[$key] = $image_editor->save();
			$image_editor = null;
		}

		gc_collect_cycles(); // Free up memory

		return $resizes;
	}

	public function getImageInfo($dirname, $filename) {
		$file_path = $dirname . $filename;

		$extensions = array('jpg', 'png', 'jpeg');

		foreach ($extensions as $ext) {
			if (getimagesize($file_path . '.' . $ext)) {
				$file_info = pathinfo($file_path . '.' . $ext);

				return $file_info;
			}
		}

		return null;
	}

	public function insertPostImageGallery($post_id, $k2_post, $image_path) {
	    $image_info = array();

	    try {
            $image_info = pathinfo(dirname( ABSPATH )  . '/' .  $image_path);
        }
        catch ( Exception $e ) {
	        print_r ( 'Error: ' . $e->getMessage() );
        }
        finally {
            if ( ! $image_info ) return '';
        }

		$image_sizes = $this->generateImageSizes( $image_info['dirname'] . '/' . $image_info['basename']);

		$attachment_id = wp_insert_post([
			'post_author' => get_current_user_id(),
			'post_date' => current_time('mysql'),
			'post_date_gmt' => current_time('mysql', 1),
			'post_content' => $k2_post->title,
			'post_title' => $image_info['filename'],
			'post_excerpt' => $k2_post->title,
			'post_status' => 'inherit',
			'comment_status' => 'open',
			'ping_status' => 'closed',
			'post_password' => '',
			'post_name' => urlencode($image_info['filename']),
			'to_ping' => '',
			'pinged' => '',
			'post_modified' => current_time('mysql'),
			'post_modified_gmt' => current_time('mysql', 1),
			'post_content_filtered' => '',
			'post_parent' => $post_id,
			'guid' => wp_get_upload_dir()['url'] . $image_sizes['original']['file'],
			'menu_order' => 0,
			'post_type' => 'attachment',
			'post_mime_type' => $image_sizes['original']['mime-type'],
			'comment_count' => 0,
			'meta_input' => [
				'_wp_attached_file' => ltrim( wp_get_upload_dir()['subdir'] , '/' ) . '/' . $image_sizes['original']['file'],
				'_wp_attachment_metadata' => [
					'width' => $image_sizes['original']['width'],
					'height' => $image_sizes['original']['height'],
					'file' => ltrim( wp_get_upload_dir()['subdir'] , '/' ) . '/' . $image_sizes['original']['file'],
					'sizes' => [
						'thumbnail' => [
							'file' => $image_sizes['150x150']['file'],
							'width' => $image_sizes['150x150']['width'],
							'height' => $image_sizes['150x150']['height'],
							'mime-type' => $image_sizes['150x150']['mime-type'],
						],
						'post-thumbnail' => [
							'file' => $image_sizes['300x300_uncropped']['file'],
							'width' => $image_sizes['300x300_uncropped']['width'],
							'height' => $image_sizes['300x300_uncropped']['height'],
							'mime-type' => $image_sizes['300x300_uncropped']['mime-type'],
						],
						'woocommerce_thumbnail' => [
							'file' => $image_sizes['300x300']['file'],
							'width' => $image_sizes['300x300']['width'],
							'height' => $image_sizes['300x300']['height'],
							'mime-type' => $image_sizes['300x300']['mime-type'],
							'uncropped' => 'false'
						],
						'woocommerce_gallery_thumbnail' => [
							'file' => $image_sizes['300x300']['file'],
							'width' => $image_sizes['300x300']['width'],
							'height' => $image_sizes['300x300']['height'],
							'mime-type' => $image_sizes['300x300']['mime-type']
						],
						'shop_catalog' => [
							'file' => $image_sizes['300x300']['file'],
							'width' => $image_sizes['300x300']['width'],
							'height' => $image_sizes['300x300']['height'],
							'mime-type' => $image_sizes['300x300']['mime-type']
						],
					],
					'image-meta' => [
						'aperture' => '0',
						'credit' => '',
						'camera' => '',
						'caption' => '',
						'created_timestamp' => '',
						'copyright' => '',
						'focal_length' => '0',
						'iso' => '0',
						'shutter_speed' => '0',
						'title' => '',
						'orientation' => '0',
						'keywords' => []
					]
				],
				'_wp_attachment_image_alt' => $k2_post->title,
			]
		]);

		return $attachment_id;
	}

	public function allocateImageGallery($post_id, $k2_post) {
		// Get images from k2 post body
		$dom = new \DOMDocument();
		$dom->loadHTML($k2_post->introtext . $k2_post->fulltext);

		$imgs = $dom->getElementsByTagName('img');

		$img_srcs = array();
		for ( $i = 0; $i < $imgs->length; $i++ ) {
			$img = $imgs->item($i);
			$img_src = $img->getAttribute('src');
			$img_srcs[$i] = preg_replace( '/%20/', ' ', $img_src );
		}

		$attachments_ids = array();

		foreach ( $img_srcs as $img ) {
			$attachment_id = $this->insertPostImageGallery($post_id, $k2_post, $img);
			$attachments_ids[] = $attachment_id;
		}

		if ( ! $attachments_ids ) return '';

		$image_gallery_string = implode( ',', $attachments_ids );


		return $image_gallery_string;
	}

	/**
	 * This function is used to convert an array of integers
	 * into a comma separated string surrounded with parenthesis
	 * @param array $array
	 *
	 * @return string
	 */
	public function array2String(array $array) {
		$string = implode( "','", $array );
		$string = "('" . $string . "')";

		return $string;
	}

	/**
	 * This function is used to generate download id for
	 * woocommerce download file
	 *
	 * @return string
	 */
	public function generate_woocommerce_download_id() {
		$uuid4 = wp_generate_uuid4();
		$download_id = is_scalar( $uuid4 ) ? sanitize_text_field( $uuid4 ) : $uuid4;

		return $download_id;
	}

    /**
     * This function is used to generate Download Links
     * from src DB
     *
     * @return array
     */
    public function getDownloadLinks( $k2_post ) {
        $results = $this->wpdb_src->get_results( "select filename, titleAttribute from nagsh_k2_attachments where itemID = $k2_post->id" );

        $download_links = array();
        foreach( $results as $result ) {
            $download_id = $this->generate_woocommerce_download_id();
            $download_links[ $download_id ] = [
                'id' => $download_id,
                'name' => $result->titleAttribute,
                'file' => 'https://nagsh.ir/nagsh-dl/' . $result->filename
            ];
        }

        return $download_links;
    }

	/**
	 * This function gets an array as post metadata
	 * and inserts it's key/value pairs as post meta
	 *
	 * @param $post_id
	 * @param array $metadata
	 */
	public function insertPostMeta($post_id, array $metadata) {
        foreach ($metadata as $key => $value) {
            add_post_meta($post_id, $key, $value);
        }
    }
}