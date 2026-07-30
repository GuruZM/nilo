import { AlertTriangle } from 'lucide-react';

export interface FxMeta {
    display_code: string;
    rates_as_of: string | null;
    unconvertible: string[];
}

function formatRateDate(isoDate: string): string {
    const parsed = new Date(isoDate);

    if (Number.isNaN(parsed.getTime())) {
        return isoDate;
    }

    return parsed.toLocaleDateString(undefined, {
        day: 'numeric',
        month: 'short',
    });
}

/**
 * The quiet line under converted totals.
 *
 * Mid-market rates are indicative and will not match what a bank or mobile
 * money operator settles at, so figures say which day they came from. Money
 * that could not be converted is named rather than silently omitted.
 */
export function FxNote({ fx }: { fx?: FxMeta | null }) {
    if (!fx) {
        return null;
    }

    const missing = fx.unconvertible ?? [];
    const hasMissing = missing.length > 0;

    if (!fx.rates_as_of && !hasMissing) {
        return null;
    }

    return (
        <div className="mt-3 flex flex-col gap-1 text-xs text-muted-foreground">
            {fx.rates_as_of ? (
                <span>
                    Totals shown in {fx.display_code}, converted at{' '}
                    {formatRateDate(fx.rates_as_of)} rates. Indicative only —
                    your bank's rate will differ.
                </span>
            ) : (
                <span>
                    Totals shown in {fx.display_code}. No exchange rates have
                    been synced yet.
                </span>
            )}

            {hasMissing ? (
                <span className="inline-flex items-center gap-1.5 text-amber-600 dark:text-amber-400">
                    <AlertTriangle className="h-3.5 w-3.5 shrink-0" />
                    Amounts in {missing.join(', ')} are missing from these
                    totals — no rate available.
                </span>
            ) : null}
        </div>
    );
}
