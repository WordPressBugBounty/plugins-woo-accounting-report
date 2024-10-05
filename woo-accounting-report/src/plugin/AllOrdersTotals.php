<?php

namespace BjornTech\AccountingReport;

defined('ABSPATH') || exit();

class AllOrdersTotals
{
    use Helper;

    public static function render(
        $all_orders,
        $site_currencies,
        $eu_tax_used,
        $exchange_rates,
        $payment_method_titles,
        $base_currency,
        $sort_criteria
    ) {
        echo '<div class="row">';

        echo '<table cellspacing="0" cellpadding="2" class="styled-table">';

        echo '<caption>' . __('All orders', 'woo-accounting-report') . '</caption>';

        $grand_total_ex_vat = 0;
        $grand_total_vat = 0;
        $grand_total_shipping = 0;
        $grand_total = 0;
        if (isset($all_orders)) {

            foreach ($all_orders as $country => $sort_criteria) {

                foreach ($site_currencies as $report_currency) {

                    $exchange_rate = 1/$exchange_rates->{$report_currency};

                    echo '<thead>';
                    echo '<tr>';

                    echo '<th scope="col" style="text-align:left;">' . __('Order date', 'woo-accounting-report') . '</th>';
                    echo '<th scope="col" style="text-align:left;">' . __('Order number', 'woo-accounting-report') . '</th>';
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
                    $total_ex_vat = 0;
                    $total_vat = 0;
                    $total_shipping = 0;
                    $total = 0;

                    foreach ($sort_criteria as $order) {

                        if ($order['currency'] == $report_currency) {
                            $total_ex_vat += $order['value'] - $order['tax_value'];
                            $total_vat += $order['tax_value'];
                            $total_shipping += $order['shipping'];
                            $total += $order['value'];

                            echo '<tr>';

                            echo '<td>' . substr($order['date_modified'], 0, 10) . '</td>';
                            echo '<td>' . $order['number'] . '</td>';
                            echo '<td>' . $order['id'] . '</td>';
                            echo '<td>' . $order['customer_name'] . '</td>';
                            echo '<td>' . $order['country'] . '</td>';

                            echo '<td>';
                            echo empty($payment_method_titles[$order['payment_method']])
                                ? str_replace('_', ' ', ucfirst($order['payment_method']))
                                : $payment_method_titles[$order['payment_method']];
                            echo '</td>';

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
                    }

                    $grand_total_ex_vat += $total_ex_vat * $exchange_rate;
                    $grand_total_vat += $total_vat * $exchange_rate;
                    $grand_total_shipping += $total_shipping * $exchange_rate;
                    $grand_total += $total * $exchange_rate;
                    echo '<tr class="subtotal-row">';
                    echo '<td></td>';
                    echo '<td></td>';
                    echo '<td></td>';
                    echo '<td></td>';
                    echo '<td></td>';
                    echo '<td>';
                    if ($base_currency != $report_currency) {
                        echo sprintf(
                            __('Total %s (%s exchange rate %s)', 'woo-accounting-report'),
                            $country,
                            $report_currency,
                            $exchange_rate
                        );
                    } else {
                        echo sprintf(__('Total %s', 'woo-accounting-report'), $country);
                    }
                    echo '</td>';
                    echo '<td></td>';
                    echo '<td></td>';
                    echo '<td align="right">';
                    echo static::format_number($total_ex_vat);
                    echo '</td>';
                    echo '<td align="right">';
                    echo static::format_number($total_vat);
                    echo '</td>';
                    echo '<td></td>';
                    echo '<td align="right">';
                    echo static::format_number($total_shipping);
                    echo '</td>';
                    echo '<td align="right">';
                    echo static::format_number($total);
                    echo '</td>';
                    echo '<td></td>';
                    echo '</tr>';
                }
            }
            echo '<tr class="total-row">';
            echo '<td></td>';
            echo '<td></td>';
            echo '<td></td>';
            echo '<td></td>';
            echo '<td></td>';
            echo '<td>';
            echo sprintf(__('Grand Total %s', 'woo-accounting-report'), $base_currency);
            echo '</td>';
            echo '<td></td>';
            echo '<td></td>';
            echo '<td align="right">';
            echo static::format_number($grand_total_ex_vat);
            echo '</td>';
            echo '<td align="right">';
            echo static::format_number($grand_total_vat);
            echo '</td>';
            echo '<td></td>';
            echo '<td align="right">';
            echo static::format_number($grand_total_shipping);
            echo '</td>';
            echo '<td align="right">';
            echo static::format_number($grand_total);
            echo '</td>';
            echo '<td></td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }
}

