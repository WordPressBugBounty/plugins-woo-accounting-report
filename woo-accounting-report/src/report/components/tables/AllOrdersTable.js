import { __, sprintf } from '@wordpress/i18n';

import {
    useEffect,
    useState,
    useMemo,
    useCallback
} from '@wordpress/element';

import {
    Button,
} from '@wordpress/components';

import {
    TableCard,
    TablePlaceholder
} from '@woocommerce/components';

import {
    downloadCSVFile,
    generateCSVDataFromTable,
    generateCSVFileName,
} from '@woocommerce/csv-export';

import { formatAmount, formatNumber } from '../format-number';
import DownloadIcon from '../download-icon';

import generateTotalFromOrderItems from '../generate-totals-from-order-items';

import sortData from '../sort-data';
import getTaxes from '../get-taxes';

import { TableWithPagination } from '../pagination/TableWithPagination';

const prepareData = (orders) => {
    let allTaxRates = [];
    const mappedOrders = orders.map(order => {
        let tax_rates = [];
        const orderTaxRates = order.tax_lines || [];

        const appendTaxesFromItems = (items = []) => {
            items.forEach(item => {
                (item.taxes || []).forEach(tax => {
                    const rateId = (tax.rate ?? tax.id)?.toString();
                    const ratePercent = orderTaxRates.find(rate => rate.rate_id.toString() === rateId)?.rate_percent ?? '0';

                    tax_rates.push({
                        tax_percent: ratePercent,
                        tax_total: parseFloat(tax.total)
                    });

                    if (!allTaxRates.includes(ratePercent)) {
                        allTaxRates.push(ratePercent);
                    }
                });
            });
        };

        appendTaxesFromItems(order.line_items || []);
        appendTaxesFromItems(order.shipping_lines || []);
        appendTaxesFromItems(order.fee_lines || []);

        return {
            ...order,
            tax_rates: tax_rates,
        };
    });

    return {
        orders: mappedOrders,
        allTaxRates: allTaxRates
    };
};

const getDateFieldFromStatus = (reportOnStatus) => {
    const allowedFields = ['date_created', 'date_paid', 'date_completed'];
    return allowedFields.includes(reportOnStatus) ? reportOnStatus : 'date_created';
};

const getOrderDate = (order, dateField) => {
    const dateValue = order?.[dateField] || order?.date_created || '';
    return typeof dateValue === 'string' ? dateValue : '';
};

export const AllOrdersTable = ({ data, currency }) => {

    const selectedDateField = getDateFieldFromStatus(data?.reportOnStatus);

    const [allTaxRates, setAllTaxRates] = useState([]);
    const [isLoading, setIsLoading] = useState(true);

    const [visibleColumns, setVisibleColumns] = useState(() => {
        return [
            'order_date',
            'order_number',
            'buyer_name',
            'country',
            'payment_method',
            'currency',
            'total',
            'shipping',
            'fees',
            'stripe_fee',
            'total_tax',
            'total_inc_tax',
        ];
    });

    const [currencyGroups, setCurrencyGroups] = useState({});
    const [currencyTotals, setCurrencyTotals] = useState({});

    useEffect(() => {
        setIsLoading(!data.isLoaded);
    }, [data.isLoaded]);

    useEffect(() => {
        if (data.isLoaded) {
            const preparedData = prepareData(data.orders);
            setAllTaxRates(preparedData.allTaxRates);
            
            // Group orders by currency and calculate totals
            const groupedOrders = {};
            const totals = {};
            preparedData.orders.forEach(order => {
                if (!groupedOrders[order.currency]) {
                    groupedOrders[order.currency] = [];
                    totals[order.currency] = { netSales: 0, tax: 0, total: 0 };
                }
                groupedOrders[order.currency].push(order);
                totals[order.currency].netSales += parseFloat(order.total) - parseFloat(order.total_tax);
                totals[order.currency].tax += parseFloat(order.total_tax);
                totals[order.currency].total += parseFloat(order.total);
            });

            setCurrencyGroups(groupedOrders);
            setCurrencyTotals(totals);

            // Update visibleColumns to include tax rate columns
            setVisibleColumns(prevColumns => {
                const taxColumns = preparedData.allTaxRates.map(rate => `tax_rate_${rate}`);
                return [...prevColumns, ...taxColumns];
            });
        }
    }, [data, data.isLoaded, setVisibleColumns]);

    const allTaxesHeader = allTaxRates.map(rate => ({
        key: `tax_rate_${rate}`,
        label: sprintf(
            __('%s%% TAX', 'woo-accounting-report'),
            rate
        ),
        isSortable: true,
        isNumeric: true
    }));

    const headers = [
        { key: 'order_date', label: __('Order date', 'woo-accounting-report'), isLeftAligned: true, isSortable: true, required: true },
        { key: 'order_number', label: __('Order number', 'woo-accounting-report'), isLeftAligned: true, isSortable: true, isNumeric: true },
        { key: 'order_id', label: __('Id', 'woo-accounting-report'), isLeftAligned: true, isSortable: true, required: true, isNumeric: true },
        { key: 'buyer_name', label: __('Buyer name', 'woo-accounting-report'), isLeftAligned: true, isSortable: true, required: true },
        { key: 'country', label: __('Country', 'woo-accounting-report'), isLeftAligned: true, isSortable: true, required: true },
        { key: 'payment_method', label: __('Payment method', 'woo-accounting-report'), isLeftAligned: true, isSortable: true, required: true },
        { key: 'currency', label: __('Currency', 'woo-accounting-report'), isLeftAligned: true, isSortable: true, required: true },
        { key: 'total', label: __('Items', 'woo-accounting-report'), isSortable: true, isNumeric: true },
        { key: 'shipping', label: __('Shipping', 'woo-accounting-report'), isSortable: true, isNumeric: true },
        { key: 'fees', label: __('Fees', 'woo-accounting-report'), isSortable: true, isNumeric: true },
        { key: 'stripe_fee', label: __('Stripe fee', 'woo-accounting-report'), isSortable: true, isNumeric: true },
        ...allTaxesHeader,
        { key: 'total_tax', label: __('TOTAL TAX', 'woo-accounting-report'), isSortable: true, isNumeric: true },
        { key: 'total_inc_tax', label: __('Total paid', 'woo-accounting-report'), isSortable: true, isNumeric: true },
    ];

    const formatTableData = (orders) => {
        return orders.map(item => {
            const orderDate = getOrderDate(item, selectedDateField);
            const totalValue = Number(item.total ?? 0);
            const totalTaxValue = Number(item.total_tax ?? 0);
            const isRefund = Boolean(item.parent_id);

            let itemNetValue = Number(item.line_items_total ?? 0);
            if (isRefund && itemNetValue === 0 && totalValue !== 0) {
                itemNetValue = totalValue - totalTaxValue;
            }

            const shippingValue = Number(item.shipping_total ?? 0);
            const feeValue = Number(item.fee_total ?? 0);
            const stripeFeeValue = Number(item.stripe_fee ?? 0);

            const allTaxesColumns = allTaxRates.map(rate => {
                const taxTotalForRate = item.tax_rates.reduce((sum, tax) => {
                    if (tax.tax_percent === rate) {
                        return sum + (Number(tax.tax_total) || 0);
                    }
                    return sum;
                }, 0);

                return { display: formatNumber(taxTotalForRate, item.total), value: taxTotalForRate }
            });
            return [
                { display: formatNumber(orderDate.substring(0, 10), totalValue, false), value: orderDate },
                { display: formatNumber(item.number, totalValue, false), value: item.number },
                { display: formatNumber(item.id, totalValue, false), value: item.id },
                { display: formatNumber(item.buyer_name, totalValue, false), value: item.buyer_name },
                { display: formatNumber(item.billing?.country, totalValue, false), value: item.billing?.country },
                { display: formatNumber(item.payment_method_title, totalValue, false), value: item.payment_method_title },
                { display: formatNumber(item.currency, totalValue, false), value: item.currency },
                { display: formatNumber(itemNetValue, totalValue), value: itemNetValue },
                { display: formatNumber(shippingValue, totalValue), value: shippingValue },
                { display: formatNumber(feeValue, totalValue), value: feeValue },
                { display: formatNumber(stripeFeeValue, totalValue), value: stripeFeeValue },
                ...allTaxesColumns,
                { display: formatNumber(totalTaxValue, totalValue), value: totalTaxValue },
                { display: formatNumber(totalValue, totalValue), value: totalValue },
            ];
        });
    };

    const onClickDownload = (currencyCode) => {
        const name = generateCSVFileName('accounting_all_orders_' + currencyCode);
        const data = generateCSVDataFromTable(headers, formatTableData(currencyGroups[currencyCode]).filter(row => row[6].value === currencyCode));
        downloadCSVFile(name, data);
    }

    const formatCurrencyValue = (value, currencyCode) => {
        return `${currencyCode} ${formatAmount(value)}`;
    };

    if (!data.orders || data.orders.length === 0) {
        return (
            <TableCard
                key={0}
                title={__('All Orders', 'woo-accounting-report')}
                headers={headers}
                rows={[]}
                rowsPerPage={1}
                totalRows={0}
                isLoading={isLoading}
            />
        );
    }

    return (
        <>
            {Object.entries(currencyGroups).map(([currencyCode, orders]) => (
                <TableWithPagination
                    initialSort={{ orderby: 'order_date', order: 'desc' }}
                    key={currencyCode}
                    initialData={formatTableData(orders)}
                    columns={headers}
                    title={sprintf(
                        __('All Orders - %s', 'woo-accounting-report'),
                        currencyCode
                    )}
                    isLoading={isLoading}
                    actions={[
                        (
                            <Button
                                key="download"
                                className="woocommerce-table__download-button"
                                onClick={() => onClickDownload(currencyCode)}
                            >
                                <DownloadIcon />
                                <span className="woocommerce-table__download-button__label">
                                    {__('Download', 'woo-accounting-report')}
                                </span>
                            </Button>
                        ),
                    ]}
                    summary={[
                        {
                            label: __('Net sales', 'woo-accounting-report'),
                            value: `${formatCurrencyValue(currencyTotals[currencyCode].netSales, currencyCode)}`,
                        },
                        {
                            label: __('Tax', 'woo-accounting-report'),
                            value: `${formatCurrencyValue(currencyTotals[currencyCode].tax, currencyCode)}`,
                        },
                        {
                            label: __('Total', 'woo-accounting-report'),
                            value: `${formatCurrencyValue(currencyTotals[currencyCode].total, currencyCode)}`,
                        },
                    ]}
                />
            ))}
        </>
    );

}
