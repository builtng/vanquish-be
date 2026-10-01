<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CompanySettingsService
{
    /**
     * Company settings with logo URLs inlined as base64 data URIs.
     * PDF rendering (dompdf) runs server-side and cannot reliably fetch the
     * stored logo URL, so callers generating PDFs must use this instead of
     * reading company_settings directly.
     */
    public static function getForPdf(): array
    {
        $map = DB::table('company_settings')->pluck('value', 'key')->toArray();

        foreach ([
            'platform_logo_url' => 'platform_logo_base64',
            'platform_logo_dark_url' => 'platform_logo_dark_base64',
        ] as $urlKey => $base64Key) {
            if (!empty($map[$urlKey])) {
                $map[$base64Key] = self::toBase64($map[$urlKey]);
            }
        }

        return $map;
    }

    public static function toBase64(string $url): ?string
    {
        try {
            // Match on the URL path rather than requiring the host to equal
            // the current asset() base — a logo uploaded under a different
            // APP_URL (e.g. localhost during development) would otherwise
            // never resolve, even though the file is present on disk.
            $path = parse_url($url, PHP_URL_PATH);
            if (!$path) {
                return null;
            }

            $marker = '/storage/';
            $pos = strpos($path, $marker);
            if ($pos === false) {
                return null;
            }

            $relativePath = substr($path, $pos + strlen($marker));
            if (Storage::disk('public')->exists($relativePath)) {
                $content = Storage::disk('public')->get($relativePath);
                $mime = Storage::disk('public')->mimeType($relativePath);
                return 'data:' . $mime . ';base64,' . base64_encode($content);
            }
        } catch (\Throwable) {
            return null;
        }
        return null;
    }
}
