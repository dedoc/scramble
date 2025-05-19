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
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\UnknownType;
use PHPStan\PhpDocParser\Ast\PhpDoc\TemplateTagValueNode;
use ReflectionClass;

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
            ->values()
            ->map(fn (TemplateTagValueNode $n) => new TemplateType(
                name: $n->name,
                is: PhpDocTypeHelper::toType($n->bound),
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

            if ($reflectionProperty->isStatic()) {
                $classDefinitionData->properties[$reflectionProperty->name] = new ClassPropertyDefinition(
                    type: $reflectionProperty->hasDefaultValue()
                        ? (TypeHelper::createTypeFromValue($reflectionProperty->getDefaultValue()) ?: new UnknownType)
                        : new UnknownType,
                );
            } else {
                $classDefinitionData->properties[$reflectionProperty->name] = new ClassPropertyDefinition(
                    type: $reflectionProperty->hasType() ? TypeHelper::createTypeFromReflectionType($reflectionProperty->getType()) : new UnknownType,
                    defaultType: $reflectionProperty->hasDefaultValue()
                        ? TypeHelper::createTypeFromValue($reflectionProperty->getDefaultValue())
                        : null,
                );
                // @todo: handle templates
            }
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

        $classDefinition = new LazyShallowClassDefinition($classDefinitionData);

        //        Context::getInstance()->extensionsBroker->afterClassDefinitionCreated(new ClassDefinitionCreatedEvent($this->reflection->name, $classDefinition));

        return $classDefinition;
    }
}
