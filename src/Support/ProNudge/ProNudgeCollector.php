<?php

namespace Dedoc\Scramble\Support\ProNudge;

use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\Str;

/** @internal */
class ProNudgeCollector
{
    /**
     * @var array<string, array<string, true>>
     */
    private array $signals = [];

    public function record(ProNudgeSignal $signal, RouteInfo $routeInfo): void
    {
        $this->signals[$signal->value][$this->endpointKey($routeInfo)] = true;
    }

    /** @return array{title: string, description: string}|null */
    public function message(): ?array
    {
        $title = '';

        foreach (ProNudgeSignal::cases() as $signal) {
            if (isset($this->signals[$signal->value])) {
                $title .= ($title ? ', ' : '').$signal->description(count($this->signals[$signal->value]));
            }
        }

        if (! $title) {
            return null;
        }

        return [
            'title' => Str::replaceLast(', ', ', and ', $title),
            'description' => 'Scramble PRO will document these endpoints accurately.',
        ];
    }

    private function endpointKey(RouteInfo $routeInfo): string
    {
        return mb_strtolower($routeInfo->method).':'.$routeInfo->route->uri();
    }
}
