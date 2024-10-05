<?php

namespace BjornTech\Common;

use ReflectionClass;


defined('ABSPATH') || exit;

trait BootstrapTrait
{

    use InstanceTrait;


    protected static function init_plugin($calledClass, $instance, $name, $args)
    {

        add_action('before_woocommerce_init', array($calledClass, 'declare_hpos'));

        load_plugin_textdomain($instance->slug, false, dirname(plugin_basename($instance->file)) . '/languages');

    }

    public static function declare_hpos()
    {
        $instance = static::get_instance();
        if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', $instance->file, true);
        }
    }

    protected static function _bjorntech_function_bootstrap($calledClass, $instance, $name, $args)
    {
        static::init_file($calledClass, $instance, $name, $args);
        static::init_plugin($calledClass, $instance, $name, $args);
        return $instance;
    }

}