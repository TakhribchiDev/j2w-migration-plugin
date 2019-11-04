<?php
/**
 * @package J2WMigration
 */

namespace Includes\Controllers;

class Deactivate
{
    public static function deactivate()
    {
        flush_rewrite_rules();
    }
}