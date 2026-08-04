<?php

namespace Dedoc\Scramble\Support\ProNudge;

use Dedoc\Scramble\Support\RouteInfo;

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

    public function count(ProNudgeSignal $signal): int
    {
        return count($this->signals[$signal->value] ?? []);
    }

    public function hasAny(): bool
    {
        foreach (ProNudgeSignal::cases() as $signal) {
            if ($this->count($signal) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{signal: ProNudgeSignal, count: int}>
     */
    public function summaries(): array
    {
        $summaries = [];

        foreach (ProNudgeSignal::cases() as $signal) {
            $count = $this->count($signal);

            if ($count === 0) {
                continue;
            }

            $summaries[] = [
                'signal' => $signal,
                'count' => $count,
            ];
        }

        return $summaries;
    }

    private function endpointKey(RouteInfo $routeInfo): string
    {
        return mb_strtolower($routeInfo->method).':'.$routeInfo->route->uri();
    }
}
