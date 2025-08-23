<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Definition\ClassPropertyDefinition;
use Dedoc\Scramble\Infer\Extensions\AfterClassDefinitionCreatedExtension;
use Dedoc\Scramble\Infer\Extensions\Event\ClassDefinitionCreatedEvent;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\MixedType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Http\Resources\Json\ResourceCollection;

class AfterResourceCollectionDefinitionCreatedExtension implements AfterClassDefinitionCreatedExtension
{
    public function shouldHandle(string $name): bool
    {
        return $name === ResourceCollection::class;
    }

    public function afterClassDefinitionCreated(ClassDefinitionCreatedEvent $event): void
    {
        $definition = $event->classDefinition;

        $definition->templateTypes[] = $tCollects = new TemplateType(
            'TCollects',
            default: new UnknownType,
        );

        $definition->properties['collects'] = new ClassPropertyDefinition(
            $tCollects,
        );

        $definition->methods['toArray'] = new ShallowFunctionDefinition(
            type: new FunctionType(
                name: 'toArray',
                arguments: [
                    'resource' => new MixedType,
                ],
                returnType: new ArrayType,
            ),
            definingClassName: ResourceCollection::class,
        );
    }
}
