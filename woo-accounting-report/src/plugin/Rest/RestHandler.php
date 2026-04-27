<?php

namespace BjornTech\AccountingReport\Rest;



defined('ABSPATH') || exit;

class RestHandler
{

    use \BjornTech\AccountingReport\AccountingReportSingletonTrait;

    private const REST_CONTROLLERS = [
        RestRefundsController::class,
        RestOrdersController::class,
        RestEuCountriesController::class,
        RestExchangeRatesController::class,
        RestEuVatController::class,
        RestLogController::class,
    ];

    public function __construct()
    {
        add_action('rest_api_init', array($this, 'register_routes'));
        add_filter("pre_option_bjorntech_wcar_nonce", array($this, 'get_nonce'), 10, 3);
    }

    /**
     * Register REST API routes.
     *
     * New endpoints/controllers can be added here.
     */
    public function register_routes()
    {

        foreach (self::REST_CONTROLLERS as $controller_class) {
            if (!class_exists($controller_class)) {
                $controller_file = __DIR__ . '/' . $this->get_class_name($controller_class) . '.php';

                if (file_exists($controller_file)) {
                    require_once $controller_file;
                }
            }

            if (!class_exists($controller_class)) {
                continue;
            }

            $controller = new $controller_class();
            $controller->register_routes();

            if (method_exists($controller, 'register_routes_v2')) {
                $controller->register_routes_v2();
            }
        }

    }

    private function get_class_name($class_name)
    {
        $parts = explode('\\', (string) $class_name);

        return end($parts);
    }

    public function get_nonce($status, $option, $default)
    {

        if ($option !== 'bjorntech_wcar_nonce') {
            return $status;
        }

        $nonce = wp_create_nonce('wp_rest');

        return $nonce;

    }

}
