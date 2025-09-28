<?php

namespace Dedoc\Scramble\Infer\Definition;

use Dedoc\Scramble\Infer\DefinitionBuilders\FunctionLikeReflectionDefinitionBuilder;
use Dedoc\Scramble\Infer\Reflector\ClassReflector;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Infer\Services\FileNameResolver;
use Dedoc\Scramble\PhpDoc\PhpDocTypeHelper;
use Dedoc\Scramble\Support\PhpDoc;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PHPStan\PhpDocParser\Ast\PhpDoc\ExtendsTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\UsesTagValueNode;

class LazyClassDefinition extends ClassDefinition
{
    private Index $index;

    public function setIndex(Index $index): void
    {
        $this->index = $index;
    }

    public function getMethod(string $name): ?FunctionLikeDefinition
    {
        if (array_key_exists($name, $this->methods)) {
            return parent::getMethod($name);
        }

        if (! $classReflection = rescue(fn () => new \ReflectionClass($this->name), report: false)) {
            return null;
        }
        /** @var \ReflectionClass $classReflection */
        if (! $methodReflection = rescue(fn () => $classReflection->getMethod($name), report: false)) {
            return null;
        }
        /** @var \ReflectionMethod $methodReflection */

        return $this->methods[$name] = (new FunctionLikeReflectionDefinitionBuilder(
            $name,
            $methodReflection,
            collect($this->templateTypes)->keyBy->name
                ->merge($this->getMethodContextTemplates($methodReflection)),
        ))->build();
    }

    private function getMethodContextTemplates(\ReflectionMethod $methodReflection): Collection
    {
        $classContexts = $this->getClassContexts();

        return $classContexts->get($methodReflection->class, collect());
    }

    private Collection $classContexts;

    public function getClassContexts()
    {
        if (isset($this->classContexts)) {
            return $this->classContexts;
        }

        $classContexts = collect();

        $reflector = ClassReflector::make($this->name);

        $classSource = ($reflector->getReflection()->getDocComment() ?: '')."\n".rescue($reflector->getSource(...), '', report: false);

        $contextPhpDoc = Str::matchAll(
            '/@use|@extends\s+[^\r\n*]+/',
            $classSource,
        )->map(fn ($s) => " * $s")
            ->prepend("/**")->push("*/")->join("\n");

        $phpDoc = PhpDoc::parse(
            $contextPhpDoc,
            new FileNameResolver($reflector->getNameContext()),
        );

        /** @var (ExtendsTagValueNode|UsesTagValueNode)[] $tags */
        $tags = [
            ...array_values($phpDoc->getExtendsTagValues()),
            ...array_values($phpDoc->getUsesTagValues()),
        ];

        foreach ($tags as $tag) {
            $type = PhpDocTypeHelper::toType($tag->type);

            if (! $type instanceof Generic) {
                continue;
            }

            if (! $definition = $this->index->getClass($type->name)) {
                continue;
            }

            $classContext = collect();
            foreach ($definition->templateTypes as $i => $templateType) {
                $classContext->offsetSet(
                    $templateType->name,
                    $type->templateTypes[$i] ?? $templateType->default ?? new UnknownType,
                );
            }
            $classContexts->offsetSet($type->name, $classContext);
        }

        return $this->classContexts = $classContexts;
    }

}
