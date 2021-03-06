<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitebf4ee968d4bf1801157db8f3d2bf3a4
{
    public static $prefixLengthsPsr4 = array (
        'I' => 
        array (
            'Includes\\' => 9,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Includes\\' => 
        array (
            0 => __DIR__ . '/../..' . '/includes',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitebf4ee968d4bf1801157db8f3d2bf3a4::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitebf4ee968d4bf1801157db8f3d2bf3a4::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}
