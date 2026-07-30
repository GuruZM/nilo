import { router, usePage } from '@inertiajs/react';
import { Building2, Check, ChevronDown, Coins } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

import { FloatingMenu, menuRowClass } from '@/components/floating-menu';
import { useSidebar } from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';

type Company = {
    id: number;
    name: string;
};

type Currency = {
    code: string;
    name: string;
    symbol?: string | null;
};

type WorkspacePageProps = {
    companies?: Company[] | { all?: Company[]; current?: Company | null };
    active_company_id?: number | null;
    currencies?: { all?: Currency[]; current?: Currency | null };
};

type SwitcherOption = {
    key: string;
    label: string;
    hint?: string;
};

/**
 * Hand-rolled dropdown so the workspace pickers can carry the same soft,
 * borderless language as the nav rail instead of the stock menu chrome.
 */
function Switcher({
    icon: Icon,
    caption,
    value,
    options,
    activeKey,
    emptyLabel,
    onSelect,
    collapsed,
}: {
    icon: React.ComponentType<{ className?: string }>;
    caption: string;
    value: string;
    options: SwitcherOption[];
    activeKey: string | null;
    emptyLabel: string;
    onSelect: (key: string) => void;
    collapsed: boolean;
}) {
    const isDisabled = options.length <= 1;

    return (
        <FloatingMenu
            role="listbox"
            placement={collapsed ? 'right-start' : 'bottom-stretch'}
            panelClassName={collapsed ? 'w-60' : undefined}
            trigger={({ open, toggle }) => (
                <button
                    type="button"
                    title={collapsed ? `${caption}: ${value}` : undefined}
                    aria-haspopup="listbox"
                    aria-expanded={open}
                    disabled={isDisabled}
                    onClick={toggle}
                    className={cn(
                        'group/switcher flex w-full items-center gap-3 rounded-2xl text-left transition',
                        'focus-visible:ring-2 focus-visible:ring-brand-400 focus-visible:outline-none',
                        collapsed ? 'justify-center p-2' : 'px-3 py-2.5',
                        isDisabled
                            ? 'cursor-default opacity-60'
                            : 'hover:bg-muted dark:hover:bg-white/5',
                        open && 'bg-muted dark:bg-white/5',
                    )}
                >
                    <span
                        className={cn(
                            'flex h-8 w-8 shrink-0 items-center justify-center rounded-xl transition',
                            open
                                ? 'bg-brand-100 text-brand-700 dark:bg-brand-500/20 dark:text-brand-200'
                                : 'bg-muted text-muted-foreground group-hover/switcher:text-foreground dark:bg-white/5',
                        )}
                    >
                        <Icon className="h-4 w-4" />
                    </span>

                    {!collapsed && (
                        <>
                            <span className="min-w-0 flex-1">
                                <span className="block text-[10px] font-semibold tracking-[0.08em] text-muted-foreground uppercase">
                                    {caption}
                                </span>
                                <span className="block truncate text-[13px] font-medium text-foreground">
                                    {value}
                                </span>
                            </span>
                            <ChevronDown
                                className={cn(
                                    'h-4 w-4 shrink-0 text-muted-foreground transition-transform duration-200',
                                    open && 'rotate-180',
                                )}
                            />
                        </>
                    )}
                </button>
            )}
        >
            {(close) =>
                options.length === 0 ? (
                    <p className="px-3 py-2.5 text-[13px] text-muted-foreground">
                        {emptyLabel}
                    </p>
                ) : (
                    <ul className="max-h-72 space-y-0.5 overflow-y-auto">
                        {options.map((option) => {
                            const isActive = option.key === activeKey;

                            return (
                                <li key={option.key}>
                                    <button
                                        type="button"
                                        role="option"
                                        aria-selected={isActive}
                                        onClick={() => {
                                            close();

                                            if (!isActive) {
                                                onSelect(option.key);
                                            }
                                        }}
                                        className={menuRowClass(isActive)}
                                    >
                                        <span className="min-w-0 flex-1 truncate">
                                            {option.label}
                                            {option.hint ? (
                                                <span className="ml-1 text-muted-foreground">
                                                    {option.hint}
                                                </span>
                                            ) : null}
                                        </span>
                                        {isActive && (
                                            <Check className="h-4 w-4 shrink-0" />
                                        )}
                                    </button>
                                </li>
                            );
                        })}
                    </ul>
                )
            }
        </FloatingMenu>
    );
}

export function WorkspaceSwitchers({ className }: { className?: string }) {
    const props = usePage().props as unknown as WorkspacePageProps;
    const { state, isMobile } = useSidebar();
    const collapsed = state === 'collapsed' && !isMobile;

    const companyProp = props.companies;

    const companies = React.useMemo<Company[]>(
        () =>
            Array.isArray(companyProp) ? companyProp : (companyProp?.all ?? []),
        [companyProp],
    );

    const activeCompanyId: number | null =
        (Array.isArray(companyProp)
            ? null
            : (companyProp?.current?.id ?? null)) ??
        props.active_company_id ??
        null;

    const activeCompany = React.useMemo(
        () =>
            companies.find((company) => company.id === activeCompanyId) ?? null,
        [companies, activeCompanyId],
    );

    const currencies: Currency[] = props.currencies?.all ?? [];
    const activeCurrency: Currency | null = props.currencies?.current ?? null;

    const handleCompanySwitch = (companyId: string) => {
        router.post(
            '/companies/switch',
            { company_id: Number(companyId) },
            {
                preserveScroll: true,
                onSuccess: () => toast.success('Company switched.'),
                onError: (errors) =>
                    toast.error(
                        errors?.company_id || 'Failed to switch company.',
                    ),
            },
        );
    };

    const handleCurrencySwitch = (currencyCode: string) => {
        router.post(
            '/currencies/switch',
            { currency_code: currencyCode },
            {
                preserveScroll: true,
                onSuccess: () => toast.success('Currency switched.'),
                onError: (errors) =>
                    toast.error(
                        errors?.currency_code || 'Failed to switch currency.',
                    ),
            },
        );
    };

    return (
        <div className={cn('flex flex-col gap-1', className)}>
            {!collapsed && (
                <div className="px-3 pt-1 pb-2 text-[11px] font-semibold tracking-[0.08em] text-muted-foreground uppercase">
                    Workspace
                </div>
            )}

            <Switcher
                icon={Building2}
                caption="Company"
                value={activeCompany?.name ?? 'Select company'}
                activeKey={
                    activeCompanyId !== null ? String(activeCompanyId) : null
                }
                emptyLabel="No companies available."
                onSelect={handleCompanySwitch}
                collapsed={collapsed}
                options={companies.map((company) => ({
                    key: String(company.id),
                    label: company.name,
                }))}
            />

            <Switcher
                icon={Coins}
                caption="Currency"
                value={
                    activeCurrency
                        ? `${activeCurrency.code}${activeCurrency.symbol ? ` (${activeCurrency.symbol})` : ''}`
                        : 'Select currency'
                }
                activeKey={activeCurrency?.code ?? null}
                emptyLabel="No currencies available."
                onSelect={handleCurrencySwitch}
                collapsed={collapsed}
                options={currencies.map((currency) => ({
                    key: currency.code,
                    label: currency.code,
                    hint: currency.name,
                }))}
            />
        </div>
    );
}
