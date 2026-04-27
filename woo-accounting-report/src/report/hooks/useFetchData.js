import { useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import getSetting from './wooCommerceOptions';
import logger from '../components/logger';
import { appendTimestamp, getCurrentDates, getDateParamsFromQuery, isoDateFormat } from '@woocommerce/date';
import { partialRight } from 'lodash';
import getMetaData from '../components/get-metadata';

const defaultDateRange = 'period=month';
const storeGetDateParamsFromQuery = partialRight(
    getDateParamsFromQuery,
    defaultDateRange
);
const storeGetCurrentDates = partialRight(getCurrentDates, defaultDateRange);

const storeDate = {
    getDateParamsFromQuery: storeGetDateParamsFromQuery,
    getCurrentDates: storeGetCurrentDates,
    isoDateFormat,
};

const addPwGiftCardSales = (giftCardLines) => {
    if (giftCardLines?.length) {
        return giftCardLines.reduce((acc, gc) => acc + Number(gc.amount), 0);
    }
    return 0;
}

const calculateItemsTotal = (items) => {
    let total = 0;
    items.forEach(item => {
        total += Number(item.total);
    });
    return total;
};

const safeNumber = (value) => Number(value ?? 0);

const convertTaxEntries = (taxes, multiplier) => {
    if (!Array.isArray(taxes)) {
        return [];
    }

    return taxes.map((tax) => ({
        ...tax,
        total: safeNumber(tax.total) * multiplier,
        subtotal: safeNumber(tax.subtotal) * multiplier,
    }));
};

const convertOrderItems = (items, multiplier) => {
    if (!Array.isArray(items)) {
        return [];
    }

    return items.map((item) => ({
        ...item,
        total: safeNumber(item.total) * multiplier,
        subtotal: safeNumber(item.subtotal) * multiplier,
        total_tax: safeNumber(item.total_tax) * multiplier,
        subtotal_tax: safeNumber(item.subtotal_tax) * multiplier,
        taxes: convertTaxEntries(item.taxes, multiplier),
    }));
};

const convertGiftCardLines = (giftCardLines, multiplier) => {
    if (!Array.isArray(giftCardLines)) {
        return [];
    }

    return giftCardLines.map((giftCardLine) => ({
        ...giftCardLine,
        amount: safeNumber(giftCardLine.amount) * multiplier,
    }));
};

const isEnabledSetting = (value) => {
    return ['yes', 'true', '1', 1, true, 'on'].includes(value);
};

const getStoreCurrencyCode = async (storeCurrencySetting) => {
    const wcCurrencyOption = await getSetting('woocommerce_currency', '');
    if (wcCurrencyOption) {
        return String(wcCurrencyOption).toUpperCase();
    }

    try {
        const wcCurrencySetting = await apiFetch({ path: '/wc/v3/settings/general/woocommerce_currency' });
        if (wcCurrencySetting?.value) {
            return String(wcCurrencySetting.value).toUpperCase();
        }
    } catch (error) {
        logger('store currency lookup error', error?.message || error);
    }

    return String(
        storeCurrencySetting?.code || storeCurrencySetting?.currency_code || storeCurrencySetting?.value || ''
    ).toUpperCase();
};

const convertMetaData = (metaData, multiplier) => {
    if (!Array.isArray(metaData)) {
        return [];
    }

    return metaData.map((meta) => {
        if (meta?.key === '_stripe_fee') {
            return {
                ...meta,
                value: safeNumber(meta.value) * multiplier,
            };
        }

        return meta;
    });
};

const applyLocalCurrencyConversion = async (orders, localCurrencyCode) => {
    if (!Array.isArray(orders) || orders.length === 0) {
        return orders;
    }

    const normalizedLocalCurrency = String(localCurrencyCode || '').toUpperCase();
    const orderCurrencies = Array.from(new Set(
        orders
            .map((order) => String(order.currency || '').toUpperCase())
            .filter((currency) => currency && currency !== normalizedLocalCurrency)
    ));

    if (!normalizedLocalCurrency || orderCurrencies.length === 0) {
        return orders.map((order) => ({
            ...order,
            currency: normalizedLocalCurrency || order.currency,
        }));
    }

    const exchangeRateResponse = await apiFetch({
        path: `/bjorntech-accounting/v1/exchange-rates?base=${normalizedLocalCurrency}&symbols=${orderCurrencies.join(',')}`,
    });

    const rates = exchangeRateResponse?.rates || {};

    return orders.map((order) => {
        const orderCurrency = String(order.currency || '').toUpperCase();
        const rawRate = orderCurrency && orderCurrency !== normalizedLocalCurrency
            ? Number(rates[orderCurrency])
            : 1;
        const multiplier = rawRate > 0 ? 1 / rawRate : 1;

        return {
            ...order,
            currency: normalizedLocalCurrency,
            total: safeNumber(order.total) * multiplier,
            total_tax: safeNumber(order.total_tax) * multiplier,
            stripe_fee: safeNumber(order.stripe_fee) * multiplier,
            line_items: convertOrderItems(order.line_items, multiplier),
            fee_lines: convertOrderItems(order.fee_lines, multiplier),
            shipping_lines: convertOrderItems(order.shipping_lines, multiplier),
            pw_gift_card_lines: convertGiftCardLines(order.pw_gift_card_lines, multiplier),
            meta_data: convertMetaData(order.meta_data, multiplier),
        };
    });
};

const defaultData = {
    isLoaded: false,
    orders: [],
    allCountries: [],
    taxClasses: [],
    euCountries: [],
    reportOnStatus: 'date_completed',
    presentInLocalCurrency: false,
    localCurrencyCode: '',
    treatAllSalesAsDomestic: false,
    storeCountryCode: '',
};
const useFetchData = (storeCurrencySetting) => {

    const [error, setError] = useState(null);
    const [processing, setProcessing] = useState(false);

    const [data, setData] = useState(defaultData);

    const fetchAllPages = (path, query, startPage = 1) => {
        logger('fetchAllPages start', startPage);
        const queryParameters = query ? `&${query}` : '';
        const fetchPage = (currentPage) => {
            logger('fetchPage start', currentPage);
            return apiFetch({ path: `${path}&limit=100${queryParameters}&page=${currentPage}` }).then((response) => {
                logger('fetchAllPages response', currentPage);
                logger('fetchAllPages response length', response.length);

                if (response.length == 0) {
                    return response;
                }

                return fetchPage(currentPage + 1).then((nextResponse) => {
                    return response.concat(nextResponse);
                });
            });
        };

        return fetchPage(startPage);
    };

    const getOrderQueryParameters = (dateQuery, orderStatus, reportOnStatus) => {
        //  logger('getOrderQueryParameters start', appendTimestamp(dateQuery.primaryDate.after, 'start').slice(0, 10) + 'T00:00:01');
        //   logger('getOrderQueryParameters end', appendTimestamp(dateQuery.primaryDate.before, 'end').slice(0, 10) + 'T23:59:59');
        const afterDate = (new Date(appendTimestamp(dateQuery.primaryDate.after, 'start').slice(0, 10) + 'T00:00:01')).getTime() / 1000;
        const beforeDate = (new Date(appendTimestamp(dateQuery.primaryDate.before, 'end').slice(0, 10) + 'T23:59:59')).getTime() / 1000;
        return `&${reportOnStatus}=${afterDate}...${beforeDate}&status=${orderStatus}&order=asc&_locale=user`;
    }

    const getRefundQueryParameters = (dateQuery) => {
        const afterDate = (new Date(appendTimestamp(dateQuery.primaryDate.after, 'start').slice(0, 10) + 'T00:00:01')).getTime() / 1000;
        const beforeDate = (new Date(appendTimestamp(dateQuery.primaryDate.before, 'end').slice(0, 10) + 'T23:59:59')).getTime() / 1000;
        return `&date_created=${afterDate}...${beforeDate}&order=asc&_locale=user`;
    }

    const fetchAllOrders = (path, dateQuery, orderStatuses, reportOnStatus) => {
        logger('fetchAllOrders', orderStatuses);
        logger('fetchAllOrders', reportOnStatus);
        return Promise.all(orderStatuses.map(status => {
            status = status.replace('wc-', '');
            const queryParameters = getOrderQueryParameters(dateQuery, status, reportOnStatus);
            logger('fetchAllOrders queryParameters', path + queryParameters);
            return fetchAllPages(path, queryParameters);
        })).then(orderResponses => {
            // Flatten the array of arrays into a single array
            return [].concat(...orderResponses);
        });
    };

    const fetchAllRefunds = (path, dateQuery) => {
        const queryParameters = getRefundQueryParameters(dateQuery);
        //  logger('fetchAllRefunds queryParameters', path + queryParameters);
        return fetchAllPages(path, queryParameters).then(refundResponses => {
            // Flatten the array of arrays into a single array
            return [].concat(...refundResponses);
        });
    };


    const fetchData = async (dateQuery) => {

        setProcessing(true);
        setError(null);
        setData(defaultData);

        const includeOrderStatuses = await getSetting('bjorntech_wcar_include_order_statuses', ['completed']);
        const reportOnStatus = await getSetting('bjorntech_wcar_on_status', 'date_completed');
        const treatAllSalesAsDomestic = isEnabledSetting(await getSetting('bjorntech_wcar_force_local', 'no'));
        const storeCountryCode = String(await getSetting('woocommerce_default_country', '')).split(':')[0] || '';
        const presentInLocalCurrencySetting = await getSetting('bjorntech_wcar_present_local_currency', 'no');
        const presentInLocalCurrency = isEnabledSetting(presentInLocalCurrencySetting);
        const localCurrencyCode = await getStoreCurrencyCode(storeCurrencySetting);
        const endPoints = {
            "eu_countries": "/bjorntech-accounting/v1/data/eu-countries?_fields=code&scope=eu_vat",
            "countries": "/wc/v3/data/countries?_fields=code,name",
            'tax_classes': '/wc/v3/taxes?context=view',
            'orders': '/bjorntech-accounting/v1/orders?context=view',
            'refunds': '/bjorntech-accounting/v1/refunds?context=view',
        };

        const euCountriesPath = endPoints.eu_countries;
        const countriesPath = endPoints.countries;
        const ordersPath = endPoints.orders;
        const refundsPath = endPoints.refunds;
        const taxClassesPath = endPoints.tax_classes;

        const prepareData = async (rawData) => {

            logger('prepareData data', rawData);
            const { orders } = rawData;
            const preparedOrders = orders.map(order => ({
                ...order,
                stripe_fee: Number(order.stripe_fee ?? getMetaData(order, '_stripe_fee')),
                buyer_name: order.billing?.company || `${order.billing?.first_name} ${order.billing?.last_name}`,
                effective_billing_country: rawData.treatAllSalesAsDomestic
                    ? rawData.storeCountryCode
                    : (order.billing?.country ?? ''),
                line_items_total: order.line_items ? calculateItemsTotal(order.line_items) : 0,
                fee_total: order.fee_lines ? calculateItemsTotal(order.fee_lines) : 0,
                total: Number(order.total ?? 0) + addPwGiftCardSales(order.pw_gift_card_lines)
            }));

            return { ...rawData, orders: preparedOrders };

        };

        try {
            const result = await Promise.all([
                apiFetch({ path: euCountriesPath }),
                apiFetch({ path: countriesPath }),
                apiFetch({ path: taxClassesPath }),
                fetchAllOrders(ordersPath, dateQuery, includeOrderStatuses, reportOnStatus),
                fetchAllRefunds(refundsPath, dateQuery),
            ]);

            const [eu_countries, countries, tax_classes, orders, refunds] = result;
            let allOrders = orders.concat(refunds);

            if (presentInLocalCurrency) {
                try {
                    allOrders = await applyLocalCurrencyConversion(allOrders, localCurrencyCode);
                } catch (conversionError) {
                    logger('local currency conversion error', conversionError?.message || conversionError);
                }
            }

            const rawData = {
                isLoaded: true,
                orders: allOrders,
                allCountries: countries,
                taxClasses: tax_classes,
                euCountries: eu_countries,
                reportOnStatus,
                presentInLocalCurrency,
                localCurrencyCode,
                treatAllSalesAsDomestic,
                storeCountryCode,
            };

            const preparedData = await prepareData(rawData);
            setData(preparedData);
            logger('useAccountingReport data', preparedData);
        } catch (fetchError) {
            setError(fetchError);
            logger('useAccountingReport error', fetchError?.message || fetchError);
        } finally {
            setProcessing(false);
        }

    };

    return { processing, data, error, fetchData };

};

export default useFetchData;
