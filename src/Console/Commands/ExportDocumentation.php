<?php

namespace Dedoc\Scramble\Console\Commands;

use Dedoc\Scramble\Console\Commands\Concerns\CreatesGenerator;
use Dedoc\Scramble\Console\Commands\Concerns\RendersDiagnostics;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\Types\UnknownType;
use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\File;
use JsonException;
use Symfony\Component\Console\Output\ConsoleOutputInterface;

class ExportDocumentation extends Command
{
    use CreatesGenerator;
    use RendersDiagnostics;

    protected $signature = 'scramble:export
        {--path= : The path to save the exported JSON file}
        {--stdout : Write the OpenAPI document to stdout}
        {--api=default : The API to export a documentation for}
        {--routes= : Comma-separated route names to export}
        {--fail-on-unknown : Fail when an UnknownType schema is generated}
    ';

    protected $help = 'Use -v / --verbose to print full diagnostics.';

    protected $description = 'Export the OpenAPI document as JSON.';

    public function handle(): int
    {
        $standardOutput = $this->getOutput();
        $writeToStdout = $this->option('stdout') === true;

        if ($writeToStdout && $this->input->hasParameterOption('--path')) {
            $this->useErrorOutput($standardOutput);
            $this->error('The --stdout and --path options cannot be used together.');

            return self::INVALID;
        }

        $generator = $this->createGenerator();

        if ($this->option('fail-on-unknown')) {
            Scramble::preventSchema(UnknownType::class, throw: false);
        }

        $api = $this->option('api');
        $path = $this->option('path');

        $config = Scramble::getGeneratorConfig($api);

        $result = $generator->generate($config);

        try {
            $specification = json_encode(
                $result->spec(),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $exception) {
            if ($writeToStdout) {
                $this->useErrorOutput($standardOutput);
            }

            $this->error('Unable to encode the OpenAPI document: '.$exception->getMessage());

            return self::FAILURE;
        }

        /** @var string $filename */
        $filename = $path ?: $config->get('export_path') ?? 'api'.($api === 'default' ? '' : "-$api").'.json';

        if ($writeToStdout) {
            $standardOutput->write($specification.PHP_EOL);
            $this->useErrorOutput($standardOutput);
        } else {
            File::put($filename, $specification);
        }

        $verboseSuffix = $this->getOutput()->isVerbose()
            ? ''
            : 'Run this command with -v/--verbose to print full diagnostics.';

        $successMessage = $writeToStdout ? null : "OpenAPI document exported to {$filename}.";
        $issuesMessage = $writeToStdout
            ? fn ($summary) => "OpenAPI document generated with {$summary}. {$verboseSuffix}"
            : fn ($summary) => "OpenAPI document exported to {$filename} with {$summary}. {$verboseSuffix}";

        if ($this->getOutput()->isVerbose()) {
            $this->renderDiagnostics($result, $successMessage, $issuesMessage);
        } else {
            $this->renderDiagnosticsSummary($result, $successMessage, $issuesMessage);
        }

        return $this->option('fail-on-unknown')
            ? $this->getDiagnosticsBasedReturnCode($result)
            : self::SUCCESS;
    }

    private function useErrorOutput(OutputStyle $standardOutput): void
    {
        $output = $standardOutput->getOutput();

        if ($output instanceof ConsoleOutputInterface) {
            $this->setOutput(new OutputStyle($this->input, $output->getErrorOutput()));
        }
    }
}
