<?php

namespace BjornTech\Common;

use BjornTech\Common\Logger;

defined('ABSPATH') || exit;

class ApiException extends GeneralException
{
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
        Logger::add('API Exception file: ' . $this->getFile());
        Logger::add('API Exception line: ' . $this->getLine());
        Logger::add('API Exception code: ' . $this->getCode());
        Logger::add('API Exception message: ' . $this->getMessage());

        if (!empty($this->curl_request_url)) {
            Logger::add('API Exception Request URL: ' . $this->curl_request_url);
        }

        if (!empty($this->curl_request_data)) {
            Logger::add('API Exception Request DATA: ' . $this->curl_request_data);
        }

        if (!empty($this->curl_response_data)) {
            Logger::add('API Exception Response DATA: ' . $this->curl_response_data);
        }

        Logger::separator();

    }
}
