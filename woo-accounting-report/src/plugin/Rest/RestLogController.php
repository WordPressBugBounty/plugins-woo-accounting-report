<?php

namespace BjornTech\AccountingReport\Rest;


use WP_REST_Controller;
use WP_REST_Response;
use WP_REST_Server;
use BjornTech\AccountingReport\Logger;

class RestLogController extends WP_REST_Controller
{

    use RestControllerTrait;

    public function __construct()
    {
        $this->namespace = 'accounting/v1';
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
                    'args' => $this->get_endpoint_args_for_item_schema(true),
                ),
            )
        );
    }

    private function fix_message($message, $is_json)
    {
        if (is_object($message)) {
            return $is_json ? json_encode($message) : json_encode($message, JSON_PRETTY_PRINT);
        }
        if (is_array($message)) {
            return $is_json ? json_encode($message) : json_encode($message, JSON_PRETTY_PRINT);
        }
        return $message;
    }
    public function log_message($request)
    {
        $params = $request->get_json_params();

        Logger::add($params['function'] . ' : ' . $this->fix_message($params['message'], $params['is_json']));

        return new WP_REST_Response('Message logged', 200);
    }



}
