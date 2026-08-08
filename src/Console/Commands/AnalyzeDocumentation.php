<?php

namespace Dedoc\Scramble\Console\Commands;

use Dedoc\Scramble\Console\Commands\Concerns\ReportsDiagnostics;
use Dedoc\Scramble\Generator;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\ProNudge\ProNudgeReporter;
use Illuminate\Console\Command;

class AnalyzeDocumentation extends Command
{
    use ReportsDiagnostics;

    protected $signature = 'scramble:analyze
        {--api=default : The API to analyze}
    ';

    protected $description = 'Analyzes the documentation generation process to surface any issues.';

    public function handle(Generator $generator): int
    {
        $generator->setThrowExceptions(false);

        $apiOption = $this->option('api');
        $api = is_string($apiOption) ? $apiOption : 'default';

        $generator(Scramble::getGeneratorConfig($api));

        $context = $generator->context;
        assert($context instanceof OpenApiContext);

        $status = $this->reportDiagnostics($context->diagnostics->diagnostics);

        (new ProNudgeReporter($generator->proNudge))->report($this);

        return $status;
    }
}
