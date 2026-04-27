=== BjornTech Accounting Report for WooCommerce ===
Contributors: bjorntech
Tags: accounting, report, woocommerce, vat, export
Requires at least: 4.9
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 4.0.1
License: GPL-3.0
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Generates an accounting report from WooCommerce

== Description ==
This is the report that will make your accountant happy!

You will find the report in the WooCommerce->Reports section (if you need a country specific report, please contact us and we will add what is needed)

The report is working with WPML and Polylang and will specify the fee part for Stripe payments (if you have a payment plugin storing data on orders and you want it in the report, please contact us)

Configuration can be found at WooCommerce->Settings->Accounting Report

The configuration that can be done is:

Base report on order date - Choose which order date is used when filtering report data.

Include order statuses - Select which order statuses are included in report calculations.

Treat all sales as domestic - Classify all sales as domestic regardless of customer country.

Present in local currency - Convert all Analytics report values to store currency using exchange rates.

Send bug reports or suggestions to hello@bjorntech.com

== External services ==

This plugin connects to BjornTech services to fetch exchange rates and EU VAT rate data used by the reports.

Exchange rate service:
- Service: BjornTech Accounting Services (`https://accounting.services.bjorntech.eu/latest`)
- Purpose: Fetch latest currency exchange rates for local-currency presentation and totals conversion.
- Data sent: Your configured API key, the requested base currency, and optionally requested currency symbols.
- When sent: When an admin opens a report that requires exchange-rate conversion or calls the exchange-rates REST endpoint.
- Terms of service: https://bjorntech.com/terms-and-conditions/
- Privacy policy: https://bjorntech.com/privacy-policy/

EU VAT service:
- Service: BjornTech Tax Services (`https://taxes.services.bjorntech.eu/eu/vat`)
- Purpose: Fetch EU VAT rates, country lists, and VAT rate categories for the Analytics report.
- Data sent: Your configured API key, optionally selected country codes, optionally selected VAT rate types, and the requested date/path.
- When sent: When an admin loads VAT-related Analytics data or calls the EU VAT REST endpoints.
- Terms of service: https://bjorntech.com/terms-and-conditions/
- Privacy policy: https://bjorntech.com/privacy-policy/

== Changelog ==
= 4.0.1 =
* Fixed WordPress.org review issues for plugin metadata, sanitization, escaping, i18n, and external-service disclosure.

= 4.0 =
* Refreshed analytics experience and settings.
* Legacy report marked as deprecated with link to Analytics report.
* Added local-currency presentation mode and improved tax/refund handling.
