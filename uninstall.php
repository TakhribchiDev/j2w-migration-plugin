<?php
/**
 * Trigger this file on plugin uninstallation
 *
 * @package J2WMigration
 */

defined('WP_UNINSTALL_PLUGIN') or die;

// Clear data stored in database
$books = get_posts(array('post_type' => 'book', 'post_numbers' => -1));

foreach ($books as $book) {
    wp_delete_post($book->ID, true);
}

