<?php

namespace Dedoc\Scramble\Support\ProNudge;

use Illuminate\Support\Str;

/** @internal */
enum ProNudgeSignal: string
{
    case QueryBuilder = 'query_builder';
    case LaravelDataReturn = 'laravel_data_return';
    case LaravelDataRequest = 'laravel_data_request';

    public function description(int $count): string
    {
        $endpoints = $count.' '.Str::plural('endpoint', $count);

        return match ($this) {
            self::QueryBuilder => $count === 1
                ? '1 endpoint uses Spatie Query Builder'
                : "{$endpoints} use Spatie Query Builder",
            self::LaravelDataReturn => $count === 1
                ? '1 endpoint returns Laravel Data objects'
                : "{$endpoints} return Laravel Data objects",
            self::LaravelDataRequest => $count === 1
                ? '1 endpoint accepts Laravel Data objects'
                : "{$endpoints} accept Laravel Data objects",
        };
    }
}
