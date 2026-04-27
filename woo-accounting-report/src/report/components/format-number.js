const formatSettings = {
    thousandSeparator: ',',
    decimalSeparator: '.',
    decimals: 2,
};

const normalizeDecimals = (value) => {
    const parsed = Number.parseInt(value, 10);
    if (Number.isNaN(parsed) || parsed < 0) {
        return 2;
    }

    return parsed;
};

export const setNumberFormatSettings = ({ thousandSeparator, decimalSeparator, decimals } = {}) => {
    if (typeof thousandSeparator === 'string') {
        formatSettings.thousandSeparator = thousandSeparator;
    }

    if (typeof decimalSeparator === 'string' && decimalSeparator.length > 0) {
        formatSettings.decimalSeparator = decimalSeparator;
    }

    if (decimals !== undefined) {
        formatSettings.decimals = normalizeDecimals(decimals);
    }
};

export const formatAmount = (number) => {

    if (typeof number !== 'number') {
        number = parseFloat(number);
    }
    if (Number.isNaN(number)) {
        return '';
    }

    const isNegative = number < 0;
    const absoluteNumber = Math.abs(number);
    const fixedValue = absoluteNumber.toFixed(formatSettings.decimals);
    const [rawIntegerPart, rawDecimalPart] = fixedValue.split('.');
    const integerPart = rawIntegerPart.replace(/\B(?=(\d{3})+(?!\d))/g, formatSettings.thousandSeparator);

    let formattedValue = integerPart;

    if (formatSettings.decimals > 0) {
        formattedValue = `${integerPart}${formatSettings.decimalSeparator}${rawDecimalPart}`;
    }

    return isNegative ? `-${formattedValue}` : formattedValue;

}

export const formatNumber = (displayValue, indicatorValue = null, isNumber = true) => {
    indicatorValue = indicatorValue === null ? displayValue : indicatorValue;
    if (typeof indicatorValue !== 'number') {
        indicatorValue = parseFloat(indicatorValue);
    }
    if (isNumber && typeof displayValue !== 'number') {
        displayValue = parseFloat(displayValue);
    }
    if (indicatorValue < 0) {
        return (
            <span className="is-negative">
                {isNumber ? formatAmount(displayValue) : displayValue}
            </span>
        );
    }
    return isNumber ? formatAmount(displayValue) : displayValue;
};
