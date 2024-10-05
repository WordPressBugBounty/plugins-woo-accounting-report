import { __ } from '@wordpress/i18n';

import {
    useEffect,
    useState
} from '@wordpress/element';

import {
    TableCard,
    TablePlaceholder
} from '@woocommerce/components';

import {
    Button,
} from '@wordpress/components';

import {
    downloadCSVFile,
    generateCSVDataFromTable,
    generateCSVFileName,
} from '@woocommerce/csv-export';

import { formatNumber } from '../format-number';
import DownloadIcon from '../download-icon';
import sortData from '../sort-data';
import getMetaData from '../get-metadata';
import { getSetting } from '@woocommerce/settings';
import getWooSettings  from '../../hooks/wooCommerceOptions';

export const TotalSalesPerRegionTable = ({ data, currency, dataIsLoaded }) => {

    const [sortColumn, setSortColumn] = useState('payment_method');
    const [sortOrder, setSortOrder] = useState('desc');
    const [sortedRegionData, setSortedRegionData] = useState([]);
    const [regionData, setRegionData] = useState([]);

    const [isLoading, setIsLoading] = useState(true);
    const [storeCountry, setStoreCountry] = useState('');

    useEffect(() => {
        const loader = async () => {
            try {
                const country = await getWooSettings('woocommerce_default_country');
                setStoreCountry(country);
                
                if (data.isLoaded) {
                    setRegionData(prepareSalesPerRegionData(data, country));
                    setIsLoading(false);
                } 

            } catch (error) {
                console.error('Error fetching settings:', error);
                setRegionData([]);
                setIsLoading(true);
            }
        };

        loader();
    }, [data]);

    useEffect(() => {
        setSortedRegionData(sortData(regionData, sortColumn, sortOrder));
    }, [regionData, sortColumn, sortOrder]);

    const handleSort = (newSortColumn) => {
        if (newSortColumn === sortColumn) {
            setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc');
        } else {
            setSortColumn(newSortColumn);
            setSortOrder('asc');
        }
    }
    const onClickDownload = () => {
        const name = generateCSVFileName('accounting_report_total_sales_region');
        const data = generateCSVDataFromTable(tableData.headers, tableData.rows);
        downloadCSVFile(name, data);
    }

    const prepareSalesPerRegionData = (data, country) => {
        const { allCountries, orders, euCountries } = data;
        const onlyLocal = getSetting('bjorntech_wcar_force_local') === 'yes';
        var salesPerRegion = [];

        orders.map(order => {

            let region = 'global';
            let regionTitle = __('Global', 'woo-accounting-report');
            const euVATNumberValid = getMetaData(order, '_vat_number_is_valid')
            console.log(country);
            if (country !== '') {
                if ((order.billing.country === '') || (order.billing.country === country) || onlyLocal) {
                    region = 'local';
                    regionTitle = __('Local', 'woo-accounting-report') + ' (' + country + ')';
                } else if (euCountries.find(country => { return country.code === order.billing.country })) {
                    if (euVATNumberValid) {
                        region = 'eu-vat-exempt';
                        regionTitle = __('EU VAT Exempt', 'woo-accounting-report');
                    } else {
                        region = 'eu';
                        regionTitle = __('EU', 'woo-accounting-report');
                    }
                }
            }

            const orderCurrency = order.currency ? order.currency : '???';
            const orderTotal = Number(order.total ?? 0);
            const orderTotalTax = Number(order.total_tax ?? 0);

            let key = region + '#' + orderCurrency;

            let existingMethod = salesPerRegion.find(method => {
                return method.key === key;
            });

            if (existingMethod) {
                existingMethod.total = Number(existingMethod.total) + orderTotal;
                existingMethod.total_tax = Number(existingMethod.total_tax) + orderTotalTax;
                existingMethod.total_ex_tax = Number(existingMethod.total_ex_tax) + orderTotal - orderTotalTax;
            } else {
                salesPerRegion.push({
                    key: key,
                    region: regionTitle,
                    currency: orderCurrency,
                    total: orderTotal,
                    total_ex_tax: orderTotal - orderTotalTax,
                    total_tax: orderTotalTax
                });
            }

        });

        const salesPerRegionData = salesPerRegion.reduce((acc, item) => {

            const existingObject = acc.find(object => object.currency === item.currency);

            if (existingObject) {
                existingObject.items.push(item);
            } else {
                acc.push({
                    key: item.key,
                    region: item.region,
                    currency: item.currency,
                    items: [{ ...item }]
                });
            }

            return acc;

        }, []);

        return salesPerRegionData;

    }

    const headers = [
        { key: 'region', label: __('Region', 'woo-accounting-report'), isLeftAligned: true, isSortable: true, required: true },
        { key: 'total_ex_tax', label: __('Sales', 'woo-accounting-report'), required: true, isNumeric: true, isSortable: true },
        { key: 'total_tax', label: __('Tax', 'woo-accounting-report'), required: true, isNumeric: true, isSortable: true },
        { key: 'total', label: __('Total', 'woo-accounting-report'), required: true, isNumeric: true, isSortable: true },
    ];

    var totalCards = [];
    var tableData = {};
    sortedRegionData.map(cardItem => {
        tableData = {
            headers: headers.map((header) => ({
                ...header,
                defaultSort: header.key === sortColumn,
                defaultOrder: sortOrder,
            })),
            rows: cardItem.items.map(item =>
                [
                    { display: item.region, value: item.region },
                    { display: formatNumber(item.total_ex_tax), value: item.total_ex_tax },
                    { display: formatNumber(item.total_tax), value: item.total_tax },
                    { display: formatNumber(item.total), value: item.total },
                ]),
        };

        let title = __(`Net sales per region in ${cardItem.currency}`, 'woo-accounting-report');

        totalCards.push(
            <TableCard
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
            />
        )
    });

    return <>
        {(totalCards?.length > 0 && totalCards) || <TableCard
            title={__(`Net sales per region`, 'woo-accounting-report')}
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
}
