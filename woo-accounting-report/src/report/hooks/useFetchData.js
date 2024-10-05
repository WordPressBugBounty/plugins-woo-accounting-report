import { useState, useEffect, useMemo } from 'react';
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

const defaultData = {
    isLoaded: false,
    orders: [],
    allCountries: [],
    taxClasses: [],
    euCountries: [],
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
        setData(defaultData);

        const includeOrderStatuses = await getSetting('bjorntech_wcar_include_order_statuses', ['completed']);
        const reportOnStatus = await getSetting('bjorntech_wcar_on_status', 'date_completed');
        const endPoints = {
            "eu_countries": "/wc/v3/data/eu-countries?_fields=code&scope=eu_vat",
            "countries": "/wc/v3/data/countries?_fields=code,name",
            'tax_classes': '/wc/v3/taxes?context=view',
            'orders': '/wc/v3/accounting/orders?context=view',
            'refunds': '/wc/v3/accounting/refunds?context=view',
            'exchange_rates': 'https://accounting.bjorntech.net/v1/exchange-rates?base=' + storeCurrencySetting.code,
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
                stripe_fee: Number(getMetaData(order, '_stripe_fee')),
                buyer_name: order.billing?.company || `${order.billing?.first_name} ${order.billing?.last_name}`,
                line_items_total: order.line_items ? calculateItemsTotal(order.line_items) : 0,
                fee_total: order.fee_lines ? calculateItemsTotal(order.fee_lines) : 0,
                total: Number(order.total ?? 0) + addPwGiftCardSales(order.pw_gift_card_lines)
            }));

            return { ...rawData, orders: preparedOrders };

        };

        Promise.all([

            apiFetch({ path: euCountriesPath }),
            apiFetch({ path: countriesPath }),
            apiFetch({ path: taxClassesPath }),
            fetchAllOrders(ordersPath, dateQuery, includeOrderStatuses, reportOnStatus),
            fetchAllRefunds(refundsPath, dateQuery),
            fetch(endPoints.exchange_rates),

        ]).then((result) => {

            const [eu_countries, countries, tax_classes, orders, refunds, exchange_rates] = result;

            const rawData = {
                isLoaded: true,
                orders: orders.concat(refunds),
                allCountries: countries,
                taxClasses: tax_classes,
                euCountries: eu_countries
            };

            prepareData(rawData).then((preparedData) => {

                setData(preparedData);
                setProcessing(false);
                logger('useAccountingReport data', preparedData);

            });
        })

    };

    return { processing, data, error, fetchData };

};

export default useFetchData;
