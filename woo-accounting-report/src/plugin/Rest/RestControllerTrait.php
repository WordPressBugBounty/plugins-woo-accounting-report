<?php

namespace BjornTech\AccountingReport\Rest;

defined('ABSPATH') || exit;

use WP_Error;
use WP_REST_Request;

trait RestControllerTrait
{

    public function permission_check(WP_REST_Request $request)
    {
        if (current_user_can('view_woocommerce_reports') || current_user_can('manage_woocommerce')) {
            return true;
        }

        return new WP_Error(
            'woocommerce_rest_forbidden',
            __('Sorry, you are not allowed to access this resource.', 'woo-accounting-report'),
            array('status' => rest_authorization_required_code())
        );

    }

}
