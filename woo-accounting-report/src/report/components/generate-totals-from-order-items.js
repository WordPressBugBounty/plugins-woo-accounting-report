import { __, sprintf } from '@wordpress/i18n';

const existingKey = (key, array) => {
    return array.find(item => {
        return item.key === key;
    });
}

const generateTotalFromOrderItems = (order, itemTotal, itemType, orderTaxes, rowLabel, refund = false, consolidate = false, currencyRate = 1, overrideCurrency = null) => {
    // Check if any of the required parameters are undefined or null
    if (!order || !itemTotal || !itemType || !orderTaxes) {
        throw new Error('Missing required parameter(s)');
    }

    const countryCode = order.billing.country || '??';
    const currency = (overrideCurrency ? overrideCurrency : order.currency) || '???';

    // Iterate over the order items
    for (const item of order[itemType]) {

        let addedTotals = false;
        const total = Number(item.total * currencyRate);
        const rawTotalTax = Number(item.total_tax);
        const totalTax = Number(rawTotalTax * currencyRate);

        const itemTaxes = item.taxes || [];
        const hasItemTaxes = itemTaxes.length > 0;
        const orderTaxIds = Object.keys(orderTaxes || {});

        let fallbackTaxId = -1;
        if (!hasItemTaxes && totalTax !== 0 && orderTaxIds.length === 1) {
            fallbackTaxId = Number(orderTaxIds[0]);
        }

        let taxArray = ((total === 0 && totalTax === 0) || hasItemTaxes)
            ? itemTaxes
            : [{ id: fallbackTaxId, total: rawTotalTax }];

        for (const tax of taxArray) {
            const itemId = consolidate ? 'item' : (refund ? 'refund' : itemType)
            const taxRateCode = tax.id === -1 ? '0' : (orderTaxes[tax.id]?.rate_percent || '0');
            const key = `${countryCode}#${currency}#${taxRateCode}#${itemId}`;
            const existingTotalSales = existingKey(key, itemTotal);
            const taxItemTaxTotal = Number((Number(tax.total).toFixed(2)) * currencyRate);


            if (existingTotalSales) {
                // If an object with the current key already exists, update the total_tax and total properties
                existingTotalSales.total_tax += taxItemTaxTotal;
                if (!addedTotals) {
                    existingTotalSales.total = !refund ? existingTotalSales.total + total : (Math.abs(existingTotalSales.total) + Math.abs(total)) * -1;
                    addedTotals = true;
                }
            } else {
                // If no object with the current key exists, create a new object and add it to the itemTotal array
                const labelToUse = refund ? __('Refunds', 'woo-accounting-report') : rowLabel;
                const taxPercentage = tax.id === -1 ? 0 : (orderTaxes[tax.id]?.rate_percent || 0);
                const title = sprintf(
                    /* translators: 1: row label, 2: country code, 3: tax percentage */
                    __('%1$s to %2$s with %3$s%% tax', 'woo-accounting-report'),
                    labelToUse,
                    countryCode,
                    taxPercentage
                );

                itemTotal.push({
                    key,
                    currency,
                    country: countryCode,
                    taxRateCode,
                    taxPercentage,
                    type: title,
                    total_tax: taxItemTaxTotal,
                    total,
                });
            }
        }
    }

    return itemTotal;
};

export default generateTotalFromOrderItems;
