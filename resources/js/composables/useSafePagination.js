export function useSafePagination(maxItemsPerPage = 100, options = [10, 25, 50, 100]) {
  const itemsPerPageOptions = options
    .filter(value => Number.isInteger(value) && value > 0 && value <= maxItemsPerPage)

  const defaultItemsPerPage = itemsPerPageOptions[0] || 10

  const sanitizeItemsPerPage = value => {
    const parsed = parseInt(value, 10)
    if (Number.isNaN(parsed) || parsed <= 0) return defaultItemsPerPage
    return Math.min(parsed, maxItemsPerPage)
  }

  return {
    itemsPerPageOptions,
    defaultItemsPerPage,
    sanitizeItemsPerPage,
  }
}
