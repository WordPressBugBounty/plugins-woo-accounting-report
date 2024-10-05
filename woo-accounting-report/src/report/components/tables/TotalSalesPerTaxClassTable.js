import { __ } from '@wordpress/i18n';

import {
    useEffect,
    useState,
    useMemo,
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

import { formatNumber, formatAmount } from '../format-number';
import DownloadIcon from '../download-icon';

import generateTotalFromOrderItems from '../generate-totals-from-order-items';

import getTaxes from '../get-taxes';
import sortData from '../sort-data';

export const TotalSalesPerTaxClassTable = (props) => {

    const { data, currency, dataIsLoaded } = props;

    const [sortColumn, setSortColumn] = useState('order_date');
    const [sortOrder, setSortOrder] = useState('desc');
    const [unsortedData, setUnsortedData] = useState(null);
    const [isLoading, setIsLoading] = useState(true);
    const [{ query }, setQuery] = useState({
        query: {
            page: 1,
        },
    });

    const sortedData = useMemo(() => {
        if (unsortedData !== null) {
            const sortedData = sortData(unsortedData, sortColumn, sortOrder);
            return sortedData;
        }
        return null;
    }, [unsortedData, sortColumn, sortOrder]);

    useEffect(() => {
        if (data.isLoaded) {
            const unsortedData = prepareData(data.orders);
            setUnsortedData(unsortedData);
        } else {
            setIsLoading(true);
            setUnsortedData(null);
        }
    }, [data.isLoaded, data.orders]);

    useEffect(() => {
        if (sortedData !== null) {
            setIsLoading(false);
        }
    }, [sortedData]);

    const handleSort = (newSortColumn) => {
        if (newSortColumn === sortColumn) {
            setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc');
        } else {
            setSortColumn(newSortColumn);
            setSortOrder('asc');
        }
    }

    const onClickDownload = (headers, rows, grandTotal, grandTotalTax, currency, taxString) => {
        const name = generateCSVFileName('accounting_total_sales_' + currency + '_' + taxString + '_tax');
        rows.push(
            [
                { display: 'Total', value: 'Total' },
                { display: formatNumber(grandTotal, grandTotal), value: grandTotal },
                { display: formatNumber(grandTotalTax, grandTotalTax), value: grandTotalTax },
                { display: formatNumber(grandTotal + grandTotalTax, grandTotal + grandTotalTax), value: grandTotal + grandTotalTax },
            ]
        );
        const data = generateCSVDataFromTable(headers, rows);
        downloadCSVFile(name, data);
    }

    const prepareData = (orders) => {

        var lineItemsTotal = [];

        orders.map(order => {
            const orderTaxes = getTaxes(order);
            const isRefund = order.parent_id ? true : false;
            lineItemsTotal = generateTotalFromOrderItems(order, lineItemsTotal, 'line_items', orderTaxes, __('Sales', 'woo-accounting-report'), isRefund);
            lineItemsTotal = generateTotalFromOrderItems(order, lineItemsTotal, 'fee_lines', orderTaxes, __('Fees', 'woo-accounting-report'), isRefund);
            lineItemsTotal = generateTotalFromOrderItems(order, lineItemsTotal, 'shipping_lines', orderTaxes, __('Shipping', 'woo-accounting-report'), isRefund);
        });

        const totalSalesData = lineItemsTotal.reduce((acc, item) => {

            const [countryCode, currency, taxRateCodeString, itemTypeString] = item.key.split('#');
            const existingObject = acc.find(object => object.currency === currency && object.taxRateCodeString === taxRateCodeString);

            if (existingObject) {
                existingObject.items.push(item);
            } else {
                acc.push({
                    key: item.key,
                    countryCode,
                    currency,
                    taxRateCodeString,
                    taxPercentage: item.taxPercentage,
                    items: [{ ...item }]
                });
            }

            return acc;

        }, []);


        // Add a new step to create combined data
        const combinedData = totalSalesData.reduce((acc, item) => {
            if (!acc[item.currency]) {
                acc[item.currency] = {
                    key: `combined_${item.currency}`,
                    currency: item.currency,
                    items: [],
                    grandTotal: 0,
                    grandTotalTax: 0,
                };
            }
            
            item.items.forEach(subItem => {
                acc[item.currency].items.push(subItem);
                acc[item.currency].grandTotal += subItem.total;
                acc[item.currency].grandTotalTax += subItem.total_tax;
            });

            return acc;
        }, {});

        return [...totalSalesData];
    };

    var totalCards = [];

    const headers = [
        { key: 'type', label: __('Type', 'woo-accounting-report'), isLeftAligned: true, isSortable: true, required: true },
        { key: 'net', label: __('Net sales', 'woo-accounting-report'), required: true, isNumeric: true },
        { key: 'total_tax', label: __('Tax', 'woo-accounting-report'), required: true, isNumeric: true },
        { key: 'total', label: __('Total', 'woo-accounting-report'), required: true, isNumeric: true },
    ];

    if (sortedData !== null) {

        sortedData.forEach(cardItem => {
            const tableData = {
                headers: headers.map((header) => ({
                    ...header,
                    defaultSort: header.key === sortColumn,
                    defaultOrder: sortOrder,
                })),
                rows: cardItem.items.map(item =>
                    [
                        { display: formatNumber(item.type, item.total, false), value: item.type },
                        { display: formatNumber(item.total, item.total), value: item.total },
                        { display: formatNumber(item.total_tax, item.total), value: item.total_tax },
                        { display: formatNumber(item.total + item.total_tax, item.total), value: item.total + item.total_tax },
                    ]),
            };

            let title;
            let taxString;
            if (cardItem.key.startsWith('combined_')) {
                title = __(`Total sales in ${cardItem.currency}`, 'woo-accounting-report');
                taxString = 'all';
            } else {
                title = __(`Total sales in ${cardItem.currency} with ${cardItem.taxRateCodeString}% tax`, 'woo-accounting-report');
                taxString = cardItem.taxRateCodeString;
            }

            const grandTotal = cardItem.grandTotal || cardItem.items.reduce((sum, item) => sum + item.total, 0);
            const grandTotalTax = cardItem.grandTotalTax || cardItem.items.reduce((sum, item) => sum + item.total_tax, 0);

            const summary = [
                { label: __('Net sales', 'woo-accounting-report'), value: `${cardItem.currency} ${formatAmount(grandTotal)}` },
                { label: __('Tax', 'woo-accounting-report'), value: `${cardItem.currency} ${formatAmount(grandTotalTax)}` },
                { label: __('Total', 'woo-accounting-report'), value: `${cardItem.currency} ${formatAmount(grandTotal + grandTotalTax)}` },
            ];

            totalCards.push(
                <TableCard
                    key={cardItem.key}
                    onSort={handleSort}
                    title={title}
                    rows={tableData.rows}
                    headers={tableData.headers}
                    rowsPerPage={25}
                    totalRows={tableData.rows.length}
                    summary={summary}
                    onQueryChange={(param) => (value) =>
                        setQuery({
                            query: {
                                [param]: value,
                            },
                        })}
                    actions={
                        [
                            (
                                <Button
                                    key="download"
                                    className="woocommerce-table__download-button"
                                    onClick={() => { onClickDownload(tableData.headers, tableData.rows, grandTotal, grandTotalTax, cardItem.currency, taxString) }}
                                >
                                    <DownloadIcon />
                                    <span className="woocommerce-table__download-button__label">
                                        {__('Download', 'woo-accounting-report')}
                                    </span>
                                </Button>
                            ),
                        ]}
                />
            )
        });
    }

    const NoDataElement = (
        <TableCard
            key={0}
            title={__(`Total sales`, 'woo-accounting-report')}
            headers={headers}
            rows={[]}
            rowsPerPage={1}
            totalRows={0}
            isLoading={isLoading}
        />
    );

    return <>
        {(totalCards?.length > 0 && totalCards) || (NoDataElement)}
    </>;


}

