<?php

namespace Dedoc\Scramble\Infer\DefinitionBuilders;

use Dedoc\Scramble\Infer\Contracts\AstLocator;
use Dedoc\Scramble\Infer\Contracts\ClassDefinition as ClassDefinitionContract;
use Dedoc\Scramble\Infer\Contracts\FunctionLikeDefinition as FunctionLikeDefinitionContract;
use Dedoc\Scramble\Infer\Definition\ClassDefinition as ClassDefinitionData;
use Dedoc\Scramble\Infer\Symbol;
use PhpParser\Node\Stmt\ClassMethod;

class ClassDeferredDefinition implements ClassDefinitionContract
{
    public function __construct(
        public ClassDefinitionContract $definition,
        private ClassAstDefinitionBuilder $builder,
        private AstLocator $astLocator,
    ) {}

    public function getData(): ClassDefinitionData
    {
        return $this->definition->getData();
    }

    public function getMethod(string $name): ?FunctionLikeDefinitionContract
    {
        if (! $methodDefinition = $this->definition->getMethod($name)) {
            return null;
        }

        if ($methodDefinition->getData()->getAttribute('hasAnalyzedBody')) {
            return $methodDefinition;
        }

        /** @var ClassMethod $methodNode */
        $methodNode = $this->astLocator->getSource(Symbol::createForMethod($this->definition->getData()->name, $name));

        $methodDefinition = $this->builder->buildMethodDefinitionAndUpdateClassDefinitionData(
            $this->getData(),
            $methodNode,
        );

        $methodDefinition->getData()->setAttribute('hasAnalyzedBody', true);

        return $methodDefinition;
    }
}
