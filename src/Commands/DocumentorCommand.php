<?php

namespace Dedoc\Documentor\Commands;

use Illuminate\Console\Command;

class DocumentorCommand extends Command
{
    public $signature = 'documentor';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
