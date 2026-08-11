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
        $command->line('Scramble detected:');

        foreach ($this->collector->summaries() as $summary) {
            $command->line('  • '.$summary['signal']->description($summary['count']));
        }

        $command->newLine();
        $this->renderPitch($command);
        $command->newLine();
        $command->line('Learn more: <href='.self::PRO_URL.'>'.self::PRO_URL.'</>');
    }

    private function renderPitch(Command $command): void
    {
        $hasQueryBuilder = $this->collector->count(ProNudgeSignal::QueryBuilder) > 0;
        $hasLaravelData = $this->collector->count(ProNudgeSignal::LaravelDataReturn) > 0
            || $this->collector->count(ProNudgeSignal::LaravelDataRequest) > 0;

        if ($hasQueryBuilder && $hasLaravelData) {
            $command->line('Scramble PRO understands these packages and automatically documents:');
            $command->newLine();
            $command->line('  • Query Builder filters, sorts, includes, and sparse fieldsets');
            $command->line('  • Laravel Data request and response schemas');

            return;
        }

        if ($hasQueryBuilder) {
            $command->line('Scramble PRO understands Spatie Query Builder and automatically documents filters, sorts, includes, and sparse fieldsets.');

            return;
        }

        $command->line('Scramble PRO understands Laravel Data and automatically generates accurate request and response schemas from your Data objects.');
    }
}
