<?php
/**
 * @package J2WMigration
 */
namespace Includes\Controllers;

class SettingsLink extends BaseController
{
    public function register() {
        add_action("plugin_action_links_$this->plugin", array($this, 'settings_link'));
    }

    public function settings_link($links)
    {
        $settings_link = '<a href="admin.php?page=j2w_migration_plugin">Settings</a>';
        array_push($links, $settings_link);
        return $links;
    }
}