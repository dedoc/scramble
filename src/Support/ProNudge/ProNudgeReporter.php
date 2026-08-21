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
        if (! $message = $this->collector->message()) {
            return;
        }

        $command->line($message['title']);
        $command->line($message['description']);
        $command->line('Learn more: '.self::PRO_URL);
    }
}
