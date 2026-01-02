import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { router } from '@inertiajs/react';
import { ChevronsUpDown } from 'lucide-react';
import { useEffect, useState } from 'react';

interface Company {
    id: number;
    name: string;
}

export function CompanySwitcher() {
    const [companies, setCompanies] = useState<Company[]>([]);
    const [activeCompanyId, setActiveCompanyId] = useState<number | null>(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        fetch('/companies')
            .then((res) => res.json())
            .then((data) => {
                setCompanies(data.companies);
                setActiveCompanyId(data.active_company_id);
                setLoading(false);
            });
    }, []);

    const handleSwitch = (companyId: number) => {
        if (companyId === activeCompanyId) return;
        router.post(
            '/companies/switch',
            { company_id: companyId },
            {
                preserveScroll: true,
                onSuccess: () => setActiveCompanyId(companyId),
            },
        );
    };

    if (loading || companies.length === 0) return null;

    const activeCompany = companies.find((c) => c.id === activeCompanyId);

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button className="flex w-full items-center gap-2 rounded px-3 py-2 hover:bg-sidebar-accent/30">
                    <span className="truncate font-medium">
                        {activeCompany?.name ?? 'Select Company'}
                    </span>
                    <ChevronsUpDown className="ml-auto size-4" />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" className="min-w-48">
                {companies.map((company) => (
                    <DropdownMenuItem
                        key={company.id}
                        onSelect={() => handleSwitch(company.id)}
                        className={
                            company.id === activeCompanyId
                                ? 'bg-muted font-bold'
                                : ''
                        }
                    >
                        {company.name}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
