<?php

namespace BjornTech\Common;

use ReflectionClass;

defined('ABSPATH') || exit;

trait InstanceTrait
{
    protected static $instances = array();

    public static function __callStatic($name, $args)
    {

        $calledClass = get_called_class();

        if (in_array($calledClass, array_keys(self::$instances))) {

            if ($calledClass == __CLASS__) {
                return call_user_func_array(array(self::$instances[$calledClass], '_bjorntech_function_' . $name), array($calledClass, self::$instances[$calledClass], $name, $args));
            }

            return call_user_func_array(array($calledClass, $name), array($calledClass, self::$instances[$calledClass], $name, $args));

        }

        $instance = new $calledClass();

        self::$instances[$calledClass] = $instance;

        self::call_instance_init_methods($calledClass, $instance, $name, $args);

        return self::$instances[$calledClass];

    }

    protected static function is_args_empty($args)
    {
        if (!is_array($args)) {
            return empty($args);
        } else {
            return count(array_filter($args)) === 0;
        }
    }

    /**
     * Returns the name of the class the static method was called in.
     * 
     * @access protected
     * @static
     * @since 1.1.2
     * @return string
     */
    protected static function get_called_class()
    {
        if (function_exists('get_called_class')) {
            return get_called_class();
        }

        $bt = debug_backtrace();
        $lines = file($bt[1]['file']);
        preg_match(
            '/([a-zA-Z0-9\_]+)::' . $bt[1]['function'] . '/',
            $lines[$bt[1]['line'] - 1],
            $matches
        );
        return $matches[1];
    }

    /**
     * Checks for static methods named instance_init_xxx and calls them.
     *
     * @access protected
     * @static
     * @since 1.1.2
     */
    protected static function call_instance_init_methods($calledClass, $instance, $name, $args)
    {

        $methods = get_class_methods($calledClass);

        foreach ($methods as $method) {
            if (preg_match('/^_bjorntech_init_/', $method)) {
                call_user_func_array(array(self::$instances[$calledClass], $method), array($calledClass, $instance, $name, $args));
            }
        }

    }

    protected static function get_instance()
    {
        $calledClass = self::get_called_class();
        return isset(self::$instances[$calledClass]) ? self::$instances[$calledClass] : null;
    }


}