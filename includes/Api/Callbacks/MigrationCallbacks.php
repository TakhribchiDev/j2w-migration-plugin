<?php
/**
 * @package J2WMigration
 */
namespace Includes\Api\Callbacks;

use Includes\Controllers\BaseController;

class MigrationCallbacks extends BaseController
{
    public function j2wMigrationAdmin() {
        return require_once("$this->plugin_path/pages/migration.php");
    }

    public function j2wMigrationSection() {
        echo 'Modify this settings to migrate or not migrate databases';
    }

    public function j2wDBConnectionSection() {
        echo 'Set the information needed for database connection';
    }

    public function checkboxSanitize($input) {
        return $input;
    }

    public function checkboxField( $args ) {
        $name = $args['label_for'];
        echo '<input type="checkbox" name="' . $name . '" value=1 >';
    }


    public function textSanitize($input) {
        return $input;
    }

    public function textField($args) {
        $name = $args['label_for'];
        $option_name = $args['option_name'];
        $options = get_option($option_name);
        $value = isset($options[$name]) ? $options[$name] : '';

        echo '<input type="text" name="' . $option_name . '[' . $name . ']' . '" value="' . $value . '" placeholder="' . $args['placeholder'] . '" >';
    }
	public function passwordField($args) {
		$name = $args['label_for'];
		$option_name = $args['option_name'];
		$options = get_option($option_name);
		$value = isset($options[$name]) ? $options[$name] : '';

		echo '<input type="password" name="' . $option_name . '[' . $name . ']' . '" value="' . $value . '" placeholder="' . $args['placeholder'] . '" >';
	}

}