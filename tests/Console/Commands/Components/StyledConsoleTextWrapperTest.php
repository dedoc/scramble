<?php

use Dedoc\Scramble\Console\Commands\Components\StyledConsoleTextWrapper;

it('wraps on configured separators while preserving styles', function () {
    $lines = wrapStyled('<options=bold>Alpha-Beta/Gamma</>', 10);

    expect($lines)->toBe([
        '<options=bold>Alpha-Beta</>',
        '<options=bold>/Gamma</>',
    ]);
});

it('does not break urls', function () {
    $lines = wrapStyled('https://example.com/really/long/path', 12);

    expect($lines)->toBe([
        'https://example.com/really/long/path',
    ]);
});

it('wraps on backslashes', function () {
    $lines = wrapStyled('Foo\\BarBazQux', 10);

    expect($lines)->toBe([
        'Foo',
        '\\BarBazQux',
    ]);
});

it('reopens styles across wrapped namespace segments', function () {
    $lines = wrapStyled(
        '<options=bold>(Call to undefined method Dedoc\\Scramble\\Support\\OperationExtensions\\RulesEvaluator\\NodeRulesEvaluator::unknownMethod())</>',
        36,
    );

    expect($lines[0])->toBe('<options=bold>(Call to undefined method Dedoc</>');
    expect($lines)->toContain('<options=bold>\\Scramble\\Support</>');
    expect($lines)->toContain('<options=bold>\\OperationExtensions\\RulesEvaluator</>');
    expect(implode('', $lines))->toContain('\\NodeRulesEvaluator::unknownMethod()');
});

it('preserves blank lines in multiline content', function () {
    $lines = wrapStyled("Alpha\n\nBeta", 40);

    expect($lines)->toBe([
        'Alpha',
        '',
        'Beta',
    ]);
});

/**
 * @return list<string>
 */
function wrapStyled(string $content, int $maxWidth): array
{
    return (new StyledConsoleTextWrapper)->wrap($content, $maxWidth);
}
