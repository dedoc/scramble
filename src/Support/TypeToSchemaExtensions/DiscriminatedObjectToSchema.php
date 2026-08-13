<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Attributes\Discriminator as DiscriminatorAttribute;
use Dedoc\Scramble\Diagnostics\Schema\Se002InvalidDiscriminatorMappingDiagnostic;
use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\ClassBasedReference;
use Dedoc\Scramble\Support\Generator\Combined\OneOf;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\Discriminator;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use ReflectionClass;

/**
 * Documents the types annotated with the `#[Discriminator]` attribute as `oneOf` schemas.
 */
class DiscriminatedObjectToSchema extends TypeToSchemaExtension
{
    /** @var array<string, DiscriminatorAttribute|false> */
    private array $attributesCache = [];

    public function __construct(
        Infer $infer,
        TypeTransformer $openApiTransformer,
        Components $components,
        protected OpenApiContext $openApiContext,
    ) {
        parent::__construct($infer, $openApiTransformer, $components);
    }

    public function shouldHandle(Type $type): bool
    {
        return $type instanceof ObjectType
            && $this->getDiscriminatorAttribute($type->name) !== null;
    }

    /**
     * @param  ObjectType  $type
     */
    public function toSchema(Type $type): ?OpenApiType
    {
        $className = $type->name;

        if (! $this->classOrInterfaceExists($className)) {
            return null;
        }

        if (! $attribute = $this->getDiscriminatorAttribute($className)) {
            return null;
        }

        $items = [];
        /** @var array<string, Reference> $mapping */
        $mapping = [];

        foreach ($attribute->mapping as $value => $mappedClass) {
            if (! class_exists($mappedClass) && ! interface_exists($mappedClass)) {
                $this->openApiContext->diagnostics->reportOnce(
                    Se002InvalidDiscriminatorMappingDiagnostic::forMappedType($className, $mappedClass)
                );

                continue;
            }

            $items[] = $schema = $this->openApiTransformer->transform(new ObjectType($mappedClass));

            // Only the types documented as components schemas can be mapped to a discriminator value.
            if (is_string($value) && $schema instanceof Reference) {
                $mapping[$value] = $schema;
            }
        }

        if (! $items) {
            return null;
        }

        return (new OneOf)
            ->setItems($items)
            ->setDiscriminator(new Discriminator($attribute->propertyName, $mapping));
    }

    public function reference(ObjectType $type): ?Reference
    {
        if (! $this->getDiscriminatorAttribute($type->name)) {
            return null;
        }

        return ClassBasedReference::create('schemas', $type->name, $this->components);
    }

    private function getDiscriminatorAttribute(string $className): ?DiscriminatorAttribute
    {
        if (array_key_exists($className, $this->attributesCache)) {
            return $this->attributesCache[$className] ?: null;
        }

        return ($this->attributesCache[$className] = $this->getFreshDiscriminatorAttribute($className)) ?: null;
    }

    private function getFreshDiscriminatorAttribute(string $className): DiscriminatorAttribute|false
    {
        if (! $this->classOrInterfaceExists($className)) {
            return false;
        }

        $attribute = ((new ReflectionClass($className))->getAttributes(DiscriminatorAttribute::class)[0] ?? null)?->newInstance();

        // Without the mapped types there is nothing to document, so the type is handled as usual.
        if (! $attribute || ! $attribute->mapping) {
            return false;
        }

        return $attribute;
    }

    /**
     * @phpstan-assert-if-true class-string $className
     */
    private function classOrInterfaceExists(mixed $className): bool
    {
        return is_string($className)
            && (class_exists($className) || interface_exists($className));
    }
}
