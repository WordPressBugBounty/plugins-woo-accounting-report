import { __ } from '@wordpress/i18n';

import {
    useEffect,
    useState
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

import { formatNumber } from '../format-number';
import DownloadIcon from '../download-icon';

import generateTotalFromOrderItems from '../generate-totals-from-order-items';
import getTaxes from '../get-taxes';
import getMetaData from '../get-metadata';
import sortData from '../sort-data';

import useEuroFXRefData from '../../hooks/useEuroFXRefData';
import useWoocommerceSettings from '../../hooks/useWoocommerceSettings';
import logger from '../logger';
import getSetting from '../../hooks/wooCommerceOptions';

export const OssReportTable = ({ data, totals, currency, dataIsLoaded }) => {

    const { euCountries } = data;
    const [sortColumn, setSortColumn] = useState('order_date');
    const [sortOrder, setSortOrder] = useState('desc');
    const [sortedReportData, setSortedReportData] = useState([]);
    const [reportData, setReportData] = useState([]);
    const [isLoading, setIsLoading] = useState(true);
    const [useOssPane, setUseOssPane] = useState(false);
    const { error: euroFXRefDataError, getRateByCurrency } = useEuroFXRefData(currency);
    const { generalSettings, isResolving } = useWoocommerceSettings();

    useEffect(() => {
        if (!data.isLoaded) {
            setIsLoading(true);
            getSetting('bjorntech_wcar_show_oss_pane').then((value) => {
                setUseOssPane(value === 'yes');
            });
        }
    }, [data.isLoaded]);

    useEffect(() => {
        if (useOssPane && data.isLoaded && !isResolving) {
            prepareOssData(data);
        }
    }, [data, isResolving, data.isLoaded]);

    useEffect(() => {
        setSortedReportData(sortData(reportData, sortColumn, sortOrder));
    }, [reportData, sortColumn, sortOrder]);

    const handleSort = (newSortColumn) => {
        if (newSortColumn === sortColumn) {
            setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc');
        } else {
            setSortColumn(newSortColumn);
            setSortOrder('asc');
        }
    }

    function getCountryCode(countryState = '') {
        if (!countryState) {
            return null;
        }

        return countryState.split(':')[0];
    }
    const prepareOssData = (params) => {

        const { orders } = params;

        var lineItemsTotal = [];

        const baseCountry = getCountryCode(generalSettings.woocommerce_default_country);
        logger('prepareOssData', baseCountry)
        orders.map(order => {

            // const latestRate = getRateByCurrency('USD'); // Retrieve the latest rate for the currency code 'USD'
            //const rateForDate = getRateByCurrency('USD', '2023-05-27'); // Retrieve the rate for the currency code 'USD' on a specific date

            const hasReversedVAT = ('true' === getMetaData(order, '_vat_number_is_validated')) || ('valid' === getMetaData(order, '_vat_number_validated'));
            const isEuCountry = euCountries.find(country => { return country.code === getCountryCode(order.billing.country) })

            if (!hasReversedVAT && isEuCountry && baseCountry !== getCountryCode(order.billing.country)) {

                const latestRate = order.currency === 'EUR' ? 1 : getRateByCurrency(order.currency);

                const orderTaxes = getTaxes(order);
                logger('prepareOssData', orderTaxes);
                lineItemsTotal = generateTotalFromOrderItems(order, lineItemsTotal, 'line_items', orderTaxes, __('Sales', 'woo-accounting-report'), false, true, latestRate, 'EUR');
                lineItemsTotal = generateTotalFromOrderItems(order, lineItemsTotal, 'fee_lines', orderTaxes, __('Fees', 'woo-accounting-report'), false, true, latestRate, 'EUR');
                lineItemsTotal = generateTotalFromOrderItems(order, lineItemsTotal, 'shipping_lines', orderTaxes, __('Shipping', 'woo-accounting-report'), false, true, latestRate, 'EUR');
            }

        });



        const ossData = [];
        for (const item of lineItemsTotal) {

            logger('prepareOssData', item);

            const [countryCode, currency, taxRateCode, itemId] = item.key.split('#');

            const existingObject = ossData.find(object => {
                return object.currency === currency;
            });

            if (existingObject) {
                existingObject.items.push(item);
            } else {
                ossData.push({
                    key: item.key,
                    countryCode: countryCode,
                    currency: currency,
                    taxRateCode: taxRateCode,
                    items: [{ ...item }]
                });
            }

        }

        setReportData(ossData);
        setIsLoading(false);

    };

    const onClickDownload = () => {
        const name = generateCSVFileName('accounting_oss_report');
        const data = generateCSVDataFromTable(tableData.headers, tableData.rows);
        downloadCSVFile(name, data);
    }

    var totalCards = [];
    var tableData = { headers: [], rows: [] };
    const headers = [
        { key: 'type', label: __('Type', 'woo-accounting-report'), isLeftAligned: true, isSortable: true, required: true },
        { key: 'net', label: __('Net sales', 'woo-accounting-report'), required: true, isNumeric: true },
        { key: 'total_tax', label: __('Tax', 'woo-accounting-report'), required: true, isNumeric: true },
        { key: 'total', label: __('Total', 'woo-accounting-report'), required: true, isNumeric: true },
    ];
    sortedReportData.map(cardItem => {

        tableData = {
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

        let title = __(`OSS sales in ${cardItem.currency} `, 'woo-accounting-report');

        totalCards.push(<TableCard
            key={cardItem.key}
            onSort={handleSort}
            title={title}
            rows={tableData.rows}
            headers={tableData.headers}
            rowsPerPage={25}
            totalRows={tableData.rows.length}
            actions={[
                (
                    <Button
                        key="download"
                        className="woocommerce-table__download-button"
                        onClick={onClickDownload}
                    >
                        <DownloadIcon />
                        <span className="woocommerce-table__download-button__label">
                            {__('Download', 'woo-accounting-report')}
                        </span>
                    </Button>
                ),
            ]}
        />)
    });

    if (useOssPane) {
        return <>
            {(totalCards?.length > 0 && totalCards) || <TableCard
                title={__(`OSS sales`, 'woo-accounting-report')}
                headers={headers}
                isLoading={isLoading}
                rowsPerPage={isLoading ? 1 : 25}
                rows={[]}
                totalRows={0}
                actions={[
                    (
                        <Button
                            key="download"
                            className="woocommerce-table__download-button"
                            onClick={onClickDownload}
                        >
                            <DownloadIcon />
                            <span className="woocommerce-table__download-button__label">
                                {__('Download', 'woo-accounting-report')}
                            </span>
                        </Button>
                    ),
                ]}
            />}
        </>;
    } else {
        return <></>;
    }

}

