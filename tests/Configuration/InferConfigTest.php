<?php

use Dedoc\Scramble\Configuration\InferConfig;
use Dedoc\Scramble\Tests\Files\SamplePostModel;

it('builds definitions from reflection for classes in a vendor directory not named vendor', function () {
    // As in a project whose composer.json sets "vendor-dir": "lib".
    $config = new class extends InferConfig
    {
        protected function vendorDirectories(): array
        {
            return [dirname((new ReflectionClass(SamplePostModel::class))->getFileName())];
        }
    };

    expect($config->shouldAnalyzeAst(SamplePostModel::class))->toBeFalse();
});
