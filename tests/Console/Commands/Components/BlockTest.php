<?php

use Dedoc\Scramble\Console\Commands\Components\Block;
use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

it('wraps on configured separators while preserving styles', function () {
    [$raw, $plain] = renderBlock('<options=bold>Alpha-Beta/Gamma</>', 2, 12, decorated: true);

    expect(explode(PHP_EOL, $plain))->toBe([
        '  Alpha-Beta',
        '  /Gamma',
    ]);

    [$firstLine, $secondLine] = explode(PHP_EOL, $raw);

    expect($firstLine)->toMatch("/\e\\[[\\d;]*m/");
    expect($secondLine)->toMatch("/\e\\[[\\d;]*m/");
});

it('does not break urls', function () {
    [, $plain] = renderBlock('https://example.com/really/long/path', 2, 12);

    expect(explode(PHP_EOL, $plain))->toBe([
        '  https://example.com/really/long/path',
    ]);
});

it('prefers configured separators over hard splits', function () {
    [, $plain] = renderBlock('Alpha-BetaQux', 2, 12);

    expect(explode(PHP_EOL, $plain))->toBe([
        '  Alpha-',
        '  BetaQux',
    ]);
});

it('wraps on backslashes', function () {
    [$raw, $plain] = renderBlock('Foo\\BarBazQux', 2, 12, decorated: true);

    expect(explode(PHP_EOL, $plain))->toBe([
        '  Foo',
        '  \\BarBazQux',
    ]);

    expect($raw)->not->toContain('</>');
});

it('does not leak synthetic closing tags when wrapping styled namespaces', function () {
    [$raw, $plain] = renderBlock(
        '<options=bold>(Call to undefined method Dedoc\\Scramble\\Support\\OperationExtensions\\RulesEvaluator\\NodeRulesEvaluator::unknownMethod())</>',
        4,
        40,
        decorated: true,
    );

    $lines = explode(PHP_EOL, $plain);

    expect($raw)->not->toContain('</>');
    expect($lines[0])->toBe('    (Call to undefined method Dedoc');
    expect($lines)->toContain('    \\Scramble\\Support');
    expect($lines)->toContain('    \\OperationExtensions\\RulesEvaluator');
    expect(implode('', $lines))->toContain('\\NodeRulesEvaluator::unknownMethod()');
});

function renderBlock(string $content, int $paddingLeft, int $columns, bool $decorated = false): array
{
    $previousColumns = getenv('COLUMNS');
    putenv("COLUMNS={$columns}");

    try {
        $output = new BufferedOutput(decorated: $decorated);
        $style = new OutputStyle(new ArrayInput([]), $output);

        (new Block($content, $paddingLeft))->render($style);

        $raw = rtrim($output->fetch(), PHP_EOL);
        $plain = rtrim(Helper::removeDecoration($output->getFormatter(), $raw), PHP_EOL);

        return [$raw, $plain];
    } finally {
        $previousColumns === false
            ? putenv('COLUMNS')
            : putenv("COLUMNS={$previousColumns}");
    }
}
