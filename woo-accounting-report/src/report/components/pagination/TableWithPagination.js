import React, { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import { TableCard } from '@woocommerce/components';
import usePagination from './usePagination';

export function TableWithPagination({
  initialData,
  columns,
  itemsPerPage = 25,
  title,
  isLoading,
  actions,
  summary,
  initialSort
}) {
  const [visibleColumns, setVisibleColumns] = useState(columns.map(col => col.key));

  const {
    paginatedData,
    query,
    setData,
    handleQueryChange,
    handleSort,
    handleColumnsChange,
    totalRows,
  } = usePagination(initialData, {
    paged: 1,
    per_page: itemsPerPage,
    orderby: initialSort.orderby,
    order: initialSort.order,
  }, visibleColumns);

  useEffect(() => {
    setData(initialData);
  }, [initialData]);

  const headers = columns.map((header) => ({
    ...header,
    defaultSort: header.key === query.orderby,
    defaultOrder: query.order,
  }));

  return (
    <TableCard
      title={title}
      headers={headers}
      rows={paginatedData}
      totalRows={totalRows}
      rowsPerPage={itemsPerPage}
      onQueryChange={handleQueryChange}
      onColumnsChange={(newColumns) => {
        setVisibleColumns(newColumns);
        handleColumnsChange(newColumns);
      }}
      query={query}
      isLoading={isLoading}
      actions={actions}
      summary={summary}
    />
  );
}