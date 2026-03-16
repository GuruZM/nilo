// resources/js/Pages/Settings/InvoiceTemplates/Builder.tsx
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import type { LucideIcon } from 'lucide-react';
import {
    ArrowLeft,
    Check,
    CheckCircle2,
    Eye,
    LayoutTemplate,
    Palette,
    Save,
    Settings2,
    Sparkles,
    Type,
} from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types/index.d';

// shadcn
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';

import { cn } from '@/lib/utils';

type Mode = 'create' | 'edit';

type TemplateSettings = {
    preset:
        | 'modern_minimal'
        | 'classic_business'
        | 'bold_header'
        | 'wave_premium';
    brand: {
        primary: string;
        accent: string;
        font: 'Inter' | 'Roboto' | 'Arial';
    };
    layout: {
        header: 'split' | 'left' | 'center';
        table: 'striped' | 'lined' | 'clean';
        density: 'compact' | 'normal' | 'airy';
    };
    visibility: {
        show_logo: boolean;
        show_client_email: boolean;
        show_contact_person: boolean;
        show_terms: boolean;
        show_notes: boolean;
        show_bank_details: boolean;
        show_signature: boolean;
    };
};

type TemplatePayload = {
    id?: number;
    name: string;
    is_default: boolean;
    settings: TemplateSettings;
    terms_html?: string | null;
    footer_html?: string | null;
};

type TemplateModule = {
    type: 'invoice' | 'quotation';
    singularTitle: string;
    pluralTitle: string;
    basePath: string;
    createPath: string;
};

type BuilderStep = 'preset' | 'style' | 'layout' | 'content' | 'review';

type FlashBag = {
    success?: string | null;
    error?: string | null;
    info?: string | null;
};

type BuilderPageProps = {
    flash?: FlashBag;
};

type FormData = {
    name: string;
    is_default: boolean;
    settings: TemplateSettings;
    terms_html: string;
    footer_html: string;
};

const STEPS: Array<{ key: BuilderStep; title: string; icon: LucideIcon }> = [
    { key: 'preset', title: 'Preset', icon: Sparkles },
    { key: 'style', title: 'Style', icon: Palette },
    { key: 'layout', title: 'Layout', icon: Settings2 },
    { key: 'content', title: 'Content', icon: Type },
    { key: 'review', title: 'Review', icon: Eye },
];

const PRESETS: Array<{
    id: TemplateSettings['preset'];
    name: string;
    description: string;
    defaults: Partial<TemplateSettings>;
}> = [
    {
        id: 'wave_premium',
        name: 'Wave Premium',
        description: 'Bold header with wave + accent table + grand total.',
        defaults: {
            brand: { primary: '#111827', accent: '#F59E0B', font: 'Inter' },
            layout: { header: 'split', table: 'striped', density: 'normal' },
        },
    },
    {
        id: 'modern_minimal',
        name: 'Modern Minimal',
        description: 'Clean, modern look. Great default.',
        defaults: {
            brand: { primary: '#0F172A', accent: '#22C55E', font: 'Inter' },
            layout: { header: 'split', table: 'striped', density: 'normal' },
        },
    },
    {
        id: 'classic_business',
        name: 'Classic Business',
        description: 'Traditional layout, lined table, corporate safe.',
        defaults: {
            brand: { primary: '#111827', accent: '#2563EB', font: 'Arial' },
            layout: { header: 'left', table: 'lined', density: 'normal' },
        },
    },
    {
        id: 'bold_header',
        name: 'Bold Header',
        description: 'Strong title + airy spacing for premium brands.',
        defaults: {
            brand: { primary: '#111827', accent: '#F97316', font: 'Inter' },
            layout: { header: 'center', table: 'clean', density: 'airy' },
        },
    },
];

const DEFAULT_SETTINGS: TemplateSettings = {
    preset: 'wave_premium',
    brand: { primary: '#111827', accent: '#F59E0B', font: 'Inter' },
    layout: { header: 'split', table: 'striped', density: 'normal' },
    visibility: {
        show_logo: true,
        show_client_email: true,
        show_contact_person: true,
        show_terms: true,
        show_notes: true,
        show_bank_details: false,
        show_signature: false,
    },
};

// sample preview data (updated to include bank + signature)
const SAMPLE = {
    company: {
        name: 'Nilo Labs Ltd',
        address: 'Lusaka, Zambia',
        phone: '+260 97X XXX XXX',
        email: 'billing@nilo.ai',
        tpin: 'TPIN: 1234567890',
        bank: {
            name: 'Zanaco',
            account_name: 'Nilo Labs Ltd',
            account_number: '00123456789',
            branch: 'Cairo Road',
        },
    },
    client: {
        name: 'Resonant Technologies',
        email: 'accounts@resonant.tech',
        contact_person: 'Charles Ngalasa',
        address: 'Ibex Hill, Lusaka',
    },
    document: {
        issue_date: '2026-01-03',
        due_date: '2026-01-10',
        notes: 'Thank you for your business. Kindly settle within due date.',
        items: [
            { desc: 'Prime POS Setup & Configuration', qty: 1, price: 2500 },
            { desc: 'ZRA Smart Tax Integration', qty: 1, price: 1800 },
            { desc: 'Training Session (2 hours)', qty: 1, price: 650 },
        ],
        signature: {
            name: 'Thomas Daney',
            title: 'Accounting Manager',
            label: 'Signature',
        },
    },
};

export default function InvoiceTemplateBuilder({
    mode,
    template,
    module,
}: {
    mode: Mode;
    template: TemplatePayload | null;
    module: TemplateModule;
}) {
    const page = usePage<BuilderPageProps>();
    const singularLabel = module.singularTitle.toLowerCase();
    const documentTitle = module.type === 'quotation' ? 'QUOTATION' : 'INVOICE';
    const documentNumberLabel =
        module.type === 'quotation' ? 'Quote No' : 'Invoice No';
    const documentNumberValue =
        module.type === 'quotation' ? 'QUO-000123' : 'INV-000123';
    const sampleLabel =
        module.type === 'quotation' ? 'A4 preview (sample quotation)' : 'A4 preview (sample invoice)';

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Settings', href: '/settings/profile' },
        { title: module.pluralTitle, href: module.basePath },
        { title: 'Builder', href: module.createPath },
    ];

    React.useEffect(() => {
        const s = page.props.flash?.success;
        const e = page.props.flash?.error;
        const i = page.props.flash?.info;
        if (s) toast.success(s);
        if (e) toast.error(e);
        if (i) toast.message(i);
    }, [
        page.props.flash?.success,
        page.props.flash?.error,
        page.props.flash?.info,
    ]);

    const [step, setStep] = React.useState<BuilderStep>('preset');

    const initialSettings: TemplateSettings = React.useMemo(() => {
        const s = template?.settings ?? null;
        if (!s) return DEFAULT_SETTINGS;

        return {
            ...DEFAULT_SETTINGS,
            ...s,
            brand: { ...DEFAULT_SETTINGS.brand, ...(s.brand ?? {}) },
            layout: { ...DEFAULT_SETTINGS.layout, ...(s.layout ?? {}) },
            visibility: {
                ...DEFAULT_SETTINGS.visibility,
                ...(s.visibility ?? {}),
            },
        };
    }, [template]);

    const form = useForm<FormData>({
        name: template?.name ?? '',
        is_default: template?.is_default ?? false,
        settings: initialSettings,
        terms_html:
            template?.terms_html ??
            (module.type === 'quotation'
                ? 'Quotation valid for 7 days.'
                : 'Payment due within 7 days.'),
        footer_html: template?.footer_html ?? 'Powered by Nilo',
    });

    const settings = form.data.settings;

    const applyPreset = (presetId: TemplateSettings['preset']) => {
        const preset = PRESETS.find((p) => p.id === presetId);

        const next: TemplateSettings = {
            ...DEFAULT_SETTINGS,
            ...settings,
            preset: presetId,
            brand: {
                ...DEFAULT_SETTINGS.brand,
                ...(preset?.defaults.brand ?? {}),
                ...settings.brand,
            },
            layout: {
                ...DEFAULT_SETTINGS.layout,
                ...(preset?.defaults.layout ?? {}),
                ...settings.layout,
            },
            visibility: {
                ...DEFAULT_SETTINGS.visibility,
                ...settings.visibility,
            },
        };

        form.setData('settings', next);
    };

    const setBrand = <K extends keyof TemplateSettings['brand']>(
        key: K,
        v: TemplateSettings['brand'][K],
    ) => {
        form.setData('settings', {
            ...settings,
            brand: { ...settings.brand, [key]: v },
        });
    };

    const setLayout = <K extends keyof TemplateSettings['layout']>(
        key: K,
        v: TemplateSettings['layout'][K],
    ) => {
        form.setData('settings', {
            ...settings,
            layout: { ...settings.layout, [key]: v },
        });
    };

    const setVisibility = <K extends keyof TemplateSettings['visibility']>(
        key: K,
        v: boolean,
    ) => {
        form.setData('settings', {
            ...settings,
            visibility: { ...settings.visibility, [key]: v },
        });
    };

    const order: BuilderStep[] = [
        'preset',
        'style',
        'layout',
        'content',
        'review',
    ];

    const validateStep = (s: BuilderStep) => {
        if (s === 'preset' && !form.data.name.trim()) {
            toast.error('Template name is required.');
            return false;
        }
        if (
            s === 'style' &&
            (!settings.brand.primary || !settings.brand.accent)
        ) {
            toast.error('Pick primary and accent colors.');
            return false;
        }
        return true;
    };

    const goNext = () => {
        if (!validateStep(step)) return;
        setStep(order[Math.min(order.indexOf(step) + 1, order.length - 1)]);
    };

    const goBack = () => {
        setStep(order[Math.max(order.indexOf(step) - 1, 0)]);
    };

    const save = () => {
        if (!form.data.name.trim()) {
            toast.error('Template name is required.');
            setStep('preset');
            return;
        }

        const payload = {
            name: form.data.name,
            is_default: !!form.data.is_default,
            settings: form.data.settings,
            terms_html: form.data.terms_html,
            footer_html: form.data.footer_html,
        };

        const onError = (errors: any) => {
            toast.error(
                errors?.template ||
                    errors?.name ||
                    errors?.settings ||
                    'Failed to save template.',
            );
        };

        if (mode === 'create') {
            router.post(module.basePath, payload, {
                preserveScroll: true,
                preserveState: false,
                onSuccess: () => toast.success(`${module.singularTitle} created.`),
                onError,
            });
        } else {
            router.put(`${module.basePath}/${template?.id}`, payload, {
                preserveScroll: true,
                preserveState: false,
                onSuccess: () => toast.success(`${module.singularTitle} updated.`),
                onError,
            });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${module.singularTitle} builder`}>
                {/* Optional: ensure Inter/Roboto actually exist */}
                <link rel="preconnect" href="https://fonts.googleapis.com" />
                <link
                    rel="preconnect"
                    href="https://fonts.gstatic.com"
                    crossOrigin=""
                />
                <link
                    href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Roboto:wght@400;500;700&display=swap"
                    rel="stylesheet"
                />
            </Head>

            <SettingsLayout>
                <motion.div
                    initial={{ opacity: 0, y: 14 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ duration: 0.25, ease: 'easeOut' }}
                    className="space-y-6"
                >
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div className="flex items-start gap-3">
                            <div className="grid h-11 w-11 place-items-center rounded-2xl bg-muted">
                                <LayoutTemplate className="h-6 w-6 opacity-80" />
                            </div>
                            <div>
                                <div className="flex items-center gap-2">
                                    <h1 className="text-2xl font-semibold tracking-tight">
                                        {module.singularTitle} builder
                                    </h1>
                                    <Badge
                                        variant="secondary"
                                        className="rounded-full"
                                    >
                                        {mode === 'create' ? 'Create' : 'Edit'}
                                    </Badge>
                                </div>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Premium presets + styling + layout controls
                                    with a designer-grade live preview.
                                </p>
                            </div>
                        </div>

                        <div className="flex items-center gap-2">
                            <Button
                                variant="outline"
                                className="gap-2 rounded-xl"
                                onClick={() => router.visit(module.basePath)}
                            >
                                <ArrowLeft className="h-4 w-4" />
                                Back
                            </Button>

                            <Button
                                className="gap-2 rounded-xl transition-transform hover:scale-[1.02] active:scale-[0.98]"
                                onClick={save}
                                disabled={form.processing}
                            >
                                <Save className="h-4 w-4" />
                                {mode === 'create'
                                    ? 'Save template'
                                    : 'Update template'}
                            </Button>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-12">
                        {/* Left: Controls */}
                        <Card className="flex flex-col rounded-2xl border bg-background p-6 shadow-sm lg:col-span-5">
                            <Stepper step={step} onStep={setStep} />

                            <Separator className="my-4" />

                            <div className="flex-1">
                                <AnimatePresence mode="wait">
                                    {step === 'preset' && (
                                        <motion.div
                                            key="preset"
                                            initial={{ opacity: 0, y: 10 }}
                                            animate={{ opacity: 1, y: 0 }}
                                            exit={{ opacity: 0, y: 10 }}
                                            transition={{ duration: 0.2 }}
                                            className="space-y-4"
                                        >
                                            <div className="space-y-2">
                                                <Label>Template name *</Label>
                                                <Input
                                                    value={form.data.name}
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'name',
                                                            e.target.value,
                                                        )
                                                    }
                                                    className="rounded-xl"
                                                    placeholder={`e.g. Wave Premium (${module.type === 'quotation' ? 'Quotation' : 'Invoice'})`}
                                                />
                                                {form.errors.name && (
                                                    <p className="text-sm text-destructive">
                                                        {form.errors.name}
                                                    </p>
                                                )}
                                            </div>

                                            <div className="flex items-center justify-between rounded-xl border p-4">
                                                <div>
                                                    <div className="text-sm font-semibold">
                                                        Default template
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        Make this the default{' '}
                                                        {singularLabel} for
                                                        this company.
                                                    </div>
                                                </div>
                                                <Switch
                                                    checked={
                                                        !!form.data.is_default
                                                    }
                                                    onCheckedChange={(v) =>
                                                        form.setData(
                                                            'is_default',
                                                            !!v,
                                                        )
                                                    }
                                                />
                                            </div>

                                            <div className="space-y-2">
                                                <Label>Preset</Label>
                                                <div className="grid grid-cols-1 gap-3">
                                                    {PRESETS.map((p) => {
                                                        const selected =
                                                            settings.preset ===
                                                            p.id;
                                                        return (
                                                            <button
                                                                type="button"
                                                                key={p.id}
                                                                onClick={() =>
                                                                    applyPreset(
                                                                        p.id,
                                                                    )
                                                                }
                                                                className={cn(
                                                                    'w-full rounded-2xl border p-4 text-left transition',
                                                                    'hover:bg-muted/30',
                                                                    selected &&
                                                                        'border-foreground/30 bg-muted/40',
                                                                )}
                                                            >
                                                                <div className="flex items-start justify-between gap-2">
                                                                    <div>
                                                                        <div className="text-sm font-semibold">
                                                                            {
                                                                                p.name
                                                                            }
                                                                        </div>
                                                                        <div className="mt-1 text-xs text-muted-foreground">
                                                                            {
                                                                                p.description
                                                                            }
                                                                        </div>
                                                                    </div>
                                                                    {selected && (
                                                                        <Check className="h-4 w-4 opacity-80" />
                                                                    )}
                                                                </div>
                                                            </button>
                                                        );
                                                    })}
                                                </div>
                                            </div>
                                        </motion.div>
                                    )}

                                    {step === 'style' && (
                                        <motion.div
                                            key="style"
                                            initial={{ opacity: 0, y: 10 }}
                                            animate={{ opacity: 1, y: 0 }}
                                            exit={{ opacity: 0, y: 10 }}
                                            transition={{ duration: 0.2 }}
                                            className="space-y-4"
                                        >
                                            <div className="grid grid-cols-2 gap-3">
                                                <div className="space-y-2">
                                                    <Label>Primary color</Label>
                                                    <Input
                                                        type="color"
                                                        value={
                                                            settings.brand
                                                                .primary
                                                        }
                                                        onChange={(e) =>
                                                            setBrand(
                                                                'primary',
                                                                e.target.value,
                                                            )
                                                        }
                                                        className="h-10 rounded-xl p-1"
                                                    />
                                                </div>
                                                <div className="space-y-2">
                                                    <Label>Accent color</Label>
                                                    <Input
                                                        type="color"
                                                        value={
                                                            settings.brand
                                                                .accent
                                                        }
                                                        onChange={(e) =>
                                                            setBrand(
                                                                'accent',
                                                                e.target.value,
                                                            )
                                                        }
                                                        className="h-10 rounded-xl p-1"
                                                    />
                                                </div>
                                            </div>

                                            <div className="space-y-2">
                                                <Label>Font</Label>
                                                <Select
                                                    value={settings.brand.font}
                                                    onValueChange={(v: any) =>
                                                        setBrand('font', v)
                                                    }
                                                >
                                                    <SelectTrigger className="rounded-xl">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="Inter">
                                                            Inter
                                                        </SelectItem>
                                                        <SelectItem value="Roboto">
                                                            Roboto
                                                        </SelectItem>
                                                        <SelectItem value="Arial">
                                                            Arial
                                                        </SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>

                                            <div className="space-y-2">
                                                <Label>Density</Label>
                                                <Select
                                                    value={
                                                        settings.layout.density
                                                    }
                                                    onValueChange={(v: any) =>
                                                        setLayout('density', v)
                                                    }
                                                >
                                                    <SelectTrigger className="rounded-xl">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="compact">
                                                            Compact
                                                        </SelectItem>
                                                        <SelectItem value="normal">
                                                            Normal
                                                        </SelectItem>
                                                        <SelectItem value="airy">
                                                            Airy
                                                        </SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                        </motion.div>
                                    )}

                                    {step === 'layout' && (
                                        <motion.div
                                            key="layout"
                                            initial={{ opacity: 0, y: 10 }}
                                            animate={{ opacity: 1, y: 0 }}
                                            exit={{ opacity: 0, y: 10 }}
                                            transition={{ duration: 0.2 }}
                                            className="space-y-4"
                                        >
                                            <div className="space-y-2">
                                                <Label>Header layout</Label>
                                                <Select
                                                    value={
                                                        settings.layout.header
                                                    }
                                                    onValueChange={(v: any) =>
                                                        setLayout('header', v)
                                                    }
                                                >
                                                    <SelectTrigger className="rounded-xl">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="split">
                                                            Split (logo left,
                                                            meta right)
                                                        </SelectItem>
                                                        <SelectItem value="left">
                                                            Left aligned
                                                        </SelectItem>
                                                        <SelectItem value="center">
                                                            Centered
                                                        </SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>

                                            <div className="space-y-2">
                                                <Label>Table style</Label>
                                                <Select
                                                    value={
                                                        settings.layout.table
                                                    }
                                                    onValueChange={(v: any) =>
                                                        setLayout('table', v)
                                                    }
                                                >
                                                    <SelectTrigger className="rounded-xl">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="striped">
                                                            Striped
                                                        </SelectItem>
                                                        <SelectItem value="lined">
                                                            Lined
                                                        </SelectItem>
                                                        <SelectItem value="clean">
                                                            Clean
                                                        </SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>

                                            <Separator />

                                            <div className="space-y-3">
                                                <div className="text-sm font-semibold">
                                                    Visibility (layout)
                                                </div>

                                                <ToggleRow
                                                    label="Show logo"
                                                    checked={
                                                        settings.visibility
                                                            .show_logo
                                                    }
                                                    onChange={(v) =>
                                                        setVisibility(
                                                            'show_logo',
                                                            v,
                                                        )
                                                    }
                                                />
                                                <ToggleRow
                                                    label="Show client email"
                                                    checked={
                                                        settings.visibility
                                                            .show_client_email
                                                    }
                                                    onChange={(v) =>
                                                        setVisibility(
                                                            'show_client_email',
                                                            v,
                                                        )
                                                    }
                                                />
                                                <ToggleRow
                                                    label="Show contact person"
                                                    checked={
                                                        settings.visibility
                                                            .show_contact_person
                                                    }
                                                    onChange={(v) =>
                                                        setVisibility(
                                                            'show_contact_person',
                                                            v,
                                                        )
                                                    }
                                                />
                                            </div>
                                        </motion.div>
                                    )}

                                    {step === 'content' && (
                                        <motion.div
                                            key="content"
                                            initial={{ opacity: 0, y: 10 }}
                                            animate={{ opacity: 1, y: 0 }}
                                            exit={{ opacity: 0, y: 10 }}
                                            transition={{ duration: 0.2 }}
                                            className="space-y-4"
                                        >
                                            {/* Content toggles moved here */}
                                            <div className="space-y-3">
                                                <div className="text-sm font-semibold">
                                                    Content blocks
                                                </div>

                                                <ToggleRow
                                                    label="Show notes"
                                                    checked={
                                                        settings.visibility
                                                            .show_notes
                                                    }
                                                    onChange={(v) =>
                                                        setVisibility(
                                                            'show_notes',
                                                            v,
                                                        )
                                                    }
                                                />
                                                <ToggleRow
                                                    label="Show terms"
                                                    checked={
                                                        settings.visibility
                                                            .show_terms
                                                    }
                                                    onChange={(v) =>
                                                        setVisibility(
                                                            'show_terms',
                                                            v,
                                                        )
                                                    }
                                                />
                                                <ToggleRow
                                                    label="Show bank details"
                                                    checked={
                                                        settings.visibility
                                                            .show_bank_details
                                                    }
                                                    onChange={(v) =>
                                                        setVisibility(
                                                            'show_bank_details',
                                                            v,
                                                        )
                                                    }
                                                />
                                                <ToggleRow
                                                    label="Show signature"
                                                    checked={
                                                        settings.visibility
                                                            .show_signature
                                                    }
                                                    onChange={(v) =>
                                                        setVisibility(
                                                            'show_signature',
                                                            v,
                                                        )
                                                    }
                                                />
                                            </div>

                                            <Separator />

                                            <div className="space-y-2">
                                                <Label>Default terms</Label>
                                                <Textarea
                                                    value={form.data.terms_html}
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'terms_html',
                                                            e.target.value,
                                                        )
                                                    }
                                                    className="min-h-[120px] rounded-xl"
                                                    placeholder="Payment terms..."
                                                />
                                            </div>

                                            <div className="space-y-2">
                                                <Label>Footer text</Label>
                                                <Textarea
                                                    value={
                                                        form.data.footer_html
                                                    }
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'footer_html',
                                                            e.target.value,
                                                        )
                                                    }
                                                    className="min-h-[90px] rounded-xl"
                                                    placeholder="Footer..."
                                                />
                                            </div>
                                        </motion.div>
                                    )}

                                    {step === 'review' && (
                                        <motion.div
                                            key="review"
                                            initial={{ opacity: 0, y: 10 }}
                                            animate={{ opacity: 1, y: 0 }}
                                            exit={{ opacity: 0, y: 10 }}
                                            transition={{ duration: 0.2 }}
                                            className="space-y-4"
                                        >
                                            <div className="rounded-2xl border p-4">
                                                <div className="text-sm font-semibold">
                                                    Summary
                                                </div>

                                                <div className="mt-3 space-y-2 text-sm text-muted-foreground">
                                                    <div>
                                                        <span className="font-medium text-foreground">
                                                            Name:
                                                        </span>{' '}
                                                        {form.data.name || '—'}
                                                    </div>
                                                    <div>
                                                        <span className="font-medium text-foreground">
                                                            Preset:
                                                        </span>{' '}
                                                        {settings.preset}
                                                    </div>
                                                    <div>
                                                        <span className="font-medium text-foreground">
                                                            Font:
                                                        </span>{' '}
                                                        {settings.brand.font}
                                                    </div>
                                                    <div>
                                                        <span className="font-medium text-foreground">
                                                            Header:
                                                        </span>{' '}
                                                        {settings.layout.header}
                                                    </div>
                                                    <div>
                                                        <span className="font-medium text-foreground">
                                                            Table:
                                                        </span>{' '}
                                                        {settings.layout.table}
                                                    </div>
                                                    <div>
                                                        <span className="font-medium text-foreground">
                                                            Default:
                                                        </span>{' '}
                                                        {form.data.is_default
                                                            ? 'Yes'
                                                            : 'No'}
                                                    </div>
                                                </div>
                                            </div>

                                            <Button
                                                type="button"
                                                className="w-full gap-2 rounded-xl transition-transform hover:scale-[1.02] active:scale-[0.98]"
                                                onClick={save}
                                                disabled={form.processing}
                                            >
                                                <CheckCircle2 className="h-4 w-4" />
                                                {mode === 'create'
                                                    ? 'Save template'
                                                    : 'Update template'}
                                            </Button>

                                            {form.errors &&
                                                (form.errors as any)
                                                    .template && (
                                                    <p className="text-sm text-destructive">
                                                        {
                                                            (form.errors as any)
                                                                .template
                                                        }
                                                    </p>
                                                )}
                                        </motion.div>
                                    )}
                                </AnimatePresence>
                            </div>

                            <Separator className="my-4" />

                            <div className="flex items-center justify-between">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={goBack}
                                    disabled={
                                        step === 'preset' || form.processing
                                    }
                                    className="rounded-xl"
                                >
                                    Back
                                </Button>

                                {step !== 'review' ? (
                                    <Button
                                        type="button"
                                        onClick={goNext}
                                        disabled={form.processing}
                                        className="rounded-xl"
                                    >
                                        Next
                                    </Button>
                                ) : (
                                    <Button
                                        type="button"
                                        onClick={save}
                                        disabled={form.processing}
                                        className="rounded-xl"
                                    >
                                        Save
                                    </Button>
                                )}
                            </div>
                        </Card>

                        {/* Right: Live Preview */}
                        <Card className="flex flex-col rounded-2xl border bg-background p-6 shadow-sm lg:col-span-7">
                            <div className="mb-4 flex items-center justify-between">
                                <div className="flex items-center gap-2">
                                    <Eye className="h-5 w-5 opacity-80" />
                                    <div className="text-sm font-semibold">
                                        Live preview
                                    </div>
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    {sampleLabel}
                                </div>
                            </div>

                            <div className="flex-1">
                                <A4PreviewPremium
                                    documentNumberLabel={documentNumberLabel}
                                    documentNumberValue={documentNumberValue}
                                    documentTitle={documentTitle}
                                    documentType={module.type}
                                    settings={settings}
                                    terms={form.data.terms_html}
                                    footer={form.data.footer_html}
                                />
                            </div>
                        </Card>
                    </div>
                </motion.div>
            </SettingsLayout>
        </AppLayout>
    );
}

function Stepper({
    step,
    onStep,
}: {
    step: BuilderStep;
    onStep: (k: BuilderStep) => void;
}) {
    return (
        <div className="flex flex-wrap gap-2">
            {STEPS.map((s) => {
                const active = s.key === step;
                const Icon = s.icon;
                return (
                    <button
                        type="button"
                        key={s.key}
                        onClick={() => onStep(s.key)}
                        className={cn(
                            'flex items-center gap-2 rounded-xl border px-3 py-2 text-sm transition',
                            'hover:bg-muted/30',
                            active && 'bg-muted/50',
                        )}
                    >
                        <Icon className="h-4 w-4 opacity-80" />
                        {s.title}
                    </button>
                );
            })}
        </div>
    );
}

function ToggleRow({
    label,
    checked,
    onChange,
}: {
    label: string;
    checked: boolean;
    onChange: (v: boolean) => void;
}) {
    return (
        <div className="flex items-center justify-between rounded-xl border p-3">
            <div className="text-sm">{label}</div>
            <Switch checked={checked} onCheckedChange={(v) => onChange(!!v)} />
        </div>
    );
}

/**
 * Premium A4 preview inspired by the reference image:
 * - Dark hero header band
 * - Wave separator
 * - Accent table header + Grand Total bar
 * - Clean blocks for bill-to, notes, terms
 * - Added: bank + signature controlled by toggles
 * - Added: font family mapping (Inter/Roboto/Arial)
 */
function A4PreviewPremium({
    documentNumberLabel,
    documentNumberValue,
    documentTitle,
    documentType,
    settings,
    terms,
    footer,
}: {
    documentNumberLabel: string;
    documentNumberValue: string;
    documentTitle: string;
    documentType: 'invoice' | 'quotation';
    settings: TemplateSettings;
    terms: string;
    footer: string;
}) {
    const accent = settings.brand.accent;
    const primary = settings.brand.primary;

    const fontMap: Record<TemplateSettings['brand']['font'], string> = {
        Inter: 'Inter, ui-sans-serif, system-ui, -apple-system, Segoe UI, sans-serif',
        Roboto: 'Roboto, ui-sans-serif, system-ui, -apple-system, Segoe UI, sans-serif',
        Arial: 'Arial, Helvetica, ui-sans-serif, system-ui, sans-serif',
    };
    const fontFamily = fontMap[settings.brand.font] ?? fontMap.Inter;

    const items = SAMPLE.document.items.map((it) => ({
        ...it,
        total: it.qty * it.price,
    }));

    const subtotal = items.reduce((a, b) => a + b.total, 0);

    // NOTE: preview math only
    const vat = subtotal * 0.16;
    const grand = subtotal + vat;

    const density = settings.layout.density;
    const pad =
        density === 'compact'
            ? 'px-7 pt-7 pb-7'
            : density === 'airy'
              ? 'px-10 pt-10 pb-10'
              : 'px-8 pt-8 pb-8';

    const table = settings.layout.table;
    const isCenter = settings.layout.header === 'center';

    return (
        <div className="flex justify-center">
            <div
                className="relative w-full max-w-[760px] overflow-hidden rounded-2xl bg-white shadow-xl"
                style={{ fontFamily }}
            >
                {/* ===== HERO HEADER ===== */}
                <div
                    className={cn(
                        'relative text-white',
                        isCenter ? 'text-center' : 'text-left',
                    )}
                    style={{ backgroundColor: primary }}
                >
                    <div className={cn(pad, 'relative z-10 pb-16')}>
                        <div
                            className={cn(
                                'flex items-start justify-between gap-6',
                                isCenter && 'flex-col items-center',
                            )}
                        >
                            <div
                                className={cn(
                                    'flex items-center gap-4',
                                    isCenter && 'justify-center',
                                )}
                            >
                                {settings.visibility.show_logo && (
                                    <div
                                        className="grid h-12 w-12 place-items-center rounded-xl text-[11px] font-bold"
                                        style={{
                                            backgroundColor: accent,
                                            color: primary,
                                        }}
                                    >
                                        LOGO
                                    </div>
                                )}
                                <div
                                    className={cn(
                                        'min-w-0',
                                        isCenter && 'text-center',
                                    )}
                                >
                                    <div className="text-lg leading-tight font-semibold">
                                        {SAMPLE.company.name}
                                    </div>
                                    <div className="mt-1 text-xs opacity-80">
                                        {SAMPLE.company.address}
                                    </div>
                                    <div className="text-xs opacity-80">
                                        {SAMPLE.company.phone} •{' '}
                                        {SAMPLE.company.email}
                                    </div>
                                    <div className="text-xs opacity-80">
                                        {SAMPLE.company.tpin}
                                    </div>
                                </div>
                            </div>

                            <div
                                className={cn(
                                    'shrink-0',
                                    isCenter ? 'text-center' : 'text-right',
                                )}
                            >
                                <div className="text-3xl font-extrabold tracking-[0.18em]">
                                    {documentTitle}
                                </div>
                                <div className="mt-3 space-y-1 text-xs opacity-80">
                                    <div>
                                        {documentNumberLabel}:{' '}
                                        {documentNumberValue}
                                    </div>
                                    <div>Date: {SAMPLE.document.issue_date}</div>
                                    <div>Due: {SAMPLE.document.due_date}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* wave */}
                    <svg
                        viewBox="0 0 1440 140"
                        className="absolute bottom-0 left-0 w-full"
                        preserveAspectRatio="none"
                    >
                        <path
                            fill="#ffffff"
                            d="M0,96L60,90.7C120,85,240,75,360,74.7C480,75,600,85,720,96C840,107,960,117,1080,117.3C1200,117,1320,107,1380,101.3L1440,96L1440,140L1380,140C1320,140,1200,140,1080,140C960,140,840,140,720,140C600,140,480,140,360,140C240,140,120,140,60,140L0,140Z"
                        />
                    </svg>
                </div>

                {/* ===== BODY ===== */}
                <div className={cn(pad, 'pt-8')}>
                    {/* Bill To + mini summary */}
                    <div className="mb-8 flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                        <div className="min-w-0">
                            <div
                                className="text-xs font-semibold uppercase"
                                style={{ color: accent }}
                            >
                                To:
                            </div>
                            <div className="mt-1 text-sm font-semibold">
                                {SAMPLE.client.name}
                            </div>
                            <div className="text-xs text-muted-foreground">
                                {SAMPLE.client.address}
                            </div>

                            {settings.visibility.show_client_email && (
                                <div className="text-xs text-muted-foreground">
                                    {SAMPLE.client.email}
                                </div>
                            )}
                            {settings.visibility.show_contact_person && (
                                <div className="text-xs text-muted-foreground">
                                    Attn: {SAMPLE.client.contact_person}
                                </div>
                            )}
                        </div>

                        <div
                            className="w-full rounded-xl px-5 py-3 sm:w-[260px]"
                            style={{ backgroundColor: accent + '22' }}
                        >
                            <div className="flex items-center justify-between text-xs text-muted-foreground">
                                <span>Sub Total</span>
                                <span className="font-semibold text-foreground">
                                    K {subtotal.toFixed(2)}
                                </span>
                            </div>
                            <div className="mt-2 flex items-center justify-between text-xs text-muted-foreground">
                                <span>VAT (16%)</span>
                                <span className="font-semibold text-foreground">
                                    K {vat.toFixed(2)}
                                </span>
                            </div>
                            <div
                                className="mt-3 flex items-center justify-between rounded-lg px-3 py-2 text-sm font-bold"
                                style={{
                                    backgroundColor: accent,
                                    color: primary,
                                }}
                            >
                                <span>GRAND TOTAL</span>
                                <span>K {grand.toFixed(2)}</span>
                            </div>
                        </div>
                    </div>

                    {/* Items table */}
                    <div className="overflow-hidden rounded-xl border">
                        <div
                            className="grid grid-cols-12 gap-2 px-4 py-3 text-xs font-semibold uppercase"
                            style={{ backgroundColor: accent, color: primary }}
                        >
                            <div className="col-span-6">Item Description</div>
                            <div className="col-span-2 text-right">Price</div>
                            <div className="col-span-2 text-right">Qty</div>
                            <div className="col-span-2 text-right">Total</div>
                        </div>

                        {items.map((it, idx) => (
                            <div
                                key={idx}
                                className={cn(
                                    'grid grid-cols-12 gap-2 px-4 py-3 text-sm',
                                    table === 'lined' && 'border-t',
                                    table === 'striped' &&
                                        idx % 2 === 1 &&
                                        'bg-black/[0.03]',
                                    table === 'clean' &&
                                        'border-t border-black/[0.04]',
                                )}
                            >
                                <div className="col-span-6">
                                    <div className="font-semibold">
                                        {it.desc}
                                    </div>
                                    <div className="mt-0.5 text-xs text-muted-foreground">
                                        Contrary to popular belief Lorem ipsum
                                        simply random.
                                    </div>
                                </div>
                                <div className="col-span-2 text-right">
                                    {it.price.toFixed(2)}
                                </div>
                                <div className="col-span-2 text-right">
                                    {it.qty}
                                </div>
                                <div className="col-span-2 text-right font-semibold">
                                    {it.total.toFixed(2)}
                                </div>
                            </div>
                        ))}
                    </div>

                    {/* Bottom blocks */}
                    <div className="mt-10 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="space-y-4">
                            {settings.visibility.show_notes && (
                                <div className="rounded-xl border p-4">
                                    <div className="text-xs font-semibold text-muted-foreground uppercase">
                                        Payment Info
                                    </div>
                                    <div className="mt-2 text-sm text-muted-foreground">
                                        Paypal: paypal@company.com <br />
                                        Payment: Visa, Master Card <br />
                                        We accept Cheque
                                    </div>
                                </div>
                            )}

                            {settings.visibility.show_bank_details && (
                                <div className="rounded-xl border p-4">
                                    <div className="text-xs font-semibold text-muted-foreground uppercase">
                                        Bank Details
                                    </div>
                                    <div className="mt-2 text-sm text-muted-foreground">
                                        Bank: {SAMPLE.company.bank.name}
                                        <br />
                                        Account Name:{' '}
                                        {SAMPLE.company.bank.account_name}
                                        <br />
                                        Account No:{' '}
                                        {SAMPLE.company.bank.account_number}
                                        <br />
                                        Branch: {SAMPLE.company.bank.branch}
                                    </div>
                                </div>
                            )}

                            {settings.visibility.show_terms && (
                                <div className="rounded-xl border p-4">
                                    <div className="text-xs font-semibold text-muted-foreground uppercase">
                                        Terms
                                    </div>
                                    <div className="mt-2 text-sm whitespace-pre-wrap text-muted-foreground">
                                        {terms || '—'}
                                    </div>
                                </div>
                            )}
                        </div>

                        <div className="rounded-xl border p-4">
                            <div className="text-xs font-semibold text-muted-foreground uppercase">
                                Thank you for your business!
                            </div>
                            <div className="mt-2 text-sm text-muted-foreground">
                                {documentType === 'quotation'
                                    ? 'Thanks for considering this quotation. We are ready to proceed once approved.'
                                    : SAMPLE.document.notes}
                            </div>

                            {settings.visibility.show_signature && (
                                <div className="mt-6 flex items-end justify-end">
                                    <div className="text-right">
                                        <div className="text-sm font-semibold">
                                            {SAMPLE.document.signature.name}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {SAMPLE.document.signature.title}
                                        </div>
                                        <div className="mt-3 text-sm font-semibold italic opacity-70">
                                            {SAMPLE.document.signature.label}
                                        </div>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Footer */}
                    <div className="mt-10 text-center text-xs text-muted-foreground">
                        {footer || ''}
                    </div>
                </div>
            </div>
        </div>
    );
}
