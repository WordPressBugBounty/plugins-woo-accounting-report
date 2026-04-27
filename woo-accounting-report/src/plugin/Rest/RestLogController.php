<?php

namespace BjornTech\AccountingReport\Rest;


use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use BjornTech\AccountingReport\Logger;

class RestLogController extends WP_REST_Controller
{

    use RestControllerTrait;

    private const MAX_LOG_MESSAGE_LENGTH = 10000;

    public function __construct()
    {
        $this->namespace = 'bjorntech-accounting/v1';
        $this->rest_base = 'log';

    }

    public function register_routes()
    {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            array(
                array(
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => array($this, 'log_message'),
                    'permission_callback' => array($this, 'permission_check'),
                    'args' => $this->get_log_endpoint_args(),
                ),
            )
        );
    }

    public function get_log_endpoint_args()
    {
        return array(
            'function' => array(
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => 'rest_validate_request_arg',
            ),
            'message' => array(
                'required' => true,
                'sanitize_callback' => array($this, 'sanitize_log_message_param'),
                'validate_callback' => array($this, 'validate_log_message_param'),
            ),
            'is_json' => array(
                'default' => false,
                'type' => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'validate_callback' => 'rest_validate_request_arg',
            ),
        );
    }

    public function sanitize_log_message_param($value, WP_REST_Request $request, $param)
    {
        if (is_string($value)) {
            return sanitize_text_field($value);
        }

        return $value;
    }

    public function validate_log_message_param($value, WP_REST_Request $request, $param)
    {
        if (is_string($value) || is_numeric($value) || is_bool($value) || is_array($value)) {
            return true;
        }

        return new WP_Error(
            'woocommerce_rest_invalid_log_message',
            __('The message must be a scalar value or array.', 'woo-accounting-report'),
            array('status' => 400)
        );
    }

    private function fix_message($message, $is_json)
    {
        if (is_array($message)) {
            $message = $is_json ? wp_json_encode($message) : wp_json_encode($message, JSON_PRETTY_PRINT);
        }

        $message = sanitize_textarea_field((string) $message);

        if (strlen($message) > self::MAX_LOG_MESSAGE_LENGTH) {
            $message = substr($message, 0, self::MAX_LOG_MESSAGE_LENGTH) . '... [truncated]';
        }

        return $message;
    }

    public function log_message($request)
    {
        if ('yes' !== get_option('bjorntech_wcar_logging')) {
            return new WP_REST_Response('Logging disabled', 200);
        }

        $function_name = $request->get_param('function');
        $message = $request->get_param('message');
        $is_json = (bool) $request->get_param('is_json');

        $function_name = sanitize_text_field((string) $function_name);

        Logger::add($function_name . ' : ' . $this->fix_message($message, $is_json));

        return new WP_REST_Response('Message logged', 200);
    }



}
