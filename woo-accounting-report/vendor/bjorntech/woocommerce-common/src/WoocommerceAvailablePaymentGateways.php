<?php

namespace BjornTech\Common;

defined('ABSPATH') || exit;

class WoocommerceAvailablePaymentGateways
{

    protected static $disallowed_users = false;

    protected static $gateway_class;

    public static function init($gateway_class, $disallowed_users = false)
    {

        static::$gateway_class = $gateway_class;

        add_filter('woocommerce_available_payment_gateways', [__CLASS__, 'filter_gateway']);

        add_filter('woocommerce_payment_gateways', [__CLASS__, 'add_gateway']);
    }

    public static function set_disallowed_users($disallowed_users)
    {
        static::$disallowed_users = $disallowed_users;
    }

    public static function add_gateway($gateways)
    {
        $gateways[] = static::$gateway_class;
        return $gateways;
    }

    /**
     * Add the Gateway to WooCommerce
     **/
    public static function filter_gateway($gateways)
    {
        $user_id = wp_get_current_user()->ID;

        if ((is_array(static::$disallowed_users) && in_array($user_id, static::$disallowed_users))) {
            unset($gateways[static::$gateway_class::$gateway_id]);
        }

        return $gateways;
    }

}
