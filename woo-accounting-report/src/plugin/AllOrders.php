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

        echo '<caption>' . __('All orders', 'woo-accounting-report') . '</caption>';

        if (isset($all_orders)) {
            foreach ($all_orders as $country => $sort_criteria) {

                echo '<thead>';
                echo '<tr>';

                echo '<th scope="col" style="text-align:left;">' . __('Order date', 'woo-accounting-report') . '</th>';
                echo '<th scope="col" style="text-align:left;">' . __('Order number', 'woo-accounting-report') . '</th>';
                if ($show_fortnox) {
                    echo '<th scope="col" style="text-align:left;">' . __('Fortnox', 'woo-accounting-report') . '</th>';
                }

                echo '<th scope="col" style="text-align:left;">' . __('Id', 'woo-accounting-report') . '</th>';
                echo '<th scope="col" style="text-align:left;">' . __('Buyer name', 'woo-accounting-report') . '</th>';
                echo '<th scope="col" style="text-align:left;">' . __('Country', 'woo-accounting-report') . '</th>';
                echo '<th scope="col" style="text-align:left;">' . __('Payment method', 'woo-accounting-report') . '</th>';
                echo '<th scope="col" style="text-align:left;">' . __('Stripe fee', 'woo-accounting-report') . '</th>';
                echo '<th scope="col" style="text-align:left;">' . __('Currency', 'woo-accounting-report') . '</th>';
                echo '<th scope="col" style="text-align:left;">' . __('Value ex. TAX', 'woo-accounting-report') . '</th>';
                echo '<th scope="col" style="text-align:left;">' . __('TAX', 'woo-accounting-report') . '</th>';
                echo '<th scope="col" style="text-align:left;">' . __('TAX rate', 'woo-accounting-report') . '</th>';
                echo '<th scope="col" style="text-align:left;">' . __('Shipping', 'woo-accounting-report') . '</th>';
                echo '<th scope="col" style="text-align:left;">' . __('Total amount', 'woo-accounting-report') . '</th>';
                if ($eu_tax_used) {
                    echo '<th scope="col" style="text-align:left;">' . __('EU Corporate VAT number', 'woo-accounting-report') . '</th>';
                }

                echo '</tr>';
                echo '</thead>';

                echo '<tbody>';

                foreach ($sort_criteria as $order) {
                    echo '<tr>';

                    echo '<td>' . substr($order['date_modified'], 0, 10) . '</td>';
                    echo '<td>' . $order['number'] . '</td>';
                    if ($show_fortnox) {
                        echo '<td>' . $order['fortnox_invoice'] . '</td>';
                    }
                    echo '<td>' . $order['id'] . '</td>';
                    echo '<td>' . $order['customer_name'] . '</td>';
                    echo '<td>' . $order['country'] . '</td>';
                    echo '<td>' . (empty($payment_method_titles[$order['payment_method']]) ? str_replace('_', ' ', ucfirst($order['payment_method'])) : $payment_method_titles[$order['payment_method']]) . '</td>';
                    echo '<td align="right">' . static::format_number($order['stripe_fee']) . '</td>';
                    echo '<td>' . $order['currency'] . '</td>';
                    echo '<td align="right">' . static::format_number($order['value'] - $order['tax_value']) . '</td>';
                    echo '<td align="right">' . static::format_number($order['tax_value']) . '</td>';
                    echo '<td align="right">' . $order['tax_rates'] . '</td>';
                    echo '<td align="right">' . static::format_number($order['shipping']) . '</td>';
                    echo '<td align="right">' . static::format_number($order['value']) . '</td>';
                    if ($eu_tax_used) {
                        echo '<td>' . $order['customer_vat_number'] . '</td>';
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

