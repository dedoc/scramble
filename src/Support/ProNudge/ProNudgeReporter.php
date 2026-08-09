<?php

namespace Dedoc\Scramble\Support\ProNudge;

use Illuminate\Console\Command;

/** @internal */
class ProNudgeReporter
{
    public const PRO_URL = 'https://scramble.dedoc.co/pro';

    public function __construct(
        private ProNudgeCollector $collector,
    ) {}

    public function report(Command $command): void
    {
        if (! $this->collector->hasAny()) {
            return;
        }

        $command->newLine();

        $lines = [];

        foreach ($this->collector->summaries() as $summary) {
            $lines[] = '  • '.$summary['signal']->description($summary['count']);
        }

        $command->getOutput()->block(implode("\n", [
            '⚡️ Scramble detected:',
            ...$lines,
            ...$this->renderPitch(),
            'Learn more: '.self::PRO_URL,
        ]), null, 'fg=gray', ' | ', escape: false);
    }

    /** @return string[] */
    private function renderPitch(): array
    {
        $hasQueryBuilder = $this->collector->count(ProNudgeSignal::QueryBuilder) > 0;
        $hasLaravelData = $this->collector->count(ProNudgeSignal::LaravelDataReturn) > 0
            || $this->collector->count(ProNudgeSignal::LaravelDataRequest) > 0;

        if ($hasQueryBuilder && $hasLaravelData) {
            return [
                'Scramble PRO understands these packages and automatically documents:',
                '  • Query Builder filters, sorts, includes, and sparse fieldsets',
                '  • Laravel Data request and response schemas',
            ];
        }

        if ($hasQueryBuilder) {
            return ['Scramble PRO understands Spatie Query Builder and automatically documents filters, sorts, includes, and sparse fieldsets.'];
        }

        return ['Scramble PRO understands Laravel Data and automatically generates accurate request and response schemas from your Data objects.'];
    }
}
