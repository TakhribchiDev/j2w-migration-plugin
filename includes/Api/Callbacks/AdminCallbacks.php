<?php
/**
 * @package J2WMigration
 */
namespace Includes\Api\Callbacks;

use Includes\Controllers\BaseController;

class AdminCallbacks extends BaseController
{
    public function adminDashboard() {
        return require_once("$this->plugin_path/pages/admin.php");
    }

    public function j2wAdminSection() {
        echo 'Modify the plugin settings';
    }

    public function adminSettings() {
        return require_once("$this->plugin_path/pages/settings.php");
    }

    public function j2wOptionsGroup( $input ) {
        return $input;
    }

    public function j2wExampleText() {
        echo '<input type="text" class="regular-text" name="example_text" placeholder="Your example text">';
    }

}