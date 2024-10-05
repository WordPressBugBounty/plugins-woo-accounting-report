<?php

namespace BjornTech\AccountingReport\Rest;



defined('ABSPATH') || exit;

use BjornTech\Common\SingletonTrait;


class RestHandler
{

    use SingletonTrait;

    public function __construct()
    {
        if ('yes' === get_option('wcar_load_analytics','yes')) {
            add_filter('woocommerce_rest_api_get_rest_namespaces', array($this, 'get_rest_namespaces'));
            add_filter("pre_option_bjorntech_wcar_nonce", array($this, 'get_nonce'), 10, 3);
        }
    }

    /**
     * Register REST API routes.
     *
     * New endpoints/controllers can be added here.
     */
    public function get_rest_namespaces($routes)
    {

        $routes['wc/v3'][] = 'BjornTech\AccountingReport\Rest\RestRefundsController';
        $routes['wc/v3'][] = 'BjornTech\AccountingReport\Rest\RestOrdersController';
        $routes['wc/v3'][] = 'BjornTech\AccountingReport\Rest\RestEuCountriesController';
        $routes['wc/v3'][] = 'BjornTech\AccountingReport\Rest\RestLogController';

        return $routes;
    }

    public function get_nonce($status, $option, $default)
    {

        if ($option !== 'bjorntech_wcar_nonce') {
            return $status;
        }

        $nonce = wp_create_nonce(WC_ACCOUNTING_REPORT_ID);

        return $nonce;

    }

}