<?php
/**
 * @package J2WMigrationPlugin
 */

namespace Includes\Controllers;

class Activate
{
    public static function activate()
    {
        flush_rewrite_rules();
    }
}