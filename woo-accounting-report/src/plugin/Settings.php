<?php
/**
 * Provides functions for the plugin settings page in the WordPress admin.
 *
 * Settings can be accessed at WooCommerce -> Settings -> Accounting Hub.
 *
 * @package   WooCommerce_Accounting_Report
 * @author    BjornTech <info@bjorntech.se>
 * @license   GPL-3.0
 * @link      http://bjorntech.se
 * @copyright 2017-2018 BjornTech - BjornTech AB
 *
 * Text Domain:       woo-accounting-report
 */

namespace BjornTech\AccountingReport;

defined('ABSPATH') || exit;

use BjornTech\Common\SingletonTrait;

class Settings
{

    use SingletonTrait;
    private static $handle = WC_ACCOUNTING_REPORT_HANDLE;

    /**
     * Constructor.
     */
    public function __construct()
    {

        add_filter('woocommerce_settings_tabs_array', array($this, 'add_settings_tab'), 9999);
        add_filter('woocommerce_get_sections_' . static::$handle, array($this, 'get_sections'));


        add_action('woocommerce_settings_' . static::$handle, array($this, 'settings_tab'));
        add_action('woocommerce_update_options_' . static::$handle, array($this, 'update_settings'));

    }

    /**
     * Add settings tab.
     *
     * @param array $settings_tabs Array of WooCommerce setting tabs & their labels, excluding the Subscription tab.
     * @return array
     */

    public function add_settings_tab($settings_tabs)
    {
        $settings_tabs[static::$handle] = __('Accounting report', 'woo-accounting-report');
        return $settings_tabs;
    }

    /**
     * Get sections.
     *
     * @param array $sections Array of WooCommerce setting sections.
     * @return array
     */
    public function get_sections($sections)
    {
        $sections = array(
            '' => __('General', 'woo-accounting-report'),
            'advanced' => __('Advanced', 'woo-accounting-report'),
        );

        return $sections;
    }


    /**
     * Render settings tab.
     * 
     * @param array $sections Array of WooCommerce setting sections.
     * @return void
     */
    public function settings_tab($sections)
    {
        woocommerce_admin_fields(static::get_settings());
    }

    /**
     * Update settings.
     *
     * @return void
     */
    public function update_settings()
    {
        woocommerce_update_options(static::get_settings());
    }

    /**
     * Get settings.
     *
     * @param string $current_section Current section.
     * @return array
     */
    public function get_settings($current_section = '')
    {
        if ('' === $current_section) {

            $settings[] = [
                'title' => __('General settings', 'woo-accounting-report'),
                'type' => 'title',
                'desc' => '',
                'id' => 'woo_accounting_report_general',
            ];

            $settings[] = [
                'title' => __('Base the report on status', 'woo-accounting-report'),
                'css' => 'min-width:150px;',
                'default' => 'date_completed',
                'type' => 'select',
                'class' => 'wc-enhanced-select',
                'options' => array(
                    'date_completed' => 'Completed',
                    'date_paid' => 'Paid',
                    'date_created' => 'Created',
                ),
                'desc' => __('Base the report on when a transaction was paid or when the order was created or when it was set to completed status.', 'woo-accounting-report'),
                'id' => 'bjorntech_wcar_on_status',
            ];

            $settings[] = [
                'title' => __('Tax Class for refunds', 'woo-accounting-report'),
                'css' => 'min-width:150px;',
                'default' => '',
                'type' => 'select',
                'class' => 'wc-enhanced-select',
                'options' => wc_get_product_tax_class_options(),
                'desc' => __('Choose Tax class to use for reverse calculation of tax on refunds without tax (only used in the report section).', 'woo-accounting-report'),
                'id' => 'woo_ar_reverse_tax_class',
            ];

            $settings[] = [
                'title' => __('Include order statuses', 'woo-accounting-report'),
                'type' => 'multiselect',
                'class' => 'wc-enhanced-select',
                'css' => 'width: 400px;',
                'default' => 'wc-completed',
                'desc' => __('Choose the order statuses that you would like to be included in the report.', 'woo-accounting-report'),
                'options' => wc_get_order_statuses(),
                'id' => 'bjorntech_wcar_include_order_statuses',
            ];

            $settings[] = [
                'title' => __('Treat all sales as local', 'woo-accounting-report'),
                'default' => '',
                'type' => 'checkbox',
                'desc' => __('Choose whether to treat all sales as local, regardless of country.', 'woo-accounting-report'),
                'id' => 'bjorntech_wcar_force_local',
            ];

            if (class_exists('Woo_Fortnox_Hub', false)) {

                $settings[] = [
                    'title' => __('Show Fortnox Invoice', 'woo-accounting-report'),
                    'default' => '',
                    'type' => 'checkbox',
                    'desc' => __('Choose if the report should include the Fortnox Invoice number.', 'woo-accounting-report'),
                    'id' => 'bjorntech_wcar_fortnox_invoice',
                ];

            }

            $settings[] = [
                'title' => __('Thousand separator', 'woo-accounting-report'),
                'desc' => __('This sets the thousand separator of prices.', 'woo-accounting-report'),
                'css' => 'width:50px;',
                'default' => wc_get_price_thousand_separator(),
                'type' => 'text',
                'id' => 'bjorntech_wcar_price_thousand_sep',
            ];

            $settings[] = [
                'title' => __('Decimal separator', 'woo-accounting-report'),
                'desc' => __('This sets the decimal separator of prices.', 'woo-accounting-report'),
                'css' => 'width:50px;',
                'default' => wc_get_price_decimal_separator(),
                'type' => 'text',
                'id' => 'bjorntech_wcar_price_decimal_sep',
            ];

            $settings[] = [
                'title' => __('Number of decimals', 'woo-accounting-report'),
                'desc' => __('Please select the number of decimal points that you would like to be displayed in the prices.', 'woo-accounting-report'),

                'css' => 'width:50px;',
                'default' => '2',
                'type' => 'number',
                'custom_attributes' => array(
                    'min' => 0,
                    'step' => 1,
                ),
                'id' => 'bjorntech_wcar_price_num_decimals',
            ];

            $settings[] = [
                'title' => __('Create a log file', 'woo-accounting-report'),
                'default' => '',
                'type' => 'checkbox',
                'description' => __('A log file can be very helpful in identifying and resolving problems.', 'woo-accounting-report'),
                'id' => 'bjorntech_wcar_logging',
            ];

            $settings[] = [
                'title' => __('Load Analytics report', 'woo-accounting-report'),
                'default' => '',
                'type' => 'checkbox',
                'description' => __('(Eperimental) Enables the new analytics report', 'woo-accounting-report'),
                'id' => 'bjorntech_wcar_load_analytics',
            ];

            $settings[] = [
                'title' => __('Show OSS pane', 'woo-accounting-report'),
                'default' => '',
                'type' => 'checkbox',
                'description' => __('(Eperimental) Enables the OSS pane in the new analytics report', 'woo-accounting-report'),
                'id' => 'bjorntech_wcar_show_oss_pane',
            ];

            $settings[] = [
                'type' => 'sectionend',
                'id' => 'woo_accounting_report_general',
            ];

        } else if ('advanced' === $current_section) {

            $settings[] = [
                'title' => __('Advanced settings', 'woo-accounting-report'),
                'type' => 'title',
                'desc' => '',
                'id' => 'woo_accounting_report_advanced',
            ];

            $settings[] = [
                'type' => 'sectionend',
                'id' => 'woo_accounting_report_advanced',
            ];

        }

        return $settings;

    }


}
