import { Fragment, useState, useEffect, useMemo } from '@wordpress/element';
import useFetchData from './hooks/useFetchData';
import { CircularProgress } from '@mui/material';
import PluginInfo from './components/PluginInfo';

import {
    SummaryList,
    SummaryListPlaceholder,
    SummaryNumber,
    TablePlaceholder,
    TableCard,
    Section,
} from '@woocommerce/components';

import ReportFilters from '../analytics/components/report-filters';

import { AllOrdersTable } from './components/tables/AllOrdersTable';
import { TotalSalesPerRegionTable } from './components/tables/TotalSalesPerRegionTable';
import { TotalSalesPerCountryTable } from './components/tables/TotalSalesPerCountryTable';
import { MethodsOfPaymentTable } from './components/tables/MethodsOfPaymentTable';
import { TotalSalesTable } from './components/tables/TotalSalesTable';
import { TotalSalesPerTaxClassTable } from './components/tables/TotalSalesPerTaxClassTable';
import { OssReportTable } from './components/tables/OssReportTable';
import { AllProductsTable } from './components/tables/AllProductsTable';
import { CURRENCY as storeCurrencySetting } from '@woocommerce/settings';
import { appendTimestamp, getCurrentDates, getDateParamsFromQuery } from '@woocommerce/date';
import getSetting from './hooks/wooCommerceOptions';
import { setNumberFormatSettings } from './components/format-number';

import logger from './components/logger';

import { default as Currency } from '@woocommerce/currency';

import { Tabs, Tab, Box } from '@mui/material';

const Waiting = () => {
    return (
        <Fragment>
            <div style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', height: '100vh' }}>
                <CircularProgress />
            </div>
        </Fragment >
    );
}

const createDateQuery = (query) => {
    const { period, compare, before, after } = getDateParamsFromQuery(query);
    const { primary: primaryDate, secondary: secondaryDate } = getCurrentDates(query);
    return { period, before, after, primaryDate, secondaryDate };
}

const AcccoutingReport = ({ path, query }) => {

    const storeCurrency = new Currency(storeCurrencySetting);
    const { processing, data, error, fetchData } = useFetchData(storeCurrencySetting);
    const [useOssPane, setUseOssPane] = useState(false);
    const dateQuery = useMemo(() => createDateQuery(query), [query]);
    const vatRateDate = useMemo(() => {
        const beforeDate = dateQuery?.primaryDate?.before;

        if (!beforeDate) {
            return null;
        }

        return appendTimestamp(beforeDate, 'end').slice(0, 10);
    }, [dateQuery]);

    useEffect(() => {
        getSetting('bjorntech_wcar_show_oss_pane').then((value) => {
            setUseOssPane(value === 'yes');
        });
    }, []);

    useEffect(() => {
        Promise.all([
            getSetting('bjorntech_wcar_price_thousand_sep', ','),
            getSetting('bjorntech_wcar_price_decimal_sep', '.'),
            getSetting('bjorntech_wcar_price_num_decimals', 2),
        ]).then(([thousandSeparator, decimalSeparator, decimals]) => {
            setNumberFormatSettings({ thousandSeparator, decimalSeparator, decimals });
        });
    }, []);

    useEffect(() => {
        if (!processing) {
            fetchData(dateQuery);
        }
    }, [dateQuery]);

    const handleDateChange = (query) => {
        const dateQuery = createDateQuery(query);
        fetchData(dateQuery);
    }

    const [activeTab, setActiveTab] = useState(0);

    const handleTabChange = (event, newValue) => {
        setActiveTab(newValue);
    };

    if (data?.isLoaded === true) {
        return (
            <Fragment>
                <div style={{ display: 'flex', justifyContent: 'space-between', width: '100%', height: '100%' }}>
                    <PluginInfo />
                </div>
                <Fragment>
                    <ReportFilters
                        query={query}
                        path={path}
                        filters={[]}
                        currency={storeCurrency}
                        advancedFilters={{}}
                        onDateSelect={handleDateChange}
                        disableCompare={true}
                    />
                    <Box sx={{ borderBottom: 1, borderColor: 'divider', mb: 2 }}>
                        <Tabs value={activeTab} onChange={handleTabChange} aria-label="accounting report tabs">
                            <Tab label="Total Sales" />
                            <Tab label="Sales per Tax Class" />
                            <Tab label="Sales per Region" />
                            <Tab label="Sales per Country" />
                            <Tab label="Payment Methods" />
                            <Tab label="All Orders" />
                            <Tab label="Products Sold" />
                            {useOssPane && <Tab label="OSS Report" />}
                        </Tabs>
                    </Box>
                    {activeTab === 0 && (
                        <TotalSalesTable
                            data={data}
                            currency={storeCurrency}
                            dataIsLoaded={data.isLoaded}
                        />
                    )}
                    {activeTab === 1 && (
                        <TotalSalesPerTaxClassTable
                            data={data}
                            currency={storeCurrency}
                            dataIsLoaded={data.isLoaded}
                        />
                    )}
                    {activeTab === 2 && (
                        <TotalSalesPerRegionTable
                            data={data}
                            currency={storeCurrency}
                            dataIsLoaded={data.isLoaded}
                        />
                    )}
                    {activeTab === 3 && (
                        <TotalSalesPerCountryTable
                            data={data}
                            currency={storeCurrency}
                            dataIsLoaded={data.isLoaded}
                        />
                    )}
                    {activeTab === 4 && (
                        <MethodsOfPaymentTable
                            data={data}
                            currency={storeCurrency}
                            dataIsLoaded={data.isLoaded}
                        />
                    )}
                    {activeTab === 5 && (
                        <AllOrdersTable
                            data={data}
                            currency={storeCurrency}
                        />
                    )}
                    {activeTab === 6 && (
                        <AllProductsTable
                            data={data}
                            currency={storeCurrency}
                            dataIsLoaded={data.isLoaded}
                        />
                    )}
                    {activeTab === 7 && useOssPane && (
                        <OssReportTable
                            data={data}
                            currency={storeCurrency}
                            dataIsLoaded={data.isLoaded}
                            vatRateDate={vatRateDate}
                        />
                    )}
                </Fragment>
            </Fragment>
        );
    } else {
        return <Waiting />;
    }
}

export default AcccoutingReport;
