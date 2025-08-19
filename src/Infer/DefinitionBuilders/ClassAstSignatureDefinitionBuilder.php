<?php

namespace Dedoc\Scramble\Infer\DefinitionBuilders;

use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Contracts\ClassDefinitionBuilder;
use Dedoc\Scramble\Infer\Services\FileNameResolver;
use Dedoc\Scramble\PhpDoc\PhpDocTypeHelper;
use Dedoc\Scramble\Support\InferExtensions\ShallowFunctionDefinition;
use Dedoc\Scramble\Support\PhpDoc;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\TypeWalker;
use InvalidArgumentException;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\PhpDocParser\Ast\PhpDoc\GenericTagValueNode;

class ClassAstSignatureDefinitionBuilder implements ClassDefinitionBuilder
{
    public function __construct(
        private string $fileName,
        private string $namespace,
        private Class_ $class,
    )
    {
    }

    public function build(): ClassDefinition
    {
        $definition = new ClassDefinition(
            name: $this->getFqn(),
        );

        $this->handleClassPhpDocComments($definition);

        foreach ($this->class->stmts as $stmt) {
            if ($stmt instanceof ClassMethod) {
                $this->handleClassMethod($definition, $stmt);
                continue;
            }
            throw new InvalidArgumentException('Class node statement is not handleable: '.$stmt::class);
        }

        dd($definition);
    }

    private function getFqn(): string
    {
        if (! $this->class->name) {
            throw new InvalidArgumentException('Class name node must be set');
        }

        return $this->namespace.'\\'.$this->class->name->name;
    }

    private function handleClassPhpDocComments(ClassDefinition $definition): void
    {
        if (! $doc = $this->class->getDocComment()) {
            return;
        }

        $parsedDoc = PhpDoc::parse(
            $doc->getText(),
            FileNameResolver::createForFile($this->fileName),
        );

        foreach ($parsedDoc->getTemplateTagValues() as $templateTagValue) {
            if ($templateTagValue->bound || $templateTagValue->lowerBound) {
                throw new InvalidArgumentException('`bound` and `lowerBound` are not supported on class templates');
            }

            $definition->templateTypes[] = new TemplateType(
                name: $templateTagValue->name,
                default: $templateTagValue->default
                    ? PhpDocTypeHelper::toType($templateTagValue->default)
                    : null,
            );
        }
    }

    private function handleClassMethod(ClassDefinition $definition, ClassMethod $stmt): void
    {
        $doc = $stmt->getDocComment();

        $parsedDoc = $doc ? PhpDoc::parse(
            $doc->getText(),
            FileNameResolver::createForFile($this->fileName),
        ) : null;

        $methodDefinition = new ShallowFunctionDefinition(
            type: $methodType = new FunctionType(name: $stmt->name->name),
        );

        foreach ($parsedDoc?->getTemplateTagValues() ?: [] as $templateTagValue) {
            if ($templateTagValue->bound || $templateTagValue->lowerBound || $templateTagValue->default) {
                throw new InvalidArgumentException('`bound`, `lowerBound`, and `default` are not supported on method templates');
            }

            if (collect($definition->templateTypes)->firstWhere('name', $templateTagValue->name)) {
                throw new InvalidArgumentException("$templateTagValue->name is already defined on class and it is not possible to define method with same name on method [$methodType->name]");
            }

            $methodType->templates[] = new TemplateType(
                name: $templateTagValue->name,
            );
        }

        foreach ($parsedDoc?->getTagsByName('@self-out-type') ?: [] as $item) {
            if (! $item->value instanceof GenericTagValueNode) {
                throw new InvalidArgumentException('@self-out-type value must be '.GenericTagValueNode::class.', '.$item->value::class.' given');
            }

            $selfOutType = PhpDocTypeHelper::parsePhpDocStringToType($item->value->value);

            if (
                ! $selfOutType instanceof Generic
                || $selfOutType->name !== 'self'
            ) {
                throw new InvalidArgumentException('Self out type tag must be generic of "self"');
            }

            $methodDefinition->selfOutType = $selfOutType;
        }

        $this->replaceMethodTemplatesReferences([...$definition->templateTypes, ...$methodType->templates], [
            $methodDefinition->selfOutType,
        ]);

        $definition->methods[$methodType->name] = $methodDefinition;
    }

    /**
     * @param TemplateType[] $templateTypes
     * @param Type[] $typesToReplaceReferencesIn
     */
    private function replaceMethodTemplatesReferences(array $templateTypes, array $typesToReplaceReferencesIn): void
    {
        $templateTypesByName = collect($templateTypes)->keyBy('name');

        foreach ($typesToReplaceReferencesIn as $type) {
            (new TypeWalker)->replace(
                $type,
                fn (Type $t) => $t instanceof ObjectType ? $templateTypesByName->get($t->name) : null,
            );
        }
    }
}
