<?php

namespace Dedoc\Scramble\Console\Commands;

use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ExportDocumentation extends Command
{
    protected $signature = 'scramble:export
        {--path= : The path to save the exported JSON file}
        {--api=default : The API to export a documentation for}
    ';

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

        $this->info("OpenAPI document exported to {$filename}.");
    }
}
