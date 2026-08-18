<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Attributes\Discriminator as DiscriminatorAttribute;
use Dedoc\Scramble\Diagnostics\Schema\Se002InvalidDiscriminatorMappingDiagnostic;
use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\ClassBasedReference;
use Dedoc\Scramble\Support\Generator\Combined\CombinedType;
use Dedoc\Scramble\Support\Generator\Combined\OneOf;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\Discriminator;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ObjectType as OpenApiObjectType;
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
        $timesMapped = array_count_values(array_filter($attribute->mapping, is_string(...)));

        foreach ($attribute->mapping as $value => $mappedClass) {
            if (! class_exists($mappedClass) && ! interface_exists($mappedClass)) {
                $this->openApiContext->diagnostics->reportOnce(
                    Se002InvalidDiscriminatorMappingDiagnostic::forMappedType($className, $mappedClass)
                );

                continue;
            }

            $items[] = $schema = $this->openApiTransformer->transform(new ObjectType($mappedClass));

            if (! is_string($value)) {
                continue;
            }

            // Only the types documented as components schemas can be mapped to a discriminator value.
            if ($schema instanceof Reference) {
                $mapping[$value] = $schema;
            }

            // Only a type mapped to a single value is known to always hold it.
            if ($timesMapped[$mappedClass] === 1) {
                $this->documentDiscriminatorValue($schema, $attribute->propertyName, $value);
            }
        }

        if (! $items) {
            return null;
        }

        return (new OneOf)
            ->setItems($items)
            ->setDiscriminator(new Discriminator($attribute->propertyName, $mapping));
    }

    /** Validators ignore the discriminator, so the value is documented as a const on the mapped type. */
    private function documentDiscriminatorValue(OpenApiType $schema, string $propertyName, string $value): void
    {
        $objectType = $this->findObjectTypeWithProperty($schema, $propertyName);

        if (! $property = $objectType?->getProperty($propertyName)) {
            return;
        }

        $property->const($value);

        $objectType->addRequired([$propertyName]);
    }

    private function findObjectTypeWithProperty(?OpenApiType $schema, string $propertyName): ?OpenApiObjectType
    {
        if ($schema instanceof Reference) {
            $resolved = $this->components->has($schema) ? $schema->resolve() : null;

            $schema = $resolved instanceof Schema ? $resolved->type : null;
        }

        if ($schema instanceof OpenApiObjectType) {
            return $schema->hasProperty($propertyName) ? $schema : null;
        }

        // JSON resources are documented as a combination of schemas.
        if ($schema instanceof CombinedType) {
            foreach ($schema->items as $item) {
                if ($found = $this->findObjectTypeWithProperty($item, $propertyName)) {
                    return $found;
                }
            }
        }

        return null;
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
