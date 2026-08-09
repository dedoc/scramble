<?php

namespace Dedoc\Scramble\Console\Commands;

use Dedoc\Scramble\Console\Commands\Concerns\RendersDiagnostics;
use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ExportDocumentation extends Command
{
    use RendersDiagnostics;

    protected $signature = 'scramble:export
        {--path= : The path to save the exported JSON file}
        {--api=default : The API to export a documentation for}
    ';

    protected $help = 'Use -v / --verbose to print full diagnostics.';

    protected $description = 'Export the OpenAPI document to a JSON file.';

    public function handle(Generator $generator): void
    {
        $api = $this->option('api');
        $path = $this->option('path');

        $config = Scramble::getGeneratorConfig($api);

        $specification = json_encode($generator($config), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        /** @var string $filename */
        $filename = $path ?: $config->get('export_path') ?? 'api'.($api === 'default' ? '' : "-$api").'.json';

        File::put($filename, $specification);

        $verboseSuffix = $this->getOutput()->isVerbose()
            ? ''
            : 'Run this command with -v/--verbose to print full diagnostics.';

        $successMessage = "OpenAPI document exported to {$filename}.";
        $issuesMessage = fn ($summary) => "OpenAPI document exported to {$filename} with {$summary}. {$verboseSuffix}";

        if ($this->getOutput()->isVerbose()) {
            $this->renderDiagnostics($generator, $successMessage, $issuesMessage);
        } else {
            $this->renderDiagnosticsSummary($generator, $successMessage, $issuesMessage);
        }
    }
}
