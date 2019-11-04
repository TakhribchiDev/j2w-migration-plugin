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
class MigrationController extends BaseController
{
    public $settings;

    public $subPages = array();

    public $mig_callbacks;

    public function register()
    {
        $this->settings = new SettingsApi();

        $this->mig_callbacks = new MigrationCallbacks();

        $this->setSubPages();

        $this->setSettings();
        $this->setSections();
        $this->setFields();

        $this->settings->addSubPages($this->subPages)->register();
    }

    public function setSubPages()
    {
        $this->subPages = [
            [
                'parent_slug' => 'j2w_migration_plugin',
                'page_title' => 'Migration Settings',
                'menu_title' => 'Settings',
                'capability' => 'manage_options',
                'menu_slug' => 'j2w_settings',
                'callback' => [$this->mig_callbacks, 'j2wMigrationAdmin']
            ]
        ];
    }

    public function setSettings()
    {
        $args = [
            [
                'option_group' => 'j2w_migration_settings',
                'option_name' => 'j2w_db',
                'callback' => [$this->mig_callbacks, 'textSanitize']
            ]
        ];

        $this->settings->setSettings($args);
    }

    public function setSections()
    {
        $args = [
            [
                'id' => 'j2w_migration_index',
                'title' => 'Database Settings',
                'callback' => [$this->mig_callbacks, 'j2wDBConnectionSection'],
                'page' => 'j2w_settings'
            ]
        ];

        $this->settings->setSections($args);
    }

    public function setFields()
    {
        $args = [
            [
                'id' => 'db_host',
                'title' => 'DB Host',
                'callback' => [$this->mig_callbacks, 'textField'],
                'page' => 'j2w_settings',
                'section' => 'j2w_migration_index',
                'args' => [
                    'label_for' => 'db_host',
                    'option_name' => 'j2w_db',
                    'placeholder' => 'e.g. localhost'
                ]
            ],
            [
                'id' => 'db_port',
                'title' => 'DB Port',
                'callback' => [$this->mig_callbacks, 'textField'],
                'page' => 'j2w_settings',
                'section' => 'j2w_migration_index',
                'args' => [
                    'label_for' => 'db_port',
                    'option_name' => 'j2w_db',
                    'placeholder' => 'e.g. 3306'
                ]
            ],
            [
                'id' => 'db_name',
                'title' => 'DB Name',
                'callback' => [$this->mig_callbacks, 'textField'],
                'page' => 'j2w_settings',
                'section' => 'j2w_migration_index',
                'args' => [
                    'label_for' => 'db_name',
                    'option_name' => 'j2w_db',
                    'placeholder' => 'e.g. MyDB'
                ]
            ],
            [
                'id' => 'db_user',
                'title' => 'DB Username',
                'callback' => [$this->mig_callbacks, 'textField'],
                'page' => 'j2w_settings',
                'section' => 'j2w_migration_index',
                'args' => [
                    'label_for' => 'db_user',
                    'option_name' => 'j2w_db',
                    'placeholder' => 'e.g. root'
                ]
            ],
            [
                'id' => 'db_pass',
                'title' => 'DB Password',
                'callback' => [$this->mig_callbacks, 'passwordField'],
                'page' => 'j2w_settings',
                'section' => 'j2w_migration_index',
                'args' => [
                    'label_for' => 'db_pass',
                    'option_name' => 'j2w_db',
                    'placeholder' => 'e.g. 1234'
                ]
            ],
        ];

        $this->settings->setFields($args);
    }
}