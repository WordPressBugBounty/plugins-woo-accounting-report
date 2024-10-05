const getTaxes = (order) => {
    return order.tax_lines.reduce((acc, taxObject) => {
        acc[taxObject.rate_id] = taxObject;
        return acc;
    }, {});
}

export default getTaxes;