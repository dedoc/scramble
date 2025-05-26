<?php

namespace Dedoc\Scramble\Infer\DefinitionBuilders;

use Dedoc\Scramble\Infer\Analyzer\PropertyAnalyzer;
use Dedoc\Scramble\Infer\Context;
use Dedoc\Scramble\Infer\Contracts\ClassDefinitionBuilder;
use Dedoc\Scramble\Infer\Contracts\Index as IndexContract;
use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Definition\ClassPropertyDefinition;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\Extensions\Event\ClassDefinitionCreatedEvent;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Support\Str;
use ReflectionClass;

class AstClassDefinitionBuilder implements ClassDefinitionBuilder
{
    /**
     * @param  ReflectionClass<object>  $reflection
     */
    public function __construct(
        public IndexContract $index,
        public ReflectionClass $reflection,
    ) {}

    public function build(): ClassDefinition
    {
        $parentDefinition = null;

        if ($this->reflection->getParentClass()) {
            $parentDefinition = $this->index->getClass($parentName = $this->reflection->getParentClass()->name);
        }

        $parentDefinition = $parentDefinition?->getData();

        /*
         * @todo consider more advanced cloning implementation.
         * Currently just cloning property definition feels alright as only its `defaultType` may change.
         */
        $classDefinition = new ClassDefinition(
            name: $this->reflection->name,
            templateTypes: $parentDefinition?->templateTypes ?: [],
            properties: array_map(fn ($pd) => clone $pd, $parentDefinition?->properties ?: []),
            methods: $parentDefinition?->methods ?: [],
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
                $classDefinition->properties[$reflectionProperty->name] = new ClassPropertyDefinition(
                    type: $reflectionProperty->hasDefaultValue()
                        ? (TypeHelper::createTypeFromValue($reflectionProperty->getDefaultValue()) ?: new UnknownType)
                        : new UnknownType,
                );
            } else {
                $classDefinition->properties[$reflectionProperty->name] = new ClassPropertyDefinition(
                    type: $t = new TemplateType(
                        'T'.Str::studly($reflectionProperty->name),
                        is: $reflectionProperty->hasType() ? TypeHelper::createTypeFromReflectionType($reflectionProperty->getType()) : new UnknownType,
                    ),
                    defaultType: $reflectionProperty->hasDefaultValue()
                        ? PropertyAnalyzer::from($reflectionProperty)->getDefaultType()
                        : null,
                );
                $classDefinition->templateTypes[] = $t;
            }
        }

        foreach ($this->reflection->getMethods() as $reflectionMethod) {
            if ($reflectionMethod->class !== $this->reflection->name) {
                continue;
            }

            $classDefinition->methods[$reflectionMethod->name] = new FunctionLikeDefinition(
                new FunctionType(
                    $reflectionMethod->name,
                    arguments: [],
                    returnType: new UnknownType,
                ),
                definingClassName: $this->reflection->name,
                isStatic: $reflectionMethod->isStatic(),
            );
        }

        Context::getInstance()->extensionsBroker->afterClassDefinitionCreated(new ClassDefinitionCreatedEvent($classDefinition->name, $classDefinition));

        return $classDefinition;
    }
}
