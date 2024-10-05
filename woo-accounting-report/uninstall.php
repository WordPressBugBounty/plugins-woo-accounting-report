<?php

// if uninstall.php is not called by WordPress, die
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

register_deactivation_hook(__FILE__, 'accounting_report_deactivation');

function accounting_report_deactivation() {

}