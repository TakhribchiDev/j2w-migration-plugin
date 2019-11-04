<?php
/**
 * @package J2WMigration
 */
namespace Includes\Controllers;

class BaseController
{
    protected $plugin_path, $plugin_url, $plugin;

    public function __construct() {
        $this->plugin_path = plugin_dir_path($this->rec_dirname(__FILE__, 2));
        $this->plugin_url = plugin_dir_url($this->rec_dirname(__FILE__, 2));
        $this->plugin = plugin_basename($this->rec_dirname(__FILE__, 3) . '/j2w-migration-plugin.php');

    }

    public function rec_dirname($path, $levels=1) {
        if ($levels > 1) {
            return dirname($this->rec_dirname($path, --$levels));
        }
        else {
            return dirname($path);
        }
    }
}