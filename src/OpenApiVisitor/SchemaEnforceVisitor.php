<?php

namespace Dedoc\Scramble\OpenApiVisitor;

use Dedoc\Scramble\AbstractOpenApiVisitor;
use Dedoc\Scramble\Diagnostics\DiagnosticsCollector;
use Dedoc\Scramble\Diagnostics\GenericDiagnostic;
use Dedoc\Scramble\Exceptions\InvalidSchema;
use Dedoc\Scramble\Exceptions\RouteAware;
use Dedoc\Scramble\OpenApiTraverser;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Illuminate\Routing\Route;

class SchemaEnforceVisitor extends AbstractOpenApiVisitor
{
    protected array $operationReferences = [];

    protected static array $handledReferences = [];

    public function __construct(
        private Route                $route,
        private DiagnosticsCollector $diagnostics,
    ) {}

    public function popReferences()
    {
        return tap($this->operationReferences, fn () => $this->operationReferences = []);
    }

    public function enter($object, array $path = []): void
    {
        if ($object instanceof Reference) {
            if (array_key_exists($object->fullName, static::$handledReferences)) {
                return;
            }
            $this->operationReferences[] = $object;
            static::$handledReferences[$object->fullName] = true;
        }

        if ($object instanceof Type) {
            $this->validateSchema($object, $path);
        }
    }

    protected function validateSchema($object, $path): void
    {
        $exceptions = [];
        try {
            $exceptions = Scramble::getSchemaValidator()->validate(
                $object,
                implode('/', array_map(OpenApiTraverser::normalizeJsonPointerReferenceToken(...), $path)),
            );
        } catch (InvalidSchema $e) {
            $e->setRoute($this->route);

            $this->diagnostics->report(GenericDiagnostic::fromException($e));
        }

        foreach ($exceptions as $exception) {
            if ($exception instanceof RouteAware) {
                $exception->setRoute($this->route);
            }

            $this->diagnostics->reportQuietly(GenericDiagnostic::fromException($exception));
        }
    }
}
