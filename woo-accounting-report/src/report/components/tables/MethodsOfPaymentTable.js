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
import sortData from '../sort-data';


const preparePaymentMethodData = (data) => {
    const paymentMethods = [];
    data.orders.forEach(order => {
        const key = `${order.payment_method ?? '???'}_${order.currency ?? '???'}`;

        // Remove unnecessary empty if statement
        const existingMethod = paymentMethods.find(method => method.key === key);

        if (existingMethod) {
            existingMethod.total += Number(order.total);
            existingMethod.fee += Number(order.stripe_fee);
        } else {

            const title = order.payment_method.replace(/_/g, ' ').replace(/\b\w+\b/g, str => str.charAt(0).toUpperCase() + str.slice(1));
            paymentMethods.push({
                key,
                title: title ?? __('Unknown', 'woo-accounting-report'),
                currency: order.currency ?? '???',
                total: Number(order.total),
                fee: Number(order.stripe_fee)
            });
        }

        if (order.pw_gift_card_lines) {
            const giftCardKey = `pw_gift_card_${order.currency ?? 'xxx'}`;

            // Use Array.forEach() instead of Array.map() for the same reason as above.
            order.pw_gift_card_lines.forEach(giftCard => {
                const existingGiftCard = paymentMethods.find(method => method.key === giftCardKey);

                if (existingGiftCard) {
                    existingGiftCard.total += Number(giftCard.amount);
                } else {
                    paymentMethods.push({
                        key: giftCardKey,
                        title: __('PW gift cards', 'woo-accounting-report'),
                        currency: order.currency ?? '???',
                        total: Number(giftCard.amount),
                        fee: 0
                    });
                }
            });
        }
    });

    return paymentMethods;
};
export const MethodsOfPaymentTable = ({ data, totals, currency, dataIsLoaded }) => {

    const [sortColumn, setSortColumn] = useState('payment_method');
    const [sortOrder, setSortOrder] = useState('desc');
    const [sortedPaymentMethodData, setSortedPaymentMethodData] = useState([]);
    const [paymentMethodData, setPaymentMethodData] = useState([]);
    const [isLoading, setIsLoading] = useState(true);

    useEffect(() => {
        setIsLoading(!data.isLoaded);
    }, [data.isLoaded]);

    useEffect(() => {
        if (data.isLoaded) {
            setPaymentMethodData(preparePaymentMethodData(data));
        }
    }, [data, data.isLoaded]);

    useEffect(() => {
        setSortedPaymentMethodData(sortData(paymentMethodData, sortColumn, sortOrder));
    }, [paymentMethodData, sortColumn, sortOrder]);

    const handleSort = (newSortColumn) => {
        if (newSortColumn === sortColumn) {
            setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc');
        } else {
            setSortColumn(newSortColumn);
            setSortOrder('asc');
        }
    }
    const onClickDownload = () => {
        const name = generateCSVFileName('accounting_report_mothods_of_payment');
        const data = generateCSVDataFromTable(tableData.headers, tableData.rows);
        downloadCSVFile(name, data);
    }

    const headers = [
        { key: 'payment_method', label: __('Payment method', 'woo-accounting-report'), isLeftAligned: true, isSortable: true, required: true },
        { key: 'currency', label: __('Currency', 'woo-accounting-report'), isLeftAligned: true, isSortable: true, required: true },
        { key: 'amount', label: __('Amount', 'woo-accounting-report'), isSortable: true, isNumeric: true },
        { key: 'fee', label: __('Fee', 'woo-accounting-report'), isSortable: true, isNumeric: true },
    ];

    const tableData = {
        headers: headers.map((header) => ({
            ...header,
            defaultSort: header.key === sortColumn,
            defaultOrder: sortOrder,
        })),
        rows: sortedPaymentMethodData.map((item) => [
            { display: item.title, value: item.title },
            { display: item.currency, value: item.currency },
            { display: formatNumber(item.total), value: item.total },
            { display: formatNumber(item.fee), value: item.fee },
        ]),
    };

    return (
        <TableCard
            onSort={handleSort}
            title={__('Payment methods', 'woo-accounting-report')}
            rows={tableData.rows}
            headers={tableData.headers}
            rowsPerPage={isLoading ? 1 : 25}
            totalRows={tableData.rows.length}
            isLoading={isLoading}
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

