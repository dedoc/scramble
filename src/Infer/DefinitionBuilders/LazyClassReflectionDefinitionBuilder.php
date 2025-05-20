<?php

namespace Dedoc\Scramble\Infer\DefinitionBuilders;

use Dedoc\Scramble\Infer\Context;
use Dedoc\Scramble\Infer\Contracts\ClassDefinitionBuilder;
use Dedoc\Scramble\Infer\Contracts\Index as IndexContract;
use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Definition\ClassPropertyDefinition;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\Definition\LazyShallowClassDefinition;
use Dedoc\Scramble\Infer\Extensions\Event\ClassDefinitionCreatedEvent;
use Dedoc\Scramble\PhpDoc\PhpDocTypeHelper;
use Dedoc\Scramble\Support\PhpDoc;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\MixedType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Support\Collection;
use League\Uri\UriTemplate\Template;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\TemplateTagValueNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use ReflectionClass;
use ReflectionProperty;

class LazyClassReflectionDefinitionBuilder implements ClassDefinitionBuilder
{
    public function __construct(
        public IndexContract $index,
        public ReflectionClass $reflection,
    ) {}

    public function build(): LazyShallowClassDefinition
    {
        $parentDefinition = ($parentName = ($this->reflection->getParentClass() ?: null)?->name)
            ? ($this->index->getClass($parentName)?->getData() ?? new ClassDefinition(name: ''))
            : new ClassDefinition(name: '');

        $classPhpDoc = PhpDoc::parse($this->reflection->getDocComment() ?: '/** */');

        $classTemplates = collect($classPhpDoc->getTemplateTagValues())
            ->merge($classPhpDoc->getTemplateTagValues('@template-covariant'))
            ->values()
            ->map(fn (TemplateTagValueNode $n) => new TemplateType(
                name: $n->name,
                is: $n->bound ? PhpDocTypeHelper::toType($n->bound) : null,
            ))
            ->keyBy('name');

        $classDefinitionData = new ClassDefinition(
            name: $this->reflection->name,
            templateTypes: $classTemplates->values()->all(),
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

            $classDefinitionData->properties[$reflectionProperty->name] = $this->buildPropertyDefinition($reflectionProperty, $classTemplates);
        }

        foreach ($this->reflection->getMethods() as $reflectionMethod) {
            if ($reflectionMethod->class !== $this->reflection->name) {
                continue;
            }

            $classDefinitionData->methods[$reflectionMethod->name] = new FunctionLikeDefinition(
                new FunctionType(
                    $reflectionMethod->name,
                    arguments: [],
                    returnType: new UnknownType,
                ),
                definingClassName: $this->reflection->name,
                isStatic: $reflectionMethod->isStatic(),
            );
        }

        $classDefinition = new LazyShallowClassDefinition(
            $classDefinitionData,
            parentDefinedTemplates: $this->getParentDefinedTemplates($parentDefinition, $classPhpDoc, $classDefinitionData->templateTypes),
            interfacesDefinedTemplates: [],
        );

        //        Context::getInstance()->extensionsBroker->afterClassDefinitionCreated(new ClassDefinitionCreatedEvent($this->reflection->name, $classDefinition));

        return $classDefinition;
    }

    private function buildPropertyDefinition(ReflectionProperty $reflectionProperty, Collection $classTemplates)
    {
        $propertyPhpDoc = PhpDoc::parse($reflectionProperty->getDocComment() ?: '/** */');

        if ($reflectionProperty->isStatic()) {
            return new ClassPropertyDefinition(
                type: $reflectionProperty->hasDefaultValue()
                    ? (TypeHelper::createTypeFromValue($reflectionProperty->getDefaultValue()) ?: new UnknownType)
                    : new UnknownType,
            );
        }

        $propertyPhpDocType = $this->toInferType(collect($propertyPhpDoc->getVarTagValues())->first()?->type, $classTemplates);
        $typeHintType = $reflectionProperty->hasType() ? TypeHelper::createTypeFromReflectionType($reflectionProperty->getType()) : null;

        return new ClassPropertyDefinition(
            type: $propertyPhpDocType ?? $typeHintType ?? new MixedType,
            defaultType: $reflectionProperty->hasDefaultValue()
                ? TypeHelper::createTypeFromValue($reflectionProperty->getDefaultValue())
                : null,
        );
    }

    private function toInferType(?TypeNode $type, Collection $classTemplates): ?Type
    {
        if (! $type) {
            return null;
        }

        $inferType = PhpDocTypeHelper::toType($type);

        return (new TypeWalker)
            ->map(
                $inferType,
                fn (Type $t) => $t instanceof ObjectType && $classTemplates->has($t->name) ? $classTemplates->get($t->name) : $t,
            );
    }

    /**
     * @param TemplateType[] $definitionTemplates
     *
     * @return array<string, Type>
     */
    private function getParentDefinedTemplates(?ClassDefinition $parentDefinition, PhpDocNode $doc, array $definitionTemplates): array
    {
        if (! $extendsNodes = $doc->getExtendsTagValues()) {
            return [];
        }

        $extendsNode = array_values($extendsNodes)[0];

        $extendedType = $this->toInferType(
            $extendsNode->type,
            collect($definitionTemplates)->keyBy('name'),
        );

        if (! $extendedType instanceof Generic) {
            return [];
        }

        return collect($parentDefinition->templateTypes)
            ->mapWithKeys(function ($parentTemplateType, int $i) use ($extendedType) {
                return [
                    $parentTemplateType->name => $extendedType->templateTypes[$i] ?? new UnknownType()
                ];
            })
            ->all();
    }
}
