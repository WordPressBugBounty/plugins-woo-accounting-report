<?php

namespace BjornTech\AccountingReport\Rest;

use WC_Data;
use WC_Order_item;
use WC_REST_Orders_Controller;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use BjornTech\AccountingReport\Logger;
use Automattic\WooCommerce\Utilities\OrderUtil;


defined('ABSPATH') || exit;
/**
 * REST API Refunds controller class.
 *
 * @package WooCommerce\RestApi
 * @extends WC_REST_Orders_Controller
 */
class RestRefundsController extends WC_REST_Orders_Controller
{

    /**
     * Endpoint namespace.
     * @var string
     */
    protected $namespace = 'wc/v3/accounting';

    /**
     * Route base.
     *
     * @var string
     */
    protected $rest_base = 'refunds';

    /**
     * Post type.
     *
     * @var string
     */
    protected $post_type = 'shop_order_refund';

    /**
     * Stores the request.
     *
     * @var array
     */
    protected $request = array();

    /**
     * Register the routes for order refunds.
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
                    'permission_callback' => array($this, 'get_items_permissions_check'),
                    'args' => $this->get_collection_params(),
                ),
                'schema' => array($this, 'get_public_item_schema'),
            )
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>[\d]+)',
            array(
                'args' => array(
                    'order_id' => array(
                        'description' => __('The order ID.', 'woocommerce'),
                        'type' => 'integer',
                    ),
                    'id' => array(
                        'description' => __('Unique identifier for the resource.', 'woocommerce'),
                        'type' => 'integer',
                    ),
                ),
                array(
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => array($this, 'get_item'),
                    'permission_callback' => array($this, 'get_item_permissions_check'),
                    'args' => array(
                        'context' => $this->get_context_param(array('default' => 'view')),
                    ),
                ),
                'schema' => array($this, 'get_public_item_schema'),
            )
        );
    }

    /**
     * Get object.
     *
     * @since  3.0.0
     * @param  int $id Object ID.
     * @return WC_Data
     */
    protected function get_object($id)
    {
        return wc_get_order($id);
    }

    /**
     * Get formatted item data.
     *
     * @since  3.0.0
     * @param  WC_Data $object WC_Data instance.
     * @return array
     */
    protected function get_formatted_item_data($object)
    {
        $parent_id = $object->get_parent_id();
        $parent = wc_get_order($parent_id);
        $data = $object->get_data();
        $format_decimal = array('amount');
        $extra_fields = array('id', 'number', 'line_items', 'tax_lines', 'shipping_lines', 'fee_lines', 'payment_method', 'payment_method_title', 'billing', 'item_total');
        $format_date = array('date_created');
        $format_line_items = array('line_items', 'shipping_lines', 'tax_lines', 'fee_lines');

        foreach ($extra_fields as $field) {
            switch ($field) {
                case 'id':
                    $data['id'] = $object->get_id();
                    break;
                case 'number';
                    $data['number'] = $parent->get_order_number();
                    break;
                case 'payment_method':
                    $data['payment_method'] = $parent->get_payment_method();
                    break;
                case 'payment_method_title':
                    $data['payment_method_title'] = $parent->get_payment_method_title();
                    break;
                case 'tax_lines':
                    $data['tax_lines'] = $parent->get_items('tax');
                    break;
                case 'line_items':
                    $data['line_items'] = $object->get_items('line_item');
                    break;
                case 'shipping_lines':
                    $data['shipping_lines'] = $object->get_items('shipping');
                    break;
                case 'fee_lines':
                    $data['fee_lines'] = $object->get_items('fee');
                    break;
                case 'billing':
                    $data['billing'] = array(
                        'company' => $parent->get_billing_company(),
                        'first_name' => $parent->get_billing_first_name(),
                        'last_name' => $parent->get_billing_last_name(),
                        'country' => $parent->get_billing_country(),
                    );
                    break;
                case 'item_total':
                    $data['item_total'] = 0;
                    foreach ($object->get_items('line_item') as $item) {
                        $data['item_total'] += $object->get_item_total($item, false, false);
                    }
            }
        }

        /*     foreach ( $refund->get_items( $item_type ) as $refunded_item ) {
        if ( absint( $refunded_item->get_meta( '_refunded_item_id' ) ) === $item_id ) {
        $qty += $refunded_item->get_quantity();
        }
        }*/
        // Format decimal values.
        foreach ($format_decimal as $key) {
            $data[$key] = wc_format_decimal($data[$key], $this->request['dp']);
        }

        // Format date values.
        foreach ($format_date as $key) {
            $datetime = $data[$key];
            $data[$key] = wc_rest_prepare_date_response($datetime, false);
            $data[$key . '_gmt'] = wc_rest_prepare_date_response($datetime);
        }

        // Format line items.
        foreach ($format_line_items as $key) {
            $data[$key] = array_values(array_map(array($this, 'get_order_item_data'), $data[$key]));
        }

        return $data;

    }

    /**
     * Expands an order item to get its data.
     *
     * @param WC_Order_item $item Order item data.
     * @return array
     */
    protected function get_order_item_data($item)
    {
        $data = $item->get_data();
        $format_decimal = array('subtotal', 'subtotal_tax', 'total', 'total_tax', 'tax_total', 'shipping_tax_total');

        // Format decimal values.
        foreach ($format_decimal as $key) {
            if (isset($data[$key])) {
                $data[$key] = wc_format_decimal($data[$key], $this->request['dp']);
            }
        }

        // Add SKU and PRICE to products.
        if (is_callable(array($item, 'get_product'))) {
            $data['sku'] = $item->get_product() ? $item->get_product()->get_sku() : null;
            $data['price'] = $item->get_quantity() ? wc_format_decimal($item->get_total() / $item->get_quantity(), $this->request['dp']) : 0;
        }

        // Add parent_name if the product is a variation.
        if (is_callable(array($item, 'get_product'))) {
            $product = $item->get_product();

            if (is_callable(array($product, 'get_parent_data'))) {
                $data['parent_name'] = $product->get_title();
            } else {
                $data['parent_name'] = null;
            }
        }

        // Format taxes.
        if (!empty($data['taxes']['total'])) {
            $taxes = array();

            foreach ($data['taxes']['total'] as $tax_rate_id => $tax) {
                $taxes[] = array(
                    'id' => $tax_rate_id,
                    'rate' => $tax_rate_id,
                    'total' => $tax,
                    'subtotal' => isset($data['taxes']['subtotal'][$tax_rate_id]) ? $data['taxes']['subtotal'][$tax_rate_id] : '',
                );
            }
            $data['taxes'] = $taxes;
        } elseif (isset($data['taxes'])) {
            $data['taxes'] = array();
        }

        // Remove names for coupons, taxes and shipping.
        if (isset($data['code']) || isset($data['rate_code']) || isset($data['method_title'])) {
            unset($data['name']);
        }

        // Remove props we don't want to expose.
        unset($data['order_id']);
        unset($data['type']);

        // Expand meta_data to include user-friendly values.
        $formatted_meta_data = $item->get_formatted_meta_data(null, true);
        $data['meta_data'] = array_map(
            array($this, 'merge_meta_item_with_formatted_meta_display_attributes'),
            $data['meta_data'],
            array_fill(0, count($data['meta_data']), $formatted_meta_data)
        );

        return $data;
    }

    /**
     * Prepare a single order output for response.
     *
     * @since  3.0.0
     *
     * @param  WC_Data         $object  Object data.
     * @param  WP_REST_Request $request Request object.
     *
     * @return WP_Error|WP_REST_Response
     */
    public function prepare_object_for_response($object, $request)
    {
        $this->request = $request;
        $this->request['dp'] = is_null($this->request['dp']) ? wc_get_price_decimals() : absint($this->request['dp']);

        if (!$object || !$object->get_parent_id()) {
            return new WP_Error('woocommerce_rest_invalid_order_refund_id', __('Invalid order refund ID.', 'woocommerce'), 404);
        }

        $data = $this->get_formatted_item_data($object);
        $context = !empty($request['context']) ? $request['context'] : 'view';
        $data = $this->add_additional_fields_to_object($data, $request);
        $data = $this->filter_response_by_context($data, $context);

        // Wrap the data in a response object.
        $response = rest_ensure_response($data);

        $response->add_links($this->prepare_links($object, $request));

        /**
         * Filter the data for a response.
         *
         * The dynamic portion of the hook name, $this->post_type,
         * refers to object type being prepared for the response.
         *
         * @param WP_REST_Response $response The response object.
         * @param WC_Data          $object   Object data.
         * @param WP_REST_Request  $request  Request object.
         */
        return apply_filters("woocommerce_rest_prepare_{$this->post_type}_object", $response, $object, $request);
    }

    /**
     * Get a collection of posts.
     *
     * @param WP_REST_Request $request Full details about the request.
     * @return WP_Error|WP_REST_Response
     */
    public function get_items($request)
    {
        $query_args = $this->prepare_objects_query($request);
        if (is_wp_error(current($query_args))) {
            return current($query_args);
        }

        $date_field = !empty($request['date_completed']) ? 'date_completed' : (!empty($request['date_paid']) ? 'date_paid' : 'date_created');

        $params = array(
            $date_field => $request[$date_field],
            'paginate' => true,
            'type' => 'shop_order_refund',
            'limit' => $request['limit'],
            'page' => $request['page'],
        );

        $query = new \WC_Order_Query($params);

        $results = $query->get_orders();

        $objects = array();
        foreach ($results->orders as $object) {
            if (!wc_rest_check_post_permissions($this->post_type, 'read', $object->get_id())) {
                continue;
            }

            $data = $this->prepare_object_for_response($object, $request);
            $objects[] = $this->prepare_response_for_collection($data);
        }

        $page = (int) $query_args['paged'];
        $max_pages = $results->max_num_pages;

        $response = rest_ensure_response($objects);
        $response->header('X-WP-Total', $results->total);
        $response->header('X-WP-TotalPages', (int) $max_pages);

        $base = $this->rest_base;
        $attrib_prefix = '(?P<';
        if (strpos($base, $attrib_prefix) !== false) {
            $attrib_names = array();
            preg_match('/\(\?P<[^>]+>.*\)/', $base, $attrib_names, PREG_OFFSET_CAPTURE);
            foreach ($attrib_names as $attrib_name_match) {
                $beginning_offset = strlen($attrib_prefix);
                $attrib_name_end = strpos($attrib_name_match[0], '>', $attrib_name_match[1]);
                $attrib_name = substr($attrib_name_match[0], $beginning_offset, $attrib_name_end - $beginning_offset);
                if (isset($request[$attrib_name])) {
                    $base = str_replace("(?P<$attrib_name>[\d]+)", $request[$attrib_name], $base);
                }
            }
        }
        $base = add_query_arg($request->get_query_params(), rest_url(sprintf('/%s/%s', $this->namespace, $base)));

        if ($page > 1) {
            $prev_page = $page - 1;
            if ($prev_page > $max_pages) {
                $prev_page = $max_pages;
            }
            $prev_link = add_query_arg('page', $prev_page, $base);
            $response->link_header('prev', $prev_link);
        }
        if ($max_pages > $page) {
            $next_page = $page + 1;
            $next_link = add_query_arg('page', $next_page, $base);
            $response->link_header('next', $next_link);
        }

        Logger::add('get_refund_items: ' . json_encode($response->data));

        return $response;
    }

    /**
     * Prepare links for the request.
     *
     * @param WC_Data         $object  Object data.
     * @param WP_REST_Request $request Request object.
     * @return array                   Links for the given post.
     */
    protected function prepare_links($object, $request)
    {
        $base = str_replace('(?P<order_id>[\d]+)', $object->get_parent_id(), $this->rest_base);
        $links = array(
            'self' => array(
                'href' => rest_url(sprintf('/%s/%s/%d', $this->namespace, $base, $object->get_id())),
            ),
            'collection' => array(
                'href' => rest_url(sprintf('/%s/%s', $this->namespace, $base)),
            ),
            'up' => array(
                'href' => rest_url(sprintf('/%s/orders/%d', $this->namespace, $object->get_parent_id())),
            ),
        );

        return $links;
    }

    /**
     * Prepare objects query.
     *
     * @since  3.0.0
     * @param  WP_REST_Request $request Full details about the request.
     * @return array
     */
    protected function prepare_objects_query($request)
    {
        $args = parent::prepare_objects_query($request);

        $args['post_status'] = array_keys(wc_get_order_statuses());

        return $args;
    }

    /**
     * Get the refund schema, conforming to JSON Schema.
     *
     * @return array
     */
    public function get_item_schema()
    {
        $schema = array(
            '$schema' => 'http://json-schema.org/draft-04/schema#',
            'title' => $this->post_type,
            'type' => 'object',
            'properties' => array(
                'id' => array(
                    'description' => __('Unique identifier for the resource.', 'woocommerce'),
                    'type' => 'integer',
                    'context' => array('view', 'edit'),
                    'readonly' => true,
                ),
                'date_created' => array(
                    'description' => __("The date the order refund was created, in the site's timezone.", 'woocommerce'),
                    'type' => 'date-time',
                    'context' => array('view', 'edit'),
                    'readonly' => true,
                ),
                'date_created_gmt' => array(
                    'description' => __('The date the order refund was created, as GMT.', 'woocommerce'),
                    'type' => 'date-time',
                    'context' => array('view', 'edit'),
                    'readonly' => true,
                ),
                'amount' => array(
                    'description' => __('Refund amount.', 'woocommerce'),
                    'type' => 'string',
                    'context' => array('view', 'edit'),
                ),
                'item_total' => array(
                    'description' => __('Item total amount.', 'woocommerce'),
                    'type' => 'string',
                    'context' => array('view', 'edit'),
                ),
                'reason' => array(
                    'description' => __('Reason for refund.', 'woocommerce'),
                    'type' => 'string',
                    'context' => array('view', 'edit'),
                ),
                'refunded_by' => array(
                    'description' => __('User ID of user who created the refund.', 'woocommerce'),
                    'type' => 'integer',
                    'context' => array('view', 'edit'),
                ),
                'refunded_payment' => array(
                    'description' => __('If the payment was refunded via the API.', 'woocommerce'),
                    'type' => 'boolean',
                    'context' => array('view'),
                    'readonly' => true,
                ),
                'meta_data' => array(
                    'description' => __('Meta data.', 'woocommerce'),
                    'type' => 'array',
                    'context' => array('view', 'edit'),
                    'items' => array(
                        'type' => 'object',
                        'properties' => array(
                            'id' => array(
                                'description' => __('Meta ID.', 'woocommerce'),
                                'type' => 'integer',
                                'context' => array('view', 'edit'),
                                'readonly' => true,
                            ),
                            'key' => array(
                                'description' => __('Meta key.', 'woocommerce'),
                                'type' => 'string',
                                'context' => array('view', 'edit'),
                            ),
                            'value' => array(
                                'description' => __('Meta value.', 'woocommerce'),
                                'type' => 'mixed',
                                'context' => array('view', 'edit'),
                            ),
                        ),
                    ),
                ),
                'line_items' => array(
                    'description' => __('Line items data.', 'woocommerce'),
                    'type' => 'array',
                    'context' => array('view', 'edit'),
                    'items' => array(
                        'type' => 'object',
                        'properties' => array(
                            'id' => array(
                                'description' => __('Item ID.', 'woocommerce'),
                                'type' => 'integer',
                                'context' => array('view', 'edit'),
                                'readonly' => true,
                            ),
                            'name' => array(
                                'description' => __('Product name.', 'woocommerce'),
                                'type' => 'mixed',
                                'context' => array('view', 'edit'),
                            ),
                            'parent_name' => array(
                                'description' => __('Parent product name if the product is a variation.', 'woocommerce'),
                                'type' => 'string',
                                'context' => array('view', 'edit'),
                            ),
                            'product_id' => array(
                                'description' => __('Product ID.', 'woocommerce'),
                                'type' => 'mixed',
                                'context' => array('view', 'edit'),
                            ),
                            'variation_id' => array(
                                'description' => __('Variation ID, if applicable.', 'woocommerce'),
                                'type' => 'integer',
                                'context' => array('view', 'edit'),
                            ),
                            'quantity' => array(
                                'description' => __('Quantity ordered.', 'woocommerce'),
                                'type' => 'integer',
                                'context' => array('view', 'edit'),
                            ),
                            'tax_class' => array(
                                'description' => __('Tax class of product.', 'woocommerce'),
                                'type' => 'string',
                                'context' => array('view', 'edit'),
                            ),
                            'subtotal' => array(
                                'description' => __('Line subtotal (before discounts).', 'woocommerce'),
                                'type' => 'string',
                                'context' => array('view', 'edit'),
                            ),
                            'subtotal_tax' => array(
                                'description' => __('Line subtotal tax (before discounts).', 'woocommerce'),
                                'type' => 'string',
                                'context' => array('view', 'edit'),
                                'readonly' => true,
                            ),
                            'total' => array(
                                'description' => __('Line total (after discounts).', 'woocommerce'),
                                'type' => 'string',
                                'context' => array('view', 'edit'),
                            ),
                            'total_tax' => array(
                                'description' => __('Line total tax (after discounts).', 'woocommerce'),
                                'type' => 'string',
                                'context' => array('view', 'edit'),
                                'readonly' => true,
                            ),
                            'taxes' => array(
                                'description' => __('Line taxes.', 'woocommerce'),
                                'type' => 'array',
                                'context' => array('view', 'edit'),
                                'readonly' => true,
                                'items' => array(
                                    'type' => 'object',
                                    'properties' => array(
                                        'id' => array(
                                            'description' => __('Tax rate ID.', 'woocommerce'),
                                            'type' => 'integer',
                                            'context' => array('view', 'edit'),
                                        ),
                                        'total' => array(
                                            'description' => __('Tax total.', 'woocommerce'),
                                            'type' => 'string',
                                            'context' => array('view', 'edit'),
                                        ),
                                        'subtotal' => array(
                                            'description' => __('Tax subtotal.', 'woocommerce'),
                                            'type' => 'string',
                                            'context' => array('view', 'edit'),
                                        ),
                                    ),
                                ),
                            ),
                            'meta_data' => array(
                                'description' => __('Meta data.', 'woocommerce'),
                                'type' => 'array',
                                'context' => array('view', 'edit'),
                                'items' => array(
                                    'type' => 'object',
                                    'properties' => array(
                                        'id' => array(
                                            'description' => __('Meta ID.', 'woocommerce'),
                                            'type' => 'integer',
                                            'context' => array('view', 'edit'),
                                            'readonly' => true,
                                        ),
                                        'key' => array(
                                            'description' => __('Meta key.', 'woocommerce'),
                                            'type' => 'string',
                                            'context' => array('view', 'edit'),
                                        ),
                                        'value' => array(
                                            'description' => __('Meta value.', 'woocommerce'),
                                            'type' => 'mixed',
                                            'context' => array('view', 'edit'),
                                        ),
                                        'display_key' => array(
                                            'description' => __('Meta key for UI display.', 'woocommerce'),
                                            'type' => 'string',
                                            'context' => array('view', 'edit'),
                                        ),
                                        'display_value' => array(
                                            'description' => __('Meta value for UI display.', 'woocommerce'),
                                            'type' => 'string',
                                            'context' => array('view', 'edit'),
                                        ),
                                    ),
                                ),
                            ),
                            'sku' => array(
                                'description' => __('Product SKU.', 'woocommerce'),
                                'type' => 'string',
                                'context' => array('view', 'edit'),
                                'readonly' => true,
                            ),
                            'price' => array(
                                'description' => __('Product price.', 'woocommerce'),
                                'type' => 'number',
                                'context' => array('view', 'edit'),
                                'readonly' => true,
                            ),
                        ),
                    ),
                ),
                'tax_lines' => array(
                    'description' => __('Tax lines data.', 'woocommerce'),
                    'type' => 'array',
                    'context' => array('view', 'edit'),
                    'readonly' => true,
                    'items' => array(
                        'type' => 'object',
                        'properties' => array(
                            'id' => array(
                                'description' => __('Item ID.', 'woocommerce'),
                                'type' => 'integer',
                                'context' => array('view', 'edit'),
                                'readonly' => true,
                            ),
                            'rate_code' => array(
                                'description' => __('Tax rate code.', 'woocommerce'),
                                'type' => 'string',
                                'context' => array('view', 'edit'),
                                'readonly' => true,
                            ),
                            'rate_id' => array(
                                'description' => __('Tax rate ID.', 'woocommerce'),
                                'type' => 'string',
                                'context' => array('view', 'edit'),
                                'readonly' => true,
                            ),
                            'label' => array(
                                'description' => __('Tax rate label.', 'woocommerce'),
                                'type' => 'string',
                                'context' => array('view', 'edit'),
                                'readonly' => true,
                            ),
                            'compound' => array(
                                'description' => __('Show if is a compound tax rate.', 'woocommerce'),
                                'type' => 'boolean',
                                'context' => array('view', 'edit'),
                                'readonly' => true,
                            ),
                            'tax_total' => array(
                                'description' => __('Tax total (not including shipping taxes).', 'woocommerce'),
                                'type' => 'string',
                                'context' => array('view', 'edit'),
                                'readonly' => true,
                            ),
                            'shipping_tax_total' => array(
                                'description' => __('Shipping tax total.', 'woocommerce'),
                                'type' => 'string',
                                'context' => array('view', 'edit'),
                                'readonly' => true,
                            ),
                            'meta_data' => array(
                                'description' => __('Meta data.', 'woocommerce'),
                                'type' => 'array',
                                'context' => array('view', 'edit'),
                                'items' => array(
                                    'type' => 'object',
                                    'properties' => array(
                                        'id' => array(
                                            'description' => __('Meta ID.', 'woocommerce'),
                                            'type' => 'integer',
                                            'context' => array('view', 'edit'),
                                            'readonly' => true,
                                        ),
                                        'key' => array(
                                            'description' => __('Meta key.', 'woocommerce'),
                                            'type' => 'string',
                                            'context' => array('view', 'edit'),
                                        ),
                                        'value' => array(
                                            'description' => __('Meta value.', 'woocommerce'),
                                            'type' => 'mixed',
                                            'context' => array('view', 'edit'),
                                        ),
                                    ),
                                ),
                            ),
                        ),
                    ),
                ),
                'shipping_lines' => array(
                    'description' => __('Shipping lines data.', 'woocommerce'),
                    'type' => 'array',
                    'context' => array('view', 'edit'),
                    'items' => array(
                        'type' => 'object',
                        'properties' => array(
                            'id' => array(
                                'description' => __('Item ID.', 'woocommerce'),
                                'type' => 'integer',
                                'context' => array('view', 'edit'),
                                'readonly' => true,
                            ),
                            'method_title' => array(
                                'description' => __('Shipping method name.', 'woocommerce'),
                                'type' => 'mixed',
                                'context' => array('view', 'edit'),
                            ),
                            'method_id' => array(
                                'description' => __('Shipping method ID.', 'woocommerce'),
                                'type' => 'mixed',
                                'context' => array('view', 'edit'),
                            ),
                            'instance_id' => array(
                                'description' => __('Shipping instance ID.', 'woocommerce'),
                                'type' => 'string',
                                'context' => array('view', 'edit'),
                            ),
                            'total' => array(
                                'description' => __('Line total (after discounts).', 'woocommerce'),
                                'type' => 'string',
                                'context' => array('view', 'edit'),
                            ),
                            'total_tax' => array(
                                'description' => __('Line total tax (after discounts).', 'woocommerce'),
                                'type' => 'string',
                                'context' => array('view', 'edit'),
                                'readonly' => true,
                            ),
                            'taxes' => array(
                                'description' => __('Line taxes.', 'woocommerce'),
                                'type' => 'array',
                                'context' => array('view', 'edit'),
                                'readonly' => true,
                                'items' => array(
                                    'type' => 'object',
                                    'properties' => array(
                                        'id' => array(
                                            'description' => __('Tax rate ID.', 'woocommerce'),
                                            'type' => 'integer',
                                            'context' => array('view', 'edit'),
                                            'readonly' => true,
                                        ),
                                        'total' => array(
                                            'description' => __('Tax total.', 'woocommerce'),
                                            'type' => 'string',
                                            'context' => array('view', 'edit'),
                                            'readonly' => true,
                                        ),
                                    ),
                                ),
                            ),
                            'meta_data' => array(
                                'description' => __('Meta data.', 'woocommerce'),
                                'type' => 'array',
                                'context' => array('view', 'edit'),
                                'items' => array(
                                    'type' => 'object',
                                    'properties' => array(
                                        'id' => array(
                                            'description' => __('Meta ID.', 'woocommerce'),
                                            'type' => 'integer',
                                            'context' => array('view', 'edit'),
                                            'readonly' => true,
                                        ),
                                        'key' => array(
                                            'description' => __('Meta key.', 'woocommerce'),
                                            'type' => 'string',
                                            'context' => array('view', 'edit'),
                                        ),
                                        'value' => array(
                                            'description' => __('Meta value.', 'woocommerce'),
                                            'type' => 'mixed',
                                            'context' => array('view', 'edit'),
                                        ),
                                    ),
                                ),
                            ),
                        ),
                    ),
                ),
                'fee_lines' => array(
                    'description' => __('Fee lines data.', 'woocommerce'),
                    'type' => 'array',
                    'context' => array('view', 'edit'),
                    'items' => array(
                        'type' => 'object',
                        'properties' => array(
                            'id' => array(
                                'description' => __('Item ID.', 'woocommerce'),
                                'type' => 'integer',
                                'context' => array('view', 'edit'),
                                'readonly' => true,
                            ),
                            'name' => array(
                                'description' => __('Fee name.', 'woocommerce'),
                                'type' => 'mixed',
                                'context' => array('view', 'edit'),
                            ),
                            'tax_class' => array(
                                'description' => __('Tax class of fee.', 'woocommerce'),
                                'type' => 'string',
                                'context' => array('view', 'edit'),
                            ),
                            'tax_status' => array(
                                'description' => __('Tax status of fee.', 'woocommerce'),
                                'type' => 'string',
                                'context' => array('view', 'edit'),
                                'enum' => array('taxable', 'none'),
                            ),
                            'total' => array(
                                'description' => __('Line total (after discounts).', 'woocommerce'),
                                'type' => 'string',
                                'context' => array('view', 'edit'),
                            ),
                            'total_tax' => array(
                                'description' => __('Line total tax (after discounts).', 'woocommerce'),
                                'type' => 'string',
                                'context' => array('view', 'edit'),
                                'readonly' => true,
                            ),
                            'taxes' => array(
                                'description' => __('Line taxes.', 'woocommerce'),
                                'type' => 'array',
                                'context' => array('view', 'edit'),
                                'readonly' => true,
                                'items' => array(
                                    'type' => 'object',
                                    'properties' => array(
                                        'id' => array(
                                            'description' => __('Tax rate ID.', 'woocommerce'),
                                            'type' => 'integer',
                                            'context' => array('view', 'edit'),
                                            'readonly' => true,
                                        ),
                                        'total' => array(
                                            'description' => __('Tax total.', 'woocommerce'),
                                            'type' => 'string',
                                            'context' => array('view', 'edit'),
                                            'readonly' => true,
                                        ),
                                        'subtotal' => array(
                                            'description' => __('Tax subtotal.', 'woocommerce'),
                                            'type' => 'string',
                                            'context' => array('view', 'edit'),
                                            'readonly' => true,
                                        ),
                                    ),
                                ),
                            ),
                            'meta_data' => array(
                                'description' => __('Meta data.', 'woocommerce'),
                                'type' => 'array',
                                'context' => array('view', 'edit'),
                                'items' => array(
                                    'type' => 'object',
                                    'properties' => array(
                                        'id' => array(
                                            'description' => __('Meta ID.', 'woocommerce'),
                                            'type' => 'integer',
                                            'context' => array('view', 'edit'),
                                            'readonly' => true,
                                        ),
                                        'key' => array(
                                            'description' => __('Meta key.', 'woocommerce'),
                                            'type' => 'string',
                                            'context' => array('view', 'edit'),
                                        ),
                                        'value' => array(
                                            'description' => __('Meta value.', 'woocommerce'),
                                            'type' => 'mixed',
                                            'context' => array('view', 'edit'),
                                        ),
                                    ),
                                ),
                            ),
                        ),
                    ),
                ),
                'api_refund' => array(
                    'description' => __('When true, the payment gateway API is used to generate the refund.', 'woocommerce'),
                    'type' => 'boolean',
                    'context' => array('edit'),
                    'default' => true,
                ),
            ),
        );

        $schema['properties']['line_items']['items']['properties']['refund_total'] = array(
            'description' => __('Amount that will be refunded for this line item (excluding taxes).', 'woocommerce'),
            'type' => 'number',
            'context' => array('edit'),
            'readonly' => true,
        );

        $schema['properties']['line_items']['items']['properties']['taxes']['items']['properties']['refund_total'] = array(
            'description' => __('Amount that will be refunded for this tax.', 'woocommerce'),
            'type' => 'number',
            'context' => array('edit'),
            'readonly' => true,
        );

        $schema['properties']['api_restock'] = array(
            'description' => __('When true, refunded items are restocked.', 'woocommerce'),
            'type' => 'boolean',
            'context' => array('edit'),
            'default' => true,
        );

        return $this->add_additional_fields_schema($schema);
    }

    /**
     * Get the query params for collections.
     *
     * @return array
     */
    public function get_collection_params()
    {
        $params = parent::get_collection_params();

        unset($params['status'], $params['customer'], $params['product']);

        return $params;
    }

    /**
     * Merge the `$formatted_meta_data` `display_key` and `display_value` attribute values into the corresponding
     * {@link WC_Meta_Data}. Returns the merged array.
     *
     * @param WC_Meta_Data $meta_item           An object from {@link WC_Order_Item::get_meta_data()}.
     * @param array        $formatted_meta_data An object result from {@link WC_Order_Item::get_formatted_meta_data}.
     * The keys are the IDs of {@link WC_Meta_Data}.
     *
     * @return array
     */
    private function merge_meta_item_with_formatted_meta_display_attributes($meta_item, $formatted_meta_data)
    {
        $result = array(
            'id' => $meta_item->id,
            'key' => $meta_item->key,
            'value' => $meta_item->value,
            'display_key' => $meta_item->key,
            // Default to original key, in case a formatted key is not available.
            'display_value' => $meta_item->value,
            // Default to original value, in case a formatted value is not available.
        );

        if (array_key_exists($meta_item->id, $formatted_meta_data)) {
            $formatted_meta_item = $formatted_meta_data[$meta_item->id];

            $result['display_key'] = wc_clean($formatted_meta_item->display_key);
            $result['display_value'] = wc_clean($formatted_meta_item->display_value);
        }

        return $result;
    }
}
