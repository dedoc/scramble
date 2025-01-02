<?php

namespace Dedoc\Scramble\Infer\FlowNodes;

use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\BooleanType;
use Dedoc\Scramble\Support\Type\CallableStringType;
use Dedoc\Scramble\Support\Type\FloatType;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralBooleanType;
use Dedoc\Scramble\Support\Type\Literal\LiteralFloatType;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\MixedType;
use Dedoc\Scramble\Support\Type\NullType;
use Dedoc\Scramble\Support\Type\Reference\CallableCallReferenceType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\Reference\NewCallReferenceType;
use Dedoc\Scramble\Support\Type\Reference\PropertyFetchReferenceType;
use Dedoc\Scramble\Support\Type\Reference\StaticMethodCallReferenceType;
use Dedoc\Scramble\Support\Type\SelfType;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\Union;
use Dedoc\Scramble\Support\Type\UnknownType;
use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar;
use WeakMap;

class FlowNodeTypeGetter
{
    public function __construct(
        public readonly ?Expr $expression,
        public readonly FlowNode $flowNode,
    )
    {
    }

    public function getType(): Type
    {
        if (! $this->expression) {
            return new NullType;
        }

        if ($type = $this->getExpressionType($this->expression, $this->flowNode)) {
            return $type;
        }

        return $type ?: new UnknownType('???');
    }

    /**
     * This method is used only when getting a type of something that needs traversing flow up. Currently
     * only variables, for example `$x`. Accessing to variable property ($x['foo']) or key ($x->foo) is marked as
     * incomplete type and resolved based on the variable type.
     */
    protected function getFlowNodeType(FlowNode $flowNode): ?Type
    {
        $type = null;

        // When trying out to figure out a type of the variable in place where that variable is getting assigned,
        // look up for antecedent node to avoid loop.
        if ($this->flowNode instanceof AssignmentFlowNode && $this->flowNode->assigningTo($this->expression)) {
            $flowNode = $this->flowNode->antecedents[0] ?? null;
        }

        while (true) {
            if (! $flowNode) {
                break;
            }

            if ($flowNode instanceof TerminateFlowNode) {
                $flowNode = $flowNode->antecedents[0] ?? null;
                continue;
            }

            if ($flowNode instanceof AssignmentFlowNode) {
                $type = $this->getAssignmentFlowType($flowNode);
                if (! $type) {
                    $flowNode = $flowNode->antecedents[0] ?? null;
                    continue;
                }
            }

            if ($flowNode instanceof BranchLabel) {
                $type = $this->getBranchFlowType($flowNode);
            }

            if ($flowNode instanceof BasicFlowNode) {
                $flowNode = $flowNode->antecedents[0] ?? null;
                continue;
            }

            if ($flowNode instanceof ConditionFlowNode) {
                $flowNode = $flowNode->antecedents[0] ?? null;
                continue;
            }

            if ($flowNode instanceof EnterFunctionLikeFlowNode) {
                $type = $this->getEnterFunctionLikeFlowType($flowNode);
            }

            // We're about to leave the function scope and maybe check the parent scope in there are types defined there.
            if (
                ! $type
                && $flowNode instanceof EnterFunctionLikeFlowNode
                && $flowNode->containerAntecedent
                && $flowNode->hasAccessToParent($this->expression)
            ) {
                $flowNode = $flowNode->containerAntecedent;
                continue;
            }

            return $type ?: new UnknownType('Cannot get a type from traversal');
        }

        return $type;
    }

    protected function getEnterFunctionLikeFlowType(EnterFunctionLikeFlowNode $flowNode)
    {
        if (! $parameter = $flowNode->getParameter($this->expression)) {
            return null;
        }

        if ($parameter->type) {
            return TypeHelper::createTypeFromTypeNode($parameter->type);
        }

        return new MixedType;
    }

    protected function getBranchFlowType(BranchLabel $flowNode)
    {
        $types = [];

        foreach ($flowNode->antecedents as $antecedent) {
            $types[] = $this->getFlowNodeType($antecedent);
        }

        return Union::wrap(array_values(array_filter($types)));
    }

    protected function getAssignmentFlowType(AssignmentFlowNode $flowNode)
    {
        if (! $flowNode->assigningTo($this->expression)) {
            return null;
        }

        if ($flowNode->kind === AssignmentFlowNode::KIND_ASSIGN_COMPOUND) {
            return match ($flowNode->expression::class) {
                AssignOp\Concat::class => new StringType,
                AssignOp\BitwiseOr::class, AssignOp\BitwiseXor::class, AssignOp\BitwiseAnd::class => new IntegerType,
                default => (($expressionType = $this->getLocalExpressionType($flowNode->expression->expr)) && ($expressionType instanceof FloatType || $expressionType instanceof IntegerType))
                    ? Union::wrap([new FloatType, new IntegerType])
                    : null,
            };
        }

        return $this->getExpressionType($flowNode->expression->expr, $flowNode);
    }

    public static WeakMap $_alreadySeen;

    protected function getExpressionType(Expr $expression, FlowNode $flowNode): ?Type
    {
        if (! isset(static::$_alreadySeen)) {
            static::$_alreadySeen = new WeakMap();
        }

        if ($localType = $this->getLocalExpressionType($expression)) {
            return $localType;
        }

        if (static::$_alreadySeen->offsetExists($expression)) {
            return static::$_alreadySeen->offsetGet($expression);
        }

        $remember = function ($type) use ($expression) {
            static::$_alreadySeen->offsetSet($expression, $type);
            return $type;
        };

        static::$_alreadySeen->offsetSet($expression, true);

        if ($incompleteType = $this->getIncompleteType($expression, $flowNode)) {
            return $remember($incompleteType);
        }

        if (
            $expression instanceof Expr\Closure
            || $expression instanceof Expr\ArrowFunction
        ) {
            return $remember($this->getAnonymousFunctionType($expression));
        }

        if ($expression instanceof Expr\Variable) {
            return $remember($this->getFlowNodeType($flowNode));
        }

        if ($expression instanceof Expr\Assign) {
            return $remember($this->getExpressionFlowType($expression->expr, $flowNode));
        }

        if ($expression instanceof Expr\AssignOp) {
            return $remember($this->getExpressionFlowType($expression->var, $flowNode));
        }

        if ($expression instanceof Expr\Array_) {
            $arrayItems = array_map(
                fn (ArrayItem $arrayItem) => new ArrayItemType_(
                    key: ($arrayItem->key && ($localKeyType = $this->getLocalExpressionType($arrayItem->key))) ? ($localKeyType->value ?? null) : null,
                    value: $this->getExpressionFlowType($arrayItem->value, $flowNode->antecedents[0] ?? $flowNode),
                    isOptional: false,
                    shouldUnpack: $arrayItem->unpack,
                ),
                $expression->items,
            );
            return $remember(new KeyedArrayType($arrayItems));
        }

        return null;
    }

    protected function getIncompleteType(Expr $expression, FlowNode $flowNode): ?Type
    {
        if ($expression instanceof Expr\Variable && $expression->name === 'this') {
            // @todo: reference a proper class name!
            return new SelfType('self');
        }

        // @todo: variadic placeholder support (represents `...` in `foo(...)`)
        // @todo: unpack
        if ($expression instanceof Expr\FuncCall) {
            return new CallableCallReferenceType(
                $expression->name instanceof Name
                    ? new CallableStringType($expression->name->name)
                    : $this->getExpressionFlowType($expression->name, $flowNode),
                $this->makeIncompleteArguments($expression->getArgs(), $flowNode),
            );
        }

        if (
            $expression instanceof Expr\MethodCall
            && $expression->name instanceof Identifier
        ) {
            return new MethodCallReferenceType(
                $this->getExpressionFlowType($expression->var, $flowNode),
                $expression->name->name,
                $this->makeIncompleteArguments($expression->getArgs(), $flowNode),
            );
        }

        if (
            $expression instanceof Expr\StaticCall
            && $expression->class instanceof Name
            && $expression->name instanceof Identifier
        ) {
            return new StaticMethodCallReferenceType(
                $expression->class->name,
                $expression->name->name,
                $this->makeIncompleteArguments($expression->getArgs(), $flowNode),
            );
        }

        if (
            $expression instanceof Expr\New_
            && $expression->class instanceof Name
        ) {
            return new NewCallReferenceType(
                $expression->class->name,
                $this->makeIncompleteArguments($expression->getArgs(), $flowNode),
            );
        }

        if (
            $expression instanceof Expr\PropertyFetch
            && $expression->name instanceof Identifier
        ) {
            return new PropertyFetchReferenceType(
                $this->getExpressionFlowType($expression->var, $flowNode),
                $expression->name->name,
            );
        }

        return null;
    }

    protected function makeIncompleteArguments(array $arguments, FlowNode $flowNode)
    {
        // The possible optimization here is to make some sort of promise and postpone the arguments
        // incomplete types resolution till the moment we actually need them - if the function return type includes
        // some argument template types. Unless that, we don't need to analyze it at all!

        // @todo: variadic placeholder support (represents `...` in `foo(...)`)
        // @todo: unpack
        $arguments = array_values(array_filter(
            $arguments,
            fn ($a) => $a instanceof Arg,
        ));

        $result = [];
        /** @var Arg $argument  */
        foreach ($arguments as $index => $argument) {
            $result[$argument->name ?: $index] = $this->getExpressionFlowType($argument->value, $flowNode);
        }
        return $result;
    }

    protected function getExpressionFlowType(Expr $expr, FlowNode $flowNode)
    {
        return (new static($expr, $flowNode))->getType();
    }

    protected function getLocalExpressionType(Expr $expression): ?Type
    {
        if (
            ! $expression instanceof Expr\ConstFetch
            && ! $expression instanceof Scalar
            && ! $expression instanceof Expr\Cast
        ) {
            return null;
        }

        if ($expression instanceof Scalar\Int_) {
            return new LiteralIntegerType($expression->value);
        }

        if ($expression instanceof Scalar\String_) {
            return new LiteralStringType($expression->value);
        }

        if ($expression instanceof Scalar\Float_) {
            return new LiteralFloatType($expression->value);
        }

        if ($expression instanceof Expr\ConstFetch) {
            if ($expression->name->name === 'null') {
                return new NullType;
            }
            if ($expression->name->name === 'true') {
                return new LiteralBooleanType(true);
            }
            if ($expression->name->name === 'false') {
                return new LiteralBooleanType(false);
            }
        }

        if ($expression instanceof Expr\Cast) {
            if ($expression instanceof Expr\Cast\Int_) {
                return new IntegerType;
            }
            if ($expression instanceof Expr\Cast\String_) {
                return new StringType;
            }
            if ($expression instanceof Expr\Cast\Bool_) {
                return new BooleanType;
            }
            if ($expression instanceof Expr\Cast\Double) {
                return new FloatType;
            }
        }

        return null;
    }

    private function getAnonymousFunctionType(Expr\Closure|Expr\ArrowFunction $expression)
    {
        if (! $flowContainer = $expression->getAttribute('flowContainer')) {
            return null;
        }

        /** @var FlowNodes $flowContainer */

        $returnType = (new IncompleteTypeGetter())->getFunctionReturnType($flowContainer->nodes);
        $parameters = $flowContainer->nodes[0] instanceof EnterFunctionLikeFlowNode
            ? $flowContainer->nodes[0]->getParametersTypesDeclaration()
            : [];

        return new FunctionType(
            'anonymous',
            $parameters,
            $returnType,
        );
    }
}
