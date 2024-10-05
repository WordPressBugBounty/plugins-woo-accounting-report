<?php

namespace BjornTech\Common;

defined('ABSPATH') || exit;

use ReflectionClass;
use Exception;

trait SingletonTrait
{

    protected static $_instance = array();

    /**
     * Prevent object cloning
     */
    final protected function __clone()
    {
    }

    /**
     * To return new or existing Singleton instance of the class from which it is called.
     * As it sets to final it can't be overridden.
     *
     * @return object Singleton instance of the class.
     */
    final public static function init()
    {
        $args = func_get_args();
        $called_class = get_called_class();

        /**
         * Returns name of the class the static method is called in.
         */
        $called_class = get_called_class();

        if (!isset(static::$_instance[$called_class])) {

            if (!empty($args)) {
                $reflection = new ReflectionClass($called_class);
                static::$_instance[$called_class] = $reflection->newInstanceArgs($args);
            } else {
                static::$_instance[$called_class] = new $called_class();
            }

        }

        return static::$_instance[$called_class];

    }

    final public static function get_instance()
    {
        $called_class = get_called_class();
        if (!isset(static::$_instance[$called_class])) {
            throw new Exception('Instance not initialized');
        }
        return static::$_instance[$called_class];
    }


}
