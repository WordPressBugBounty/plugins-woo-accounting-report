<?php

namespace BjornTech\AccountingReport;

defined('ABSPATH') || exit;

class SalesPerRegion
{

    use Helper;

    public static function render($sales_per_region, $site_currencies)
    {

        echo '<div class="row">';

        echo '<table cellspacing="0" cellpadding="2" class="styled-table">';

        echo '<caption>' . esc_html__('Total sales per region', 'woo-accounting-report') . '</caption>';

        echo '<thead>';
        echo '<tr>';
        echo '<th scope="col" style="text-align:left;">' . esc_html__('Region', 'woo-accounting-report') . '</th>';
        echo '<th scope="col" style="text-align:right;">' . esc_html__('Net sales', 'woo-accounting-report') . '</th>';
        echo '<th scope="col" style="text-align:right;">' . esc_html__('TAX', 'woo-accounting-report') . '</th>';
        echo '<th scope="col" style="text-align:right;">' . esc_html__('Sales incl. TAX', 'woo-accounting-report') . '</th>';
        echo '<th scope="col" style="text-align:right">' . esc_html__('Currency', 'woo-accounting-report') . '</th>';
        echo '</tr>';
        echo '</thead>';

        foreach ($site_currencies as $report_currency) {
            foreach ($sales_per_region[$report_currency] as $region => $amount) {
                echo '<tbody>';
                echo '<tr>';
                echo '<td align="left">' . esc_html($region) . '</td>';
                echo '<td align="right">' . esc_html(static::format_number($amount['total'] - $amount['tax'])) . '</td>';
                echo '<td align="right">' . esc_html(static::format_number($amount['tax'])) . '</td>';
                echo '<td align="right">' . esc_html(static::format_number($amount['total'])) . '</td>';
                echo '<td align="right">' . esc_html($report_currency) . '</td>';
                echo '</tr>';
                echo '</tbody>';
            }
        }

        echo '</table>';

        echo '</div>';

    }
}
