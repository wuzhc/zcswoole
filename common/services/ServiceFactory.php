<?php

namespace common\services;


/**
 * Class ServiceFactory
 * @package services
 */
class ServiceFactory
{
    private static $_models = array ();

    public static function factory()
    {
        $className = __CLASS__;
        if (isset (self::$_models[$className])) {
            return self::$_models[$className];
        } else {
            self::$_models[$className] = new $className(null);
            return self::$_models[$className];
        }
    }
}