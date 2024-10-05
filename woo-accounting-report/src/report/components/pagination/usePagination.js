import { useState, useMemo, useCallback } from '@wordpress/element';

const usePagination = (initialData, initialQuery) => {
    const [data, setData] = useState(initialData);
    const [query, setQuery] = useState(initialQuery);

    const paginatedData = useMemo(() => {
        const startIndex = (query.paged - 1) * query.per_page;
        const endIndex = startIndex + query.per_page;
        return data.slice(startIndex, endIndex);
    }, [data, query.paged, query.per_page]);

    const handleQueryChange = useCallback((param) => {
        return (value) => {
            console.log('handleQueryChange', param, value);
            setQuery((prevQuery) => ({
                ...prevQuery,
                [param]: value,
            }));
        };
    }, []);

    const handleSort = useCallback((column) => {
        setQuery(prevQuery => ({
            ...prevQuery,
            orderby: column,
            order: prevQuery.orderby === column && prevQuery.order === 'asc' ? 'desc' : 'asc',
        }));
    }, []);

    return {
        paginatedData,
        query,
        setData,
        handleQueryChange,
        handleSort,
        totalRows: data.length,
    };
};

export default usePagination;