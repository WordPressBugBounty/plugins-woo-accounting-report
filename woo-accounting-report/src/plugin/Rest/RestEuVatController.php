<?php

namespace BjornTech\AccountingReport\Rest;

defined('ABSPATH') || exit;

use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Server;

class RestEuVatController extends WP_REST_Controller
{

    use RestControllerTrait;

    private const VAT_SERVICE_BASE_URL = 'https://taxes.services.bjorntech.eu/eu/vat';

    public function __construct()
    {
        $this->namespace = 'bjorntech-accounting/v1';
        $this->rest_base = 'data/eu-vat';
    }

    public function register_routes()
    {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/latest',
            array(
                array(
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => array($this, 'get_latest'),
                    'permission_callback' => array($this, 'permission_check'),
                    'args' => $this->get_rate_params(),
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<date>\d{4}-\d{2}-\d{2})',
            array(
                array(
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => array($this, 'get_by_date'),
                    'permission_callback' => array($this, 'permission_check'),
                    'args' => $this->get_rate_params(),
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/countries',
            array(
                array(
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => array($this, 'get_countries'),
                    'permission_callback' => array($this, 'permission_check'),
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/categories',
            array(
                array(
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => array($this, 'get_categories'),
                    'permission_callback' => array($this, 'permission_check'),
                ),
            )
        );
    }

    public function get_rate_params()
    {
        return array(
            'countries' => array(
                'description' => __('Comma-separated list of ISO 3166-1 alpha-2 country codes.', 'woo-accounting-report'),
                'type' => 'string',
                'required' => false,
                'sanitize_callback' => array($this, 'sanitize_countries_param'),
                'validate_callback' => array($this, 'validate_countries_param'),
            ),
            'rate_types' => array(
                'description' => __('Comma-separated VAT rate types, for example STANDARD,REDUCED.', 'woo-accounting-report'),
                'type' => 'string',
                'required' => false,
                'sanitize_callback' => array($this, 'sanitize_rate_types_param'),
                'validate_callback' => array($this, 'validate_rate_types_param'),
            ),
        );
    }

    public function sanitize_countries_param($value, WP_REST_Request $request = null, $param = null)
    {
        if (!is_string($value)) {
            return '';
        }

        $values = array_filter(array_map('trim', explode(',', strtoupper($value))));

        return implode(',', $values);
    }

    public function validate_countries_param($value, WP_REST_Request $request = null, $param = null)
    {
        if ('' === $value) {
            return true;
        }

        $values = array_filter(array_map('trim', explode(',', strtoupper((string) $value))));

        foreach ($values as $country_code) {
            if (!preg_match('/^[A-Z]{2}$/', $country_code)) {
                return new WP_Error(
                    'woocommerce_rest_invalid_vat_country',
                    __('Countries must be ISO 3166-1 alpha-2 codes.', 'woo-accounting-report'),
                    array('status' => 400)
                );
            }
        }

        return true;
    }

    public function sanitize_rate_types_param($value, WP_REST_Request $request = null, $param = null)
    {
        if (!is_string($value)) {
            return '';
        }

        $values = array_filter(array_map('trim', explode(',', strtoupper($value))));

        return implode(',', $values);
    }

    public function validate_rate_types_param($value, WP_REST_Request $request = null, $param = null)
    {
        if ('' === $value) {
            return true;
        }

        $values = array_filter(array_map('trim', explode(',', strtoupper((string) $value))));

        foreach ($values as $rate_type) {
            if (!preg_match('/^[A-Z_]+$/', $rate_type)) {
                return new WP_Error(
                    'woocommerce_rest_invalid_vat_rate_type',
                    __('Rate types must contain letters and underscores only.', 'woo-accounting-report'),
                    array('status' => 400)
                );
            }
        }

        return true;
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

    public function get_latest(WP_REST_Request $request)
    {
        return $this->fetch_service('/latest', $request);
    }

    public function get_by_date(WP_REST_Request $request)
    {
        $date = $request->get_param('date');

        return $this->fetch_service('/' . $date, $request);
    }

    public function get_countries(WP_REST_Request $request)
    {
        return $this->fetch_service('/countries', $request);
    }

    public function get_categories(WP_REST_Request $request)
    {
        return $this->fetch_service('/categories', $request);
    }

    private function build_query_args(WP_REST_Request $request)
    {
        $query_args = array();

        $countries = $request->get_param('countries');
        if (!empty($countries)) {
            $query_args['countries'] = $countries;
        }

        $rate_types = $request->get_param('rate_types');
        if (!empty($rate_types)) {
            $query_args['rate_types'] = $rate_types;
        }

        return $query_args;
    }

    private function fetch_service($path, WP_REST_Request $request)
    {
        $api_key = $this->get_api_key();

        if ('' === $api_key) {
            return new WP_Error(
                'woocommerce_rest_vat_service_missing_api_key',
                __('VAT service API key is missing. Add it in plugin settings or define BJORNTECH_ACCOUNTING_EXCHANGE_RATES_API_KEY in wp-config.php.', 'woo-accounting-report'),
                array('status' => 500)
            );
        }

        $query_args = $this->build_query_args($request);
        $query_args['access_key'] = $api_key;

        $response = wp_remote_get(
            add_query_arg($query_args, self::VAT_SERVICE_BASE_URL . $path),
            array('timeout' => 20)
        );

        if (is_wp_error($response)) {
            return new WP_Error(
                'woocommerce_rest_vat_service_request_failed',
                $response->get_error_message(),
                array('status' => 502)
            );
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        if (200 !== $http_code) {
            $message = is_array($decoded) && isset($decoded['message'])
                ? $decoded['message']
                : __('VAT service returned an unexpected status.', 'woo-accounting-report');

            return new WP_Error(
                'woocommerce_rest_vat_service_http_error',
                $message,
                array('status' => $http_code ?: 502)
            );
        }

        if (!is_array($decoded)) {
            return new WP_Error(
                'woocommerce_rest_vat_service_invalid_response',
                __('VAT service returned invalid JSON.', 'woo-accounting-report'),
                array('status' => 502)
            );
        }

        return rest_ensure_response($decoded);
    }

}
