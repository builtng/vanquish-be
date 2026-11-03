<?php

namespace App\Support;

/**
 * Builds public links to files on the "public" storage disk (e.g. counsellor photos).
 *
 * Files are served by the API (e.g. https://api.vqtmanagement.com/storage/...), not by the
 * website, so the link must not be built from APP_URL when APP_URL points at the website.
 * Order of preference:
 *   1. STORAGE_URL, when set (e.g. https://api.vqtmanagement.com/storage)
 *   2. the host of the current API request
 *   3. APP_URL + /storage (queued jobs and console commands)
 */
class PublicStorageUrl
{
    public static function base(): string
    {
        $configured = config('app.storage_url');
        if (!empty($configured)) {
            return rtrim($configured, '/');
        }

        if (!app()->runningInConsole() || app()->runningUnitTests()) {
            $request = request();
            if ($request && $request->getHost()) {
                return $request->getSchemeAndHttpHost() . '/storage';
            }
        }

        return rtrim((string) config('app.url'), '/') . '/storage';
    }

    public static function for(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return self::fixWebsiteHostedLink($path);
        }

        return self::base() . '/' . ltrim($path, '/');
    }

    /**
     * Links saved earlier against the website address (e.g. https://vqtmanagement.com/storage/...)
     * point at a host that does not serve the file. Rebuild those on the storage base; leave every
     * other absolute link (S3, external images) untouched.
     */
    private static function fixWebsiteHostedLink(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        $path = (string) parse_url($url, PHP_URL_PATH);

        if (!$host || !str_starts_with($path, '/storage/')) {
            return $url;
        }

        $websiteHosts = array_filter(array_map(
            fn ($u) => parse_url(trim($u), PHP_URL_HOST),
            (array) config('app.frontend_urls', [])
        ));
        $websiteHosts[] = parse_url((string) config('app.frontend_url'), PHP_URL_HOST);
        $websiteHosts = array_unique(array_filter($websiteHosts));
        $websiteHosts = array_merge($websiteHosts, array_map(fn ($h) => 'www.' . preg_replace('/^www\./', '', $h), $websiteHosts));

        $storageHost = parse_url(self::base(), PHP_URL_HOST);
        if ($host === $storageHost || !in_array($host, $websiteHosts, true)) {
            return $url;
        }

        return self::base() . '/' . substr($path, strlen('/storage/'));
    }
}
