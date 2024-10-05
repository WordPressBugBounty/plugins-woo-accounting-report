<?php

/*
The main plugin file for WooCommerce Accounting Report.

Generates an accounting report from WooCommerce

@package   BjornTech\AccountingReport
@author    BjornTech <info@bjorntech.com>
@license   GPL-3.0
@link      https://bjorntech.com
@copyright 2018-2023 BjornTech AB

@wordpress-plugin
Plugin Name:       WooCommerce Accounting Report
Plugin URI:        https://www.bjorntech.se/accountingreport
Description:       Generates Accounting reports
Version:           3.1.1
Author:            BjornTech
Author URI:        https://bjorntech.se
Text Domain:       woo-accounting-report
Domain Path:       /languages

WC requires at least: 4.0
WC tested up to: 9.1
Requires at least: 4.9
Tested up to: 6.6
Requires PHP: 7.4

Copyright:         2018-2024 BjornTech AB
License:           GNU General Public License v3.0
License URI:       http://www.gnu.org/licenses/gpl-3.0.html
*/

namespace BjornTech\AccountingReport;

defined('ABSPATH') || exit;

require __DIR__ . '/vendor/autoload_packages.php';

use BjornTech\Common\SingletonTrait;
use BjornTech\AccountingReport\Rest\RestHandler;

define('WC_ACCOUNTING_REPORT_VERSION', '3.0.3');
define('WC_ACCOUNTING_REPORT_HANDLE', 'woo_accounting_report');
define('WC_ACCOUNTING_REPORT_SLUG', 'woo-accounting-report');
define('WC_ACCOUNTING_REPORT_ID', 'wcar');

/**
 *    WC_Accounting_Report
 *
 */
class MainPluginClass
{

    use SingletonTrait;

    public $version = WC_ACCOUNTING_REPORT_VERSION;
    public $handle = WC_ACCOUNTING_REPORT_HANDLE;
    public $slug = WC_ACCOUNTING_REPORT_SLUG;
    public $id = WC_ACCOUNTING_REPORT_ID;
    public $file = __FILE__;

    /**
     * Init and hook in the integration.
     **/
    public function __construct()
    {

        RestHandler::init();

        add_action('plugins_loaded', array($this, 'maybe_load_plugin'));

        add_action('init', array($this, 'register_settings'));

        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_action_links'));

        add_action('before_woocommerce_init', function () {
            if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
            }
        });

        load_plugin_textdomain($this->slug, false, dirname(plugin_basename(__FILE__)) . '/languages');

        add_action('admin_enqueue_scripts', array($this, 'admin_add_styles_and_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'add_accounting_report_script'));

    }

    public function maybe_load_plugin()
    {

        if (!class_exists('WooCommerce')) {
            return;
        }

        if ('yes' === get_option('wcar_load_analytics','yes')) {
            add_filter('woocommerce_analytics_report_menu_items', array($this, 'add_woo_accounting_report_to_analytics_menu'));
        }

        Settings::init();

        Logger::init('yes' === get_option('bjorntech_' . $this->id . '_logging'), $this->slug);

        add_filter('woocommerce_admin_reports', array($this, 'add_report_to_menu'));
    }

    public function register_settings()
    {
        register_setting(
            'general',
            'bjorntech_wcar_include_order_statuses',
            [
                'show_in_rest' => array(
                    'schema' => array(
                        'type' => 'array',
                        'items' => array(
                            'type' => 'string',
                        ),
                    ),
                ),
                'default' => ['wc-completed'],
                'type' => 'object',
            ]
        );
        register_setting(
            'general',
            'bjorntech_wcar_on_status',
            [
                'default' => 'date_paid',
                'show_in_rest' => true,
                'type' => 'string',
            ]
        );
        register_setting(
            'general',
            'bjorntech_wcar_force_local',
            [
                'default' => '',
                'show_in_rest' => true,
                'type' => 'string',
            ]
        );
        register_setting(
            'general',
            'bjorntech_wcar_logging',
            [
                'default' => '',
                'show_in_rest' => true,
                'type' => 'string',
            ]
        );
        register_setting(
            'general',
            'bjorntech_wcar_nonce',
            [
                'default' => '',
                'show_in_rest' => true,
                'type' => 'string',
            ]
        );
        register_setting(
            'general',
            'bjorntech_wcar_show_oss_pane',
            [
                'default' => '',
                'show_in_rest' => true,
                'type' => 'string',
            ]
        );
        register_setting(
            'general',
            'wcar_load_analytics',
            [
                'default' => '',
                'show_in_rest' => true,
                'type' => 'string',
            ]
        );
        register_setting(
            'general',
            'bjorntech_wcar_fortnox_invoice',
            [
                'default' => '',
                'show_in_rest' => true,
                'type' => 'string',
            ]
        );
        register_setting(
            'general',
            'bjorntech_wcar_price_num_decimals',
            [
                'default' => '',
                'show_in_rest' => true,
                'type' => 'string',
            ]
        );
        register_setting(
            'general',
            'bjorntech_wcar_price_decimal_sep',
            [
                'default' => '',
                'show_in_rest' => true,
                'type' => 'string',
            ]
        );
        register_setting(
            'general',
            'bjorntech_wcar_price_thousand_sep',
            [
                'default' => '',
                'show_in_rest' => true,
                'type' => 'string',
            ]
        );
        register_setting(
            'general',
            'woocommerce_default_country',
            [
                'default' => '',
                'show_in_rest' => true,
                'type' => 'string',
            ]
        );

    }

    public function add_report_to_menu($reports)
    {
        $reports['accounting'] = array(
            'title' => __('Accounting report', 'woo-accounting-report'),
            'reports' => array(
                'total-report' => array(
                    'title' => __('Total report', 'woo-accounting-report'),
                    'description' => '',
                    'hide_title' => true,
                    'callback' => array($this, 'process_report'),
                ),
                'total-sales' => array(
                    'title' => __('Total sales', 'woo-accounting-report'),
                    'description' => '',
                    'hide_title' => true,
                    'callback' => array($this, 'process_report'),
                ),
                'methods-of-payment' => array(
                    'title' => __('Methods of payment', 'woo-accounting-report'),
                    'description' => '',
                    'hide_title' => true,
                    'callback' => array($this, 'process_report'),
                ),
                'sales-per-region' => array(
                    'title' => __('Sales by region', 'woo-accounting-report'),
                    'description' => '',
                    'hide_title' => true,
                    'callback' => array($this, 'process_report'),
                ),
                'all-orders' => array(
                    'title' => __('All orders', 'woo-accounting-report'),
                    'description' => '',
                    'hide_title' => true,
                    'callback' => array($this, 'process_report'),
                ),
                'all-orders-totals' => array(
                    'title' => __('All orders with totals', 'woo-accounting-report'),
                    'description' => '',
                    'hide_title' => true,
                    'callback' => array($this, 'process_report'),
                ),

            ),
        );
        return $reports;
    }

    public function admin_add_styles_and_scripts($pagehook)
    {

        wp_register_style('accounting-report', plugin_dir_url(__FILE__) . 'assets/css/accounting-report.css', array(), WC_ACCOUNTING_REPORT_VERSION);
        wp_enqueue_style('accounting-report');

    }
    /**
     * Get a report from our reports subfolder.
     *
     * @param string $name
     */
    public function process_report($name)
    {

        $report = new AccountingReport();
        $report->process_report($name != 'total-report' ? array($name) : array());

    }

    public function add_action_links($links)
    {
        $links = array_merge(
            array(
                '<a href="' . admin_url('admin.php?page=wc-settings&tab=accounting_report') . '">' . __('Settings', 'woo-accounting-report') . '</a>',
            ),
            $links
        );

        return $links;
    }

    public function add_woo_accounting_report_to_analytics_menu($report_pages)
    {
        $report_pages[] = array(
            'id' => 'woo-accounting-report',
            'title' => __('Accounting', 'woo-accounting-report'),
            'parent' => 'woocommerce-analytics',
            'path' => '/analytics/woo-accounting-report',
        );
        return $report_pages;
    }

    /**
     * Register the JS.
     */
    public function add_accounting_report_script()
    {

        if (!class_exists('Automattic\WooCommerce\Admin\PageController') || !\Automattic\WooCommerce\Admin\PageController::is_admin_or_embed_page()) {
            return;
        }

        $script_path = '/build/report.js';
        $script_asset_path = dirname(__FILE__) . '/build/report.asset.php';
        $script_asset = file_exists($script_asset_path)
            ? require $script_asset_path
            : array('dependencies' => array(), 'version' => filemtime($script_path));
        $script_url = plugins_url($script_path, __FILE__);
        $style_path = dirname(__FILE__) . '/build/report.css';
        $style_url = plugins_url('/build/report.css', __FILE__);

        wp_register_script(
            'woo-accounting-report',
            $script_url,
            $script_asset['dependencies'],
            $script_asset['version'],
            true
        );

        if (file_exists($style_path)) {
            wp_register_style(
                'woo-accounting-report',
                $style_url,
                // Add any dependencies styles may have, such as wp-components.
                array(),
                filemtime($style_path)
            );
        }

        wp_enqueue_script('woo-accounting-report');
        wp_enqueue_style('woo-accounting-report');

    }

}

MainPluginClass::init();
