<?php
/**
 * Provides functions for the plugin settings page in the WordPress admin.
 *
 * Settings can be accessed at WooCommerce -> Settings -> Accounting report.
 *
 * @package   WooCommerce_Accounting_Report
 * @author    BjornTech <hello@bjorntech.com>
 * @license   GPL-3.0
 * @link      http://bjorntech.com
 * @copyright 2017-2026 BjornTech - BjornTech AB
 *
 * Text Domain:       woo-accounting-report
 */

namespace BjornTech\AccountingReport;

defined('ABSPATH') || exit;

class Settings
{

    use AccountingReportSingletonTrait;
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
                'title' => __('Base report on order date', 'woo-accounting-report'),
                'css' => 'min-width:150px;',
                'default' => 'date_completed',
                'type' => 'select',
                'class' => 'wc-enhanced-select',
                'options' => array(
                    'date_completed' => 'Completed',
                    'date_paid' => 'Paid',
                    'date_created' => 'Created',
                ),
                'desc' => __('Choose which order date is used when filtering report data.', 'woo-accounting-report'),
                'id' => 'bjorntech_wcar_on_status',
            ];

            $settings[] = [
                'title' => __('Include order statuses', 'woo-accounting-report'),
                'type' => 'multiselect',
                'class' => 'wc-enhanced-select',
                'css' => 'width: 400px;',
                'default' => 'wc-completed',
                'desc' => __('Select which order statuses are included in report calculations.', 'woo-accounting-report'),
                'options' => wc_get_order_statuses(),
                'id' => 'bjorntech_wcar_include_order_statuses',
            ];

            $settings[] = [
                'title' => __('Treat all sales as domestic', 'woo-accounting-report'),
                'default' => '',
                'type' => 'checkbox',
                'desc' => __('Classify all sales as domestic regardless of customer country.', 'woo-accounting-report'),
                'id' => 'bjorntech_wcar_force_local',
            ];

            $settings[] = [
                'title' => __('Present in local currency', 'woo-accounting-report'),
                'default' => '',
                'type' => 'checkbox',
                'desc' => __('Convert all Analytics report values to store currency using exchange rates.', 'woo-accounting-report'),
                'id' => 'bjorntech_wcar_present_local_currency',
            ];

            if (class_exists('Woo_Fortnox_Hub', false)) {

                $settings[] = [
                    'title' => __('Show Fortnox invoice number', 'woo-accounting-report'),
                    'default' => '',
                    'type' => 'checkbox',
                    'desc' => __('Show the Fortnox invoice number in report output.', 'woo-accounting-report'),
                    'id' => 'bjorntech_wcar_fortnox_invoice',
                ];

            }

            $settings[] = [
                'title' => __('Thousand separator', 'woo-accounting-report'),
                'desc' => __('Character used as the thousands separator in report amounts.', 'woo-accounting-report'),
                'css' => 'width:50px;',
                'default' => wc_get_price_thousand_separator(),
                'type' => 'text',
                'id' => 'bjorntech_wcar_price_thousand_sep',
            ];

            $settings[] = [
                'title' => __('Decimal separator', 'woo-accounting-report'),
                'desc' => __('Character used as the decimal separator in report amounts.', 'woo-accounting-report'),
                'css' => 'width:50px;',
                'default' => wc_get_price_decimal_separator(),
                'type' => 'text',
                'id' => 'bjorntech_wcar_price_decimal_sep',
            ];

            $settings[] = [
                'title' => __('Number of decimals', 'woo-accounting-report'),
                'desc' => __('Number of decimals shown for report amounts.', 'woo-accounting-report'),

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
                'title' => __('Enable debug logging', 'woo-accounting-report'),
                'default' => '',
                'type' => 'checkbox',
                'desc' => __('Write debug information to WooCommerce logs to help troubleshooting.', 'woo-accounting-report'),
                'id' => 'bjorntech_wcar_logging',
            ];

            $settings[] = [
                'title' => __('Show OSS pane', 'woo-accounting-report'),
                'default' => '',
                'type' => 'checkbox',
                'desc' => __('(Experimental) Show the OSS pane in the Analytics report.', 'woo-accounting-report'),
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
                'title' => __('Exchange rates API key', 'woo-accounting-report'),
                'type' => 'password',
                'desc' => __('Used server-side for exchange rate lookups (currency conversion and OSS calculations). You can also define BJORNTECH_ACCOUNTING_EXCHANGE_RATES_API_KEY in wp-config.php.', 'woo-accounting-report'),
                'id' => 'bjorntech_wcar_exchange_rates_api_key',
            ];

            $settings[] = [
                'type' => 'sectionend',
                'id' => 'woo_accounting_report_advanced',
            ];

        }

        return $settings;

    }


}
