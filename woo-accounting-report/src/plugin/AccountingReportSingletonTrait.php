<?php

namespace BjornTech\AccountingReport;

defined('ABSPATH') || exit;

use Exception;
use ReflectionClass;

trait AccountingReportSingletonTrait
{
    protected static $_instance = array();

    final protected function __clone()
    {
    }

    final public static function init()
    {
        $args = func_get_args();
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
