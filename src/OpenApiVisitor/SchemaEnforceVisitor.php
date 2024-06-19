<?php

namespace Dedoc\Scramble\OpenApiVisitor;

use Dedoc\Scramble\AbstractOpenApiVisitor;
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
        private Route $route,
        private bool $throwExceptions = true,
        protected array &$exceptions = [],
    ) {}

    public function popReferences()
    {
        return tap($this->operationReferences, fn () => $this->operationReferences = []);
    }

    public function enter($object, array $path = [])
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

    protected function validateSchema($object, $path)
    {
        $exceptions = [];
        try {
            $exceptions = Scramble::getSchemaValidator()->validate(
                $object,
                implode('/', array_map(OpenApiTraverser::normalizeJsonPointerReferenceToken(...), $path)),
            );
        } catch (InvalidSchema $e) {
            $e->setRoute($this->route);

            if ($this->throwExceptions) {
                throw $e;
            }

            $this->exceptions[] = $e;
        }
        foreach ($exceptions as $exception) {
            if ($exception instanceof RouteAware) {
                $exception->setRoute($this->route);
            }
        }
        $this->exceptions = array_merge($this->exceptions, $exceptions);
    }
}
