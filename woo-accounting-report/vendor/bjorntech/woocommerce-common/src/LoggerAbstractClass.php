<?php

namespace BjornTech\Common;

defined('ABSPATH') || exit;

abstract class LoggerAbstractClass
{

    use SingletonTrait;

    /**
     * Contains pid for the logger
     *
     * @var int
     * @access protected
     */
    protected static $pid = 999999;

    /**
     * Contains the handle for the plugin
     * @access protected
     */
    protected $wc_logger = false;

    /**
     * Contains information about if to log all
     *
     * @var bool
     * @access protected
     */
    protected $log_all = true;

    /**
     * Contains information about if to log all
     *
     * @var bool
     * @access protected
     */
    protected $slug = 'logger';

    /**
     * Init function
     *
     * @param boolean $log_all
     * @param string $slug
     * @return void
     */
    public function __construct($log_all = true, $slug = 'logger')
    {
        $this->log_all = $log_all;
        $this->slug = $slug;
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
            return static::$pid;
        }

        return getmypid();
    }

    /**
     * Add log message
     *
     * @access protected
     *
     * @param mixed $message
     * @param bool $force
     * @param bool $wp_debug
     * @param string $slug
     *
     * @return void
     */
    public static function add($message, $force = false, $wp_debug = false, $slug = false)
    {

        $instance = static::get_instance();

        if (!$instance->wc_logger) {
            $instance->wc_logger = wc_get_logger();
        }

        if (true === $instance->log_all || true === $force) {

            if (is_array($message) || is_object($message)) {
                $message = print_r($message, true);
            }

            $instance->wc_logger->log(
                'info',
                static::get_pid() . ' - ' . $message,
                array(
                    'source' => $slug ? $slug : $instance->slug,
                )
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
    public static function separator()
    {
        static::add('-------------------------------------------------------');
    }

    /**
     * Returns a link to the log files in the WP backend.
     * 
     * @access protected
     * @param string $slug
     * @return string
     * 
     */
    public static function get_admin_link($slug = false)
    {

        $instance = static::get_instance();

        $log_path = wc_get_log_file_path($slug ? $slug : $instance->slug);
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

