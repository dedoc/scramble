<?php

namespace Dedoc\Scramble\Console\Commands;

use Dedoc\Scramble\Console\Commands\Concerns\CreatesGenerator;
use Dedoc\Scramble\Console\Commands\Concerns\RendersDiagnostics;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\Types\UnknownType;
use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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

    protected $description = 'Export the OpenAPI document as JSON.';

    public function handle(): int
    {
        $standardOutput = $this->getOutput();
        $writeToStdout = $this->option('stdout') === true;

        if ($writeToStdout) {
            $this->useErrorOutput($standardOutput);
        }

        if ($writeToStdout && $this->input->hasParameterOption('--path')) {
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

        $specification = json_encode(
            $result->spec(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        /** @var string $filename */
        $filename = $path ?: $config->get('export_path') ?? 'api'.($api === 'default' ? '' : "-$api").'.json';

        if ($writeToStdout) {
            $standardOutput->write($specification, true, OutputInterface::OUTPUT_RAW | OutputInterface::VERBOSITY_QUIET);
        } else {
            File::put($filename, $specification);
        }

        $message = $writeToStdout
            ? 'OpenAPI document generated'
            : "OpenAPI document exported to {$filename}";
        $successMessage = $writeToStdout ? null : "{$message}.";
        $issuesMessage = fn ($summary) => "{$message} with {$summary}.";

        $this->renderDiagnostics($result, $successMessage, $issuesMessage);

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
