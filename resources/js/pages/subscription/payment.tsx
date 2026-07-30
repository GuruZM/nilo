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
import { cn } from '@/lib/utils';
import { CouponQuote, Plan } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import {
    Building2,
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
}

type PaymentMethod = 'airtel_money' | 'bank_transfer';

const BANK_DETAILS = [
    { label: 'Bank', value: 'Stanbic Bank Zambia' },
    { label: 'Account name', value: 'Nilo Technologies Ltd' },
    { label: 'Account number', value: '9130001234567' },
    { label: 'Branch', value: 'Main Branch' },
];

const money = (amount: number): string =>
    `K${amount.toLocaleString(undefined, { maximumFractionDigits: 2 })}`;

export default function Payment({ plan, quote, couponCode }: PaymentProps) {
    const [method, setMethod] = useState<PaymentMethod>('airtel_money');
    const [code, setCode] = useState(couponCode);
    const [checking, setChecking] = useState(false);

    const applied = quote.coupon;
    /** A coupon can settle the bill outright, leaving nothing to transfer. */
    const settledByCoupon = applied !== null && quote.total <= 0;

    const { data, setData, post, transform, processing, errors } = useForm<{
        plan_id: number;
        payment_method: string;
        phone_number: string;
        payment_reference: string;
        pop_file: File | null;
        coupon_code: string;
    }>({
        plan_id: plan.id,
        payment_method: 'airtel_money',
        phone_number: '',
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
    const reprice = (nextCode: string) => {
        setChecking(true);

        router.get(
            `/subscription/payment/${plan.id}`,
            nextCode ? { coupon: nextCode } : {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['quote', 'couponCode'],
                onFinish: () => setChecking(false),
            },
        );
    };

    const clearCoupon = () => {
        setCode('');
        reprice('');
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
                            placeholder="e.g. LAUNCH20"
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
                        <div className="mb-4 flex flex-wrap items-center gap-2">
                            <Chip
                                active={method === 'airtel_money'}
                                onClick={() =>
                                    handleMethodChange('airtel_money')
                                }
                            >
                                <Phone className="h-3.5 w-3.5" />
                                Airtel Money
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

                        <form onSubmit={submit} className="flex flex-col gap-4">
                            {method === 'airtel_money' ? (
                                <>
                                    <FormField
                                        label="Airtel Money number"
                                        htmlFor="phone_number"
                                        required
                                    >
                                        <input
                                            id="phone_number"
                                            type="tel"
                                            placeholder="e.g. 097XXXXXXX"
                                            value={data.phone_number}
                                            onChange={(e) =>
                                                setData(
                                                    'phone_number',
                                                    e.target.value,
                                                )
                                            }
                                            className={fieldInputClass}
                                        />
                                        {errors.phone_number ? (
                                            <p className="text-xs text-destructive">
                                                {errors.phone_number}
                                            </p>
                                        ) : null}
                                    </FormField>

                                    <FormField
                                        label="Transaction reference"
                                        htmlFor="payment_reference"
                                    >
                                        <input
                                            id="payment_reference"
                                            type="text"
                                            placeholder="Enter a reference if you have already paid"
                                            value={data.payment_reference}
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
                                                {errors.payment_reference}
                                            </p>
                                        ) : null}
                                    </FormField>

                                    <SoftTile className="text-sm">
                                        <div className="text-xs font-semibold">
                                            How to pay with Airtel Money
                                        </div>
                                        <ol className="mt-2 list-inside list-decimal space-y-1 text-xs text-muted-foreground">
                                            <li>
                                                Dial *778# on your Airtel phone
                                            </li>
                                            <li>
                                                Select &quot;Send Money&quot;
                                            </li>
                                            <li>
                                                Enter the payment number
                                                provided
                                            </li>
                                            <li>Enter amount: {amount}</li>
                                            <li>Confirm and enter your PIN</li>
                                        </ol>
                                    </SoftTile>
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
                                            placeholder="Enter your payment reference"
                                            value={data.payment_reference}
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
                                                {errors.payment_reference}
                                            </p>
                                        ) : null}
                                    </FormField>

                                    <FormField
                                        label="Proof of payment"
                                        htmlFor="pop_file"
                                        required
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
                                </>
                            )}

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
                )}
            </Panel>
        </GateShell>
    );
}
