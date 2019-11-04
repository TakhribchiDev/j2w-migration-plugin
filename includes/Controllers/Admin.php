<?php
/**
 * @package J2WMigration
 */

namespace Includes\Controllers;

use Includes\Api\SettingsApi;
use Includes\Controllers\BaseController;
use Includes\Api\Callbacks\AdminCallbacks;
use Includes\Api\Callbacks\MigrationCallbacks;

class Admin extends BaseController
{
    public $settings;
    public $callbacks;
    public $mig_callbacks;
    public $pages = array();
    public $subpages = array();

    public function register()
    {
        $this->settings = new SettingsApi();
        $this->callbacks = new AdminCallbacks();
        $this->mig_callbacks = new MigrationCallbacks();

        $this->setPages();
//        $this->setSubPages();

        $this->setSettings();
        $this->setSections();
        $this->setFields();

        $this->settings->addPages($this->pages)->withSubPage('Dashboard')->register();
    }

    public function setPages()
    {
        $this->pages = [
            [
                'page_title' => 'J2W Migration',
                'menu_title' => 'J2W Migration',
                'capability' => 'manage_options',
                'menu_slug' => 'j2w_migration_plugin',
                'callback' => [$this->callbacks, 'adminDashboard'],
                'icon_url' => 'dashicons-leftright',
                'position' => '110'
            ]
        ];
    }

//    public function setSubPages()
//    {
//        $this->subpages = [
//            [
//                'parent_slug' => 'j2w_migration_plugin',
//                'page_title' => 'J2W Migration Settings',
//                'menu_title' => 'Settings',
//                'capability' => 'manage_options',
//                'menu_slug' => 'j2w_settings',
//                'callback' => [$this->callbacks, 'adminSettings']
//            ]
//        ];
//    }

    public function setSettings() {
        $args = [
            [
                'option_group' => 'j2w_options_group',
                'option_name' => 'text_example',
                'callback' => [ $this->callbacks, 'j2wOptionsGroup']
            ]
        ];

        $this->settings->setSettings( $args );
    }

    public function setSections() {
        $args = [
            [
                'id' => 'j2w_admin_index',
                'title' => 'Settings',
                'callback' => [$this->callbacks, 'j2wAdminSection'],
                'page' => 'j2w_migration_plugin'
            ]
        ];

        $this->settings->setSections( $args );
    }

    public function setFields() {
        $args = [
            [
                'id' => 'text_example',
                'title' => 'Text Example',
                'callback' => [$this->callbacks, 'j2wExampleText'],
                'page' => 'j2w_migration_plugin',
                'section' => 'j2w_admin_index',
                'args' => [
                    'label_for' => 'text_example',
                    'class' => 'example-class'
                ]
            ]
        ];

        $this->settings->setFields( $args );
    }
}