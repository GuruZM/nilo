import { router, usePage } from '@inertiajs/react';
import { Building2, Check, ChevronDown, Coins } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

import { Breadcrumbs } from '@/components/breadcrumbs';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem as BreadcrumbItemType } from '@/types/index.d';

type Company = {
    id: number;
    name: string;
};

type Currency = {
    code: string;
    name: string;
    symbol?: string | null;
};

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const page = usePage() as any;

    const companies: Company[] =
        page.props?.companies?.all ?? page.props?.companies ?? [];

    const active_company_id: number | null =
        page.props?.companies?.current?.id ??
        page.props?.active_company_id ??
        null;

    const activeCompany = React.useMemo(
        () => companies.find((c) => c.id === active_company_id) ?? null,
        [companies, active_company_id],
    );

    const handleCompanySwitch = (companyId: number) => {
        router.post(
            '/companies/switch',
            { company_id: companyId },
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

    /* ---------------- Currencies ---------------- */
    const currencies: Currency[] = page.props?.currencies?.all ?? [];
    const activeCurrency: Currency | null =
        page.props?.currencies?.current ?? null;

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
        <header className="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/50 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex w-full items-center justify-between gap-3">
                {/* Left */}
                <div className="flex min-w-0 items-center gap-2">
                    <SidebarTrigger className="-ml-1" />
                    <div className="min-w-0">
                        <Breadcrumbs breadcrumbs={breadcrumbs} />
                    </div>
                </div>

                {/* Right: Company + Currency */}
                <div className="flex items-center gap-2">
                    {/* Currency Switcher */}
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="outline"
                                size="sm"
                                className={cn(
                                    'gap-2 rounded-xl',
                                    'transition-transform hover:scale-[1.02] active:scale-[0.98]',
                                )}
                                disabled={currencies.length <= 1}
                            >
                                <Coins className="h-4 w-4" />
                                <span className="max-w-[120px] truncate">
                                    {activeCurrency
                                        ? `${activeCurrency.code}${
                                              activeCurrency.symbol
                                                  ? ` (${activeCurrency.symbol})`
                                                  : ''
                                          }`
                                        : 'Currency'}
                                </span>
                                <ChevronDown className="h-4 w-4 opacity-70" />
                            </Button>
                        </DropdownMenuTrigger>

                        <DropdownMenuContent
                            align="end"
                            className="w-56 rounded-xl"
                        >
                            <DropdownMenuLabel className="text-xs text-muted-foreground">
                                Active currency
                            </DropdownMenuLabel>
                            <DropdownMenuSeparator />

                            {currencies.map((c) => {
                                const isActive =
                                    c.code === activeCurrency?.code;

                                return (
                                    <DropdownMenuItem
                                        key={c.code}
                                        onClick={() =>
                                            handleCurrencySwitch(c.code)
                                        }
                                        className={cn(
                                            'flex items-center justify-between rounded-lg',
                                            isActive && 'bg-muted',
                                        )}
                                    >
                                        <span className="truncate">
                                            {c.code} — {c.name}
                                        </span>
                                        {isActive && (
                                            <Check className="h-4 w-4 opacity-80" />
                                        )}
                                    </DropdownMenuItem>
                                );
                            })}
                        </DropdownMenuContent>
                    </DropdownMenu>

                    {/* Company Switcher */}
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="outline"
                                size="sm"
                                className={cn(
                                    'gap-2 rounded-xl',
                                    'transition-transform hover:scale-[1.02] active:scale-[0.98]',
                                )}
                                disabled={companies.length <= 1}
                            >
                                <Building2 className="h-4 w-4" />
                                <span className="max-w-[180px] truncate">
                                    {activeCompany?.name ?? 'Select company'}
                                </span>
                                <ChevronDown className="h-4 w-4 opacity-70" />
                            </Button>
                        </DropdownMenuTrigger>

                        <DropdownMenuContent
                            align="end"
                            className="w-64 rounded-xl"
                        >
                            <DropdownMenuLabel className="text-xs text-muted-foreground">
                                Switch company
                            </DropdownMenuLabel>
                            <DropdownMenuSeparator />

                            {companies.length === 0 ? (
                                <div className="px-3 py-2 text-sm text-muted-foreground">
                                    No companies available.
                                </div>
                            ) : (
                                companies.map((c) => {
                                    const isActive = c.id === active_company_id;

                                    return (
                                        <DropdownMenuItem
                                            key={c.id}
                                            onClick={() =>
                                                handleCompanySwitch(c.id)
                                            }
                                            className={cn(
                                                'flex items-center justify-between rounded-lg',
                                                isActive && 'bg-muted',
                                            )}
                                        >
                                            <span className="truncate">
                                                {c.name}
                                            </span>
                                            {isActive && (
                                                <Check className="h-4 w-4 opacity-80" />
                                            )}
                                        </DropdownMenuItem>
                                    );
                                })
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>
        </header>
    );
}
