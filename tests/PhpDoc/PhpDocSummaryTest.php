<?php

use Dedoc\Scramble\Support\PhpDoc;

it('extracts summary and description', function (string $eol) {
    $phpDoc = str_replace("\n", $eol, <<<'EOD'
    /**
     * This is summary.
     *
     * This is a description.
     * It can span multiple lines.
     */
    EOD);

    $node = PhpDoc::parse($phpDoc);

    expect($node->getAttribute('summary'))->toBe('This is summary.')
        ->and($node->getAttribute('description'))->toBe("This is a description.\nIt can span multiple lines.");
})->with([
    'LF' => ["\n"],
    'CRLF' => ["\r\n"],
]);
