import { cn } from '@/lib/utils';
import { usePage } from '@inertiajs/react';
import * as React from 'react';

export interface CurrencyLike {
    code: string;
    symbol?: string | null;
    precision?: number | null;
}

const FALLBACK_CURRENCY: CurrencyLike = { code: 'ZMW', precision: 2 };

/**
 * The active currency from the shared Inertia `currencies` prop. Guests and
 * pages rendered before a currency is chosen fall back to ZMW.
 */
export function useActiveCurrency(): CurrencyLike {
    const page = usePage<{
        currencies?: { current?: CurrencyLike | null } | null;
    }>();

    return page.props.currencies?.current ?? FALLBACK_CURRENCY;
}

function resolvePrecision(currency: CurrencyLike): number {
    const precision = Number(currency.precision);

    return Number.isFinite(precision) ? precision : 2;
}

/** Fixed-decimal figure without any currency marker. */
export function formatAmount(amount: number, precision: number): string {
    const value = Number(amount);

    return (Number.isFinite(value) ? value : 0).toFixed(precision);
}

/**
 * Plain-string form of a money value, for places that cannot take markup —
 * chart tooltips rendered into SVG, `title` attributes, toast copy.
 */
export function formatMoneyText(
    amount: number,
    currency: CurrencyLike = FALLBACK_CURRENCY,
): string {
    return `${currency.code} ${formatAmount(amount, resolvePrecision(currency))}`;
}

/**
 * A money value with the currency code trailing the figure as a small
 * superscript. The figure stays unbolded so amounts read quietly and the code
 * never competes with the number itself.
 *
 * Defaults to the active currency; pass `code` to override just the marker
 * (per-row currencies) or `currency` to override the precision too.
 */
export function Money({
    amount,
    code,
    currency,
    className,
    codeClassName,
}: {
    amount: number;
    code?: string | null;
    currency?: CurrencyLike | null;
    className?: string;
    codeClassName?: string;
}) {
    const active = useActiveCurrency();
    const resolved = currency ?? active;
    const displayCode = code ?? resolved.code;

    return (
        <span className={cn('whitespace-nowrap tabular-nums', className)}>
            {formatAmount(amount, resolvePrecision(resolved))}
            <sup
                className={cn(
                    'ml-0.5 text-[0.6em] font-medium tracking-wide text-muted-foreground',
                    codeClassName,
                )}
            >
                {displayCode}
            </sup>
        </span>
    );
}

/**
 * Curried `Money` bound to one currency, for lists that render many amounts in
 * the same currency without repeating the prop.
 */
export function useMoney(currency?: CurrencyLike | null) {
    const active = useActiveCurrency();
    const resolved = currency ?? active;

    return React.useCallback(
        (amount: number, code?: string | null): React.ReactNode => (
            <Money amount={amount} code={code} currency={resolved} />
        ),
        [resolved],
    );
}
