<?php

namespace Dedoc\Scramble\Infer\Handler;

use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\BooleanType;
use Dedoc\Scramble\Support\Type\FloatType;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\UnknownType;
use Dedoc\Scramble\Support\Type\VoidType;
use PhpParser\Node;
use PhpParser\Node\FunctionLike;

class FunctionLikeHandler implements CreatesScope
{
    public function shouldHandle($node)
    {
        return $node instanceof FunctionLike;
    }

    public function createScope(Scope $scope, Node $node): Scope
    {
        $fnScope = $scope->createChildScope(clone $scope->context);

        if ($node instanceof Node\Expr\ArrowFunction) {
            $fnScope->variables = $scope->variables;
        }

        if ($node instanceof Node\Expr\Closure) {
            foreach ($node->uses as $use) {
                $fnScope->variables[$use->var->name] = $scope->variables[$use->var->name] ?? [];
            }
        }

        return $fnScope;
    }

    public function enter(FunctionLike $node, Scope $scope)
    {
        // when entering function node, the only thing we need/want to do
        // is to set node param types to scope.
        // Also, if here we add a reference to the function node type, it may allow us to
        // set function return types not in leave function, but in the return handlers.
        $scope->setType($node, $fnType = new FunctionType($node->name->name ?? 'anonymous'));
        if (isset($node->name->name)) {
            $scope->index->registerFunctionType($node->name->toString(), $fnType);
        }

        $fnType->arguments = collect($node->getParams())
            ->mapWithKeys(function (Node\Param $param) {
                return $param->var instanceof Node\Expr\Variable ? [
                    $param->var->name => isset($param->type)
                        ? TypeHelper::createTypeFromTypeNode($param->type)
                        : new UnknownType,
                ] : [];
            })
            ->toArray();

        foreach ($node->getParams() as $param) {
            if (! $param->var instanceof Node\Expr\Variable) {
                continue;
            }

            $scope->addVariableType(
                $param->getAttribute('startLine'),
                (string) $param->var->name,
                isset($param->default)
                    ? $scope->getType($param->default)
                    : (isset($param->type)
                    ? TypeHelper::createTypeFromTypeNode($param->type)
                    : new UnknownType),
            );
        }

        $scope->context->setFunction($fnType);
    }

    public function leave(FunctionLike $node, Scope $scope)
    {
        $type = $scope->context->function;

        /*
         * @todo
         *
         * Here we may not need to go deep in the fn and analyze nodes as we already know the type from
         * the annotation. The problem is that almost always annotated type is not specific enough to be
         * useful for analysis.
         */
        if (
            ($returnTypeAnnotation = $node->getReturnType())
            && (
                in_array(get_class($type->getReturnType()), [
                    UnknownType::class,
                    VoidType::class, // When fn is not analyzed (?)
                ])
                || in_array(get_class(TypeHelper::createTypeFromTypeNode($returnTypeAnnotation)), [
                    IntegerType::class,
                    FloatType::class,
                    BooleanType::class,
                ])
            )
        ) {
            $type->setReturnType(TypeHelper::createTypeFromTypeNode($returnTypeAnnotation) ?: new VoidType);
        }

        // Simple way of handling the arrow functions, as they do not have a return statement.
        // So here we just create a "virtual" return and processing it as by default.
        if ($node instanceof Node\Expr\ArrowFunction) {
            (new ReturnHandler)->leave(
                new Node\Stmt\Return_($node->expr, $node->getAttributes()),
                $scope,
            );
        }

        // In case of method in class being analyzed, we want to attach the method information
        // to the class so classes can be analyzed later.
        if ($node instanceof Node\Stmt\ClassMethod) {
            // @todo: remove as this should not happen - class must be always there
            if (! $scope->context->class) {
                return;
            }
            $scope->context->class->methods = array_merge(
                $scope->context->class->methods,
                [$node->name->name => $type],
            );
        }
    }
}
