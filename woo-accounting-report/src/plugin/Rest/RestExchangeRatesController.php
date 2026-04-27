<?php

namespace BjornTech\AccountingReport\Rest;

use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Server;

defined('ABSPATH') || exit;

class RestExchangeRatesController extends WP_REST_Controller
{

    use RestControllerTrait;

    private const EXCHANGE_RATE_ENDPOINT = 'https://accounting.services.bjorntech.eu/latest';

    public function __construct()
    {
        $this->namespace = 'bjorntech-accounting/v1';
        $this->rest_base = 'exchange-rates';
    }

    public function register_routes()
    {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            array(
                array(
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => array($this, 'get_items'),
                    'permission_callback' => array($this, 'permission_check'),
                    'args' => $this->get_collection_params(),
                ),
            )
        );
    }

    public function get_collection_params()
    {
        return array(
            'base' => array(
                'description' => __('Base currency (ISO 4217).', 'woo-accounting-report'),
                'type' => 'string',
                'default' => 'EUR',
                'sanitize_callback' => array($this, 'sanitize_currency_param'),
                'validate_callback' => array($this, 'validate_currency_param'),
            ),
            'symbols' => array(
                'description' => __('Optional comma-separated list of currency codes.', 'woo-accounting-report'),
                'type' => 'string',
                'required' => false,
                'sanitize_callback' => array($this, 'sanitize_symbols_param'),
            ),
        );
    }

    public function sanitize_currency_param($value, WP_REST_Request $request = null, $param = null)
    {
        return strtoupper(sanitize_text_field((string) $value));
    }

    public function validate_currency_param($value, WP_REST_Request $request = null, $param = null)
    {
        if ('' === $value || preg_match('/^[A-Z]{3}$/', $value)) {
            return true;
        }

        return new WP_Error(
            'woocommerce_rest_invalid_base_currency',
            __('Base currency must be a valid 3-letter ISO 4217 code.', 'woo-accounting-report'),
            array('status' => 400)
        );
    }

    public function sanitize_symbols_param($value, WP_REST_Request $request = null, $param = null)
    {
        if (!is_string($value)) {
            return '';
        }

        $symbols = array_filter(array_map('trim', explode(',', strtoupper($value))));
        $symbols = array_filter($symbols, function ($symbol) {
            return preg_match('/^[A-Z]{3}$/', $symbol);
        });

        return implode(',', array_unique($symbols));
    }

    private function get_api_key()
    {
        $api_key = defined('BJORNTECH_ACCOUNTING_EXCHANGE_RATES_API_KEY')
            ? BJORNTECH_ACCOUNTING_EXCHANGE_RATES_API_KEY
            : '';

        if ('' === $api_key) {
            $api_key = get_option('bjorntech_wcar_exchange_rates_api_key', '');
        }

        return is_string($api_key) ? trim($api_key) : '';
    }

    public function get_items($request)
    {
        $api_key = $this->get_api_key();

        if ('' === $api_key) {
            return new WP_Error(
                'woocommerce_rest_exchange_rates_missing_api_key',
                __('Exchange rate API key is missing. Add it in plugin settings or define BJORNTECH_ACCOUNTING_EXCHANGE_RATES_API_KEY in wp-config.php.', 'woo-accounting-report'),
                array('status' => 500)
            );
        }

        $query_args = array(
            'access_key' => $api_key,
            'base' => $request->get_param('base'),
        );

        $symbols = $request->get_param('symbols');
        if (!empty($symbols)) {
            $query_args['symbols'] = $symbols;
        }

        $response = wp_remote_get(
            add_query_arg($query_args, self::EXCHANGE_RATE_ENDPOINT),
            array('timeout' => 20)
        );

        if (is_wp_error($response)) {
            return new WP_Error(
                'woocommerce_rest_exchange_rates_request_failed',
                $response->get_error_message(),
                array('status' => 502)
            );
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        if (200 !== $http_code) {
            $message = is_array($decoded) && isset($decoded['error']['message'])
                ? $decoded['error']['message']
                : __('Exchange rate service returned an unexpected status.', 'woo-accounting-report');

            return new WP_Error(
                'woocommerce_rest_exchange_rates_http_error',
                $message,
                array('status' => $http_code ?: 502)
            );
        }

        if (!is_array($decoded)) {
            return new WP_Error(
                'woocommerce_rest_exchange_rates_invalid_response',
                __('Exchange rate service returned invalid JSON.', 'woo-accounting-report'),
                array('status' => 502)
            );
        }

        if (isset($decoded['success']) && false === $decoded['success']) {
            $message = isset($decoded['error']['message'])
                ? $decoded['error']['message']
                : __('Exchange rate service reported an error.', 'woo-accounting-report');

            return new WP_Error(
                'woocommerce_rest_exchange_rates_api_error',
                $message,
                array('status' => 502)
            );
        }

        return rest_ensure_response($decoded);
    }

}
