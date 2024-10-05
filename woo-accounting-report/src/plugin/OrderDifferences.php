<?php

namespace BjornTech\AccountingReport;

defined('ABSPATH') || exit;

class OrderDifferences
{

    use Helper;

    public static function render($order_differences)
    {
        echo '<div class="row">';
        echo '<table cellspacing="0" cellpadding="2" class="styled-table">';
        echo '<caption>';
        echo __('Differences found in orders', 'woo-accounting-report');
        echo '</caption>';
        echo '<thead>';
        echo '<tr>';
        echo '<th scope="col" style="text-align:left;">';
        echo __('Order details', 'woo-accounting-report');
        echo '</th>';
        echo '</tr>';
        echo '</thead>';
        foreach ($order_differences as $order_difference) {
            echo '<tbody>';
            echo '<tr>';
            echo '<td align="left">';
            echo $order_difference;
            echo '</td>';
            echo '</tr>';
            echo '</tbody>';
        }
        echo '</table>';
        echo '</div>';

    }
}

