<?php

namespace Dedoc\Scramble\Infer\Analyzer;

use Dedoc\Scramble\Infer\Context;
use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Reflector\PropertyReflector;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Infer\Scope\NodeTypesResolver;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Infer\Scope\ScopeContext;
use Dedoc\Scramble\Infer\Services\FileNameResolver;
use Dedoc\Scramble\Infer\TypeInferer;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Illuminate\Support\Arr;
use PhpParser\Node\PropertyItem;
use PhpParser\NodeTraverser;
use ReflectionProperty;

class PropertyAnalyzer
{
    public function __construct(private ReflectionProperty $reflectionProperty) {}

    public static function from(ReflectionProperty $reflectionProperty): self
    {
        return new self($reflectionProperty);
    }

    public function getDefaultType(): ?Type
    {
        if (! $this->reflectionProperty->hasDefaultValue()) {
            return null;
        }

        if ($astType = $this->getDefaultTypeFromAst()) {
            return $astType;
        }

        return TypeHelper::createTypeFromValue($this->reflectionProperty->getDefaultValue());
    }

    private function getDefaultTypeFromAst(): ?Type
    {
        $reflector = PropertyReflector::make($this->reflectionProperty->getDeclaringClass()->name, $this->reflectionProperty->name);

        $astNode = $reflector->getAstNode();
        if (! $astNode) {
            return null;
        }

        $propertyItem = collect($astNode->props)->first(fn (PropertyItem $p) => $p->name->name === $this->reflectionProperty->name);
        if (! $propertyItem instanceof PropertyItem) {
            return null;
        }

        $index = app(Index::class);
        $traverser = new NodeTraverser;
        $nameResolver = new FileNameResolver($reflector->getClassReflector()->getNameContext());
        $traverser->addVisitor($inferer = new TypeInferer(
            $index,
            $nameResolver,
            $scope = new Scope($index, new NodeTypesResolver, new ScopeContext(new ClassDefinition($this->reflectionProperty->getDeclaringClass()->name)), $nameResolver),
            Context::getInstance()->extensionsBroker->extensions,
        ));
        $traverser->traverse(Arr::wrap($propertyItem));

        if (! $propertyItem->default) {
            return null;
        }

        return $scope->getType($propertyItem->default);
    }
}
