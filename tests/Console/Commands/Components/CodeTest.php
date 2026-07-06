<?php

use Dedoc\Scramble\Console\Commands\Components\Code;

it('aligns annotations with highlighted lines that have utf8 gutters', function () {
    $file = tempnam(sys_get_temp_dir(), 'scramble-code-');

    if ($file === false) {
        throw new RuntimeException('Unable to create a temporary file.');
    }

    try {
        file_put_contents($file, implode(PHP_EOL, [
            '<?php',
            '',
            '',
            '',
            '',
            '',
            'class PublisherImportContentResource extends JsonResource',
        ]).PHP_EOL);

        $output = (new Code($file, 7, linesBefore: 0, linesAfter: 0))
            ->annotate('PublisherImportContentResource', "cannot infer resource's model")
            ->highlight();
    } finally {
        @unlink($file);
    }

    $plain = preg_replace('/\e\[[0-9;]*m/', '', $output) ?? $output;
    $plain = preg_replace('/<[^>]+>/', '', $plain) ?? $plain;

    expect(explode(PHP_EOL, $plain)[1])->toBe(
        '       ▕       ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^ cannot infer resource\'s model'
    );
});
