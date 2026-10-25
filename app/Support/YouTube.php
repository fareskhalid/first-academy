<?php

namespace App\Support;

final class YouTube
{
    public static function videoId(string $url): ?string
    {
        $parts = parse_url(trim($url));
        if (! is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https') {
            return null;
        }

        $host = strtolower($parts['host'] ?? '');
        $host = str_starts_with($host, 'www.') ? substr($host, 4) : $host;
        $path = trim($parts['path'] ?? '', '/');
        $videoId = null;

        if ($host === 'youtu.be') {
            $videoId = explode('/', $path)[0] ?? null;
        } elseif (in_array($host, ['youtube.com', 'm.youtube.com', 'youtube-nocookie.com'], true)) {
            parse_str($parts['query'] ?? '', $query);
            if ($path === 'watch') {
                $videoId = $query['v'] ?? null;
            } elseif (preg_match('#^(?:embed|shorts|live)/([^/]+)#', $path, $matches)) {
                $videoId = $matches[1];
            }
        }

        return is_string($videoId) && preg_match('/^[A-Za-z0-9_-]{11}$/', $videoId) ? $videoId : null;
    }
}
