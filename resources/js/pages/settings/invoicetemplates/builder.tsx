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
    preset: 'modern_minimal' | 'classic_business' | 'bold_header';
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
        id: 'modern_minimal',
        name: 'Modern Minimal',
        description: 'Clean header, modern look, great default.',
        defaults: {
            brand: { primary: '#0F172A', accent: '#22C55E', font: 'Inter' },
            layout: { header: 'split', table: 'striped', density: 'normal' },
        },
    },
    {
        id: 'classic_business',
        name: 'Classic Business',
        description: 'Traditional layout, lined table, safe for corporates.',
        defaults: {
            brand: { primary: '#111827', accent: '#2563EB', font: 'Arial' },
            layout: { header: 'left', table: 'lined', density: 'normal' },
        },
    },
    {
        id: 'bold_header',
        name: 'Bold Header',
        description: 'Strong title + airy spacing. Great for premium brands.',
        defaults: {
            brand: { primary: '#111827', accent: '#F97316', font: 'Inter' },
            layout: { header: 'center', table: 'clean', density: 'airy' },
        },
    },
];

const DEFAULT_SETTINGS: TemplateSettings = {
    preset: 'modern_minimal',
    brand: { primary: '#0F172A', accent: '#22C55E', font: 'Inter' },
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

// sample preview data
const SAMPLE = {
    company: {
        name: 'Nilo Labs Ltd',
        address: 'Lusaka, Zambia',
        phone: '+260 97X XXX XXX',
        email: 'billing@nilo.ai',
        tpin: 'TPIN: 1234567890',
    },
    client: {
        name: 'Resonant Technologies',
        email: 'accounts@resonant.tech',
        contact_person: 'Charles Ngalasa',
        address: 'Ibex Hill, Lusaka',
    },
    invoice: {
        number: 'INV-000123',
        issue_date: '2026-01-03',
        due_date: '2026-01-10',
        notes: 'Thank you for your business. Kindly settle within due date.',
        items: [
            { desc: 'Prime POS Setup & Configuration', qty: 1, price: 2500 },
            { desc: 'ZRA Smart Invoice Integration', qty: 1, price: 1800 },
            { desc: 'Training Session (2 hours)', qty: 1, price: 650 },
        ],
    },
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Settings', href: '/settings/profile' },
    { title: 'Invoice templates', href: '/settings/invoice-templates' },
    { title: 'Builder', href: '/settings/invoice-templates/create' },
];

export default function InvoiceTemplateBuilder({
    mode,
    template,
}: {
    mode: Mode;
    template: TemplatePayload | null;
}) {
    const page = usePage<BuilderPageProps>();

    // show shared flash messages if present
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

        // merge for backwards compatibility
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
        terms_html: template?.terms_html ?? 'Payment due within 7 days.',
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
                // keep user overrides if they already changed after preset
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

        if (mode === 'create') {
            form.post('/settings/invoice-templates', {
                preserveScroll: true,
                preserveState: false,
                onSuccess: () => toast.success('Invoice template created.'),
                onError: (errors) =>
                    toast.error(
                        (errors as any)?.template ||
                            (errors as any)?.name ||
                            'Failed to create template.',
                    ),
            });
        } else {
            form.put(`/settings/invoice-templates/${template?.id}`, {
                preserveScroll: true,
                preserveState: false,
                onSuccess: () => toast.success('Invoice template updated.'),
                onError: (errors) =>
                    toast.error(
                        (errors as any)?.template ||
                            (errors as any)?.name ||
                            'Failed to update template.',
                    ),
            });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Invoice template builder" />

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
                                <h1 className="text-2xl font-semibold tracking-tight">
                                    Invoice template builder
                                </h1>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Build a reusable invoice template for the
                                    active company. Presets + styling + live
                                    preview.
                                </p>
                            </div>
                        </div>

                        <div className="flex items-center gap-2">
                            <Button
                                variant="outline"
                                className="gap-2 rounded-xl"
                                onClick={() =>
                                    router.visit('/settings/invoice-templates')
                                }
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
                                                    placeholder="e.g. Modern Minimal (Invoice)"
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
                                                        Make this the default
                                                        invoice template for
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
                                                    Visibility
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
                                                    label="Show bank details (later)"
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
                                                    label="Show signature (later)"
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

                                            {form.errors && (
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
                                    A4 preview (sample data)
                                </div>
                            </div>

                            <div className="flex-1">
                                <A4Preview
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
 * A4Preview: lightweight visual preview.
 * Later we can reuse this component to generate PDFs (browsershot/dompdf).
 */
function A4Preview({
    settings,
    terms,
    footer,
}: {
    settings: TemplateSettings;
    terms: string;
    footer: string;
}) {
    const density = settings.layout.density;

    const pad =
        density === 'compact' ? 'p-6' : density === 'airy' ? 'p-10' : 'p-8';

    const font = 'font-sans';
    const headerAlign =
        settings.layout.header === 'center' ? 'text-center' : 'text-left';
    const tableStyle = settings.layout.table;

    const items = SAMPLE.invoice.items.map((it) => ({
        ...it,
        total: it.qty * it.price,
    }));

    const subtotal = items.reduce((a, b) => a + b.total, 0);

    return (
        <div className="flex justify-center">
            <div
                className={cn(
                    'w-full max-w-[760px] overflow-hidden rounded-2xl border bg-white shadow-sm',
                    font,
                )}
                style={{ color: settings.brand.primary }}
            >
                <div className={cn(pad)}>
                    {/* Header */}
                    <div
                        className={cn(
                            'flex items-start justify-between gap-6',
                            headerAlign,
                        )}
                    >
                        <div
                            className={cn(
                                settings.layout.header === 'center'
                                    ? 'w-full'
                                    : 'min-w-0',
                            )}
                        >
                            <div className="flex items-center gap-3">
                                {settings.visibility.show_logo && (
                                    <div
                                        className="grid h-10 w-10 place-items-center rounded-xl"
                                        style={{
                                            background:
                                                settings.brand.accent + '22',
                                        }}
                                    >
                                        <span
                                            className="text-xs font-semibold"
                                            style={{
                                                color: settings.brand.accent,
                                            }}
                                        >
                                            LOGO
                                        </span>
                                    </div>
                                )}
                                <div className="min-w-0">
                                    <div className="text-lg font-semibold">
                                        {SAMPLE.company.name}
                                    </div>
                                    <div className="text-xs opacity-70">
                                        {SAMPLE.company.address}
                                    </div>
                                    <div className="text-xs opacity-70">
                                        {SAMPLE.company.phone} •{' '}
                                        {SAMPLE.company.email}
                                    </div>
                                    <div className="text-xs opacity-70">
                                        {SAMPLE.company.tpin}
                                    </div>
                                </div>
                            </div>
                        </div>

                        {settings.layout.header !== 'center' && (
                            <div className="shrink-0 text-right">
                                <div className="text-xl font-semibold">
                                    INVOICE
                                </div>
                                <div className="mt-1 text-xs opacity-70">
                                    <div>#{SAMPLE.invoice.number}</div>
                                    <div>
                                        Issue: {SAMPLE.invoice.issue_date}
                                    </div>
                                    <div>Due: {SAMPLE.invoice.due_date}</div>
                                </div>
                            </div>
                        )}
                    </div>

                    {settings.layout.header === 'center' && (
                        <div className="mt-4 text-center">
                            <div className="text-2xl font-semibold">
                                INVOICE
                            </div>
                            <div className="mt-2 text-xs opacity-70">
                                #{SAMPLE.invoice.number} • Issue{' '}
                                {SAMPLE.invoice.issue_date} • Due{' '}
                                {SAMPLE.invoice.due_date}
                            </div>
                        </div>
                    )}

                    <Separator className="my-6" />

                    {/* Client block */}
                    <div className="grid grid-cols-2 gap-6">
                        <div>
                            <div className="text-xs font-semibold opacity-80">
                                BILL TO
                            </div>
                            <div className="mt-2 text-sm font-semibold">
                                {SAMPLE.client.name}
                            </div>
                            <div className="text-xs opacity-70">
                                {SAMPLE.client.address}
                            </div>
                            {settings.visibility.show_client_email && (
                                <div className="text-xs opacity-70">
                                    {SAMPLE.client.email}
                                </div>
                            )}
                            {settings.visibility.show_contact_person && (
                                <div className="text-xs opacity-70">
                                    Attn: {SAMPLE.client.contact_person}
                                </div>
                            )}
                        </div>

                        <div className="text-right">
                            <div className="text-xs font-semibold opacity-80">
                                SUMMARY
                            </div>
                            <div
                                className="mt-2 inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs"
                                style={{
                                    background: settings.brand.accent + '22',
                                    color: settings.brand.accent,
                                }}
                            >
                                <span className="font-semibold">Subtotal</span>
                                <span>K {subtotal.toFixed(2)}</span>
                            </div>
                        </div>
                    </div>

                    <Separator className="my-6" />

                    {/* Items table */}
                    <div className="overflow-hidden rounded-xl border">
                        <div
                            className="grid grid-cols-12 gap-2 px-4 py-3 text-xs font-semibold"
                            style={{
                                background: settings.brand.primary + '08',
                            }}
                        >
                            <div className="col-span-6">Description</div>
                            <div className="col-span-2 text-right">Qty</div>
                            <div className="col-span-2 text-right">Price</div>
                            <div className="col-span-2 text-right">Total</div>
                        </div>

                        {items.map((it, idx) => (
                            <div
                                key={idx}
                                className={cn(
                                    'grid grid-cols-12 gap-2 px-4 py-3 text-sm',
                                    tableStyle === 'lined' && 'border-t',
                                    tableStyle === 'striped' &&
                                        idx % 2 === 1 &&
                                        'bg-black/[0.02]',
                                    tableStyle === 'clean' &&
                                        'border-t border-black/[0.04]',
                                )}
                            >
                                <div className="col-span-6">{it.desc}</div>
                                <div className="col-span-2 text-right">
                                    {it.qty}
                                </div>
                                <div className="col-span-2 text-right">
                                    {it.price.toFixed(2)}
                                </div>
                                <div className="col-span-2 text-right font-semibold">
                                    {it.total.toFixed(2)}
                                </div>
                            </div>
                        ))}
                    </div>

                    {/* Notes / Terms */}
                    <div className="mt-6 grid grid-cols-1 gap-4">
                        {settings.visibility.show_notes && (
                            <div className="rounded-xl border p-4">
                                <div className="text-xs font-semibold opacity-80">
                                    NOTES
                                </div>
                                <div className="mt-2 text-sm opacity-80">
                                    {SAMPLE.invoice.notes}
                                </div>
                            </div>
                        )}

                        {settings.visibility.show_terms && (
                            <div className="rounded-xl border p-4">
                                <div className="text-xs font-semibold opacity-80">
                                    TERMS
                                </div>
                                <div className="mt-2 text-sm whitespace-pre-wrap opacity-80">
                                    {terms || '—'}
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Footer */}
                    <div className="mt-8 text-center text-xs opacity-70">
                        {footer || ''}
                    </div>
                </div>
            </div>
        </div>
    );
}
