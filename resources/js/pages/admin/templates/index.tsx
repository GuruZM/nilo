import { Chip, Panel, PanelHeader } from '@/components/dashboard/primitives';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { LayoutTemplate } from 'lucide-react';
import { toast } from 'sonner';

interface CompanyOption {
    id: number;
    name: string;
}

interface PresetRow {
    id: string;
    name: string;
    is_fallback: boolean;
    owner: CompanyOption | null;
    other_companies_using: number;
}

interface TemplatesIndexProps {
    presets: PresetRow[];
    fallback: string;
    companies: CompanyOption[];
}

/** Radix Select cannot hold an empty value, so "nobody owns it" needs a name. */
const EVERYONE = 'everyone';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Templates', href: '/admin/templates' },
];

const usageNote = (preset: PresetRow, fallback: string): string => {
    const companies =
        preset.other_companies_using === 1
            ? '1 company'
            : `${preset.other_companies_using} companies`;

    if (!preset.owner) {
        return preset.other_companies_using === 0
            ? 'Not used yet'
            : `Used by ${companies}`;
    }

    return preset.other_companies_using === 0
        ? `Only ${preset.owner.name}`
        : `${companies} besides the owner print on ${fallback} instead`;
};

export default function TemplatesIndex({
    presets,
    fallback,
    companies,
}: TemplatesIndexProps) {
    const assign = (preset: PresetRow, value: string) => {
        router.put(
            `/admin/templates/${preset.id}`,
            { company_id: value === EVERYONE ? null : Number(value) },
            {
                preserveScroll: true,
                onError: (errors) => {
                    if (errors.company_id) {
                        toast.error(errors.company_id);
                    }
                },
            },
        );
    };

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin - Templates" />

            <div className="mx-auto flex w-full flex-col gap-4 py-6">
                <Panel>
                    <PanelHeader
                        icon={LayoutTemplate}
                        title="Template designs"
                        subtitle={`Give a design to one company and no other company can build on it. Their existing templates print on ${fallback}.`}
                    />

                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-left text-xs text-muted-foreground">
                                    <th className="py-2 pr-3 font-medium">
                                        Design
                                    </th>
                                    <th className="py-2 pr-3 font-medium">
                                        Belongs to
                                    </th>
                                    <th className="py-2 font-medium">In use</th>
                                </tr>
                            </thead>
                            <tbody>
                                {presets.map((preset) => (
                                    <tr
                                        key={preset.id}
                                        className="border-t border-border/60"
                                    >
                                        <td className="py-3 pr-3">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="font-semibold">
                                                    {preset.name}
                                                </span>
                                                {preset.owner ? (
                                                    <Chip
                                                        interactive={false}
                                                        active
                                                    >
                                                        Proprietary
                                                    </Chip>
                                                ) : null}
                                                {preset.is_fallback ? (
                                                    <Chip interactive={false}>
                                                        Fallback
                                                    </Chip>
                                                ) : null}
                                            </div>
                                        </td>
                                        <td className="py-3 pr-3">
                                            <Select
                                                value={
                                                    preset.owner
                                                        ? String(
                                                              preset.owner.id,
                                                          )
                                                        : EVERYONE
                                                }
                                                onValueChange={(value) =>
                                                    assign(preset, value)
                                                }
                                                disabled={preset.is_fallback}
                                            >
                                                <SelectTrigger className="w-full min-w-48 rounded-xl sm:w-64">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem
                                                        value={EVERYONE}
                                                    >
                                                        Everyone
                                                    </SelectItem>
                                                    {companies.map(
                                                        (company) => (
                                                            <SelectItem
                                                                key={company.id}
                                                                value={String(
                                                                    company.id,
                                                                )}
                                                            >
                                                                {company.name}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                        </td>
                                        <td className="py-3 text-muted-foreground">
                                            {usageNote(preset, fallback)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Panel>
            </div>
        </AppSidebarLayout>
    );
}
