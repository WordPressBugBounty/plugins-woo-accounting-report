import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

const defaultRates = {};

const useEuVatData = (vatRateDate) => {
    const [countryCodes, setCountryCodes] = useState([]);
    const [standardRatesByCountry, setStandardRatesByCountry] = useState(defaultRates);
    const [error, setError] = useState(null);
    const [isLoaded, setIsLoaded] = useState(false);

    useEffect(() => {
        let isMounted = true;

        const fetchEuVatData = async () => {
            if (!/^\d{4}-\d{2}-\d{2}$/.test(vatRateDate || '')) {
                if (isMounted) {
                    setError(new Error('Invalid VAT rate date. Expected YYYY-MM-DD.'));
                    setCountryCodes([]);
                    setStandardRatesByCountry(defaultRates);
                    setIsLoaded(true);
                }
                return;
            }

            if (isMounted) {
                setError(null);
                setIsLoaded(false);
                setCountryCodes([]);
                setStandardRatesByCountry(defaultRates);
            }

            try {
                const countriesResponse = await apiFetch({
                    path: '/bjorntech-accounting/v1/data/eu-vat/countries',
                });

                const countries = Array.isArray(countriesResponse?.data)
                    ? countriesResponse.data
                    : [];

                const codes = countries
                    .map((country) => country?.countryCode)
                    .filter((countryCode) => typeof countryCode === 'string' && countryCode !== '');

                if (!isMounted) {
                    return;
                }

                setCountryCodes(codes);

                if (codes.length === 0) {
                    return;
                }

                const query = new URLSearchParams({
                    countries: codes.join(','),
                    rate_types: 'STANDARD',
                });

                const vatRatesResponse = await apiFetch({
                    path: `/bjorntech-accounting/v1/data/eu-vat/${vatRateDate}?${query.toString()}`,
                });

                if (!isMounted) {
                    return;
                }

                const rates = Array.isArray(vatRatesResponse?.data?.rates)
                    ? vatRatesResponse.data.rates
                    : [];

                const parsedRates = rates.reduce((acc, rate) => {
                    const countryCode = rate?.country;
                    const ratePercent = Number(rate?.ratePercent);

                    if (
                        typeof countryCode === 'string'
                        && countryCode !== ''
                        && Number.isFinite(ratePercent)
                    ) {
                        acc[countryCode] = ratePercent;
                    }

                    return acc;
                }, {});

                setStandardRatesByCountry(parsedRates);
            } catch (fetchError) {
                if (isMounted) {
                    setError(fetchError);
                }
            } finally {
                if (isMounted) {
                    setIsLoaded(true);
                }
            }
        };

        fetchEuVatData();

        return () => {
            isMounted = false;
        };
    }, [vatRateDate]);

    return {
        countryCodes,
        standardRatesByCountry,
        error,
        isLoaded,
    };
};

export default useEuVatData;
