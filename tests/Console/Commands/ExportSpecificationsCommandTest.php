<?php

use Dedoc\Scramble\Generator;

use function Pest\Laravel\{artisan};

it('exports_the_specifications_to_a_specified_json_file', function () {
    $filepath = 'api-test.json';

    $generator = app(Generator::class);

    artisan("scramble:export --path={$filepath}")->assertExitCode(0);

    $json_data = json_decode(file_get_contents($filepath), true);

    expect($json_data)->toBe($generator());
});
