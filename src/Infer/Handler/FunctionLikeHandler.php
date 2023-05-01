<?php

namespace Dedoc\Scramble\Infer\Handler;

use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\BooleanType;
use Dedoc\Scramble\Support\Type\FloatType;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\UnknownType;
use Dedoc\Scramble\Support\Type\VoidType;
use Illuminate\Support\Str;
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
        $scope->context->setFunctionDefinition($fnDefinition = new FunctionLikeDefinition(
            type: $fnType = new FunctionType($node->name->name ?? 'anonymous'),
            sideEffects: [],
        ));

        if ($node instanceof Node\Expr\ArrowFunction || $node instanceof Node\Expr\Closure) {
            $scope->setType($node, $fnType);
        }

        if (isset($node->name->name) && $node instanceof Node\Stmt\Function_) {
            $scope->index->registerFunctionDefinition($fnDefinition);
        }

        // If the function is __construct and we're in the class context, we want to handle
        // simple assigning of args to props ("simple" - assigning is in the fn's statements, means
        // it is not in if or other block) by setting args types to be prop's ones.
        // Also, here we find calls to `parent::__construct` and infer args class' templates types from there.
        $classDefinitionTemplatesTypes = $this->findPropertyAssignedArgs($node, $scope, $fnType);

        $localTemplates = [];
        $fnType->arguments = collect($node->getParams())
            ->mapWithKeys(function (Node\Param $param) use ($scope, $classDefinitionTemplatesTypes, &$localTemplates) {
                if (! $param->var instanceof Node\Expr\Variable) {
                    return [];
                }

                if (array_key_exists($param->var->name, $classDefinitionTemplatesTypes)) {
                    return [$param->var->name => $classDefinitionTemplatesTypes[$param->var->name]];
                }

                $type = isset($param->type)
                    ? TypeHelper::createTypeFromTypeNode($param->type)
                    : new TemplateType($scope->makeConflictFreeTemplateName('T'.Str::studly($param->var->name)));

                if ($type instanceof TemplateType) {
                    $localTemplates[] = $type;
                }

                return [$param->var->name => $type];
            })
            ->toArray();

        $fnType->templates = $localTemplates;

        foreach ($node->getParams() as $param) {
            if (! $param->var instanceof Node\Expr\Variable) {
                continue;
            }

            $scope->addVariableType(
                $param->getAttribute('startLine'),
                $paramName = (string) $param->var->name,
                isset($param->default)
                    ? $scope->getType($param->default)
                    : $fnType->arguments[$paramName],
            );
        }

        if ($scope->isInClass() && $node instanceof Node\Stmt\ClassMethod) {
            $scope->classDefinition()->methods[$fnType->name] = $fnDefinition;
        }
    }

    public function leave(FunctionLike $node, Scope $scope)
    {
        $fnDefinition = $scope->functionDefinition();

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
                in_array(get_class($fnDefinition->type->getReturnType()), [
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
            $fnDefinition->type->setReturnType(TypeHelper::createTypeFromTypeNode($returnTypeAnnotation) ?: new VoidType);
        }

        // Simple way of handling the arrow functions, as they do not have a return statement.
        // So here we just create a "virtual" return and processing it as by default.
        if ($node instanceof Node\Expr\ArrowFunction) {
            (new ReturnHandler)->leave(
                new Node\Stmt\Return_($node->expr, $node->getAttributes()),
                $scope,
            );
        }
    }

    private function findPropertyAssignedArgs(FunctionLike $node, Scope $scope, FunctionType $fnType)
    {
        if (! $scope->isInClass()) {
            return [];
        }

        if ($fnType->name !== '__construct') {
            return [];
        }

        $argumentsByKeys = collect($node->getParams())
            ->mapWithKeys(function (Node\Param $param) {
                return $param->var instanceof Node\Expr\Variable ? [
                    $param->var->name => true,
                ] : [];
            })
            ->toArray();

        $argumentsAssignedToProperties = [];

        $callToParentConstruct = $scope->classDefinition()->parentFqn ? array_filter(
            $node->getStmts() ?: [],
            fn (Node\Stmt $s) => $s instanceof Node\Stmt\Expression
                && $s->expr instanceof Node\Expr\StaticCall
                && $s->expr->class instanceof Node\Name
                && $s->expr->class->toString() === 'parent'
                && $s->expr->name instanceof Node\Identifier
                && $s->expr->name->toString() === '__construct',
        )[0] ?? null : null;

        if (
            $callToParentConstruct
            && ($parentDefinition = $scope->index->getClassDefinition($scope->classDefinition()->parentFqn))
            && ($parentConstructorDefinition = $parentDefinition->methods['__construct'] ?? null)
        ) {
            $parentConstructorArguments = $parentConstructorDefinition->type->arguments;

            foreach ($callToParentConstruct->expr->args as $index => $arg) {
                if (! $arg->value instanceof Node\Expr\Variable) {
                    continue;
                }

                $correspondingParentArgumentType = $arg->name
                    ? ($parentConstructorArguments[$arg->name->toString()] ?? null)
                    : (array_values($parentConstructorArguments)[$index] ?? null);

                if (! $correspondingParentArgumentType) {
                    continue;
                }

                $argumentsAssignedToProperties[$arg->value->name] = $correspondingParentArgumentType;
            }
        }

        $assignPropertiesToThisNodes = array_filter(
            $node->getStmts() ?: [],
            fn (Node\Stmt $s) => $s instanceof Node\Stmt\Expression
                && $s->expr instanceof Node\Expr\Assign
                && $s->expr->var instanceof Node\Expr\PropertyFetch
                && $s->expr->var->var instanceof Node\Expr\Variable
                && $s->expr->var->var->name === 'this'
                && $s->expr->var->name instanceof Node\Identifier
                && $s->expr->expr instanceof Node\Expr\Variable
                && ($argumentsByKeys[$s->expr->expr->name] ?? false),
        );

        return array_reduce($assignPropertiesToThisNodes, function ($acc, Node\Stmt\Expression $s) use ($scope) {
            $propName = $s->expr->var->name->name;

            if (! array_key_exists($propName, $scope->classDefinition()->properties)) {
                return $acc;
            }

            $acc[$s->expr->expr->name] = $scope->classDefinition()->properties[$propName]->type;

            return $acc;
        }, $argumentsAssignedToProperties);
    }
}
