<?php

use Dedoc\Scramble\Diagnostics\ClassContext;
use Dedoc\Scramble\Diagnostics\DiagnosticSeverity;
use Dedoc\Scramble\Diagnostics\GenericDiagnostic;

it('serializes a diagnostic from the diagnostic itself', function () {
    $diagnostic = new GenericDiagnostic(
        DiagnosticSeverity::Error,
        'Schema `Dedoc\Scramble\Support\Generator\Types\UnknownType` is not allowed.',
        context: new ClassContext(SerializationTestModel::class),
    );

    expect($diagnostic->toArray())->toBe([
        'key' => 'GEN001|class:SerializationTestModel',
        'code' => 'GEN001',
        'severity' => 'error',
        'message' => 'Schema `UnknownType` is not allowed',
        'tip' => null,
        'details' => [],
        'context' => [
            'key' => 'class:SerializationTestModel',
            'type' => 'class',
            'label' => 'SerializationTestModel',
            'method' => null,
            'detail' => null,
        ],
    ]);
});

it('serializes diagnostic severity as a string-backed enum value', function () {
    expect(DiagnosticSeverity::Error->value)->toBe('error')
        ->and(DiagnosticSeverity::Warning->value)->toBe('warning');
});

class SerializationTestModel {}
