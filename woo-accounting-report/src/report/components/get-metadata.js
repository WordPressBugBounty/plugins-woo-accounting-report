const getMetaData = (order, metaKey) => {
    // Check if meta_data exists
    if (!order.meta_data) {
        return '';
    }

    // Use Array.find() to find the matching metadata and return its value,
    // or an empty string if no matching metadata is found.
    const matchingData = order.meta_data.find(data => data.key === metaKey);
    return matchingData ? matchingData.value : '';
};

export default getMetaData;