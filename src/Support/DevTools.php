<?php

namespace Dedoc\Scramble\Support;

class DevTools
{
    public const ASSETS = ['devtools.js', 'devtools.css'];

    public static function enabled(): bool
    {
        return (bool) config('scramble.dev_tools', config('app.debug', false));
    }

    public static function assetPath(string $asset): string
    {
        return dirname(__DIR__, 2).'/dist/'.$asset;
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
