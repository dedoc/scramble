<?php

namespace Dedoc\Scramble\Console\Commands;

use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ExportSpecifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scramble:export
        {--path= : The path where to save the exported json file}
        {--api=default : The API for export}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export the OpenAPI specification to a json file.';

    /**
     * Execute the console command.
     */
    public function handle(Generator $generator): void
    {
        $specification = json_encode($generator());

        $config = Scramble::getGeneratorConfig($api = $this->option('api'));

        /** @var string $filename */
        $filename = $this->option('path') ?? $config->get('export_path', 'api'.($api === 'default' ? '' : "-$api").'.json');

        File::put($filename, $specification);

        $this->info("OpenAPI specification exported to {$filename}.");
    }
}
