<?php

/**
 * Accounting Report Generic Class
 *
 * @package BjornTech\AccountingReport
 */

namespace BjornTech\AccountingReport;

use WC_Admin_Report;
use WC_Countries;
use WC_Order;
use WC_Tax;

defined('ABSPATH') || exit;

class AccountingReport extends WC_Admin_Report
{

    static $order_differences = array();
    static $sales_per_region = array();
    static $tax_classes = array();
    static $site_currencies = array();
    static $eu_tax_used = false;
    static $report_type;
    static $show_fortnox;
    static $sum_total_items = array();
    static $sum_total_order = array();
    static $line_item_total = array();
    static $line_item_total_tax = array();
    static $fee_total = array();
    static $fee_total_tax = array();
    static $shipping_total = array();
    static $shipping_total_tax = array();
    static $refund_total = array();
    static $refund_total_tax = array();

    /**
    /**
     * Get the legend for the main chart sidebar.
     *
     * @return array
     */
    public function get_chart_legend()
    {
        return array();
    }

    /**
     * Output an export link.
     */
    public function get_export_button()
    {

        $current_range = !empty($_GET['range']) ? sanitize_text_field($_GET['range']) : 'last_month';
?>
        <a href="#"
            download="report-<?php echo esc_attr($current_range); ?>-<?php echo date_i18n('Y-m-d', current_time('timestamp')); ?>.csv"
            class="export_csv" data-export="table">
            <?php _e('Export CSV', 'woocommerce'); ?>
        </a>
<?php
    }

    /**
     * Output the report.
     */
    public function process_report($report_type = array())
    {

        self::$report_type = $report_type;

        $ranges = array(
            'year' => __('Year', 'woocommerce'),
            'last_month' => __('Last month', 'woocommerce'),
            'month' => __('This month', 'woocommerce'),
        );

        $current_range = !empty($_GET['range']) ? sanitize_text_field($_GET['range']) : 'last_month';

        if (!in_array($current_range, array('custom', 'year', 'last_month', 'month', '7day'))) {
            $current_range = 'last_month';
        }

        $this->check_current_range_nonce($current_range);
        $this->calculate_current_range($current_range);

        $hide_sidebar = true;

        include WC()->plugin_path() . '/includes/admin/views/html-report-by-date.php';
    }

    private function get_order_tax_classes($order)
    {
        $return_array = [];
        $order_taxes = $this->get_order_taxes($order);
        foreach ($order_taxes as $order_tax) {
            $return_array[] = number_format($order_tax, 2) . '%';
        }
        return implode(', ', $return_array);
    }

    private function add_amount(&$target, $amount, $tax_rate_percent, $currency)
    {
        $target[$currency][$tax_rate_percent] = array_key_exists($tax_rate_percent, $target[$currency]) ? $target[$currency][$tax_rate_percent] + $amount : $amount;
    }

    /**
     * Get order taxes
     *
     * @param WC_Order a WooCommerce order
     * @return array an array of tax-rate id:s and their corresponding tax percentage
     *
     */
    private function get_order_taxes($order)
    {

        $tax_items_labels = array();

        foreach ($order->get_items('tax') as $tax_item) {
            $tax_items_labels[$tax_item->get_rate_id()] = number_format($tax_item->get_rate_percent(), 2);
        }

        return $tax_items_labels;
    }

    private function process_order_items($order)
    {
        $is_refund = $order->get_type() == 'shop_order_refund';
        $parent_order = $is_refund ? wc_get_order($order->get_parent_id()) : $order;
        $order_currency = $parent_order->get_currency();
        $order_id = $parent_order->get_id();
        $types = array('line_item', 'fee', 'shipping');
        $order_total = 0;

        foreach ($types as $type) {

            $items = $order->get_items($type);

            $report_type = $is_refund ? 'refund' : $type;
            $variable_name_total = $report_type . '_total';
            $variable_name_total_tax = $report_type . '_total_tax';

            $order_taxes = $this->get_order_taxes($parent_order);

            foreach ($items as $item) {

                $item_total = $order->get_line_total($item, false);
                $item_total_tax = $order->get_line_tax($item, false);

                if (($tax_data = $item->get_taxes('edit'))) {

                    $tax_rate_percent = '0%';
                    if (array_key_exists('total', $tax_data)) {

                        foreach ($tax_data['total'] as $tax_rate_id => $tax_item_total) {

                            $tax_rate_percent = number_format($order_taxes[$tax_rate_id], 2) . '%';
                            if (!in_array($tax_rate_percent, self::$tax_classes[$order_currency])) {
                                self::$tax_classes[$order_currency][] = (string) $tax_rate_percent;
                            }

                            $rounded_tax = wc_round_tax_total($tax_item_total);

                            Logger::add(sprintf('process_order_items (%s): Adding %s %s as %s %s tax', $order_id, $order_currency, $tax_item_total, $tax_rate_percent, $type));
                            $this->add_amount(self::$$variable_name_total_tax, $rounded_tax, $tax_rate_percent, $order_currency);

                            $variable_name_sum_total_items = 'sum_total_items';
                            $this->add_amount(self::$$variable_name_sum_total_items, $rounded_tax, $tax_rate_percent, $order_currency);
                        }
                    }

                    Logger::add(sprintf('process_order_items (%s): Adding %s %s as %s %s', $order_id, $order_currency, $item_total, $tax_rate_percent, $type));
                    $this->add_amount(self::$$variable_name_total, $item_total, $tax_rate_percent, $order_currency);
                }

                $order_total += $item_total + $item_total_tax;
            }
        }

        return $order_total;
    }

    private function process_order($type, $order, $order_value)
    {
        $order_items_amount = 0;
        $order_difference = false;
        $order_currency = $order->get_currency();
        if (!in_array($order_currency, self::$site_currencies)) {

            array_push(self::$site_currencies, $order_currency);
            self::$tax_classes[$order_currency] = array();

            self::$line_item_total[$order_currency] = array();
            self::$line_item_total[$order_currency]['0%'] = 0;

            self::$line_item_total_tax[$order_currency] = array();
            self::$line_item_total_tax[$order_currency]['0%'] = 0;

            self::$fee_total[$order_currency] = array();
            self::$fee_total[$order_currency]['0%'] = 0;

            self::$fee_total_tax[$order_currency] = array();
            self::$fee_total_tax[$order_currency]['0%'] = 0;

            self::$shipping_total[$order_currency] = array();
            self::$shipping_total[$order_currency]['0%'] = 0;

            self::$shipping_total_tax[$order_currency] = array();
            self::$shipping_total_tax[$order_currency]['0%'] = 0;

            self::$refund_total[$order_currency] = array();
            self::$refund_total[$order_currency]['0%'] = 0;

            self::$refund_total_tax[$order_currency] = array();
            self::$refund_total_tax[$order_currency]['0%'] = 0;

            self::$sales_per_region[$order_currency] = array();

            self::$sum_total_items[$order_currency] = array();
            self::$sum_total_items[$order_currency]['0%'] = 0;

            self::$sum_total_order[$order_currency] = array();
            self::$sum_total_order[$order_currency]['0%'] = 0;
        }

        $order_tax = $order->get_taxes();
        foreach ($order_tax as $order_taxes) {
            $tax_rate_percent = number_format($order_taxes->get_rate_percent(), 2) . '%';
            $tax_total = $order_taxes->get_tax_total();
            $this->add_amount(self::$sum_total_order, $tax_total, $tax_rate_percent, $order_currency);
        }

        $order_items_amount += $this->process_order_items($order);

        if ($order_items_amount != $order_value) {

            $diff = $order_value - $order_items_amount;
            if (floatval(round($diff, wc_get_price_decimals())) != 0) {
                if ($order->get_type() == 'shop_order_refund') {
                    self::$refund_total[$order_currency]['unknown'] = array_key_exists('unknown', self::$refund_total[$order_currency]) ? self::$refund_total[$order_currency]['unknown'] += $diff : self::$refund_total[$order_currency]['unknown'] = $diff;
                } else {
                    self::$line_item_total[$order_currency]['unknown'] = array_key_exists('unknown', self::$line_item_total[$order_currency]) ? self::$line_item_total[$order_currency]['unknown'] += $diff : self::$line_item_total[$order_currency]['unknown'] = $diff;
                }
                $order_difference = sprintf('Difference in order %s, order value is %s but the order items sum is %s making a difference of %s', $order->get_id(), $order_value, $order_items_amount, $diff);
            }
        }

        if (!array_key_exists($type, self::$sales_per_region[$order_currency])) {
            self::$sales_per_region[$order_currency][$type] = array();
        }

        self::$sales_per_region[$order_currency][$type]['total'] = array_key_exists('total', self::$sales_per_region[$order_currency][$type]) ? self::$sales_per_region[$order_currency][$type]['total'] + $order->get_total() : $order->get_total();
        self::$sales_per_region[$order_currency][$type]['tax'] = array_key_exists('tax', self::$sales_per_region[$order_currency][$type]) ? self::$sales_per_region[$order_currency][$type]['tax'] + $order->get_total_tax() : $order->get_total_tax();
        return $order_difference;
    }

    public function get_exchage_rates($base = false)
    {

        $args = ($base ? '?base=' . $base : '');

        $response = wp_remote_get('https://accounting.bjorntech.net/v1/exchange-rates' . $args);

        if (is_wp_error($response)) {
            Logger::add(print_r($response, true));
            return false;
        } else {
            if (($http_code = wp_remote_retrieve_response_code($response)) != 200) {
                Logger::add(print_r($http_code, true));
                return false;
            }
            $body = wp_remote_retrieve_body($response);
            return json_decode($body);
        }
    }

    public function get_val($value_array, $value_key)
    {
        if (array_key_exists($value_key, $value_array)) {
            return $value_array[$value_key];
        }
        return 0;
    }

    public function get_main_chart()
    {
        self::$eu_tax_used = false;

        $sort_criteria = '';
        $base_currency = get_woocommerce_currency();

        if ($timezone_string = get_option('timezone_string')) {
            date_default_timezone_set($timezone_string);
        }

        $my_country = WC()->countries->get_base_country();
        $only_local = get_option('bjorntech_wcar_force_local') === 'yes';
        $from_timestamp = strtotime(date('Y-m-d 00:00:01', $this->start_date));
        $to_timestamp = strtotime(date('Y-m-d 23:59:59', $this->end_date));
        $period = $from_timestamp . '...' . $to_timestamp;
        $base_on_status = get_option('bjorntech_wcar_on_status', 'date_completed');

        $shop_order_params = array(
            $base_on_status => $period,
            'type' => 'shop_order',
            'status' => get_option('bjorntech_wcar_include_order_statuses', ['wc-completed']),
        );

        Logger::add('get_order_params: ' . print_r($shop_order_params, true));

        $shop_orders = [];
        $page = 1;
        while (true) {
            $temp_orders = wc_get_orders(array_merge($shop_order_params, ['page' => $page]));
            if (empty($temp_orders)) {
                break;
            }
            $shop_orders = array_merge($shop_orders, $temp_orders);
            $page++;
        }

        $shop_order_refund_params = array(
            'type' => 'shop_order_refund',
            'date_created' => $period,
            'status' => get_option('bjorntech_wcar_include_order_statuses', ['wc-completed']),
        );

        Logger::add('shop_order_refund_params: ' . print_r($shop_order_refund_params, true));

        $shop_order_refunds = [];
        $page = 1;
        while (true) {
            $temp_refunds = wc_get_orders(array_merge($shop_order_refund_params, ['page' => $page]));
            if (empty($temp_refunds)) {
                break;
            }
            $shop_order_refunds = array_merge($shop_order_refunds, $temp_refunds);
            $page++;
        }

        $orders = array_merge($shop_orders, $shop_order_refunds);

        // make shure that orders being fully refunded before they get to the completed-status is also included (to match with the refund)
        if ('date_completed' == $base_on_status) {

            $already_got_ids = array();
            foreach ($orders as $order) {
                array_push($already_got_ids, $order->get_id());
            }

            $extras = wc_get_orders(
                array(
                    'date_paid' => $period,
                    'type' => 'shop_order',
                    'status' => 'refunded',
                    'exclude' => $already_got_ids,
                    'limit' => -1,
                )
            );

            // Only include the fully refunded orders that never got to the completed stage until refunded
            $refunded_before_completion = array();
            foreach ($extras as $extra) {
                if (!$extra->get_date_completed()) {
                    array_push($refunded_before_completion, $extra);
                }
            }

            $orders = array_merge($orders, $refunded_before_completion);
        }

        $countries = new WC_Countries();

        $payment_methods = array();
        $payment_method_titles = array();

        $non_eu_sales = array();
        $all_orders = array();
        $completed_date = '';
        $stripe_fees = 0;

        $parent_order = null;
        $eu_private_sales = array();

        $parent_id = 0;
        $total_payments = array();

        $tax_rates_array = WC_Tax::find_rates(
            array(
                'country' => $my_country,
                'state' => WC()->countries->get_base_state(),
                'postcode' => WC()->countries->get_base_postcode(),
                'city' => WC()->countries->get_base_city(),
                'tax_class' => get_option('woo_ar_reverse_tax_class'),
            )
        );
        $tax_rates = reset($tax_rates_array);
        $accounting_reverse_calculate_tax_class = key($tax_rates_array);

        foreach ($orders as $order) {

            $order_id = $order->get_id();
            $order_value = $order->get_total();
            $tax_value = $order->get_total_tax();
            $order_type = $order->get_type();

            if ($order_type == 'shop_order_refund') {
                $parent_id = $order->get_parent_id();
                $parent_order = new WC_Order($parent_id);
                $order_tax_rates = $this->get_order_tax_classes($parent_order);
                $payment_method = $parent_order->get_payment_method();
                $payment_method_titles[$payment_method] = $parent_order->get_payment_method_title();
                $billing_country = $parent_order->get_billing_country();
                $eu_vat_number_validated = get_post_meta($parent_id, '_vat_number_is_valid', true) == 'true' ? true : false;
                $customer_name = $parent_order->get_billing_company() ? $parent_order->get_billing_company() : $parent_order->get_billing_first_name() . ' ' . $parent_order->get_billing_last_name();
                $order_currency = $parent_order->get_currency();
                $completed_date = 'n/a';
                if (($order_value != 0) && ($tax_value == 0)) {
                    $tax_array = WC_Tax::calc_inclusive_tax($order_value, $tax_rates_array);
                    foreach ($tax_array as $tax) {
                        $tax_value += $tax;
                    }
                }
            } else {
                $parent_order = null;
                $order_tax_rates = $this->get_order_tax_classes($order);
                $payment_method = $order->get_payment_method();
                $payment_method_titles[$payment_method] = $order->get_payment_method_title();
                $billing_country = $order->get_billing_country();
                $eu_vat_number_validated = get_post_meta($order_id, '_vat_number_is_valid', true) == 'true' ? true : false;
                $customer_name = $order->get_billing_company() ? $order->get_billing_company() : $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
                $order_currency = $order->get_currency();
                $completed_date = $order->get_date_completed();
            }

            $sort_criteria = $order_type == 'shop_order_refund' ? $parent_order->get_order_number() : $order->get_order_number();

            if (!array_key_exists($order_currency, $total_payments)) {
                $total_payments[$order_currency] = 0;
            }

            $stripe_fee = get_post_meta($order_id, '_stripe_fee', true);
            $stripe_fees += is_numeric($stripe_fee) ? $stripe_fee : 0;

            if (!array_key_exists($payment_method, $payment_methods)) {
                $payment_methods[$payment_method] = array();
            }
            $payment_methods[$payment_method][$order_currency] = array_key_exists($order_currency, $payment_methods[$payment_method]) ? $payment_methods[$payment_method][$order_currency] + $order_value : $order_value;
            $total_payments[$order_currency] = $total_payments[$order_currency] + $order_value;

            self::$eu_tax_used = self::$eu_tax_used ?: $eu_vat_number_validated;

            self::$show_fortnox = 'yes' == get_option('bjorntech_wcar_fortnox_invoice');

            $all_orders[$billing_country][] = array(
                'number' => $order_type == 'shop_order_refund' ? $parent_order->get_order_number() : $order->get_order_number(),
                'id' => $order->get_id(),
                'value' => $order_value,
                'tax_value' => $tax_value,
                'currency' => $order->get_currency(),
                'tax_rates' => $order_tax_rates,
                'eu_vat_number_validated' => $eu_vat_number_validated,
                'country' => $billing_country,
                'payment_method' => $payment_method,
                'customer_name' => $customer_name,
                'date_created' => $order->get_date_created(),
                'date_modified' => $order->get_date_modified(),
                'date_paid' => $order_type == 'shop_order_refund' ? 'n/a' : $order->get_date_paid(),
                'date_completed' => $completed_date,
                'shipping' => $order->get_shipping_total(),
                'customer_vat_number' => ($vat_number = get_post_meta($order_id, '_billing_vat_number', true)) ? $vat_number : get_post_meta($order_id, '_vat_number', true),
                'stripe_fee' => $stripe_fee,
                'fortnox_invoice' => $order->get_meta('_fortnox_invoice_number', true, 'edit'),
            );

            $order_difference = false;
            if (($billing_country == '') || ($billing_country == $my_country) || $only_local) {

                $order_difference = $this->process_order('local', $order, $order_value);
            } elseif (in_array($billing_country, $countries->get_european_union_countries())) {
                if ($eu_vat_number_validated) {
                    $order_difference = $this->process_order('eu-vat-exempt', $order, $order_value);
                } else {
                    $order_difference = $this->process_order('eu', $order, $order_value);
                }
            } else {
                $order_difference = $this->process_order('non-eu', $order, $order_value);
            }

            if ($order_difference) {
                self::$order_differences[] = $order_difference;
            }
        }

        foreach ($all_orders as $key => $order_country) {
            asort($all_orders[$key]);
        }

        $exchange_response = $this->get_exchage_rates($base_currency);

        Logger::add(sprintf('Total sales per tax class %s', json_encode(self::$line_item_total)));

        Logger::add(sprintf('Total refunds %s', json_encode(self::$refund_total)));

        Logger::add(sprintf('Total fees %s', json_encode(self::$fee_total)));

        Logger::add(sprintf('Total shipping %s', json_encode(self::$shipping_total)));

        Logger::add(sprintf('Total sales per tax class VAT %s', json_encode(self::$line_item_total_tax)));

        Logger::add(sprintf('Total refunds VAT %s', json_encode(self::$refund_total_tax)));

        Logger::add(sprintf('Total fees VAT %s', json_encode(self::$fee_total_tax)));

        Logger::add(sprintf('Total shipping VAT %s', json_encode(self::$shipping_total_tax)));

        Logger::add(sprintf('All orders %s', json_encode($all_orders)));


        echo '<div style="display: flex; justify-content: flex-end; align-items: center; flex-direction: column; margin: 20px 0; text-align: center;">
                    <div style="border: 1px solid lightgrey; background-color: lightgrey; padding: 15px; border-radius: 10px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); display: flex; flex-direction: column; align-items: center;">
                        <p style="font-weight: bold; margin-bottom: 10px;">New Report i the Analytics section</p>
                        <p>We are happy to announce the beta of a new report in the Analytics section. Please have a look <a href="/wp-admin/admin.php?page=wc-admin&path=%2Fanalytics%2Fwoo-accounting-report">here</a></p>
                    </div>
                </div>';


        if (empty(self::$report_type) || in_array('total-sales', self::$report_type)) {
            TotalSales::render(self::$site_currencies, $base_currency, $exchange_response->rates, self::$tax_classes, self::$line_item_total, self::$refund_total, self::$fee_total, self::$line_item_total_tax, self::$refund_total_tax, self::$fee_total_tax, self::$shipping_total_tax, self::$shipping_total, self::$sum_total_items, self::$sum_total_order);
        }

        if (empty(self::$report_type) || in_array('sales-per-region', self::$report_type)) {
            SalesPerRegion::render(self::$sales_per_region, self::$site_currencies);
        }

        if (empty(self::$report_type) || in_array('methods-of-payment', self::$report_type)) {
            MethodsOfPayment::render($all_orders, self::$show_fortnox, self::$eu_tax_used, $payment_method_titles, $payment_methods, $stripe_fees);
        }

        if (empty(self::$report_type) || in_array('all-orders', self::$report_type)) {
            AllOrders::render($all_orders, self::$show_fortnox, self::$eu_tax_used, $payment_method_titles, $sort_criteria);
        }

        if (in_array('all-orders-totals', self::$report_type)) {
            AllOrdersTotals::render($all_orders, self::$site_currencies, self::$eu_tax_used, $exchange_response->rates, $payment_method_titles, $base_currency, $sort_criteria);
        }

        if (count(self::$order_differences) > 0) {
            OrderDifferences::render(self::$order_differences);
        }
    }
}
