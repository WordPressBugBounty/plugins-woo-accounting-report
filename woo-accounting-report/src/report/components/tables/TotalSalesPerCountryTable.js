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



const prepareSalesPerCountryData = (data) => {
    const { allCountries, orders } = data;

    return orders.reduce((salesPerCountry, order) => {
        const billingCountry = order.billing?.country ?? '??';
        const orderCurrency = order.currency ?? '???';

        let orderTotal = Number(order.total ?? 0);
        let orderTotalTax = Number(order.total_tax ?? 0);

        let key = `${billingCountry}_${orderCurrency}`;
        let existingItem = salesPerCountry.find(item => item.key === key);

        if (existingItem) {
            existingItem.total += orderTotal;
            existingItem.total_tax += orderTotalTax;
            existingItem.total_ex_tax += (orderTotal - orderTotalTax);
        } else {
            salesPerCountry.push({
                key: key,
                country: billingCountry,
                title: key !== '??' ? key : __('Unknown country', 'woo-accounting-report'),
                currency: orderCurrency,
                total: orderTotal,
                total_ex_tax: (orderTotal - orderTotalTax),
                total_tax: orderTotalTax
            });
        }

        return salesPerCountry;

    }, []);
};
export const TotalSalesPerCountryTable = ({ data, currency, dataIsLoaded }) => {

    const [sortColumn, setSortColumn] = useState('payment_method');
    const [sortOrder, setSortOrder] = useState('desc');
    const [sortedCountryData, setSortedCountryData] = useState([]);
    const [countryData, setCountryData] = useState([]);
    const [isLoading, setIsLoading] = useState(true);

    useEffect(() => {
        setIsLoading(!data.isLoaded);
    }, [data.isLoaded]);

    useEffect(() => {
        if (data.isLoaded) {
            setCountryData(prepareSalesPerCountryData(data));
        }
    }, [data]);

    useEffect(() => {
        setSortedCountryData(sortData(countryData, sortColumn, sortOrder));
    }, [countryData, sortColumn, sortOrder]);

    const handleSort = (newSortColumn) => {
        if (newSortColumn === sortColumn) {
            setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc');
        } else {
            setSortColumn(newSortColumn);
            setSortOrder('asc');
        }
    }
    const onClickDownload = () => {
        const name = generateCSVFileName('accounting_report_total_sales_per_country');
        const data = generateCSVDataFromTable(tableData.headers, tableData.rows);
        downloadCSVFile(name, data);
    }

    const headers = [
        { key: 'country', label: __('Country', 'woo-accounting-report'), isLeftAligned: true, isSortable: true, required: true },
        { key: 'currency', label: __('Currency', 'woo-accounting-report'), isLeftAligned: true, isSortable: true, required: true },
        { key: 'total_ex_tax', label: __('Sales', 'woo-accounting-report'), required: true, isNumeric: true },
        { key: 'total_tax', label: __('Tax', 'woo-accounting-report'), required: true, isNumeric: true },
        { key: 'total', label: __('Total', 'woo-accounting-report'), required: true, isNumeric: true },
    ];

    const tableData = {
        headers: headers.map((header) => ({
            ...header,
            defaultSort: header.key === sortColumn,
            defaultOrder: sortOrder,
        })),
        rows: sortedCountryData.map(item =>
            [
                { display: item.country, value: item.country },
                { display: item.currency, value: item.currency },
                { display: formatNumber(item.total_ex_tax), value: item.total_ex_tax },
                { display: formatNumber(item.total_tax), value: item.total_tax },
                { display: formatNumber(item.total), value: item.total },
            ]),
    };

    return (
        <TableCard
            onSort={handleSort}
            title={__('Net sales per country', 'woo-accounting-report')}
            rows={tableData.rows}
            headers={tableData.headers}
            rowsPerPage={isLoading ? 1 : 25}
            isLoading={isLoading}
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
    );

}