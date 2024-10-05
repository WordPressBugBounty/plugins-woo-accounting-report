=== WooCommerce Accounting Report ===
Contributors: BjornTech
Tags: accounting, report, accountant, woocommerce, vat, vat-report, analytics
Requires at least: 4.9
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 3.1.1
License: GPL-3.0
License URI: https://bjorntech.com/accountingreport

Generates an accounting report from WooCommerce

== Description ==
This is the report that will make your accountant happy!

You will find the report in the WooCommerce->Reports section (if you need a country specific report, please contact us and we will add what is needed)

The report is working with WPML and Polylang and will specify the fee part for Stripe payments (if you have a payment plugin storing data on orders and you want it in the report, please contact us)

Configuration can be found at WooCommerce->Settings->Accounting Report

The configuration that can be done id:

Base the report on status - The report can be based on the date when orders are set to the 'Completed' or 'Paid' status. Select based on your workflow accounting process.

Tax Class for refunds - Somnetimes refunds in WooCommerce can be done wihtout specifying a tax class. Select what tax class to use at that point.

Treat all sales as local - If you do want the report to list all sales as local rather than grouping.

Send bug reports or suggestions to hello@bjorntech.com

== Changelog ==
= 3.1.1
* Fix: Orders without tax class caused the old report to crash
= 3.1.0
* Fix: PHP errors removed
* Fix: Exchange rates calculated wrong in some cases
* Fix: Handle taxes with two decimals when calculating totals, previous version caused rounding errors
* New: Accounting report is now avaliable as beta in the Reports section
* Verified to work with WooCommerce 9.1
* Verified to work with Wordpress 6.6
= 3.0.3
* Fix: Reports are crashing if decimals was changed in settings
* Verified to work with WooCommerce 8.5
= 3.0.2
* Fix: Sometimes an Error is thrown when getting refunds
* Fix: CSV-exports gets messed up in some cases
= 3.0.1
* Fix: Error when calculating totals caused array error message
= 3.0.0
* First version of Analytics report (Enable in settings)
* Working with HPOS
* Tested with Wordpress 6.4 and WooCommerce 8.2
= 2.1.4
* Tested with Wordpress 6.1 and WooCommerce 7.3
= 2.1.3
* New: Added option to base the report on status created
= 2.1.2
* Tested with WooCommerce 6.1 and Wordpress 5.9
* Fix: Option to use space as thousand separator was not working.
* Fix: In some cases refund values where calculated wrong in the totals.
= 2.1.1
* Fix: CSV export not working
= 2.1.0
* Tested with Wordpress 5.8 and WooCommerce 5.5
* Fix: Stripe fee was not formatted as a number, causing problems when importing csv to excel.
* Fix: Various layout changes.
* Fix: Change of refund handling, fixing an issue where taxes where not handled correctly in refunds.
* Fix: Error in total amount when tax-rate was missing in an order.
= 2.0.0
* New: Various layout changes
* New: added the possibility to use parts of the report separately.
* Fix: Using '_billing_vat_number' as default instead of '_vat_number'
* Fix: Error in VAT calculation if the order contained products with more than one TAX-rate.
* Fix: The logging function did sometimes cause errors.
* Fix: Changed VAT to Tax in order to be in line with WooCommerce terminology.
* Fix: Only the first tax rate was shown for an order.
* Fix: Added the possibility to select order statuses to be included in the report.
* Verified to work with WooCommerce 5.1 and Wordpress 5.7
= 1.1.3 =
* Fix: Changed readme.txt to reflect that the plugin is compatible with WC 4.4 and WP 5.5
= 1.1.2 =
* Fix: Changed readme.txt to reflect that the plugin is compatible with WC 4.2 and WP 5.4
* Fix: Changed support e-mail.
* Fix: Changed author to BjornTech AB
= 1.1.1 =
* Fix: Some refunded orders where showing up both at creation date and payment completed date.
* Fix: Differences in calculated order value vs recorded does not show if 0 when rounding with site decimals
= 1.1.0 =
* New: Currency conversion if sales is done in currecy that is not the base currency.
* Fix: Report is crashing if it can not find a tax rate on a refund, now it is using the tax rate specified in the settings.
= 1.0.1 =
* Fix: Refunded orders was duplicated in some cases
= 1.0.0 =
* First public release