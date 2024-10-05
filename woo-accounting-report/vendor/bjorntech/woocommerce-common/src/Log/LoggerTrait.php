<?php

namespace BjornTech\Common\Log;

trait LoggerTrait{

    private static $logger_data = array();
    private static $bt_pid = false;

    public static function set_handle($handle){
        static::set_data('handle', $handle);
    }

    public static function set_logger_option($logger_option){
        static::set_data('logger_option', $logger_option);
    }

    public static function get_handle() {
        if (!static::has_data('handle')){
            static::set_data('handle', static::get_defined_handle());
        }

        return static::get_data('handle');
    }

    public static function get_logger_option() {
        if (!static::has_data('logger_option')){
            static::set_data('logger_option', static::get_defined_logger_option());
        }

        return static::get_data('logger_option');
    }

    private static function get_data($key) {
        $class = get_called_class();

        return static::$logger_data[$class][$key];
    }

    private static function set_data($key, $value) {
        $class = get_called_class();

        static::$logger_data[$class][$key] = $value;
    }

    private static function has_data($key) {
        $class = get_called_class();

        return isset(static::$logger_data[$class][$key]);
    }

    /**
     * Log a message to the WooCommerce log
     * @param $message - The message to log
     * @param $force - Force the message to be logged even if logging is disabled
     * @param $wp_debug - Log the message to the PHP error log
     * 
     * @return void
     */
    public static function log($message, $force = false, $wp_debug = false)
    {
        if (!static::has_data('logger')){
            static::set_data('logger', wc_get_logger());
        }

        if (!static::has_data('handle')){
            static::set_data('handle', static::get_defined_handle());
        }

        if (!static::has_data('logger_option')){
            static::set_data('logger_option', static::get_defined_logger_option());
        }

        if (!(static::log_enabled() || $force === true)){
            return;
        }

        if (is_array($message)) {
            $message = print_r($message, true);
        }

        static::get_data('logger')->log('info', static::getpid() . ' - ' . $message, array(
            'source' => static::get_handle(),
        ));

        /*static::get_data('logger')->add(
            static::get_handle(),
            static::getpid() . ' - ' . $message
        );*/

        if (true === $wp_debug && defined('WP_DEBUG') && WP_DEBUG) {
            error_log(static::getpid() . ' - ' . $message);
        }
    }

    public static function separator() {
        static::log('--------------------------------------------------');
    }

    public static function getpid() {
        $disabled_functions = ini_get("disable_functions");

        if (false === static::$bt_pid){
            static::$bt_pid = rand(1, 999999);
        }

        if (!$disabled_functions) {
            return getmypid();
        }

        if (strpos($disabled_functions, 'getmypid') !== false) {
            return static::$bt_pid;
        }

        return getmypid();
    }

    public static function get_wc_admin_link() {
        $log_path = home_url('/wp-admin/admin.php');
        return add_query_arg(array(
            'page' => 'wc-status',
            'tab' => 'logs',
            'view' => 'single_file',
            'file_id' => static::get_handle() . '-' . date('Y-m-d'),
        ), $log_path);
    }

    public static function log_enabled() {
        return ('yes' === get_option(static::get_logger_option()));
    }

    private static function get_defined_handle(){
        if (defined('static::handle')){
            return constant('static::handle');
        } else {
            return 'bjorntech';
        }
    }

    private static function get_defined_logger_option(){
        if (defined('static::logger_option')){
            return constant('static::logger_option');
        } else {
            return 'bjorntech_log_enabled';
        }
    }
}