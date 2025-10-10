<?php

namespace Dedoc\Scramble\Infer\DefinitionBuilders;

use Dedoc\Scramble\Infer\Context;
use Dedoc\Scramble\Infer\Contracts\ClassDefinitionBuilder;
use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Definition\ShallowClassDefinition;
use Dedoc\Scramble\Infer\Extensions\Event\ClassDefinitionCreatedEvent;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Infer\Services\FileNameResolver;
use Dedoc\Scramble\PhpDoc\PhpDocTypeHelper;
use Dedoc\Scramble\Support\PhpDoc;
use Dedoc\Scramble\Support\Type\TemplateType;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\TemplateTagValueNode;
use ReflectionClass;

class ShallowClassReflectionDefinitionBuilder implements ClassDefinitionBuilder
{
    /**
     * @param  ReflectionClass<object>  $reflection
     */
    public function __construct(
        private Index $index,
        private ReflectionClass $reflection
    ) {}

    public function build(): ClassDefinition
    {
        $parentName = ($this->reflection->getParentClass() ?: null)?->name;

        $parentDefinition = $parentName ? $this->index->getClass($parentName) : null;

        $classPhpDoc = (($comment = $this->reflection->getDocComment()) && ($path = $this->reflection->getFileName()))
            ? PhpDoc::parse($comment, FileNameResolver::createForFile($path))
            : new PhpDocNode([]);

        $classTemplates = collect($classPhpDoc->getTemplateTagValues())
            ->merge($classPhpDoc->getTemplateTagValues('@template-covariant'))
            ->values()
            ->map(fn (TemplateTagValueNode $n) => new TemplateType(
                name: $n->name,
                is: $n->bound ? PhpDocTypeHelper::toType($n->bound) : null,
            ))
            ->keyBy('name');

        /*
         * @todo consider more advanced cloning implementation.
         * Currently just cloning property definition feels alright as only its `defaultType` may change.
         */
        $classDefinition = new ShallowClassDefinition(
            name: $this->reflection->name,
            templateTypes: $parentDefinition?->propagatesTemplates()
                ? array_merge($classTemplates->values()->all(), $parentDefinition->templateTypes ?? [])
                : $classTemplates->values()->all(),
            properties: array_map(fn ($pd) => clone $pd, $parentDefinition?->properties ?: []),
            methods: $parentDefinition?->methods ?: [],
            parentFqn: $parentName,
        );
        $classDefinition->propagatesTemplates($parentDefinition?->propagatesTemplates() ?? false);

        $classDefinition->setIndex($this->index);

        Context::getInstance()->extensionsBroker->afterClassDefinitionCreated(new ClassDefinitionCreatedEvent($classDefinition->name, $classDefinition));

        return $classDefinition;
    }
}
