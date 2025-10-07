<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Definition\ClassPropertyDefinition;
use Dedoc\Scramble\Infer\Extensions\AfterClassDefinitionCreatedExtension;
use Dedoc\Scramble\Infer\Extensions\Event\ClassDefinitionCreatedEvent;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\GenericClassStringType;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\MixedType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;

class AfterResourceCollectionDefinitionCreatedExtension implements AfterClassDefinitionCreatedExtension
{
    public function __construct(private Index $index) {}

    public function shouldHandle(string $name): bool
    {
        return $name === ResourceCollection::class;
    }

    public function afterClassDefinitionCreated(ClassDefinitionCreatedEvent $event): void
    {
        $definition = $event->classDefinition;

        if ($definition->parentFqn) {
            $definition->templateTypes = $this->index->getClass($definition->parentFqn)->templateTypes ?? [];
        }

        $definition->templateTypes[] = $tCollects = new TemplateType(
            'TCollects', // @todo rename to TCollectedResource
            default: new UnknownType,
        );

        $definition->properties['collects'] = new ClassPropertyDefinition(
            type: new GenericClassStringType($tCollects),
        );

        $definition->properties['collection'] = new ClassPropertyDefinition(
            type: new Generic(Collection::class, [
                new IntegerType,
                $tCollects,
            ]),
        );

        $definition->methods['toArray'] = new ShallowFunctionDefinition(
            type: new FunctionType(
                name: 'toArray',
                arguments: [
                    'resource' => new MixedType,
                ],
                returnType: new ArrayType($tCollects),
            ),
            definingClassName: ResourceCollection::class,
        );
    }
}
