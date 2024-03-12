<?php

namespace Dedoc\Scramble\Console\Commands;

use Dedoc\Scramble\Generator;
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
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export the OpenAPI specifications to a json file.';

    /**
     * Execute the console command.
     */
    public function handle(Generator $generator): void
    {

        $specifications = json_encode($generator());

        /** @var string filename */
        $filename = $this->option('path') ?? config('scramble.export_path', 'api.json');

        File::put($filename, $specifications);

        $this->info("OpenAPI specifications exported to {$filename}.");
    }
}
