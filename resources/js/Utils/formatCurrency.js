
export function formatCurrency(value, decimals = 2) {
   return new Intl.NumberFormat('en-US', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
    }).format(value)
}
