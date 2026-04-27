<?php

namespace BjornTech\AccountingReport;

defined('ABSPATH') || exit();

class AllOrders
{
    use Helper;

    public static function render(
        $all_orders,
        $show_fortnox,
        $eu_tax_used,
        $payment_method_titles,
        $sort_criteria
    ) {

        echo '<div class="row">';

        echo '<table cellspacing="0" cellpadding="2" class="styled-table">';

        echo '<caption>' . esc_html__('All orders', 'woo-accounting-report') . '</caption>';

        if (isset($all_orders)) {
            foreach ($all_orders as $country => $sort_criteria) {

                echo '<thead>';
                echo '<tr>';

                echo '<th scope="col" style="text-align:left;">' . esc_html__('Order date', 'woo-accounting-report') . '</th>';
                echo '<th scope="col" style="text-align:left;">' . esc_html__('Order number', 'woo-accounting-report') . '</th>';
                if ($show_fortnox) {
                    echo '<th scope="col" style="text-align:left;">' . esc_html__('Fortnox', 'woo-accounting-report') . '</th>';
                }

                echo '<th scope="col" style="text-align:left;">' . esc_html__('Id', 'woo-accounting-report') . '</th>';
                echo '<th scope="col" style="text-align:left;">' . esc_html__('Buyer name', 'woo-accounting-report') . '</th>';
                echo '<th scope="col" style="text-align:left;">' . esc_html__('Country', 'woo-accounting-report') . '</th>';
                echo '<th scope="col" style="text-align:left;">' . esc_html__('Payment method', 'woo-accounting-report') . '</th>';
                echo '<th scope="col" style="text-align:left;">' . esc_html__('Stripe fee', 'woo-accounting-report') . '</th>';
                echo '<th scope="col" style="text-align:left;">' . esc_html__('Currency', 'woo-accounting-report') . '</th>';
                echo '<th scope="col" style="text-align:left;">' . esc_html__('Value ex. TAX', 'woo-accounting-report') . '</th>';
                echo '<th scope="col" style="text-align:left;">' . esc_html__('TAX', 'woo-accounting-report') . '</th>';
                echo '<th scope="col" style="text-align:left;">' . esc_html__('TAX rate', 'woo-accounting-report') . '</th>';
                echo '<th scope="col" style="text-align:left;">' . esc_html__('Shipping', 'woo-accounting-report') . '</th>';
                echo '<th scope="col" style="text-align:left;">' . esc_html__('Total amount', 'woo-accounting-report') . '</th>';
                if ($eu_tax_used) {
                    echo '<th scope="col" style="text-align:left;">' . esc_html__('EU Corporate VAT number', 'woo-accounting-report') . '</th>';
                }

                echo '</tr>';
                echo '</thead>';

                echo '<tbody>';

                foreach ($sort_criteria as $order) {
                    echo '<tr>';

                    echo '<td>' . esc_html(substr($order['date_modified'], 0, 10)) . '</td>';
                    echo '<td>' . esc_html($order['number']) . '</td>';
                    if ($show_fortnox) {
                        echo '<td>' . esc_html($order['fortnox_invoice']) . '</td>';
                    }
                    echo '<td>' . esc_html($order['id']) . '</td>';
                    echo '<td>' . esc_html($order['customer_name']) . '</td>';
                    echo '<td>' . esc_html($order['country']) . '</td>';
                    echo '<td>' . esc_html(empty($payment_method_titles[$order['payment_method']]) ? str_replace('_', ' ', ucfirst($order['payment_method'])) : $payment_method_titles[$order['payment_method']]) . '</td>';
                    echo '<td align="right">' . esc_html(static::format_number($order['stripe_fee'])) . '</td>';
                    echo '<td>' . esc_html($order['currency']) . '</td>';
                    echo '<td align="right">' . esc_html(static::format_number($order['value'] - $order['tax_value'])) . '</td>';
                    echo '<td align="right">' . esc_html(static::format_number($order['tax_value'])) . '</td>';
                    echo '<td align="right">' . esc_html($order['tax_rates']) . '</td>';
                    echo '<td align="right">' . esc_html(static::format_number($order['shipping'])) . '</td>';
                    echo '<td align="right">' . esc_html(static::format_number($order['value'])) . '</td>';
                    if ($eu_tax_used) {
                        echo '<td>' . esc_html($order['customer_vat_number']) . '</td>';
                    }
                    echo '</tr>';

                }

                echo '</tbody>';

            }
        }

        echo '</table>';

        echo '</div>';

    }
}
