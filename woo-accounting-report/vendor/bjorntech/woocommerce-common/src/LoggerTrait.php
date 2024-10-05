<?php

namespace BjornTech\Common;

defined('ABSPATH') || exit;

trait LoggerTrait
{

    use instanceTrait;
    use PluginClassTrait;

    public function __construct($message = '', $force = false, $wp_debug = false, $slug = false)
    {

    }

    /**
     * Contains pid for the logger
     *
     * @var int
     * @access protected
     */
    protected static $bt_pid = 999999;

    /**
     * Contains the handle for the plugin
     * @access protected
     */
    protected $wc_logger;

    /**
     * Contains information about if to log all
     *
     * @var bool
     * @access protected
     */
    protected $log_all;

    /**
     * Contains information about if to log all
     *
     * @var bool
     * @access protected
     */
    protected static function get_logging_option()
    {
        return 'yes' === get_option('bjorntech_' . static::$id . '_logging');
    }

    /**
     * Get current PID
     *
     * @return bool|int
     */
    protected static function get_pid()
    {
        $disabled_functions = ini_get("disable_functions");

        if (!$disabled_functions) {
            return getmypid();
        }

        if (strpos($disabled_functions, 'getmypid') !== false) {
            return static::$bt_pid;
        }

        return getmypid();
    }

    protected static function _bjorntech_function_add($calledClass, $instance, $name, $args)
    {
        return static::_add($args[0], isset($args[1]) ? $args[1] : false, isset($args[2]) ? $args[2] : false, isset($args[3]) ? $args[3] : false);
    }

    protected static function _bjorntech_function_separator($calledClass, $instance, $name, $args)
    {
        return static::_separator();
    }

    protected static function _bjorntech_function_get_admin_link($calledClass, $instance, $name, $args)
    {
        return static::_get_admin_link();
    }

    /**
     * Add log message
     *
     * @access protected
     *
     * @param mixed $message
     * @param bool $force
     * @param bool $wp_debug
     *
     * @return void
     */
    protected static function _add($message, $force = false, $wp_debug = false, $slug = false)
    {

        $instance = static::get_instance();

        if (empty($instance->wc_logger)) {
            $instance->wc_logger = wc_get_logger();
            $instance->log_all = static::get_logging_option();
        }

        if (true === $instance->log_all || true === $force) {

            if (is_array($message) || is_object($message)) {
                $message = print_r($message, true);
            }

            $instance->wc_logger->add(
                $slug ? $slug : static::get_slug(),
                $instance->get_pid() . ' - ' . $message
            );

            if (true === $wp_debug && defined('WP_DEBUG') && WP_DEBUG) {
                error_log(static::get_pid() . ' - ' . $message);
            }
        }
    }


    /**
     * separator function.
     *
     * Inserts a separation line for better overview in the logs.
     *
     * @access protected
     * @return void
     */
    protected static function _separator()
    {
        static::logger('-------------------------------------------------------');
    }

    /**
     * Returns a link to the log files in the WP backend.
     */
    protected static function _get_admin_link()
    {


        $log_path = wc_get_log_file_path(static::get_slug());
        $log_path_parts = explode('/', $log_path);
        return add_query_arg(
            array(
                'page' => 'wc-status',
                'tab' => 'logs',
                'log_file' => end($log_path_parts),
            ),
            admin_url('admin.php')
        );

    }

}

