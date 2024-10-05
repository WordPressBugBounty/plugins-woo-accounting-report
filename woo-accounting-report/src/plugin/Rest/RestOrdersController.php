<?php

namespace BjornTech\AccountingReport\Rest;

use WC_Data;
use WC_Data_Store;
use WC_Meta_Data;
use WC_Order_Item;
use WC_REST_Exception;
use WC_REST_Orders_Controller;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use BjornTech\AccountingReport\Logger;

defined('ABSPATH') || exit;

/**
 * REST API Orders controller class.
 *
 * @package WooCommerce\RestApi
 * @extends WC_REST_Orders_Controller
 */
class RestOrdersController extends WC_REST_Orders_Controller
{

    /**
     * Endpoint namespace.
     *
     * @var string
     */
    protected $namespace = 'wc/v3/accounting';

    /**
     * Route base.
     *
     * @var string
     */
    protected $rest_base = 'orders';

    /**
     * Post type.
     *
     * @var string
     */
    protected $post_type = 'shop_order';

    /**
     * If object is hierarchical.
     *
     * @var bool
     */
    protected $hierarchical = true;

    /**
     * Stores the request.
     *
     * @var array
     */
    protected $request = array();

    /**
     * Get object. Return false if object is not of required type.
     *
     * @since  3.0.0
     * @param  int $id Object ID.
     * @return WC_Data|bool
     */
    protected function get_object($id)
    {
        $order = wc_get_order($id);
        // In case it's not an order at all, don't expose it via /orders/ path.
        if (!$order) {
            return false;
        }

        return $order;
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
            'type' => 'shop_order',
            'status' => $request['status'],
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

      //  Logger::add('get_order_items: ' . json_encode($response->data));

        return $response;
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

    /**
     * Get formatted item data.
     *
     * @since 3.0.0
     * @param WC_Data $order WC_Data instance.
     *
     * @return array
     */
    protected function get_formatted_item_data($order)
    {

        $extra_fields = array('meta_data', 'line_items', 'tax_lines', 'shipping_lines', 'fee_lines', 'coupon_lines', 'pw_gift_card_lines', 'refunds', 'stripe_fee', 'item_total', 'fee_total', 'buyer_name');
        $format_decimal = array('discount_total', 'discount_tax', 'shipping_total', 'shipping_tax', 'cart_tax', 'total', 'total_tax', 'fee_total', 'fee_tax', 'stripe_fee', 'item_total', 'fee_total');
        $format_date = array('date_created', 'date_modified', 'date_completed', 'date_paid');
        // These fields are dependent on other fields.
        $dependent_fields = array(
            'date_created_gmt' => 'date_created',
            'date_modified_gmt' => 'date_modified',
            'date_completed_gmt' => 'date_completed',
            'date_paid_gmt' => 'date_paid',
        );

        $format_line_items = array('line_items', 'tax_lines', 'shipping_lines', 'fee_lines', 'coupon_lines');

        // Only fetch fields that we need.
        $fields = $this->get_fields_for_response($this->request);
        foreach ($dependent_fields as $field_key => $dependency) {
            if (in_array($field_key, $fields) && !in_array($dependency, $fields)) {
                $fields[] = $dependency;
            }
        }

        $extra_fields = array_intersect($extra_fields, $fields);
        $format_decimal = array_intersect($format_decimal, $fields);
        $format_date = array_intersect($format_date, $fields);

        $format_line_items = array_intersect($format_line_items, $fields);

        $data = $order->get_base_data();

        $line_items = $order->get_items('line_item');
        $fees = $order->get_items('fee');
        // Add extra data as necessary.
        foreach ($extra_fields as $field) {
            switch ($field) {
                case 'meta_data':
                    $data['meta_data'] = $order->get_meta_data();
                    break;
                case 'line_items':
                    $data['line_items'] = $line_items;
                    break;
                case 'tax_lines':
                    $data['tax_lines'] = $order->get_items('tax');
                    break;
                case 'shipping_lines':
                    $data['shipping_lines'] = $order->get_items('shipping');
                    break;
                case 'fee_lines':
                    $data['fee_lines'] = $fees;
                    break;
                case 'coupon_lines':
                    $data['coupon_lines'] = $order->get_items('coupon');
                    break;
                case 'pw_gift_card_lines':
                    foreach ($order->get_items('pw_gift_card') as $gift_card) {
                        $data['pw_gift_card_lines'][] = array(
                            'amount' => wc_format_decimal($gift_card->get_amount(), $this->request['dp']),
                            'card_number' => $gift_card->get_card_number(),
                        );
                    }
                    break;
                case 'refunds':
                    $data['refunds'] = array();
                    foreach ($order->get_refunds() as $refund) {
                        $data['refunds'][] = array(
                            'id' => $refund->get_id(),
                            'reason' => $refund->get_reason() ? $refund->get_reason() : '',
                            'total' => '-' . wc_format_decimal($refund->get_amount(), $this->request['dp']),
                        );
                    }
                    break;
                case 'stripe_fee':
                    $data['stripe_fee'] = '-' . $order->get_meta('_stripe_fee');
                    break;
                case 'item_total':
                    $data['item_total'] = 0;
                    foreach ($line_items as $item) {
                        $data['item_total'] += $order->get_item_total($item, false, false);
                    }
                    break;
                case 'fee_total':
                    $data['fee_total'] = 0;
                    foreach ($fees as $fee) {
                        $data['fee_total'] += $order->get_item_total($fee, false, false);
                    }
                    break;
                case 'buyer_name':
                    $data['buyer_name'] = $order->get_billing_company() ? $order->get_billing_company() : $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
                    break;

            }
        }

        // Format decimal values.
        foreach ($format_decimal as $key) {
            if (!isset($data[$key])) {
                continue;
            }
            $data[$key] = wc_format_decimal($data[$key], $this->request['dp']);
        }

        // Format date values.
        foreach ($format_date as $key) {
            $datetime = $data[$key];
            $data[$key] = wc_rest_prepare_date_response($datetime, false);
            $data[$key . '_gmt'] = wc_rest_prepare_date_response($datetime);
        }

        // Format the order status.
        $data['status'] = 'wc-' === substr($data['status'], 0, 3) ? substr($data['status'], 3) : $data['status'];

        // Format line items.
        foreach ($format_line_items as $key) {
            $data[$key] = array_values(array_map(array($this, 'get_order_item_data'), $data[$key]));
        }

        $allowed_fields = array(
            'id',
            'parent_id',
            'number',
            'order_key',
            'created_via',
            'version',
            'status',
            'currency',
            'date_created',
            'date_created_gmt',
            'date_modified',
            'date_modified_gmt',
            'discount_total',
            'discount_tax',
            'shipping_total',
            'shipping_tax',
            'buyer_name',
            'fee_total',
            'fee_tax',
            'cart_tax',
            'total',
            'total_tax',
            'item_total',
            'prices_include_tax',
            'customer_id',
            'customer_ip_address',
            'customer_user_agent',
            'customer_note',
            'billing',
            'shipping',
            'payment_method',
            'payment_method_title',
            'transaction_id',
            'date_paid',
            'date_paid_gmt',
            'date_completed',
            'date_completed_gmt',
            'cart_hash',
            'meta_data',
            'line_items',
            'tax_lines',
            'shipping_lines',
            'fee_lines',
            'coupon_lines',
            'pw_gift_card_lines',
            'refunds',
            'stripe_fee',
        );

        $data = array_intersect_key($data, array_flip($allowed_fields));

        return $data;
    }

    /**
     * Prepare a single order output for response.
     *
     * @since  3.0.0
     * @param  WC_Data         $object  Object data.
     * @param  WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function prepare_object_for_response($object, $request)
    {

        $this->request = $request;
        $this->request['dp'] = is_null($this->request['dp']) ? wc_get_price_decimals() : absint($this->request['dp']);
        $request['context'] = !empty($request['context']) ? $request['context'] : 'view';
        $data = $this->get_formatted_item_data($object);
        $data = $this->add_additional_fields_to_object($data, $request);
        $data = $this->filter_response_by_context($data, $request['context']);
        $response = rest_ensure_response($data);
        $response->add_links($this->prepare_links($object, $request));

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
        $links = array(
            'self' => array(
                'href' => rest_url(sprintf('/%s/%s/%d', $this->namespace, $this->rest_base, $object->get_id())),
            ),
            'collection' => array(
                'href' => rest_url(sprintf('/%s/%s', $this->namespace, $this->rest_base)),
            ),
        );

        if (0 !== (int) $object->get_parent_id()) {
            $links['up'] = array(
                'href' => rest_url(sprintf('/%s/orders/%d', $this->namespace, $object->get_parent_id())),
            );
        }

        return $links;
    }

    /**
     * Prepare objects query.
     *
     * @since  1.0.0
     * @param  WP_REST_Request $request Full details about the request.
     * @return array
     */
    protected function prepare_objects_query($request)
    {

        $args = parent::prepare_objects_query($request);

        $args['post_status'] = array();

        foreach ($request['status'] as $status) {
            if (in_array($status, $this->get_order_statuses(), true)) {
                $args['post_status'][] = 'wc-' . $status;
            } elseif ('any' === $status) {
                $args['post_status'] = 'any';
                break;
            } else {
                $args['post_status'][] = $status;
            }
        }

        $date_queries = array(
            'date_completed' => '_date_completed',
            'date_paid' => '_date_paid',
        );
        foreach ($date_queries as $query_var_key => $db_key) {
            if (isset($request[$query_var_key]) && '' !== $request[$query_var_key]) {
                $args = WC_Data_Store::load('order')->parse_date_for_wp_query($request[$query_var_key], $db_key, $args);
            }
        }

        return $args;
    }

    /**
     * Gets the product ID from the SKU or posted ID.
     *
     * @throws WC_REST_Exception When SKU or ID is not valid.
     * @param array  $posted Request data.
     * @param string $action 'create' to add line item or 'update' to update it.
     * @return int
     */
    protected function get_product_id($posted, $action = 'create')
    {
        if (!empty($posted['sku'])) {
            $product_id = (int) wc_get_product_id_by_sku($posted['sku']);
        } elseif (!empty($posted['product_id']) && empty($posted['variation_id'])) {
            $product_id = (int) $posted['product_id'];
        } elseif (!empty($posted['variation_id'])) {
            $product_id = (int) $posted['variation_id'];
        } elseif ('update' === $action) {
            $product_id = 0;
        } else {
            throw new WC_REST_Exception('woocommerce_rest_required_product_reference', __('Product ID or SKU is required.', 'woocommerce'), 400);
        }
        return $product_id;
    }

    /**
     * Maybe set an item prop if the value was posted.
     *
     * @param WC_Order_Item $item   Order item.
     * @param string        $prop   Order property.
     * @param array         $posted Request data.
     */
    protected function maybe_set_item_prop($item, $prop, $posted)
    {
        if (isset($posted[$prop])) {
            $item->{"set_$prop"}($posted[$prop]);
        }
    }

    /**
     * Maybe set item props if the values were posted.
     *
     * @param WC_Order_Item $item   Order item data.
     * @param string[]      $props  Properties.
     * @param array         $posted Request data.
     */
    protected function maybe_set_item_props($item, $props, $posted)
    {
        foreach ($props as $prop) {
            $this->maybe_set_item_prop($item, $prop, $posted);
        }
    }

    /**
     * Maybe set item meta if posted.
     *
     * @param WC_Order_Item $item   Order item data.
     * @param array         $posted Request data.
     */
    protected function maybe_set_item_meta_data($item, $posted)
    {
        if (!empty($posted['meta_data']) && is_array($posted['meta_data'])) {
            foreach ($posted['meta_data'] as $meta) {
                if (isset($meta['key'])) {
                    $value = isset($meta['value']) ? $meta['value'] : null;
                    $item->update_meta_data($meta['key'], $value, isset($meta['id']) ? $meta['id'] : '');
                }
            }
        }
    }

    /**
     * Get order statuses without prefixes.
     *
     * @return array
     */
    protected function get_order_statuses()
    {
        $order_statuses = array('auto-draft');

        foreach (array_keys(wc_get_order_statuses()) as $status) {
            $order_statuses[] = str_replace('wc-', '', $status);
        }

        return $order_statuses;
    }

    /**
     * Get the Order's schema, conforming to JSON Schema.
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
                'parent_id' => array(
                    'description' => __('Parent order ID.', 'woocommerce'),
                    'type' => 'integer',
                    'context' => array('view', 'edit'),
                ),
                'number' => array(
                    'description' => __('Order number.', 'woocommerce'),
                    'type' => 'string',
                    'context' => array('view', 'edit'),
                    'readonly' => true,
                ),
                'order_key' => array(
                    'description' => __('Order key.', 'woocommerce'),
                    'type' => 'string',
                    'context' => array('view', 'edit'),
                    'readonly' => true,
                ),
                'created_via' => array(
                    'description' => __('Shows where the order was created.', 'woocommerce'),
                    'type' => 'string',
                    'context' => array('view', 'edit'),
                    'readonly' => true,
                ),
                'version' => array(
                    'description' => __('Version of WooCommerce which last updated the order.', 'woocommerce'),
                    'type' => 'integer',
                    'context' => array('view', 'edit'),
                    'readonly' => true,
                ),
                'status' => array(
                    'description' => __('Order status.', 'woocommerce'),
                    'type' => 'string',
                    'default' => 'pending',
                    'enum' => $this->get_order_statuses(),
                    'context' => array('view', 'edit'),
                ),
                'currency' => array(
                    'description' => __('Currency the order was created with, in ISO format.', 'woocommerce'),
                    'type' => 'string',
                    'default' => get_woocommerce_currency(),
                    'enum' => array_keys(get_woocommerce_currencies()),
                    'context' => array('view', 'edit'),
                ),
                'date_created' => array(
                    'description' => __("The date the order was created, in the site's timezone.", 'woocommerce'),
                    'type' => 'date-time',
                    'context' => array('view', 'edit'),
                    'readonly' => true,
                ),
                'date_created_gmt' => array(
                    'description' => __('The date the order was created, as GMT.', 'woocommerce'),
                    'type' => 'date-time',
                    'context' => array('view', 'edit'),
                    'readonly' => true,
                ),
                'date_modified' => array(
                    'description' => __("The date the order was last modified, in the site's timezone.", 'woocommerce'),
                    'type' => 'date-time',
                    'context' => array('view', 'edit'),
                    'readonly' => true,
                ),
                'date_modified_gmt' => array(
                    'description' => __('The date the order was last modified, as GMT.', 'woocommerce'),
                    'type' => 'date-time',
                    'context' => array('view', 'edit'),
                    'readonly' => true,
                ),
                'discount_total' => array(
                    'description' => __('Total discount amount for the order.', 'woocommerce'),
                    'type' => 'string',
                    'context' => array('view', 'edit'),
                    'readonly' => true,
                ),
                'discount_tax' => array(
                    'description' => __('Total discount tax amount for the order.', 'woocommerce'),
                    'type' => 'string',
                    'context' => array('view', 'edit'),
                    'readonly' => true,
                ),
                'shipping_total' => array(
                    'description' => __('Total shipping amount for the order.', 'woocommerce'),
                    'type' => 'string',
                    'context' => array('view', 'edit'),
                    'readonly' => true,
                ),
                'shipping_tax' => array(
                    'description' => __('Total shipping tax amount for the order.', 'woocommerce'),
                    'type' => 'string',
                    'context' => array('view', 'edit'),
                    'readonly' => true,
                ),
                'fee_total' => array(
                    'description' => __('Total shipping amount for the order.', 'woocommerce'),
                    'type' => 'string',
                    'context' => array('view', 'edit'),
                    'readonly' => true,
                ),
                'buyer_name' => array(
                    'description' => __('Buyer name.', 'woocommerce'),
                    'type' => 'string',
                    'context' => array('view', 'edit'),
                    'readonly' => true,
                ),
                'fee_tax' => array(
                    'description' => __('Total shipping tax amount for the order.', 'woocommerce'),
                    'type' => 'string',
                    'context' => array('view', 'edit'),
                    'readonly' => true,
                ),
                'stripe_fee' => array(
                    'description' => __('Total stripe fee for the order.', 'woocommerce'),
                    'type' => 'string',
                    'context' => array('view', 'edit'),
                    'readonly' => true,
                ),
                'cart_tax' => array(
                    'description' => __('Sum of line item taxes only.', 'woocommerce'),
                    'type' => 'string',
                    'context' => array('view', 'edit'),
                    'readonly' => true,
                ),
                'total' => array(
                    'description' => __('Grand total.', 'woocommerce'),
                    'type' => 'string',
                    'context' => array('view', 'edit'),
                    'readonly' => true,
                ),
                'total_tax' => array(
                    'description' => __('Sum of all taxes.', 'woocommerce'),
                    'type' => 'string',
                    'context' => array('view', 'edit'),
                    'readonly' => true,
                ),
                'item_total' => array(
                    'description' => __('Item total ex tax.', 'woocommerce'),
                    'type' => 'string',
                    'context' => array('view', 'edit'),
                    'readonly' => true,
                ),
                'prices_include_tax' => array(
                    'description' => __('True the prices included tax during checkout.', 'woocommerce'),
                    'type' => 'boolean',
                    'context' => array('view', 'edit'),
                    'readonly' => true,
                ),
                'customer_id' => array(
                    'description' => __('User ID who owns the order. 0 for guests.', 'woocommerce'),
                    'type' => 'integer',
                    'default' => 0,
                    'context' => array('view', 'edit'),
                ),
                'customer_ip_address' => array(
                    'description' => __("Customer's IP address.", 'woocommerce'),
                    'type' => 'string',
                    'context' => array('view', 'edit'),
                    'readonly' => true,
                ),
                'customer_user_agent' => array(
                    'description' => __('User agent of the customer.', 'woocommerce'),
                    'type' => 'string',
                    'context' => array('view', 'edit'),
                    'readonly' => true,
                ),
                'customer_note' => array(
                    'description' => __('Note left by customer during checkout.', 'woocommerce'),
                    'type' => 'string',
                    'context' => array('view', 'edit'),
                ),
                'billing' => array(
                    'description' => __('Billing address.', 'woocommerce'),
                    'type' => 'object',
                    'context' => array('view', 'edit'),
                    'properties' => array(
                        'first_name' => array(
                            'description' => __('First name.', 'woocommerce'),
                            'type' => 'string',
                            'context' => array('view', 'edit'),
                        ),
                        'last_name' => array(
                            'description' => __('Last name.', 'woocommerce'),
                            'type' => 'string',
                            'context' => array('view', 'edit'),
                        ),
                        'company' => array(
                            'description' => __('Company name.', 'woocommerce'),
                            'type' => 'string',
                            'context' => array('view', 'edit'),
                        ),
                        'address_1' => array(
                            'description' => __('Address line 1', 'woocommerce'),
                            'type' => 'string',
                            'context' => array('view', 'edit'),
                        ),
                        'address_2' => array(
                            'description' => __('Address line 2', 'woocommerce'),
                            'type' => 'string',
                            'context' => array('view', 'edit'),
                        ),
                        'city' => array(
                            'description' => __('City name.', 'woocommerce'),
                            'type' => 'string',
                            'context' => array('view', 'edit'),
                        ),
                        'state' => array(
                            'description' => __('ISO code or name of the state, province or district.', 'woocommerce'),
                            'type' => 'string',
                            'context' => array('view', 'edit'),
                        ),
                        'postcode' => array(
                            'description' => __('Postal code.', 'woocommerce'),
                            'type' => 'string',
                            'context' => array('view', 'edit'),
                        ),
                        'country' => array(
                            'description' => __('Country code in ISO 3166-1 alpha-2 format.', 'woocommerce'),
                            'type' => 'string',
                            'context' => array('view', 'edit'),
                        ),
                        'email' => array(
                            'description' => __('Email address.', 'woocommerce'),
                            'type' => array('string', 'null'),
                            'format' => 'email',
                            'context' => array('view', 'edit'),
                        ),
                        'phone' => array(
                            'description' => __('Phone number.', 'woocommerce'),
                            'type' => 'string',
                            'context' => array('view', 'edit'),
                        ),
                    ),
                ),
                'shipping' => array(
                    'description' => __('Shipping address.', 'woocommerce'),
                    'type' => 'object',
                    'context' => array('view', 'edit'),
                    'properties' => array(
                        'first_name' => array(
                            'description' => __('First name.', 'woocommerce'),
                            'type' => 'string',
                            'context' => array('view', 'edit'),
                        ),
                        'last_name' => array(
                            'description' => __('Last name.', 'woocommerce'),
                            'type' => 'string',
                            'context' => array('view', 'edit'),
                        ),
                        'company' => array(
                            'description' => __('Company name.', 'woocommerce'),
                            'type' => 'string',
                            'context' => array('view', 'edit'),
                        ),
                        'address_1' => array(
                            'description' => __('Address line 1', 'woocommerce'),
                            'type' => 'string',
                            'context' => array('view', 'edit'),
                        ),
                        'address_2' => array(
                            'description' => __('Address line 2', 'woocommerce'),
                            'type' => 'string',
                            'context' => array('view', 'edit'),
                        ),
                        'city' => array(
                            'description' => __('City name.', 'woocommerce'),
                            'type' => 'string',
                            'context' => array('view', 'edit'),
                        ),
                        'state' => array(
                            'description' => __('ISO code or name of the state, province or district.', 'woocommerce'),
                            'type' => 'string',
                            'context' => array('view', 'edit'),
                        ),
                        'postcode' => array(
                            'description' => __('Postal code.', 'woocommerce'),
                            'type' => 'string',
                            'context' => array('view', 'edit'),
                        ),
                        'country' => array(
                            'description' => __('Country code in ISO 3166-1 alpha-2 format.', 'woocommerce'),
                            'type' => 'string',
                            'context' => array('view', 'edit'),
                        ),
                    ),
                ),
                'payment_method' => array(
                    'description' => __('Payment method ID.', 'woocommerce'),
                    'type' => 'string',
                    'context' => array('view', 'edit'),
                ),
                'payment_method_title' => array(
                    'description' => __('Payment method title.', 'woocommerce'),
                    'type' => 'string',
                    'context' => array('view', 'edit'),
                    'arg_options' => array(
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
                'transaction_id' => array(
                    'description' => __('Unique transaction ID.', 'woocommerce'),
                    'type' => 'string',
                    'context' => array('view', 'edit'),
                ),
                'date_paid' => array(
                    'description' => __("The date the order was paid, in the site's timezone.", 'woocommerce'),
                    'type' => 'date-time',
                    'context' => array('view', 'edit'),
                    'readonly' => true,
                ),
                'date_paid_gmt' => array(
                    'description' => __('The date the order was paid, as GMT.', 'woocommerce'),
                    'type' => 'date-time',
                    'context' => array('view', 'edit'),
                    'readonly' => true,
                ),
                'date_completed' => array(
                    'description' => __("The date the order was completed, in the site's timezone.", 'woocommerce'),
                    'type' => 'date-time',
                    'context' => array('view', 'edit'),
                    'readonly' => true,
                ),
                'date_completed_gmt' => array(
                    'description' => __('The date the order was completed, as GMT.', 'woocommerce'),
                    'type' => 'date-time',
                    'context' => array('view', 'edit'),
                    'readonly' => true,
                ),
                'cart_hash' => array(
                    'description' => __('MD5 hash of cart items to ensure orders are not modified.', 'woocommerce'),
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
                'coupon_lines' => array(
                    'description' => __('Coupons line data.', 'woocommerce'),
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
                            'code' => array(
                                'description' => __('Coupon code.', 'woocommerce'),
                                'type' => 'mixed',
                                'context' => array('view', 'edit'),
                            ),
                            'discount' => array(
                                'description' => __('Discount total.', 'woocommerce'),
                                'type' => 'string',
                                'context' => array('view', 'edit'),
                                'readonly' => true,
                            ),
                            'discount_tax' => array(
                                'description' => __('Discount total tax.', 'woocommerce'),
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
                'pw_gift_card_lines' => array(
                    'description' => __('Coupons line data.', 'woocommerce'),
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
                            'card_number' => array(
                                'description' => __('Card number.', 'woocommerce'),
                                'type' => 'string',
                                'context' => array('view', 'edit'),
                            ),
                            'amount' => array(
                                'description' => __('Amount.', 'woocommerce'),
                                'type' => 'string',
                                'context' => array('view', 'edit'),
                                'readonly' => true,
                            ),
                        ),
                    ),
                ),
                'refunds' => array(
                    'description' => __('List of refunds.', 'woocommerce'),
                    'type' => 'array',
                    'context' => array('view', 'edit'),
                    'readonly' => true,
                    'items' => array(
                        'type' => 'object',
                        'properties' => array(
                            'id' => array(
                                'description' => __('Refund ID.', 'woocommerce'),
                                'type' => 'integer',
                                'context' => array('view', 'edit'),
                                'readonly' => true,
                            ),
                            'reason' => array(
                                'description' => __('Refund reason.', 'woocommerce'),
                                'type' => 'string',
                                'context' => array('view', 'edit'),
                                'readonly' => true,
                            ),
                            'total' => array(
                                'description' => __('Refund total.', 'woocommerce'),
                                'type' => 'string',
                                'context' => array('view', 'edit'),
                                'readonly' => true,
                            ),
                        ),
                    ),
                ),
                'set_paid' => array(
                    'description' => __('Define if the order is paid. It will set the status to processing and reduce stock items.', 'woocommerce'),
                    'type' => 'boolean',
                    'default' => false,
                    'context' => array('edit'),
                ),
            ),
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

        $params['status'] = array(
            'default' => 'any',
            'description' => __('Limit result set to orders assigned a specific status.', 'woocommerce'),
            'type' => 'string',
            'enum' => array_merge(array('any', 'trash'), $this->get_order_statuses()),
            'sanitize_callback' => 'sanitize_key',
            'validate_callback' => 'rest_validate_request_arg',
        );
        $params['customer'] = array(
            'description' => __('Limit result set to orders assigned a specific customer.', 'woocommerce'),
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'validate_callback' => 'rest_validate_request_arg',
        );
        $params['product'] = array(
            'description' => __('Limit result set to orders assigned a specific product.', 'woocommerce'),
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'validate_callback' => 'rest_validate_request_arg',
        );
        $params['dp'] = array(
            'default' => wc_get_price_decimals(),
            'description' => __('Number of decimal points to use in each resource.', 'woocommerce'),
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'validate_callback' => 'rest_validate_request_arg',
        );
        $params['status'] = array(
            'default' => 'any',
            'description' => __('Limit result set to orders which have specific statuses.', 'woocommerce'),
            'type' => 'array',
            'items' => array(
                'type' => 'string',
                'enum' => array_merge(array('any', 'trash'), $this->get_order_statuses()),
            ),
            'validate_callback' => 'rest_validate_request_arg',
        );
        $params['date_completed'] = array(
            'description' => __('Only get orders completed a specific date.', 'woocommerce'),
            'type' => 'string',
            'format' => 'date',
            //       'sanitize_callback' => 'daterange',
            //     'validate_callback' => 'rest_validate_request_arg',
        );
        $params['date_paid'] = array(
            'description' => __('Only get orders paid a specific date.', 'woocommerce'),
            'type' => 'string',
            'format' => 'date',
            //      'sanitize_callback' => 'daterange',
            //     'validate_callback' => 'rest_validate_request_arg',
        );

        return $params;
    }
}
