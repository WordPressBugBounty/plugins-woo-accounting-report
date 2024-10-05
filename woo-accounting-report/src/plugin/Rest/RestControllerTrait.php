<?php

namespace BjornTech\AccountingReport\Rest;

defined('ABSPATH') || exit;

use WP_REST_Response;
use WP_REST_Request;

trait RestControllerTrait
{

    public function permission_check(WP_REST_REQUEST $request)
    {

        $params = $request->get_json_params();

        if (!wp_verify_nonce($params['nonce'], WC_ACCOUNTING_REPORT_ID)) {
            return new WP_REST_Response('Not permitted', 403);
        }

    }

}