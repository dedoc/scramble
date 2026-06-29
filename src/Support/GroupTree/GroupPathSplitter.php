<?php

namespace Dedoc\Scramble\Support\GroupTree;

/**
 * Splits a human-readable group path (e.g. "Admin / Security > Users") into
 * its individual, ordered segments.
 */
class GroupPathSplitter
{
    /**
     * @return list<string>
     */
    public static function split(string $path): array
    {
        $segments = preg_split('/\s*[\/>.]\s*/', trim($path)) ?: [];

        return array_values(array_filter(
            array_map('trim', $segments),
            fn (string $segment) => $segment !== '',
        ));
    }
}
