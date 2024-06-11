<?php

namespace Dedoc\Scramble\OpenApiVisitor;

use Dedoc\Scramble\AbstractOpenApiVisitor;
use Dedoc\Scramble\Exceptions\InvalidSchema;
use Dedoc\Scramble\OpenApiTraverser;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Types\Type;

class SchemaEnforceVisitor extends AbstractOpenApiVisitor
{
    protected array $operationReferences = [];

    protected static array $handledReferences = [];

    protected array $exceptions = [];

    public function __construct(private bool $throwExceptions = true)
    {
    }

    public function popReferences()
    {
        return tap($this->operationReferences, fn () => $this->operationReferences = []);
    }

    public function getExceptions()
    {
        return $this->exceptions;
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
        try {
            Scramble::getSchemaValidator()->validate(
                $object,
                implode('/', array_map(OpenApiTraverser::normalizeJsonPointerReferenceToken(...), $path)),
            );
        } catch (InvalidSchema $e) {
            if ($this->throwExceptions) {
                throw $e;
            }
            $this->exceptions[] = $e;
        }
    }
}
