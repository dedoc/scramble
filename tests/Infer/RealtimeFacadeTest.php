<?php

namespace Tests\Infer;

use Facades\Dedoc\Scramble\Tests\Files\SampleService;

it('infers real-time facades types', function () {
    $type = getStatementType(SampleService::class.'::do()');

    /* For now realtime facades are analyzed with reflection, not by reading AST */
    expect($type->toString())->toBe('int');
});
