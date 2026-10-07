<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Resolves a company logo into an `src` the document sheet can use.
 *
 * The browser gets the public storage URL. DomPDF gets the file inlined as a
 * data URI: remote fetching is disabled, and a filesystem path is refused
 * whenever storage resolves outside DomPDF's chroot — which it does on the
 * server, where each release links `storage` to a shared directory. The logo
 * then printed as its alt text in every emailed PDF.
 */
class DocumentLogo
{
    public static function src(?string $logoPath, bool $forPdf): ?string
    {
        $logoPath = trim((string) $logoPath);

        if ($logoPath === '') {
            return null;
        }

        if (Str::startsWith($logoPath, ['http://', 'https://'])) {
            return $forPdf ? null : $logoPath;
        }

        $relative = Str::after(ltrim($logoPath, '/'), 'storage/');

        if (! $forPdf) {
            return asset('storage/'.$relative);
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($relative)) {
            return null;
        }

        $mime = (string) $disk->mimeType($relative);

        if (! Str::startsWith($mime, 'image/')) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode((string) $disk->get($relative));
    }
}
