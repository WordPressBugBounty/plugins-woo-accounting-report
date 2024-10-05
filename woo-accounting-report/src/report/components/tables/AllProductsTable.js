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

import { formatNumber } from '../format-number';
import DownloadIcon from '../download-icon';
import sortData from '../sort-data';
import logger from '../logger';
import usePagination from '../pagination/usePagination';

export const AllProductsTable = (props) => {

    const { data, currency } = props;

    const [isLoading, setIsLoading] = useState(true);

    const {
        paginatedData: sortedProductData,
        query,
        setData: setProductData,
        handleQueryChange,
        handlePageChange,
        handleSort,
        totalRows,
    } = usePagination([], {
        paged: 1,
        per_page: 25,
        orderby: 'order_date',
        order: 'desc',
    });

    useEffect(() => {
        if (data.isLoaded && Array.isArray(data.orders)) {
            const unsortedData = prepareAllProductsData(data.orders);
            setProductData(unsortedData);
            setIsLoading(false);
        }
    }, [data]);

    const onClickDownload = () => {
        const name = generateCSVFileName('accounting_all_products');
        const data = generateCSVDataFromTable(tableData.headers, tableData.rows);
        downloadCSVFile(name, data);
    }

    const prepareAllProductsData = (orders) => {
        const productsByItemName = [];

        orders.forEach(order => {
            order.line_items.forEach(item => {

                const sortKey = item.product_id + '#' + item.variation_id + '#';

                let product = productsByItemName.find(product => product.key === sortKey);

                if (!product) {
                    product = {
                        productId: item.product_id,
                        variationId: item.variation_id,
                        key: sortKey,
                        items: [],
                        name: item.name,
                        customerNames: [],
                        totalQuantity: 0,
                    };

                    productsByItemName.push(product);
                }

                product.customerNames.push(order.buyer_name);

                product.items.push({
                    price: item.price,
                    quantity: item.quantity,
                    currency: order.currency,
                });

                product.totalQuantity += item.quantity;
            });
        });

        return productsByItemName;

    }

    const headers = [
        { key: 'productId', label: __('Product id', 'woo-accounting-report'), isLeftAligned: true, isSortable: true, required: true },
        { key: 'variationId', label: __('Variation id', 'woo-accounting-report'), isLeftAligned: true, isSortable: true, isNumeric: true },
        { key: 'name', label: __('Product name', 'woo-accounting-report'), isLeftAligned: true, isSortable: true, required: true, isNumeric: true },
        { key: 'totalQuantity', label: __('Total quantity', 'woo-accounting-report'), isRightAligned: true, isSortable: true, required: true },
        { key: 'customerNames', label: __('Customers', 'woo-accounting-report'), isRightAligned: true, isSortable: true, required: true },
    ];

    const tableData = {
        headers: headers.map((header) => ({
            ...header,
            defaultSort: header.key === query.orderby,
            defaultOrder: query.order,
        })),
        rows: sortedProductData?.map(item => [
            { display: formatNumber(item.productId, item.total, false), value: item.productId },
            { display: formatNumber(item.variationId, item.total, false), value: item.variationId },
            { display: formatNumber(item.name, item.total, false), value: item.name },
            { display: formatNumber(item.totalQuantity, item.total, false), value: item.totalQuantity },
            { display: formatNumber(item.customerNames.join(', '), item.total, false), value: item.customerNames.join(', ') },
        ]) || [],
    };

    if (isLoading) {
        <TablePlaceholder
            key={0}
            title={__('Products sold', 'woo-accounting-report')}
            headers={headers}
            rows={[]}
            rowsPerPage={1}
            totalRows={0}
        />
    }

    return (
        <TableCard
            onSort={handleSort}
            onQueryChange={handleQueryChange} d
            onPageChange={handlePageChange}
            title={__('Products sold', 'woo-accounting-report')}
            rows={tableData.rows}
            headers={tableData.headers}
            rowsPerPage={query.per_page}
            totalRows={totalRows}
            page={query.paged}
            isLoading={isLoading}
            query={query}
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

