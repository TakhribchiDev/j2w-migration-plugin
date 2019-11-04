<?php
/**
 * @package  J2WMigration
 */

namespace Includes\Controllers;

use Exception;
use Includes\Api\SettingsApi;
use Includes\Controllers\BaseController;
use Includes\Api\Callbacks\AdminCallbacks;
use Includes\Api\Callbacks\MigrationCallbacks;

/**
 *
 */
class PhocagalleryMigrationController extends BaseController
{
    public $wpdb_src;

    public function register()
    {
        // Set the source DB
        $this->setSourceDB();

        // Ajax needed hooks
        add_action('admin_enqueue_scripts', function() {
            wp_enqueue_script('migrate-ajax-script', $this->plugin_url . '/assets/phoca_migrate_ajax.js');

            $phoca_post_count = 0;
            if (isset($this->wpdb_src)) {
                $phoca_post_count = $this->wpdb_src->get_var('select max(id) from nagsh_phocagallery');
            }

            wp_localize_script('migrate-ajax-script', 'phoca_ajax_obj',
                [
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'post_count' => intval($phoca_post_count)
                ] );
        });

        // Hook migrate function to wp_ajax_migrate action
        add_action('wp_ajax_phoca_migrate', [$this, 'migrate']);
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

            if (isset($_POST['phoca_empty_posts_categories'])) {
                $this->truncateTermsAndTermTaxonomy();
                $this->truncatePostsAndPostmeta();
            }

            if (isset($_POST['phoca_migrate_categories'])) {
                $this->migrateCategories();
            }

            if (isset($_POST['phoca_migrate_posts'])) {
            	if (isset($_POST['rollback_posts'])) {
            		$this->rollbackMigratedPostsTo($_POST['first_id']);
	            }
                $this->migratePosts($_POST['first_id'], $_POST['last_id']);
            }

            if (isset($_POST['phoca_delete_thumbs_resized'])) {
                $this->deleteThumbsAndResized();
            }

            if (isset($_POST['phoca_fix_categories_parents'])) {
                $this->fixCategoriesParents();
            }

        } catch (Exception $e) {
            $message = $e->getMessage();
        } finally {

            // Send success in response
            echo isset($message) ? $message : "Success!";
            wp_die();
        }
    }

    public function migrateCategories()
    {
        $categories_list = array();

        if (isset($this->wpdb_src)) {
            // Query categories from the source database and save them in $categories_list array
            $categories_list = $this->wpdb_src->get_results('select * from  nagsh_phocagallery_categories order by id asc');
        }

        foreach ($categories_list as $category) {

            // Find parent of each category
            $old_parent = null;
            $new_parent = null;
            if ($category->parent_id) {
                // Get category parent from source database
                $filtered_array = array_filter($categories_list, function ($result) use ($category) {
                    return $result->id == $category->parent_id;
                });
                $old_parent = array_pop($filtered_array);
                // Find category term new parent from wordpress database
                $new_parent = get_term_by('name', $old_parent ? $old_parent->title : '', 'category');
            }

            // Insert new term to the database
            wp_insert_term($category->title, 'category',
                [
                    'slug' => $category->alias,
                    'description' => strip_tags($category->description),
                    'parent' => ($new_parent ? $new_parent->term_id : 0)
                ]
            );
        }

        global $wpdb;
	    $terms = $wpdb->get_col('select distinct term_taxonomy_id from wp_term_relationships');
	    // Update term counts
	    wp_update_term_count_now($terms, 'category');
    }

    public function fixCategoriesParents() {
        $last_category_id = $this->wpdb_src->get_var('select max(id) from nagsh_phocagallery_categories');

        for ($i = 1; $i <= $last_category_id; $i++) {
            $category = $this->wpdb_src->get_row('select * from  nagsh_phocagallery_categories where id =' . strval($i));

            if (!$category) continue;

            $this->wpdb_src->update('nagsh_phocagallery_categories', [
                'alias' => strval($category->id) . '-' . urldecode($category->alias),
            ] , ['id' => $i]);
        }

        echo 'Categories Fixed!';
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

    public function migratePosts($first_id, $last_id)
    {
        if (!isset($this->wpdb_src)) return;

        // Query posts from the source database and save them in $categories_list array
        $phoca_posts_num = intval($this->wpdb_src->get_var('select max(id) from nagsh_phocagallery'));
        $attachment_cursor = $phoca_posts_num + $first_id;

        $phoca_posts = $this->wpdb_src->get_results("select * from  nagsh_phocagallery where id >= $first_id and id <= $last_id ");

        foreach ($phoca_posts as $phoca_post) {

            $post = [
                'ID' => $phoca_post->id,
                'post_author' => get_current_user_id(),
                'post_date' => current_time('mysql'),
                'post_date_gmt' => current_time('mysql', 1),
                'post_content' => '
                        <!-- wp:heading {\"align\":\"center\"} -->
                        <h2 style=\"text-align:center\">' . strip_tags($phoca_post->description) . '</h2>
                        <!-- /wp:heading -->',
                'post_title' => $phoca_post->title,
                'post_excerpt' => '',
                'post_status' => 'publish',
                'comment_status' => 'open',
                'ping_status' => 'open',
                'post_password' => '',
                'post_name' => $phoca_post->alias,
                'to_ping' => '',
                'pinged' => '',
                'post_modified' => current_time('mysql'),
                'post_modified_gmt' => current_time('mysql', 1),
                'post_content_filtered' => '',
                'post_parent' => 0,
                'guid' => 'http://photo.nagsh.ir/?p=' . $phoca_post->id,
                'menu_order' => $phoca_post->ordering,
                'post_type' => 'post',
                'post_mime_type' => '',
                'comment_count' => 0,
            ];

            $post_meta = [
                '_edit_last' => get_current_user_id(),
                'slide_template' => 'default',
                'mfn-post-hide-content' => 0,
                'mfn-post-sidebar' => 0,
                'mfn-post-sidebar2' => 0,
                'mfn-post-slider' => 0,
                'mfn-post-slider-layer' => 0,
                'mfn-post-hide-title' => 0,
                'mfn-post-remove-padding' => 0,
                'mfn-post-intro' => [
                    'post-meta' => '1'
                ],
                'mfn-post-hide-image' => 0,
                '_thumbnail_id' => $attachment_cursor
            ];
            $this->insertPostWithMetaToDB($post, $post_meta);
            $phoca_category_alias = $this->wpdb_src->get_var('select alias from nagsh_phocagallery_categories where id =' . strval($phoca_post->catid));
            $term = get_term_by('slug', $phoca_category_alias,'category');
            wp_set_post_terms($phoca_post->id, $term->term_id, 'category');

            // Add post attachments

            // Generate different image sizes for attachment
            $image_sizes = $this->generateImageSizes($phoca_post->filename);

            $attachment = [
                'ID' => $attachment_cursor++, // Increase attachment_cursor by 1 for inserting the next attachment
                'post_author' => get_current_user_id(),
                'post_date' => current_time('mysql'),
                'post_date_gmt' => current_time('mysql', 1),
                'post_content' => $phoca_post->title,
                'post_title' => basename($phoca_post->filename),
                'post_excerpt' => $phoca_post->title,
                'post_status' => 'inherit',
                'comment_status' => 'open',
                'ping_status' => 'closed',
                'post_password' => '',
                'post_name' => basename($phoca_post->filename),
                'to_ping' => '',
                'pinged' => '',
                'post_modified' => current_time('mysql'),
                'post_modified_gmt' => current_time('mysql', 1),
                'post_content_filtered' => '',
                'post_parent' => $phoca_post->id,
                'guid' => 'http://photo.nagsh.ir/wp-content/uploads/gallery/' . $phoca_post->filename,
                'menu_order' => 0,
                'post_type' => 'attachment',
                'post_mime_type' => $image_sizes['original']['mime-type'],
                'comment_count' => 0,
            ];

            $attachment_meta = [
                '_wp_attached_file' => 'gallery/' . $phoca_post->filename,
                '_wp_attachment_metadata' => [
                    'width' => $image_sizes['original']['width'],
                    'height' => $image_sizes['original']['height'],
                    'file' => 'gallery/' . $phoca_post->filename,
                    'sizes' => [
                        'thumbnail' => [
                            'file' => 'sizes/' . $image_sizes['thumbnail']['file'],
                            'width' => $image_sizes['thumbnail']['width'],
                            'height' => $image_sizes['thumbnail']['height'],
                            'mime-type' => $image_sizes['thumbnail']['mime-type']
                        ],
                        'blog-navi' => [
                            'file' => 'sizes/' . $image_sizes['blog-navi']['file'],
                            'width' => $image_sizes['blog-navi']['width'],
                            'height' => $image_sizes['blog-navi']['height'],
                            'mime-type' => $image_sizes['blog-navi']['mime-type']
                        ],
                        'blog-portfolio' => [
                            'file' => 'sizes/' . $image_sizes['blog-portfolio']['file'],
                            'width' => $image_sizes['blog-portfolio']['width'],
                            'height' => $image_sizes['blog-portfolio']['height'],
                            'mime-type' => $image_sizes['blog-portfolio']['mime-type']
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
                '_wp_attachment_image_alt' => $phoca_post->title,
            ];
            $this->insertPostWithMetaToDB($attachment, $attachment_meta);

        } // End of foreach
    } // End of function migratePosts

	public function rollbackMigratedPostsTo($first_id) {
		if (!isset($this->wpdb_src)) return;

		// Get the last id of posts to use for migrating attachments
		$phoca_posts_num = intval($this->wpdb_src->get_var('select max(id) from nagsh_phocagallery'));
		$attachment_cursor = $phoca_posts_num + $first_id;

		global $wpdb;

		// Rollback posts
		$wpdb->query('delete from wp_posts where ID >= ' . strval($first_id) . ' and ID <= ' . strval($phoca_posts_num));
		$wpdb->query('delete from wp_postmeta where post_id >= ' . strval($first_id) . ' and post_id <= ' . strval($phoca_posts_num));
		// Rollback Post Attachments
		$wpdb->query('delete from wp_posts where ID >= ' . strval($attachment_cursor));
		$wpdb->query('delete from wp_postmeta where post_id >= ' . strval($attachment_cursor));
		// Rolback term relationships
		$terms = $wpdb->get_col('select distinct term_taxonomy_id from wp_term_relationships where object_id >= ' . strval($first_id) . ' and object_id <= ' . strval($phoca_posts_num));
		$wpdb->query('delete from wp_term_relationships where object_id >= ' . strval($first_id) . ' and object_id <= ' . strval($phoca_posts_num));
		// Update term counts
		wp_update_term_count_now($terms, 'category');
	}

    public function insertPostWithMetaToDB(array $post, array $metadata) {
        global $wpdb;
        $post_id = $post['ID'];

        $wpdb->insert('wp_posts', $post);

        foreach ($metadata as $key => $value) {
            add_post_meta($post_id, $key, $value);
        }
    }

    public function generateImageSizes($gallery_filename) {

        // Save Image related filesystem information
        $image_path = wp_get_upload_dir()['basedir'] . '/gallery/' . $gallery_filename;
        $image_info = pathinfo($image_path);

        // Create blank image if the image isn't available
        if (!file_exists($image_path)) {
            if (!file_exists($image_info['dirname'])) mkdir($image_info['dirname'] . '/', 0755, true);
            copy($this->imagePlaceholderPath(), $image_path);
        }

        // Original image size information
        $original_size = getimagesize($image_path);
        $resizes['original'] = [
            'path' => $image_path,
            'file' => $image_info['basename'],
            'width' => $original_size[0],
            'height' => $original_size[1],
            'mime-type'=> $original_size['mime']
        ];

        // Create save path if it does'nt exist
        $save_path = $image_info['dirname'] . '/sizes/';
        if (!file_exists($save_path)) mkdir($save_path, 0755);

        // Sizes needed for each image
        $sizes = [
            'blog-portfolio' => [
                'width' => 350,
                'height' => 600,
                'crop' => false
            ],
            'thumbnail' => [
                'width' => 150,
                'height' => 150,
                'crop' => true
            ],
            'blog-navi' => [
                'width' => 80,
                'height' => 80,
                'crop' => true
            ],
        ];

        foreach ($sizes as $key => $value) {
            // Generate multiple sizes in $sizes array
            // Get an image editor
            $image_editor = wp_get_image_editor($image_path);
            $image_editor->resize($value['width'], $value['height'], $value['crop']);

            $resizes[$key] = $image_editor->save($image_editor->generate_filename(null, $save_path));
            $image_editor = null;
        }

        gc_collect_cycles(); // Free up memory

        return $resizes;
    }

    public function deleteThumbsAndResized() {
        $wp_uploads_dir = wp_get_upload_dir();
        $gallery_dirname = $wp_uploads_dir['basedir'] . '/gallery';

        // Get all the thumbs and resized subdirectories from gallery
        if (is_dir($gallery_dirname)) {
            $thumbs = glob($gallery_dirname . '/*/thumbs', GLOB_ONLYDIR);
            $resized = glob($gallery_dirname . '/*/Resized', GLOB_ONLYDIR);
            $directories = array_merge($thumbs, $resized);
        }

        // Delete all thumbs and resized subdirectories
        foreach ($directories as $directory) {
            $this->delete_files($directory);
        }

    }

    public function delete_files($target)
    {
        if (is_file($target)) {
            unlink($target);
        }
        elseif (is_dir($target)) {
                // Get all subdirectories of a directory
                $files = glob($target . '/*', GLOB_MARK); //GLOB_MARK adds a slash to directories returned

                // Recursive delete each subdirectory
                foreach ($files as $file) {
                    $this->delete_files($file);
                }

                rmdir($target);
        }
    }

    public function imagePlaceholderPath() {
        $uploads_dir = wp_get_upload_dir();
        $placeholder_path = $uploads_dir['basedir'] . '/gallery/placeholder.jpg';

        // Create image placeholder if there isn't any placeholder
        if (!file_exists($placeholder_path)) {
            copy($this->plugin_path . '/img/placeholder.jpg', $placeholder_path);
        }

        return $placeholder_path;
    }

}