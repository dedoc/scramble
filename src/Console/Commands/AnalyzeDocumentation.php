<?php

namespace Dedoc\Scramble\Console\Commands;

use Dedoc\Scramble\Console\Commands\Concerns\RendersDiagnostics;
use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Console\Command;

class AnalyzeDocumentation extends Command
{
    use RendersDiagnostics;

    protected $signature = 'scramble:analyze
        {--api=default : The API to analyze}
    ';

    protected $description = 'Analyzes the documentation generation process to surface any issues.';

    public function handle(Generator $generator): int
    {
        $generator->setThrowExceptions(false);
        Scramble::throwOnError(false);

        $generator(Scramble::getGeneratorConfig($this->option('api')));

        $this->renderDiagnostics(
            generator: $generator,
            successMessage: 'Everything is fine! Documentation is generated without any errors 🍻',
            issuesMessage: fn ($summary) => "Found {$summary}."
        );

        return $this->getDiagnosticsBasedReturnCode($generator);
    }
}
