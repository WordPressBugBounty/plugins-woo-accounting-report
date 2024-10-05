<?php

namespace BjornTech\Common;

use Exception;

defined('ABSPATH') || exit;
class GeneralException extends Exception
{
    /**
     * Contains a log object instance
     * @access protected
     */
    protected $log;

    /**
     * Contains the curl object instance
     * @access protected
     */
    protected $curl_request_data;

    /**
     * Contains the curl url
     * @access protected
     */
    protected $curl_request_url;

    /**
     * Contains the curl response data
     * @access protected
     */
    protected $curl_response_data;

    /**
     * __Construct function.
     *
     * Redefine the exception so message isn't optional
     *
     * @access public
     * @return void
     */
    public function __construct($message, $code = 0, Exception $previous = null, $curl_request_url = '', $curl_request_data = '', $curl_response_data = '')
    {
        // make sure everything is assigned properly
        parent::__construct($message, $code, $previous);

        $this->curl_request_data = $curl_request_data;
        $this->curl_request_url = $curl_request_url;
        $this->curl_response_data = $curl_response_data;
    }

    /**
     * write_to_logs function.
     *
     * Stores the exception dump in the WooCommerce system logs
     *
     * @access public
     * @return void
     */
    public function write_to_logs()
    {
        Logger::separator();
        Logger::add('General Exception file: ' . $this->getFile());
        Logger::add('General Exception line: ' . $this->getLine());
        Logger::add('General Exception code: ' . $this->getCode());
        Logger::add('General Exception message: ' . $this->getMessage());
        Logger::separator();
    }

}
