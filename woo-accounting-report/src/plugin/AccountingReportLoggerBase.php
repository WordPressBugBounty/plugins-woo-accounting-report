<?php

namespace BjornTech\AccountingReport;

defined('ABSPATH') || exit;

abstract class AccountingReportLoggerBase
{
    use AccountingReportSingletonTrait;

    protected static $pid = 999999;

    protected $wc_logger = false;

    protected $log_all = true;

    protected $slug = 'logger';

    public function __construct($log_all = true, $slug = 'logger')
    {
        $this->log_all = $log_all;
        $this->slug = $slug;
    }

    protected static function get_pid()
    {
        $disabled_functions = ini_get('disable_functions');

        if (!$disabled_functions) {
            return getmypid();
        }

        if (strpos($disabled_functions, 'getmypid') !== false) {
            return static::$pid;
        }

        return getmypid();
    }

    public static function add($message, $force = false, $wp_debug = false, $slug = false)
    {
        $instance = static::get_instance();

        if (!$instance->wc_logger) {
            $instance->wc_logger = \wc_get_logger();
        }

        if (true === $instance->log_all || true === $force) {
            if (is_wp_error($message)) {
                $message = 'WP_Error: ' . $message->get_error_message() . ' (' . $message->get_error_code() . ')';
            } elseif (is_array($message) || is_object($message)) {
                $message = wp_json_encode($message);
            }

            $instance->wc_logger->log(
                'info',
                static::get_pid() . ' - ' . $message,
                array(
                    'source' => $slug ? $slug : $instance->slug,
                )
            );

            if (true === $wp_debug && defined('WP_DEBUG') && \WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional debug output guarded by WP_DEBUG flag.
                \error_log(static::get_pid() . ' - ' . $message);
            }
        }
    }

    public static function separator()
    {
        static::add('-------------------------------------------------------');
    }

    public static function get_admin_link($slug = false)
    {
        $instance = static::get_instance();
        $log_path = \wc_get_log_file_path($slug ? $slug : $instance->slug);
        $log_path_parts = explode('/', $log_path);

        return \add_query_arg(
            array(
                'page' => 'wc-status',
                'tab' => 'logs',
                'log_file' => end($log_path_parts),
            ),
            \admin_url('admin.php')
        );
    }
}
