import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import AccountingReport from "./AccountingReport";

addFilter('woocommerce_admin_reports_list', 'woo-accounting-report', (reports) => {
    return [
        ...reports,
        {
            report: 'woo-accounting-report',
            title: __('Accounting', 'woo-accounting-report'),
            component: AccountingReport
        },
    ];
});