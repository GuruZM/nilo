<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Resolves the branding a client-facing document carries. Invoices and
 * quotations leave the platform as correspondence from the company that raised
 * them, so this deliberately exposes nothing about the platform itself — the
 * caller gets the company's own mark and contact details or nothing at all.
 */
class CompanyDocumentBranding
{
    /**
     * Formats mail clients refuse to render, so a logo in one of them has to
     * fall back to the wordmark instead of showing a broken image.
     *
     * @var list<string>
     */
    private const UNRENDERABLE_FORMATS = ['svg', 'svgz'];

    /**
     * The view data the document email templates expect.
     *
     * @return array{
     *     companyName: string|null,
     *     companyEmail: string|null,
     *     companyPhone: string|null,
     *     companyAddress: string|null,
     *     companyLogoEmbedPath: string|null,
     *     companyLogoUrl: string|null
     * }
     */
    public function forCompany(?Company $company): array
    {
        ['embed' => $embedPath, 'url' => $logoUrl] = $this->resolveLogo($company);

        return [
            'companyName' => $this->displayName($company),
            'companyEmail' => $this->filled($company?->email),
            'companyPhone' => $this->filled($company?->phone),
            'companyAddress' => $this->filled($company?->address),
            'companyLogoEmbedPath' => $embedPath,
            'companyLogoUrl' => $logoUrl,
        ];
    }

    /**
     * The name the client should see. Falls back to the owner when the company
     * record carries no name, and to null rather than to the platform name when
     * neither can be resolved.
     */
    public function displayName(?Company $company): ?string
    {
        return $this->filled($company?->name)
            ?? $this->filled($company?->owner?->name);
    }

    /**
     * A logo has to be embedded from local storage to survive image blocking,
     * but remote logos can only be linked. Either way an unreachable or
     * unrenderable file resolves to nothing so the wordmark takes over.
     *
     * @return array{embed: string|null, url: string|null}
     */
    private function resolveLogo(?Company $company): array
    {
        $none = ['embed' => null, 'url' => null];
        $logoUrl = $company?->logo_url;

        if (! is_string($logoUrl) || $logoUrl === '' || ! $this->rendersInMail($logoUrl)) {
            return $none;
        }

        if (Str::startsWith($logoUrl, ['http://', 'https://', '//'])) {
            return ['embed' => null, 'url' => $logoUrl];
        }

        $disk = Storage::disk('public');
        $path = Str::after($logoUrl, '/storage/');

        if (! $disk->exists($path)) {
            return $none;
        }

        /** The absolute URL matters: a relative one cannot resolve in a mailbox. */
        return ['embed' => $disk->path($path), 'url' => url($logoUrl)];
    }

    private function rendersInMail(string $logoUrl): bool
    {
        $path = parse_url($logoUrl, PHP_URL_PATH) ?: $logoUrl;
        $extension = Str::lower(pathinfo($path, PATHINFO_EXTENSION));

        return ! in_array($extension, self::UNRENDERABLE_FORMATS, true);
    }

    private function filled(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
