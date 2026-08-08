/**
 * Symbols the product says differently to how ICU does.
 *
 * Intl renders ZMW as "ZMW 100,000.00", but every screen in Nilo has always
 * said "K100,000". Anything not listed here falls through to Intl's own symbol,
 * which is right for USD and for whatever gets whitelisted next.
 */
const SYMBOL_OVERRIDES: Record<string, string> = {
    ZMW: 'K',
};

export function formatMoney(
    amount: number | string | null | undefined,
    currency = 'ZMW',
): string {
    const value =
        typeof amount === 'string' ? parseFloat(amount) : (amount ?? 0);
    const safe = Number.isFinite(value) ? value : 0;
    const code = currency.toUpperCase();
    const override = SYMBOL_OVERRIDES[code];

    if (override) {
        return `${override}${safe.toLocaleString(undefined, {
            maximumFractionDigits: 2,
        })}`;
    }

    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: code,
        maximumFractionDigits: 2,
    }).format(safe);
}
