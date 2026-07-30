<?php

function companiesIndexSource(): string
{
    return file_get_contents(__DIR__.'/../../resources/js/pages/Companies/Index.tsx');
}

it('renders companies as cards with dashboard primitives instead of a table', function () {
    $page = companiesIndexSource();

    expect($page)
        ->toContain("from '@/components/dashboard/primitives'")
        ->toContain('function CompanyCard(')
        ->not->toContain("from '@/components/ui/table'")
        ->not->toContain("from '@/components/ui/card'")
        ->not->toContain('<TableCell')
        ->not->toContain('<TableHead')
        ->not->toContain('<thead')
        ->not->toContain('<SortableTh')
        ->not->toContain('type SortKey');
});

it('hides the card grid behind an illustrated empty state when there is no data', function () {
    $page = companiesIndexSource();

    expect($page)
        ->toContain('const hasCompanies = companies.length > 0;')
        ->toContain('const hasResults = visibleCompanies.length > 0;')
        ->toContain('{!hasCompanies ? (')
        ->toContain('<EmptyCompanies />')
        ->toContain(': !hasResults ? (')
        ->toContain('function EmptyCompaniesArt()')
        ->toContain('<svg');

    expect(strpos($page, '<EmptyCompanies />'))
        ->toBeLessThan(strpos($page, '<CompanyCard'));
});

it('lays the cards out as a snapping horizontal rail rather than a wrapping grid', function () {
    $page = companiesIndexSource();

    expect($page)
        ->toContain('flex snap-x snap-mandatory gap-4 overflow-x-auto')
        ->toContain('w-[19rem] shrink-0 snap-start sm:w-[21rem]')
        ->toContain('aria-label="Your companies"')
        ->not->toContain('md:grid-cols-2 xl:grid-cols-3');
});

it('filters companies across their searchable fields', function () {
    $page = companiesIndexSource();

    expect($page)
        ->toContain('<SearchField')
        ->toContain('const needle = query.trim().toLowerCase();')
        ->toContain('company.name,')
        ->toContain('company.email,')
        ->toContain('company.tpin,')
        ->toContain('.toLowerCase().includes(needle)');
});

it('surfaces profile completion as a labelled progress bar on every card', function () {
    $page = companiesIndexSource();

    expect($page)
        ->toContain('interface ProfileCompletion {')
        ->toContain('company.profile_completion')
        ->toContain('function completionTone(')
        ->toContain('role="progressbar"')
        ->toContain('aria-valuenow={completion.percent}')
        ->toContain('Company details')
        ->toContain('{completion.filled}/{completion.total}');
});

it('opens compliance documents in a modal that only accepts pdf and jpg', function () {
    $page = companiesIndexSource();

    expect($page)
        ->toContain('function ComplianceModal(')
        ->toContain('Compliance documents')
        ->toContain('accept=".pdf,.jpg,.jpeg"')
        ->toContain('/documents/${document.id}/download')
        ->toContain('function formatFileSize(')
        ->toContain('MAX_DOCUMENT_BYTES');
});
