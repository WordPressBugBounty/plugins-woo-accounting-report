<?php

namespace BjornTech\AccountingReport;

defined('ABSPATH') || exit;

class TotalSales
{

    use Helper;

    public static function render($currencies, $base_currency, $exchange_rates, $tax_classes, $line_item_total, $total_refunds, $fee_total, $line_item_total_tax, $total_refunds_vat, $fee_total_tax, $total_shipping_vat, $total_shipping, $sum_total_items, $sum_total_order)
    {

        Logger::add('Sum total order: ' . wp_json_encode($sum_total_order));
        Logger::add('Sum total items: ' . wp_json_encode($sum_total_items));
        $grand_total_amount = 0;
        $grand_total_vat_amount = 0;
        $grand_sub_total_amount = 0;
        $grand_sub_total_currency_amount = 0;
        $line_item_total_amount = 0;
        $total_refunds_amount = 0;
        $fee_total_amount = 0;
        $total_shipping_amount = 0;
        $sub_total_amount = 0;
        $line_item_total_tax_amount = 0;
        $total_refunds_vat_amount = 0;
        $fee_total_tax_amount = 0;
        $total_shipping_vat_amount = 0;
        $total_vat_amount = 0;

        foreach ($currencies as $report_currency) {

            $include_currencies = count($currencies) !== 1 || $base_currency != $report_currency;

            $raw_exchange_rate = isset($exchange_rates->{$report_currency}) ? (float) $exchange_rates->{$report_currency} : 0.0;
            $exchange_rate = $raw_exchange_rate > 0 ? 1 / $raw_exchange_rate : 0;

            foreach ($tax_classes[$report_currency] as $tax_class) {
                echo '<div class="row">';
                echo '<table cellspacing="0" cellpadding="2" class="styled-table">';
                echo '<caption>';
                if ($base_currency != $report_currency) {
                    echo esc_html(sprintf(
                        /* translators: 1: Report currency, 2: Tax class, 3: Base currency, 4: Exchange rate */
                        __('Total sales %1$s with tax rate %2$s (%3$s exchange rate %4$s)', 'woo-accounting-report'),
                        $report_currency,
                        $tax_class,
                        $base_currency,
                        $exchange_rate
                    ));
                } else {
                    echo esc_html(sprintf(
                        /* translators: 1: Report currency, 2: Tax class */
                        __('Total sales %1$s with tax rate %2$s', 'woo-accounting-report'),
                        $report_currency,
                        $tax_class
                    ));
                }
                echo '</caption>';
                echo '<thead>';
                echo '<th scope="col" style="text-align:left;">';
                esc_html_e('Type', 'woo-accounting-report');
                echo '</th>';
                echo '<th scope="col" style="text-align:left;">';
                /* translators: %s: Currency */
                echo esc_html(sprintf(__('Net sales (%s)', 'woo-accounting-report'), $report_currency));
                echo '</th>';
                echo '<th scope="col" style="text-align:left;">';
                /* translators: %s: Currency */
                echo esc_html(sprintf(__('TAX (%s)', 'woo-accounting-report'), $report_currency));
                echo '</th>';
                echo '<th scope="col" style="text-align:left;">';
                /* translators: %s: Currency */
                echo esc_html(sprintf(__('Total sales (%s)', 'woo-accounting-report'), $report_currency));
                echo '</th>';
                if ($include_currencies && $base_currency != $report_currency) {
                    echo '<th scope="col" style="text-align:left;">';
                    /* translators: %s: Currency */
                    echo esc_html(sprintf(__('Net sales (%s)', 'woo-accounting-report'), $base_currency));
                    echo '</th>';
                    echo '<th scope="col" style="text-align:left;">';
                    /* translators: %s: Currency */
                    echo esc_html(sprintf(__('TAX (%s)', 'woo-accounting-report'), $base_currency));
                    echo '</th>';
                    echo '<th scope="col" style="text-align:left;">';
                    /* translators: %s: Currency */
                    echo esc_html(sprintf(__('Total sales (%s)', 'woo-accounting-report'), $base_currency));
                    echo '</th>';
                }
                echo '</thead>';
                echo '<tbody>';

                $line_item_total_amount = (array_key_exists($report_currency, $line_item_total) && array_key_exists($tax_class, $line_item_total[$report_currency])) ? $line_item_total[$report_currency][$tax_class] : 0;

                $total_refunds_amount = (array_key_exists($report_currency, $total_refunds) && array_key_exists($tax_class, $total_refunds[$report_currency])) ? $total_refunds[$report_currency][$tax_class] : 0;

                $fee_total_amount = (array_key_exists($report_currency, $fee_total) && array_key_exists($tax_class, $fee_total[$report_currency])) ? $fee_total[$report_currency][$tax_class] : 0;

                $total_shipping_amount = (array_key_exists($report_currency, $total_shipping) && array_key_exists($tax_class, $total_shipping[$report_currency])) ? $total_shipping[$report_currency][$tax_class] : 0;

                $sub_total_amount = $line_item_total_amount + $total_refunds_amount + $fee_total_amount + $total_shipping_amount;

                $line_item_total_tax_amount = (array_key_exists($report_currency, $line_item_total_tax) && array_key_exists($tax_class, $line_item_total_tax[$report_currency])) ? $line_item_total_tax[$report_currency][$tax_class] : 0;

                $total_refunds_vat_amount = (array_key_exists($report_currency, $total_refunds_vat) && array_key_exists($tax_class, $total_refunds_vat[$report_currency])) ? $total_refunds_vat[$report_currency][$tax_class] : 0;

                $fee_total_tax_amount = (array_key_exists($report_currency, $fee_total_tax) && array_key_exists($tax_class, $fee_total_tax[$report_currency])) ? $fee_total_tax[$report_currency][$tax_class] : 0;

                $total_shipping_vat_amount = (array_key_exists($report_currency, $total_shipping_vat) && array_key_exists($tax_class, $total_shipping_vat[$report_currency])) ? $total_shipping_vat[$report_currency][$tax_class] : 0;

                $total_sum_total_order = (array_key_exists($report_currency, $sum_total_order) && array_key_exists($tax_class, $sum_total_order[$report_currency])) ? $sum_total_order[$report_currency][$tax_class] : 0;

                $total_sum_total_items = number_format( (array_key_exists($report_currency, $sum_total_items) && array_key_exists($tax_class, $sum_total_items[$report_currency])) ? $sum_total_items[$report_currency][$tax_class] : 0, 2);
                
                $total_vat_amount = $line_item_total_tax_amount + $total_refunds_vat_amount + $fee_total_tax_amount + $total_shipping_vat_amount;

                $total_amount = $sub_total_amount + $total_vat_amount;

                $sub_total_currency_amount = $total_amount * $exchange_rate;

                $grand_total_amount += $total_amount;
                $grand_total_vat_amount += $total_vat_amount;
                $grand_sub_total_amount += $sub_total_amount;
                $grand_sub_total_currency_amount += $sub_total_currency_amount;

                echo '<tr>';
                echo '<td align="left">';
                /* translators: %s: Tax class */
                echo esc_html(sprintf(__('Sales %s', 'woo-accounting-report'), $tax_class));
                echo '</td>';
                echo '<td align="right">';
                echo esc_html(static::format_number($line_item_total_amount));
                echo '</td>';
                echo '<td align="right">';
                echo esc_html(static::format_number($line_item_total_tax_amount));
                echo '</td>';
                echo '<td align="right">';
                echo esc_html(static::format_number($line_item_total_amount + $line_item_total_tax_amount));
                echo '</td>';
                if ($include_currencies && $base_currency != $report_currency) {
                    echo '<td align="right">';
                    echo esc_html(static::format_number($line_item_total_amount * $exchange_rate));
                    echo '</td>';
                    echo '<td align="right">';
                    echo esc_html(static::format_number($line_item_total_tax_amount * $exchange_rate));
                    echo '</td>';
                    echo '<td align="right">';
                    echo esc_html(static::format_number(($line_item_total_amount + $line_item_total_tax_amount) * $exchange_rate));
                    echo '</td>';
                }
                echo '</tr>';
                echo '<tr>';
                echo '<td align="left">';
                /* translators: %s: Tax class */
                echo esc_html(sprintf(__('Refunds %s', 'woo-accounting-report'), $tax_class));
                echo '</td>';
                echo '<td align="right">';
                echo esc_html(static::format_number($total_refunds_amount));
                echo '</td>';
                echo '<td align="right">';
                echo esc_html(static::format_number($total_refunds_vat_amount));
                echo '</td>';
                echo '<td align="right">';
                echo esc_html(static::format_number($total_refunds_amount + $total_refunds_vat_amount));
                echo '</td>';
                if ($include_currencies && $base_currency != $report_currency) {
                    echo '<td align="right">';
                    echo esc_html(static::format_number($total_refunds_amount * $exchange_rate));
                    echo '</td>';
                    echo '<td align="right">';
                    echo esc_html(static::format_number($total_refunds_vat_amount * $exchange_rate));
                    echo '</td>';
                    echo '<td align="right">';
                    echo esc_html(static::format_number(($total_refunds_amount + $total_refunds_vat_amount) * $exchange_rate));
                    echo '</td>';
                }
                echo '</tr>';
                echo '<tr>';
                echo '<td align="left">';
                /* translators: %s: Tax class */
                echo esc_html(sprintf(__('Fees %s', 'woo-accounting-report'), $tax_class));
                echo '</td>';
                echo '<td align="right">';
                echo esc_html(static::format_number($fee_total_amount));
                echo '</td>';
                echo '<td align="right">';
                echo esc_html(static::format_number($fee_total_tax_amount));
                echo '</td>';
                echo '<td align="right">';
                echo esc_html(static::format_number($fee_total_amount + $fee_total_tax_amount));
                echo '</td>';
                if ($include_currencies && $base_currency != $report_currency) {
                    echo '<td align="right">';
                    echo esc_html(static::format_number($fee_total_amount * $exchange_rate));
                    echo '</td>';
                    echo '<td align="right">';
                    echo esc_html(static::format_number($fee_total_tax_amount * $exchange_rate));
                    echo '</td>';
                    echo '<td align="right">';
                    echo esc_html(static::format_number(($fee_total_amount + $fee_total_tax_amount) * $exchange_rate));
                    echo '</td>';
                }
                echo '</tr>';
                echo '<tr>';
                echo '<td align="left">';
                /* translators: %s: Tax class */
                echo esc_html(sprintf(__('Shipping %s', 'woo-accounting-report'), $tax_class));
                echo '</td>';
                echo '<td align="right">';
                echo esc_html(static::format_number($total_shipping_amount));
                echo '</td>';
                echo '<td align="right">';
                echo esc_html(static::format_number($total_shipping_vat_amount));
                echo '</td>';
                echo '<td align="right">';
                echo esc_html(static::format_number($total_shipping_amount + $total_shipping_vat_amount));
                echo '</td>';
                if ($include_currencies && $base_currency != $report_currency) {
                    echo '<td align="right">';
                    echo esc_html(static::format_number($total_shipping_amount * $exchange_rate));
                    echo '</td>';
                    echo '<td align="right">';
                    echo esc_html(static::format_number($total_shipping_vat_amount * $exchange_rate));
                    echo '</td>';
                    echo '<td align="right">';
                    echo esc_html(static::format_number(($total_shipping_amount + $total_shipping_vat_amount) * $exchange_rate));
                    echo '</td>';
                }
                echo '</tr>';
                echo '<tr class="subtotal-row">';
                echo '<td align="left">';
                echo esc_html(__('Total sales incl shipping and charged fees', 'woo-accounting-report'));
                echo '</td>';
                echo '<td align="right">';
                echo esc_html(static::format_number($sub_total_amount));
                echo '</td>';
                echo '<td align="right">';
                echo esc_html(static::format_number($total_vat_amount));
                echo '</td>';
                echo '<td align="right">';
                echo esc_html(static::format_number($total_amount));
                echo '</td>';
                if ($include_currencies && $base_currency != $report_currency) {
                    echo '<td align="right">';
                    echo esc_html(static::format_number($sub_total_amount * $exchange_rate));
                    echo '</td>';
                    echo '<td align="right">';
                    echo esc_html(static::format_number($total_vat_amount * $exchange_rate));
                    echo '</td>';
                    echo '<td align="right">';
                    echo esc_html(static::format_number($sub_total_currency_amount));
                    echo '</td>';
                }
                echo '</tr>';
                echo '</tbody>';
                echo '</table>';
                echo '</div>';

            }
        }

    }
}
