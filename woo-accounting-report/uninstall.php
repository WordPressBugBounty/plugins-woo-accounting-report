<?php
/**
 * Uninstall handler for BjornTech Accounting Report for WooCommerce.
 *
 * Runs only when the plugin is uninstalled via the WordPress admin.
 * Deletes plugin-specific options so no traces remain in the database.
 *
 * @package BjornTech\AccountingReport
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$bjorntech_wcar_options = array(
    'bjorntech_wcar_include_order_statuses',
    'bjorntech_wcar_on_status',
    'bjorntech_wcar_force_local',
    'bjorntech_wcar_logging',
    'bjorntech_wcar_nonce',
    'bjorntech_wcar_show_oss_pane',
    'bjorntech_wcar_present_local_currency',
    'bjorntech_wcar_fortnox_invoice',
    'bjorntech_wcar_price_num_decimals',
    'bjorntech_wcar_price_decimal_sep',
    'bjorntech_wcar_price_thousand_sep',
    'bjorntech_wcar_exchange_rates_api_key',
);

foreach ($bjorntech_wcar_options as $bjorntech_wcar_option) {
    delete_option($bjorntech_wcar_option);
    delete_site_option($bjorntech_wcar_option);
}

unset($bjorntech_wcar_options, $bjorntech_wcar_option);
