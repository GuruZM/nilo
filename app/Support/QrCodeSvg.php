<?php

namespace App\Support;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use Throwable;

/**
 * Draws a QR code as one flat SVG path.
 *
 * The same markup has to survive a browser, the print dialog and DomPDF. DomPDF
 * only copes with plain SVG, so instead of the library's renderer (which leans
 * on transforms and nested groups) the module matrix is walked here and every
 * dark module becomes a 1×1 square on a single path.
 */
class QrCodeSvg
{
    /**
     * The SVG markup, or null when there is nothing worth encoding.
     */
    public static function make(?string $content, string $color = '#000000'): ?string
    {
        $content = trim((string) $content);

        if ($content === '') {
            return null;
        }

        try {
            $matrix = Encoder::encode($content, ErrorCorrectionLevel::M(), Encoder::DEFAULT_BYTE_MODE_ENCODING)->getMatrix();
        } catch (Throwable) {
            return null;
        }

        $size = $matrix->getWidth();
        $path = '';

        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if ($matrix->get($x, $y) === 1) {
                    $path .= "M{$x} {$y}h1v1h-1z";
                }
            }
        }

        $fill = self::safeColor($color);

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$size.' '.$size.'" width="'.$size.'" height="'.$size.'" shape-rendering="crispEdges">'
            .'<path fill="'.$fill.'" d="'.$path.'"/></svg>';
    }

    /**
     * A data URI for an <img> tag — the form DomPDF reliably rasterises.
     */
    public static function dataUri(?string $content, string $color = '#000000'): ?string
    {
        $svg = self::make($content, $color);

        return $svg === null ? null : 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * The colour comes from template settings, so anything but a hex value is
     * refused rather than written into markup.
     */
    private static function safeColor(string $color): string
    {
        return preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $color) === 1 ? $color : '#000000';
    }
}
