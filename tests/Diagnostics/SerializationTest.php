<?php

use Dedoc\Scramble\Diagnostics\ClassContext;
use Dedoc\Scramble\Diagnostics\DiagnosticsCollector;
use Dedoc\Scramble\Diagnostics\DiagnosticSeverity;
use Dedoc\Scramble\Diagnostics\GenericDiagnostic;
use Dedoc\Scramble\Diagnostics\RouteContext;
use Illuminate\Support\Facades\Route;

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

it('serializes route context after a cache round trip', function () {
    $route = Route::patch('api/user/{user}', [SerializationTestController::class, 'update']);
    $diagnostics = new DiagnosticsCollector;
    $diagnostics->report(
        (new GenericDiagnostic(DiagnosticSeverity::Warning, 'Incomplete documentation'))
            ->withContext(RouteContext::fromRoute($route))
    );

    $diagnostics = unserialize(serialize($diagnostics));

    expect($diagnostics->all()->toArray()[0]['context'])->toBe([
        'key' => 'route:PATCH:api/user/{user}:SerializationTestController@update',
        'type' => 'route',
        'label' => '/api/user/{user}',
        'method' => 'PATCH',
        'detail' => 'SerializationTestController@update',
    ]);
});

class SerializationTestModel {}

class SerializationTestController
{
    public function update(): void {}
}
