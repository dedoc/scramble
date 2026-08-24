<?php

namespace Dedoc\Scramble\Support;

class DevTools
{
    public static function enabled(): bool
    {
        return (bool) config('scramble.dev_tools.enabled');
    }

    public static function assetPath(): string
    {
        return dirname(__DIR__, 2).'/dist/devtools.js';
    }

    public static function viteServerUrl(): ?string
    {
        $hotFile = dirname(__DIR__, 2).'/dist/hot';

        if (! is_file($hotFile)) {
            return null;
        }

        $url = trim((string) file_get_contents($hotFile));

        return filter_var($url, FILTER_VALIDATE_URL) ? rtrim($url, '/') : null;
    }
}
