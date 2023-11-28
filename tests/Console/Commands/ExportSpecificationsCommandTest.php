<?php

use Dedoc\Scramble\Generator;
use Illuminate\Support\Facades\File;

use function Pest\Laravel\{artisan};

it('exports_the_specifications_to_a_specified_json_file', function () {
    $filepath = 'api-test.json';

    $generator = app(Generator::class);

    artisan("scramble:export --path={$filepath}")->assertExitCode(0);

    expect(File::json($filepath))->toBe($generator());
});
