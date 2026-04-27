import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

const defaultRates = {};

const useEuroFXRefData = () => {
  const [data, setData] = useState(defaultRates);
  const [error, setError] = useState(null);
  const [isLoaded, setIsLoaded] = useState(false);

  useEffect(() => {
    let isMounted = true;

    apiFetch({ path: '/bjorntech-accounting/v1/exchange-rates?base=EUR' })
      .then((response) => {
        if (!isMounted) {
          return;
        }

        if (response?.rates && typeof response.rates === 'object') {
          setData(response.rates);
        } else {
          setError(new Error('Exchange rate response did not include rates.'));
        }
      })
      .catch((err) => {
        if (isMounted) {
          setError(err);
        }
      })
      .finally(() => {
        if (isMounted) {
          setIsLoaded(true);
        }
      });

    return () => {
      isMounted = false;
    };
  }, []);

  const getRateByCurrency = (code) => {
    if (!code || code === 'EUR') {
      return 1;
    }

    const rate = Number(data?.[code]);
    return Number.isFinite(rate) && rate > 0 ? rate : null;
  };

  return { data, error, isLoaded, getRateByCurrency };
};

export default useEuroFXRefData;
