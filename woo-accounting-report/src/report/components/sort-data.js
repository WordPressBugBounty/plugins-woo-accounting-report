const sortData = (data, column, order) => {
    if (order === 'asc') {
        return data.sort((a, b) => (a[column] > b[column]) ? 1 : -1);
    } else if (order === 'desc') {
        return data.sort((a, b) => (b[column] > a[column]) ? 1 : -1);
    }
}

export default sortData;