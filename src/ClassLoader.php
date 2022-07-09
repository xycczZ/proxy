<?php

namespace Xycc\Proxy;

class ClassLoader
{
    private static array $classMap = [];
    private static bool $registered = false;

    public static function getClassMap(): array
    {
        return self::$classMap;
    }

    public static function load(string $class)
    {
        if (isset(self::$classMap[$class])) {
            include self::$classMap[$class];
        }
    }

    public static function addClassMap(array $classMap)
    {
        self::$classMap = array_merge(self::$classMap, $classMap);
    }

    public static function register(bool $prepend = false)
    {
        if (!self::$registered) {
            spl_autoload_register([self::class, 'load'], true, $prepend);
            self::$registered = true;
        }
    }
}