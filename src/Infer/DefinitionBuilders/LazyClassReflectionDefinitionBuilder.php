<?php

namespace Dedoc\Scramble\Infer\DefinitionBuilders;

use Dedoc\Scramble\Infer\Contracts\ClassDefinitionBuilder;
use Dedoc\Scramble\Infer\Contracts\Index as IndexContract;
use Dedoc\Scramble\Infer\Definition\AttributeDefinition;
use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Definition\ClassPropertyDefinition;
use Dedoc\Scramble\Infer\Definition\LazyShallowClassDefinition;
use Dedoc\Scramble\Infer\Definition\PendingDocComment;
use Dedoc\Scramble\Infer\Definition\PropertyVisibility;
use Dedoc\Scramble\Infer\Services\FileNameResolver;
use Dedoc\Scramble\Support\PhpDoc;
use Dedoc\Scramble\Support\Type\MixedType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Support\Str;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use ReflectionClass;

class LazyClassReflectionDefinitionBuilder implements ClassDefinitionBuilder
{
    use BuildsPropertyType;

    public function __construct(
        public IndexContract $index,
        public ReflectionClass $reflection,
    ) {}

    public function build(): LazyShallowClassDefinition
    {
        $classPhpDoc = (($comment = $this->reflection->getDocComment()) && ($path = $this->reflection->getFileName()))
            ? PhpDoc::parse($comment, FileNameResolver::createForFile($path))
            : new PhpDocNode([]);

        $propertyPhpDocTypeExtractor = (new ReflectionPropertyPhpDocTypeExtractor($this->reflection))->setClassPhpDoc($classPhpDoc);

        $parentDefinition = ($parentName = ($this->reflection->getParentClass() ?: null)?->name)
            ? ($this->index->getClass($parentName)?->getData() ?? new ClassDefinition(name: ''))
            : new ClassDefinition(name: '');

        $classDefinitionData = new ClassDefinition(
            name: $this->reflection->name,
            templateTypes: $parentDefinition->templateTypes,
            properties: array_map(fn ($pd) => clone $pd, $parentDefinition->properties ?: []),
            methods: $parentDefinition->methods ?: [],
            parentFqn: $parentName ?? null,
        );

        /*
         * Traits get analyzed by embracing default behavior of PHP reflection: reflection properties and
         * reflection methods get copied into the class that uses the trait.
         */

        foreach ($this->reflection->getProperties() as $reflectionProperty) {
            if ($reflectionProperty->class !== $this->reflection->name) {
                continue;
            }

            if ($reflectionProperty->isStatic()) {
                $phpDocType = $propertyPhpDocTypeExtractor->getType($reflectionProperty->name);

                $declarationType = ($reflectionPropertyType = $reflectionProperty->getType())
                    ? TypeHelper::createTypeFromReflectionType($reflectionPropertyType)
                    : new UnknownType;

                $classDefinitionData->properties[$reflectionProperty->name] = new ClassPropertyDefinition(
                    type: $reflectionProperty->hasDefaultValue()
                        ? (TypeHelper::createTypeFromValue($reflectionProperty->getDefaultValue()) ?: $this->buildPropertyType($declarationType, $phpDocType))
                        : $this->buildPropertyType($declarationType, $phpDocType),
                    isStatic: $reflectionProperty->isStatic(),
                    visibility: PropertyVisibility::fromReflectionProperty($reflectionProperty),
                    attributes: AttributeDefinition::fromReflectionAttributesArray($reflectionProperty->getAttributes()),
                    pendingDocComment: ($docComment = $reflectionProperty->getDocComment() ?: null)
                        ? new PendingDocComment($docComment, declaringClass: $this->reflection->name)
                        : null,
                );
            } else {
                $phpDocType = $propertyPhpDocTypeExtractor->getType($reflectionProperty->name);

                $declarationType = ($reflectionPropertyType = $reflectionProperty->getType())
                    ? TypeHelper::createTypeFromReflectionType($reflectionPropertyType)
                    : new UnknownType;

                $classDefinitionData->properties[$reflectionProperty->name] = new ClassPropertyDefinition(
                    type: $t = new TemplateType(
                        'T'.Str::studly($reflectionProperty->name),
                        is: $this->buildPropertyType($declarationType, $phpDocType),
                    ),
                    defaultType: $reflectionProperty->hasDefaultValue()
                        ? TypeHelper::createTypeFromValue($reflectionProperty->getDefaultValue())
                        : null,
                    visibility: PropertyVisibility::fromReflectionProperty($reflectionProperty),
                    attributes: AttributeDefinition::fromReflectionAttributesArray($reflectionProperty->getAttributes()),
                    pendingDocComment: ($docComment = $reflectionProperty->getDocComment() ?: null)
                        ? new PendingDocComment($docComment, declaringClass: $this->reflection->name)
                        : null,
                );
                $classDefinitionData->templateTypes[] = $t;
            }
        }

        foreach ($propertyPhpDocTypeExtractor->getClassDefinedPropertiesTagValueNodes() as $propertyTagValue) {
            $name = ltrim($propertyTagValue->propertyName, '$');

            $classDefinitionData->properties[$name] = new ClassPropertyDefinition(
                type: $propertyPhpDocTypeExtractor->getType($name) ?: new MixedType,
                visibility: PropertyVisibility::Public,
            );
        }

        return new LazyShallowClassDefinition($classDefinitionData);
    }
}
