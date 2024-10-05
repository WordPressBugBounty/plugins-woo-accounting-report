
export const formatAmount = (number) => {

    const precision = 2;

    if (typeof number !== 'number') {
        number = parseFloat(number);
    }
    if (Number.isNaN(number)) {
        return '';
    }

    return number.toFixed(precision);

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
