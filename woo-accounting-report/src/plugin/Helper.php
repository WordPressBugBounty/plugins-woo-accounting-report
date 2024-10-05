<?php

namespace BjornTech\AccountingReport;

defined('ABSPATH') || exit;
trait Helper
{
    public static function format_number($price)
    {

        $decimal_separator = get_option('bjorntech_wcar_price_decimal_sep', wc_get_price_decimal_separator());
        $thousand_separator = get_option('bjorntech_wcar_price_thousand_sep', wc_get_price_thousand_separator());
        $decimals = (int) get_option('bjorntech_wcar_price_num_decimals', wc_get_price_decimals());

        $thousand_separator = $thousand_separator === "SPACE" ? " " : $thousand_separator;

        if (is_numeric($price)) {
            return number_format($price, $decimals, $decimal_separator, $thousand_separator);
        } else {
            return $price;
        }

    }
}
