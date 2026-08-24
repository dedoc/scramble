<?php

namespace Dedoc\Scramble\Support\ProNudge;

use Illuminate\Console\Command;

/** @internal */
class ProNudgeReporter
{
    public const COLOR = 'gray';

    public const PRO_URL = 'https://scramble.dedoc.co/pro';

    public function __construct(
        private ProNudgeCollector $collector,
    ) {}

    public function report(Command $command): void
    {
        if (! $message = $this->collector->message()) {
            return;
        }

        $command->newLine();
        $command->getOutput()->block(implode("\n", [
            '⚡️ <fg='.self::COLOR.';options=bold>'.$message['title'].'.</>',
            $message['description'],
            'Learn more: '.self::PRO_URL,
        ]), null, 'fg='.self::COLOR, ' | ', escape: false);
    }
}
