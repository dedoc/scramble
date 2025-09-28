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
use PHPStan\PhpDocParser\Ast\PhpDoc\MixinTagValueNode;
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

        if (! $methodReflection = $this->findReflectionMethod($name)) {
            return null;
        }

        return $this->methods[$name] = (new FunctionLikeReflectionDefinitionBuilder(
            $name,
            $methodReflection,
            collect($this->templateTypes)->keyBy->name
                ->merge($this->getMethodContextTemplates($methodReflection)),
        ))->build();
    }

    private function findReflectionMethod(string $name): ?\ReflectionMethod
    {
        /** @var \ReflectionClass|null $classReflection */
        $classReflection = rescue(fn () => new \ReflectionClass($this->name), report: false);
        /** @var \ReflectionMethod|null $methodReflection */
        $methodReflection = rescue(fn () => $classReflection?->getMethod($name), report: false);

        // The case when method is defined in the class or its parents.
        if ($methodReflection) {
            return $methodReflection;
        }

        foreach ($this->getClassContexts()->keys() as $class) {
            /** @var \ReflectionClass|null $classReflection */
            $classReflection = rescue(fn () => new \ReflectionClass($class), report: false);
            /** @var \ReflectionMethod|null $methodReflection */
            $methodReflection = rescue(fn () => $classReflection?->getMethod($name), report: false);

            if ($methodReflection) {
                return $methodReflection;
            }
        }

        return null;
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
            '/@(?:use|extends|mixin)\s+[^\r\n*]+/',
            $classSource,
        )->map(fn ($s) => " * $s")->prepend("/**")->push("*/")->join("\n");

        $phpDoc = PhpDoc::parse(
            $contextPhpDoc,
            new FileNameResolver($reflector->getNameContext()),
        );

        /** @var (ExtendsTagValueNode|UsesTagValueNode|MixinTagValueNode)[] $tags */
        $tags = [
            ...array_values($phpDoc->getExtendsTagValues()),
            ...array_values($phpDoc->getUsesTagValues()),
            ...array_values($phpDoc->getMixinTagValues()),
        ];

        foreach ($tags as $tag) {
            $type = PhpDocTypeHelper::toType($tag->type);

            if (! $type instanceof Generic) {
                $classContexts->offsetSet($type->name, collect());
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
