<?php

namespace BjornTech\AccountingReport;

defined('ABSPATH') || exit;

class MethodsOfPayment
{

    use Helper;

    public static function render($all_orders, $show_fortnox, $eu_tax_used, $payment_method_titles, $payment_methods, $stripe_fees)
    {
        echo '<div class="row">';

        echo '<table cellspacing="0" cellpadding="2" class="styled-table">';

        echo '<caption>';
        echo __('Methods of payment', 'woo-accounting-report');
        echo '</caption>';

        echo '<thead>';
        echo '<tr>';
        echo '<th scope="col" style="text-align:left;">';
        echo __('Payment method', 'woo-accounting-report');
        echo '</th>';
        echo '<th scope="col" style="text-align:right">';
        echo __('Currency', 'woo-accounting-report');
        echo '</th>';
        echo '<th scope="col" style="text-align:right;">';
        echo __('Amount', 'woo-accounting-report');
        echo '</th>';
        echo '<th scope="col" style="text-align:right;">';
        echo __('Fee', 'woo-accounting-report');
        echo '</th>';
        echo '</tr>';
        echo '</thead>';

        echo '<tbody>';

        foreach ($payment_methods as $payment_method => $order_currencys) {
            foreach ($order_currencys as $currency_key => $order_currency) {

                echo '<tr>';
                echo '<td>';
                echo empty($payment_method_titles[$payment_method]) ? str_replace('_', ' ', ucfirst($payment_method)) : $payment_method_titles[$payment_method];
                echo '</td>';
                echo '<td>';
                echo $currency_key;
                echo '</td>';
                echo '<td align="right">';
                echo static::format_number($order_currency);
                echo '</td>';
                if ($payment_method == 'stripe') {
                    echo '<td align="right">';
                    echo static::format_number($stripe_fees);
                    echo '</td>';
                } else {
                    echo '<td>';
                    echo 'n/a';
                    echo '</td>';
                }
                echo '</tr>';
            }
        }

        echo '</tbody>';

        echo '</table>';

        echo '</div>';

    }
}
