<?php

namespace BjornTech\AccountingReport\Rest;

use WC_REST_Data_Controller;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined('ABSPATH') || exit;

class RestEuCountriesController extends WC_REST_Data_Controller
{

    use RestControllerTrait;

    /**
     * Endpoint namespace.
     *
     * @var string
     */
    protected $namespace = 'wc/v3';

    /**
     * Route base.
     *
     * @var string
     */
    protected $rest_base = 'data/eu-countries';

    /**
     * Register routes.
     *
     * @since 1.0.0
     */
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
                ),
                'schema' => array($this, 'get_public_item_schema'),
            )
        );
    }

    /**
     * Get a list of countries and states.
     *
     * @param  string          $country_code Country code.
     * @param  WP_REST_Request $request      Request data.
     * @return array|mixed Response data, ready for insertion into collection data.
     */
    public function get_country($country_code, $request)
    {
        $countries = WC()->countries->get_countries();

        if (!array_key_exists($country_code, $countries)) {
            return false;
        }

        $country = array(
            'code' => $country_code,
            'name' => $countries[$country_code],
        );

        return $country;
    }

    /**
     * Return a list of countries included in the european-union.
     *
     * @since  1.0.0
     * @param  WP_REST_Request $request Request data.
     * @return WP_Error|WP_REST_Response
     */
    public function get_items($request)
    {

        $scope = $request->get_param('scope');

        $countries = WC()->countries->get_european_union_countries($scope);
        $data = array();

        foreach ($countries as $country_code) {
            $country = $this->get_country($country_code, $request);
            $response = $this->prepare_item_for_response($country, $request);
            $data[] = $this->prepare_response_for_collection($response);
        }

        return rest_ensure_response($data);
    }

    /**
     * Prepare the data object for response.
     *
     * @since  3.5.0
     * @param mixed          $item Data object.
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response $response Response data.
     */
    public function prepare_item_for_response($item, $request)
    {
        $data = $this->add_additional_fields_to_object($item, $request);
        $data = $this->filter_response_by_context($data, 'view');
        $response = rest_ensure_response($data);
        return $response;
    }

    /**
     * Get the location schema, conforming to JSON Schema.
     *
     * @since  1.0.0
     * @return array
     */
    public function get_item_schema()
    {
        $schema = array(
            '$schema' => 'http://json-schema.org/draft-04/schema#',
            'title' => 'data_european_countries',
            'type' => 'object',
            'properties' => array(
                'code' => array(
                    'type' => 'string',
                    'description' => __('ISO3166 alpha-2 country code.', 'woocommerce'),
                    'context' => array('view'),
                    'readonly' => true,
                ),
                'name' => array(
                    'type' => 'string',
                    'description' => __('Full name of country.', 'woocommerce'),
                    'context' => array('view'),
                    'readonly' => true,
                ),
            ),
        );

        return $this->add_additional_fields_schema($schema);
    }
}
