<?php

namespace Dedoc\Scramble\Infer\Definition;

use Dedoc\Scramble\Infer\Analyzer\MethodAnalyzer;
use Dedoc\Scramble\Infer\Contracts\ClassDefinition as ClassDefinitionContract;
use Dedoc\Scramble\Infer\DefinitionBuilders\FunctionLikeReflectionDefinitionBuilder;
use Dedoc\Scramble\Infer\Reflector\ClassReflector;
use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Infer\Scope\NodeTypesResolver;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Infer\Scope\ScopeContext;
use Dedoc\Scramble\Infer\Services\FileNameResolver;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\IndexBuilders\IndexBuilder;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\Contracts\LateResolvingType;
use Dedoc\Scramble\Support\Type\FunctionLikeType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Dedoc\Scramble\Support\Type\Union;
use Dedoc\Scramble\Support\Type\UnknownType;
use LogicException;
use PhpParser\ErrorHandler\Throwing;
use PhpParser\NameContext;

class LazyClassDefinition extends ClassDefinition
{
    public function getMethod(string $name): ?FunctionLikeDefinition
    {
        if (array_key_exists($name, $this->methods)) {
            return parent::getMethod($name);
        }

        if (! $classReflection = rescue(fn () => new \ReflectionClass($this->name))) {
            return null;
        }
        /** @var \ReflectionClass $classReflection */
        if (! $methodReflection = rescue(fn () => $classReflection->getMethod($name))) {
            return null;
        }
        /** @var \ReflectionMethod $methodReflection */

        return $this->methods[$name] = (new FunctionLikeReflectionDefinitionBuilder(
            $name,
            $methodReflection,
        ))->build();
    }
}
