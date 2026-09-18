<?php

use Dedoc\Scramble\Console\Commands\Components\Code;
use Symfony\Component\Console\Formatter\OutputFormatter;

it('renders a plain source snippet with a marked target line', function () {
    $file = sourceFile(<<<'PHP'
        <?php

        class ExampleController
        {
            public function show()
            {
                return unknown_value();
            }
        }
        PHP);

    $snippet = (new Code($file, line: 7))->snippet();

    expect((new OutputFormatter)->format($snippet))->toBe(implode(PHP_EOL, [
        '      5▕     public function show()',
        '      6▕     {',
        '  ➜   7▕         return unknown_value();',
        '      8▕     }',
        '      9▕ }',
    ]));
});

it('styles only the marker, line framing, and plain source code', function () {
    $file = sourceFile("<?php\nreturn '<info>value</info>';\n");

    $snippet = (new Code($file, line: 2, linesBefore: 0, linesAfter: 0))->snippet();

    expect($snippet)->toBe(
        '<fg=red;options=bold>  ➜ </><fg=gray>  2▕ </><fg=white>return \'\\<info\\>value\\</info\\>\';</>',
    );
});

it('limits snippets at the start and end of a file', function () {
    $file = sourceFile("first\nsecond\nthird");

    expect((new OutputFormatter)->format((new Code($file, line: 1))->snippet()))->toBe(implode(PHP_EOL, [
        '  ➜   1▕ first',
        '      2▕ second',
        '      3▕ third',
    ]));

    expect((new OutputFormatter)->format((new Code($file, line: 3))->snippet()))->toBe(implode(PHP_EOL, [
        '      1▕ first',
        '      2▕ second',
        '  ➜   3▕ third',
    ]));
});

function sourceFile(string $source): string
{
    $path = tempnam(sys_get_temp_dir(), 'scramble-code-');
    file_put_contents($path, $source);

    return $path;
}
