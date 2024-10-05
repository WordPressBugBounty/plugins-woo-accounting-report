<?php

namespace BjornTech\Common;

defined('ABSPATH') || exit;

trait PluginClassTrait
{

    protected static $_id;
    protected static $_slug;
    protected static $_handle;
    protected static $_file;

    protected function _bjorntech_init_get_id($calledClass, $instance, $name, $args)
    {
        static::$_id[$calledClass] = $instance->id ?? null;
    }

    protected function _bjorntech_init_get_slug($calledClass, $instance, $name, $args)
    {
        static::$_slug[$calledClass] = $instance->slug ?? null;
    }

    protected function _bjorntech_init_get_handle($calledClass, $instance, $name, $args)
    {
        static::$_handle[$calledClass] = $instance->handle ?? null;
    }

    protected function _bjorntech_init_get_file($calledClass, $instance, $name, $args)
    {
        static::$_file[$calledClass] = $instance->file ?? null;
    }

    protected function _bjorntech_function_get_id($calledClass, $instance, $name, $args)
    {
        return static::$_id[$calledClass];
    }

    protected function _bjorntech_function_get_slug($calledClass, $instance, $name, $args)
    {
        return static::$_slug[$calledClass];
    }

    protected function _bjorntech_function_get_handle($calledClass, $instance, $name, $args)
    {
        return static::$_handle[$calledClass];
    }

    protected function _bjorntech_function_get_file($calledClass, $instance, $name, $args)
    {
        return static::$_file[$calledClass];
    }

}