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

        echo '<caption>' . __('Total sales per region', 'woo-accounting-report') . '</caption>';

        echo '<thead>';
        echo '<tr>';
        echo '<th scope="col" style="text-align:left;">' . __('Region', 'woo-accounting-report') . '</th>';
        echo '<th scope="col" style="text-align:right;">' . __('Net sales', 'woo-accounting-report') . '</th>';
        echo '<th scope="col" style="text-align:right;">' . __('TAX', 'woo-accounting-report') . '</th>';
        echo '<th scope="col" style="text-align:right;">' . __('Sales incl. TAX', 'woo-accounting-report') . '</th>';
        echo '<th scope="col" style="text-align:right">' . __('Currency', 'woo-accounting-report') . '</th>';
        echo '</tr>';
        echo '</thead>';

        foreach ($site_currencies as $report_currency) {
            foreach ($sales_per_region[$report_currency] as $region => $amount) {
                echo '<tbody>';
                echo '<tr>';
                echo '<td align="left">' . $region . '</td>';
                echo '<td align="right">' . static::format_number($amount['total'] - $amount['tax']) . '</td>';
                echo '<td align="right">' . static::format_number($amount['tax']) . '</td>';
                echo '<td align="right">' . static::format_number($amount['total']) . '</td>';
                echo '<td align="right">' . $report_currency . '</td>';
                echo '</tr>';
                echo '</tbody>';
            }
        }

        echo '</table>';

        echo '</div>';

    }
}