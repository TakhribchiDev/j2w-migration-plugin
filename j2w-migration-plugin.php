<?php
/**
 *  @package J2WMigration
 *
 * Plugin name: J2W Migration
 * Plugin URI: http://takhribchi.net/j2w
 * Description: This is a plugin used to migrate something from joomla to wordpress.
 * Version: 1.0.0
 * Author: Takhribchi
 * Author URI: http://takhribchi.net
 * License: GPLv2 or later
 * Text Domain: j2w-migration-plugin
 */

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

// If the file is called directly abort
defined('ABSPATH') or die( 'You are not doing the right thing!');

// Require once autoload file
if (file_exists(dirname(__FILE__).'/vendor/autoload.php')) {
    require_once dirname(__FILE__).'/vendor/autoload.php';
}

// Define CONSTANTS
define('PLUGIN_PATH', plugin_dir_path(__FILE__));
define('PLUGIN_URL', plugin_dir_url(__FILE__));
define('PLUGIN', plugin_basename(__FILE__));

/*
 * The code that runs during plugin activation
 */
function activate_j2w_migration_plugin() {
   Includes\Controllers\Activate::activate();
}
register_activation_hook(__FILE__, 'activate_j2w_migration_plugin');

/*
 * The code that runs during plugin deactivation
 */
function deactivate_j2w_migration_plugin() {
    Includes\Controllers\Deactivate::deactivate();
}
register_deactivation_hook(__FILE__, 'deactivate_j2w_migration_plugin');

if (class_exists('Includes\\Init')) {
    Includes\Init::register_services();
}