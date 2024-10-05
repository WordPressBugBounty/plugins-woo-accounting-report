import { useEffect, useState } from 'react';
import axios from 'axios';
import xml2js from 'xml2js';

const useEuroFXRefData = () => {
  const [data, setData] = useState(null);
  const [error, setError] = useState(null);

  const fetchData = async (currency, date) => {
    try {
      const response = await axios.get(
        'https://57e9wwzgog.execute-api.eu-north-1.amazonaws.com/prod/exchange-rates'
      );
      setData(response.data);
    } catch (err) {
      setError(err);
    }
  };

  const getRateByCurrency = (code, date) => {
    fetchData(code, date)
      .then((data) => {
        return data.rates[code];
      })
      .catch((err) => {
        console.log(err);
        return 0;
      });
  };

  return { data, error, getRateByCurrency };
};

export default useEuroFXRefData;
