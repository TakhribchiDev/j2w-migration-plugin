<?php
/**
 * @package J2WMigration
 */

namespace Includes\Controllers;

class Enqueue extends BaseController
{
    public function register()
    {
        add_action('admin_enqueue_scripts', array($this, 'enqueue'));
    }

    function enqueue() {
        // enqueue all your scripts
        wp_enqueue_style('j2wstyle', $this->plugin_url . '/assets/j2w_style.css');
        wp_enqueue_script('j2wscript',$this->plugin_url . '/assets/j2w_script.js');
    }
}