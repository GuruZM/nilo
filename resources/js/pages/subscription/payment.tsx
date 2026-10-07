import {
    Chip,
    FormField,
    Panel,
    PillButton,
    SoftTile,
    TotalRow,
    fieldInputClass,
} from '@/components/dashboard/primitives';
import NiloSpinner from '@/components/nilo-spinner';
import { GateShell } from '@/components/subscription/gate-shell';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import { ChargeQuote, CouponQuote, Plan, SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    Building2,
    ChevronDown,
    CreditCard,
    Phone,
    Sparkles,
    TicketPercent,
    Upload,
    X,
} from 'lucide-react';
import { type FormEvent, useState } from 'react';

interface PaymentProps {
    plan: Plan;
    quote: CouponQuote;
    couponCode: string;
    dpoEnabled: boolean;
    /** Only currencies the FX table can actually price this plan in. */
    currencies: string[];
    currency: string;
    charge: ChargeQuote | null;
}

type PaymentMethod = 'mobile_money' | 'bank_transfer';

/**
 * Where the money goes. Both manual routes work the same way — the customer
 * sends to one of these and files the reference for an admin to match, which is
 * why neither of them asks for the customer's own number.
 */
const MOBILE_MONEY_DETAILS = [
    { label: 'Provider', value: 'Airtel Money' },
    { label: 'Number', value: '+260770785275' },
];

const BANK_DETAILS = [
    { label: 'Bank', value: 'First National Bank (FNB)' },
    { label: 'Account name', value: 'Resonant Technologies' },
    { label: 'Account number', value: '63108067744' },
    { label: 'Branch name', value: 'Acacia Park - Commercial Suite' },
    { label: 'Branch code', value: '260026' },
    { label: 'SWIFT code', value: 'FIRNZMLX' },
];

export default function Payment({
    plan,
    quote,
    couponCode,
    dpoEnabled,
    currencies,
    currency,
    charge,
}: PaymentProps) {
    const [method, setMethod] = useState<PaymentMethod>('mobile_money');
    const [code, setCode] = useState(couponCode);
    const [checking, setChecking] = useState(false);
    const [showManual, setShowManual] = useState(!dpoEnabled);
    const [payingOnline, setPayingOnline] = useState(false);

    /**
     * A gateway handoff that fails comes back as a redirect carrying a flash
     * message. This page renders outside the app chrome and so has no toaster
     * to catch it — without this the button reads as dead.
     */
    const { flash } = usePage<SharedData>().props;

    const money = (amount: number): string =>
        formatMoney(amount, quote.currency_code);

    const applied = quote.coupon;
    /** A coupon can settle the bill outright, leaving nothing to transfer. */
    const settledByCoupon = applied !== null && quote.total <= 0;

    const { data, setData, post, transform, processing, errors } = useForm<{
        plan_id: number;
        payment_method: string;
        payment_reference: string;
        pop_file: File | null;
        coupon_code: string;
    }>({
        plan_id: plan.id,
        payment_method: 'mobile_money',
        payment_reference: '',
        pop_file: null,
        coupon_code: '',
    });

    const handleMethodChange = (next: PaymentMethod) => {
        setMethod(next);
        setData('payment_method', next);
    };

    /**
     * Re-prices the page against the server rather than doing the arithmetic
     * here, so what the customer sees is what the purchase endpoint will charge.
     */
    const reprice = (nextCode: string, nextCurrency = currency) => {
        setChecking(true);

        router.get(
            `/subscription/payment/${plan.id}`,
            {
                ...(nextCode ? { coupon: nextCode } : {}),
                ...(nextCurrency ? { currency: nextCurrency } : {}),
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['quote', 'couponCode', 'charge', 'currency'],
                onFinish: () => setChecking(false),
            },
        );
    };

    const clearCoupon = () => {
        setCode('');
        reprice('');
    };

    /**
     * The gateway path posts nothing but the plan, the code and the currency —
     * the server re-derives the total and hands back a redirect to DPO.
     */
    const payWithDpo = () => {
        setPayingOnline(true);

        router.post(
            '/subscription/payment/dpo',
            {
                plan_id: plan.id,
                coupon_code: applied?.code ?? '',
                currency,
            },
            { onFinish: () => setPayingOnline(false) },
        );
    };

    const submit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();

        if (settledByCoupon) {
            router.post('/subscription/redeem', {
                plan_id: plan.id,
                coupon_code: applied.code,
            });

            return;
        }

        // The code rides along with the payment so the server re-derives the
        // total itself instead of trusting the one on screen.
        transform((current) => ({
            ...current,
            coupon_code: applied?.code ?? '',
        }));

        post('/subscription/payment', { forceFormData: true });
    };

    const amount = money(quote.total);

    return (
        <GateShell
            title="Complete payment"
            subtitle={
                <>
                    Subscribe to{' '}
                    <span className="font-semibold text-foreground">
                        {plan.name}
                    </span>{' '}
                    — {amount}
                    {plan.billing_period === 'yearly' ? '/year' : '/month'}
                </>
            }
            backHref="/subscription/select"
        >
            <Head title="Payment" />

            {flash?.error ? (
                <p className="mb-4 rounded-2xl bg-rose-500/10 px-3 py-2 text-sm text-rose-700 dark:text-rose-400">
                    {flash.error}
                </p>
            ) : null}

            {flash?.info ? (
                <p className="mb-4 rounded-2xl bg-amber-500/10 px-3 py-2 text-sm text-amber-700 dark:text-amber-400">
                    {flash.info}
                </p>
            ) : null}

            <Panel className="mb-4">
                <div className="mb-3 flex items-center gap-2 text-sm font-semibold">
                    <TicketPercent className="h-4 w-4 text-brand-600 dark:text-brand-300" />
                    Have a coupon?
                </div>

                {applied ? (
                    <div className="flex flex-wrap items-center justify-between gap-2 rounded-2xl bg-emerald-50 px-3 py-2 dark:bg-emerald-500/10">
                        <div className="min-w-0 text-xs text-emerald-800 dark:text-emerald-300">
                            <span className="font-semibold">
                                {applied.code}
                            </span>{' '}
                            applied — {applied.label}
                            {applied.description ? (
                                <span className="block opacity-80">
                                    {applied.description}
                                </span>
                            ) : null}
                        </div>
                        <PillButton
                            variant="ghost"
                            size="sm"
                            onClick={clearCoupon}
                            disabled={checking}
                        >
                            <X className="h-3.5 w-3.5" />
                            Remove
                        </PillButton>
                    </div>
                ) : (
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <input
                            aria-label="Coupon code"
                            value={code}
                            onChange={(e) =>
                                setCode(e.target.value.toUpperCase())
                            }
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                    e.preventDefault();
                                    reprice(code.trim());
                                }
                            }}
                            className={cn(fieldInputClass, 'uppercase')}
                        />
                        <PillButton
                            variant="soft"
                            onClick={() => reprice(code.trim())}
                            disabled={checking || code.trim() === ''}
                            className="shrink-0"
                        >
                            {checking ? (
                                <>
                                    <NiloSpinner size={16} />
                                    Checking…
                                </>
                            ) : (
                                'Apply'
                            )}
                        </PillButton>
                    </div>
                )}

                {(quote.error ?? errors.coupon_code) ? (
                    <p className="mt-2 text-xs text-destructive">
                        {quote.error ?? errors.coupon_code}
                    </p>
                ) : null}

                <SoftTile className="mt-3 flex flex-col gap-2">
                    <TotalRow label="Subtotal" value={money(quote.subtotal)} />
                    {quote.discount > 0 ? (
                        <TotalRow
                            label="Discount"
                            value={`− ${money(quote.discount)}`}
                        />
                    ) : null}
                    <TotalRow label="Total due" value={amount} strong />
                </SoftTile>
            </Panel>

            <Panel>
                {settledByCoupon ? (
                    <form onSubmit={submit} className="flex flex-col gap-4">
                        <div className="flex items-start gap-3 rounded-2xl bg-brand-50 px-3 py-3 text-sm dark:bg-brand-500/10">
                            <Sparkles className="mt-0.5 h-4 w-4 shrink-0 text-brand-600 dark:text-brand-300" />
                            <span>
                                <span className="block font-semibold">
                                    Nothing to pay
                                </span>
                                <span className="block text-xs text-muted-foreground">
                                    {applied.code} covers the full price of{' '}
                                    {plan.name}. Your subscription starts as
                                    soon as you confirm.
                                </span>
                            </span>
                        </div>

                        <PillButton
                            type="submit"
                            className="w-full"
                            disabled={checking}
                        >
                            Activate {plan.name}
                        </PillButton>
                    </form>
                ) : (
                    <>
                        {dpoEnabled ? (
                            <div className="mb-4 flex flex-col gap-3">
                                <div className="flex items-center gap-2 text-sm font-semibold">
                                    <CreditCard className="h-4 w-4 text-brand-600 dark:text-brand-300" />
                                    Pay by card or mobile money
                                </div>

                                {currencies.length > 1 ? (
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="text-xs text-muted-foreground">
                                            Pay in
                                        </span>
                                        {currencies.map((code) => (
                                            <Chip
                                                key={code}
                                                active={currency === code}
                                                disabled={checking}
                                                onClick={() =>
                                                    reprice(
                                                        applied?.code ?? '',
                                                        code,
                                                    )
                                                }
                                            >
                                                {code}
                                            </Chip>
                                        ))}
                                    </div>
                                ) : null}

                                <SoftTile className="flex flex-col gap-2">
                                    <TotalRow
                                        label="You will be charged"
                                        value={
                                            charge
                                                ? formatMoney(
                                                      charge.amount,
                                                      charge.currency,
                                                  )
                                                : amount
                                        }
                                        strong
                                    />
                                    {charge?.rate ? (
                                        <p className="text-xs text-muted-foreground">
                                            Converted from {amount} at{' '}
                                            {charge.rate.toLocaleString(
                                                undefined,
                                                { maximumFractionDigits: 4 },
                                            )}{' '}
                                            {quote.currency_code} per{' '}
                                            {charge.currency} — fixed at the
                                            moment you pay.
                                        </p>
                                    ) : null}
                                </SoftTile>

                                <PillButton
                                    type="button"
                                    className="w-full"
                                    onClick={payWithDpo}
                                    disabled={payingOnline || checking}
                                >
                                    {payingOnline ? (
                                        <>
                                            <NiloSpinner size={16} />
                                            Opening secure checkout…
                                        </>
                                    ) : (
                                        `Pay ${charge ? formatMoney(charge.amount, charge.currency) : amount}`
                                    )}
                                </PillButton>

                                <p className="text-center text-xs text-muted-foreground">
                                    You will be taken to DPO Pay to complete
                                    your payment securely.
                                </p>

                                <PillButton
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="self-center"
                                    onClick={() => setShowManual((on) => !on)}
                                >
                                    <ChevronDown
                                        className={cn(
                                            'h-3.5 w-3.5 transition-transform',
                                            showManual && 'rotate-180',
                                        )}
                                    />
                                    Pay another way
                                </PillButton>
                            </div>
                        ) : null}

                        {showManual ? (
                            <>
                                <div className="mb-4 flex flex-wrap items-center gap-2">
                                    <Chip
                                        active={method === 'mobile_money'}
                                        onClick={() =>
                                            handleMethodChange('mobile_money')
                                        }
                                    >
                                        <Phone className="h-3.5 w-3.5" />
                                        Mobile money
                                    </Chip>
                                    <Chip
                                        active={method === 'bank_transfer'}
                                        onClick={() =>
                                            handleMethodChange('bank_transfer')
                                        }
                                    >
                                        <Building2 className="h-3.5 w-3.5" />
                                        Bank transfer
                                    </Chip>
                                </div>

                                <form
                                    onSubmit={submit}
                                    className="flex flex-col gap-4"
                                >
                                    {method === 'mobile_money' ? (
                                        <>
                                            <SoftTile className="flex flex-col gap-2">
                                                <div className="text-xs font-semibold">
                                                    Send payment to
                                                </div>
                                                {MOBILE_MONEY_DETAILS.map(
                                                    (detail) => (
                                                        <TotalRow
                                                            key={detail.label}
                                                            label={detail.label}
                                                            value={detail.value}
                                                        />
                                                    ),
                                                )}
                                                <TotalRow
                                                    label="Amount"
                                                    value={amount}
                                                    strong
                                                />
                                            </SoftTile>

                                            <FormField
                                                label="Transaction ID"
                                                htmlFor="payment_reference"
                                                required
                                            >
                                                <input
                                                    id="payment_reference"
                                                    type="text"
                                                    value={
                                                        data.payment_reference
                                                    }
                                                    onChange={(e) =>
                                                        setData(
                                                            'payment_reference',
                                                            e.target.value,
                                                        )
                                                    }
                                                    className={fieldInputClass}
                                                />
                                                {errors.payment_reference ? (
                                                    <p className="text-xs text-destructive">
                                                        {
                                                            errors.payment_reference
                                                        }
                                                    </p>
                                                ) : null}
                                            </FormField>
                                        </>
                                    ) : (
                                        <>
                                            <SoftTile className="flex flex-col gap-2">
                                                <div className="text-xs font-semibold">
                                                    Bank details
                                                </div>
                                                {BANK_DETAILS.map((detail) => (
                                                    <TotalRow
                                                        key={detail.label}
                                                        label={detail.label}
                                                        value={detail.value}
                                                    />
                                                ))}
                                                <TotalRow
                                                    label="Amount"
                                                    value={amount}
                                                    strong
                                                />
                                            </SoftTile>

                                            <FormField
                                                label="Payment reference / receipt number"
                                                htmlFor="payment_reference"
                                                required
                                            >
                                                <input
                                                    id="payment_reference"
                                                    type="text"
                                                    value={
                                                        data.payment_reference
                                                    }
                                                    onChange={(e) =>
                                                        setData(
                                                            'payment_reference',
                                                            e.target.value,
                                                        )
                                                    }
                                                    className={fieldInputClass}
                                                />
                                                {errors.payment_reference ? (
                                                    <p className="text-xs text-destructive">
                                                        {
                                                            errors.payment_reference
                                                        }
                                                    </p>
                                                ) : null}
                                            </FormField>
                                        </>
                                    )}

                                    {/*
                                     * Shared by both routes, but only demanded
                                     * for a bank transfer — a mobile money
                                     * transaction ID is enough for an admin to
                                     * find the payment on its own.
                                     */}
                                    <FormField
                                        label={
                                            method === 'bank_transfer'
                                                ? 'Proof of payment'
                                                : 'Proof of payment (optional)'
                                        }
                                        htmlFor="pop_file"
                                        required={method === 'bank_transfer'}
                                    >
                                        <label
                                            htmlFor="pop_file"
                                            className={cn(
                                                'flex cursor-pointer items-center gap-3 rounded-2xl bg-muted/50 p-4 transition dark:bg-white/5',
                                                'hover:bg-brand-50/70 dark:hover:bg-brand-500/10',
                                            )}
                                        >
                                            <span className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
                                                <Upload className="h-4 w-4" />
                                            </span>
                                            <span className="min-w-0 text-sm">
                                                {data.pop_file ? (
                                                    <span className="truncate font-medium">
                                                        {data.pop_file.name}
                                                    </span>
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        Click to upload — JPG,
                                                        PNG or PDF, max 5MB
                                                    </span>
                                                )}
                                            </span>
                                        </label>
                                        <input
                                            id="pop_file"
                                            type="file"
                                            className="hidden"
                                            accept=".jpg,.jpeg,.png,.pdf"
                                            onChange={(e) =>
                                                setData(
                                                    'pop_file',
                                                    e.target.files?.[0] ?? null,
                                                )
                                            }
                                        />
                                        {errors.pop_file ? (
                                            <p className="text-xs text-destructive">
                                                {errors.pop_file}
                                            </p>
                                        ) : null}
                                    </FormField>

                                    <PillButton
                                        type="submit"
                                        className="w-full"
                                        disabled={processing || checking}
                                    >
                                        {processing ? (
                                            <>
                                                <NiloSpinner size={16} />
                                                Submitting…
                                            </>
                                        ) : (
                                            `Submit payment · ${amount}`
                                        )}
                                    </PillButton>
                                </form>
                            </>
                        ) : null}
                    </>
                )}
            </Panel>
        </GateShell>
    );
}
