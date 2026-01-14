<?php

namespace Dedoc\Scramble\Infer\Flow;

use Closure;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Infer\SimpleTypeGetters\BooleanNotTypeGetter;
use Dedoc\Scramble\Infer\SimpleTypeGetters\CastTypeGetter;
use Dedoc\Scramble\Infer\SimpleTypeGetters\ConstFetchTypeGetter;
use Dedoc\Scramble\Infer\SimpleTypeGetters\ScalarTypeGetter;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\BooleanType;
use Dedoc\Scramble\Support\Type\CallableStringType;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\OffsetAccessType;
use Dedoc\Scramble\Support\Type\Reference\CallableCallReferenceType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\Reference\NewCallReferenceType;
use Dedoc\Scramble\Support\Type\Reference\PropertyFetchReferenceType;
use Dedoc\Scramble\Support\Type\Reference\StaticMethodCallReferenceType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\Union;
use Dedoc\Scramble\Support\Type\UnknownType;
use Dedoc\Scramble\Support\Type\VoidType;
use PhpParser\ConstExprEvaluationException;
use PhpParser\ConstExprEvaluator;
use PhpParser\Node as PhpParserNode;
use PhpParser\Node\Expr;

class ExpressionTypeInferer
{
    public function __construct(
        private Scope $scope,
    ) {}

    public function infer(Expr $expr, Closure $variableTypeGetter): Type
    {
        return match (true) {
            $expr instanceof PhpParserNode\Scalar => (new ScalarTypeGetter)($expr),
            $expr instanceof PhpParserNode\Expr\Cast => (new CastTypeGetter)($expr),
            $expr instanceof PhpParserNode\Expr\ConstFetch => (new ConstFetchTypeGetter)($expr),
            $expr instanceof PhpParserNode\Expr\Throw_ => new VoidType,
            $expr instanceof PhpParserNode\Expr\Ternary => Union::wrap([
                $this->infer($expr->if ?? $expr->cond, $variableTypeGetter),
                $this->infer($expr->else, $variableTypeGetter),
            ]),
            $expr instanceof PhpParserNode\Expr\BinaryOp\Coalesce => Union::wrap([
                $this->infer($expr->left, $variableTypeGetter),
                $this->infer($expr->right, $variableTypeGetter),
            ]),
            $expr instanceof PhpParserNode\Expr\Match_ => Union::wrap(array_map(
                fn (PhpParserNode\MatchArm $arm) => $this->infer($arm->body, $variableTypeGetter),
                $expr->arms,
            )),
            // @todo
            // $expr instanceof PhpParserNode\Expr\ClassConstFetch => (new ClassConstFetchTypeGetter)($expr, $scope),
            $expr instanceof PhpParserNode\Expr\BooleanNot => (new BooleanNotTypeGetter)($expr),
            $expr instanceof PhpParserNode\Expr\BinaryOp\Equal
                || $expr instanceof PhpParserNode\Expr\BinaryOp\Identical
                || $expr instanceof PhpParserNode\Expr\BinaryOp\NotEqual
                || $expr instanceof PhpParserNode\Expr\BinaryOp\NotIdentical
                || $expr instanceof PhpParserNode\Expr\BinaryOp\Greater
                || $expr instanceof PhpParserNode\Expr\BinaryOp\GreaterOrEqual
                || $expr instanceof PhpParserNode\Expr\BinaryOp\Smaller
                || $expr instanceof PhpParserNode\Expr\BinaryOp\SmallerOrEqual => new BooleanType,
            $expr instanceof PhpParserNode\Expr\New_ => $this->createNewReferenceType($expr, $variableTypeGetter),
            $expr instanceof PhpParserNode\Expr\MethodCall => $this->createMethodCallReferenceType($expr, $variableTypeGetter),
            $expr instanceof PhpParserNode\Expr\StaticCall => $this->createStaticMethodCallReferenceType($expr, $variableTypeGetter),
            $expr instanceof PhpParserNode\Expr\PropertyFetch => $this->createPropertyFetchReferenceType($expr, $variableTypeGetter),
            $expr instanceof PhpParserNode\Expr\FuncCall => $this->createCallableCallReferenceType($expr, $variableTypeGetter),
            $expr instanceof PhpParserNode\Expr\ArrayDimFetch => $this->createOffsetAccessType($expr, $variableTypeGetter),
            $expr instanceof PhpParserNode\Expr\Array_ => $this->createArrayType($expr, $variableTypeGetter),
            default => new UnknownType,
        };
    }

    private function createNewReferenceType(PhpParserNode\Expr\New_ $expr, Closure $variableTypeGetter): NewCallReferenceType
    {
        if (! $expr->class instanceof PhpParserNode\Name) {
            return new NewCallReferenceType(
                $this->infer($expr->class, $variableTypeGetter),
                $this->inferArgsTypes($expr->args, $variableTypeGetter),
            );
        }

        return new NewCallReferenceType(
            $expr->class->toString(),
            $this->inferArgsTypes($expr->args, $variableTypeGetter),
        );
    }

    private function createMethodCallReferenceType(PhpParserNode\Expr\MethodCall $expr, Closure $variableTypeGetter): Type
    {
        // Only string method names support.
        if (! $expr->name instanceof PhpParserNode\Identifier) {
            return new UnknownType;
        }

        return new MethodCallReferenceType(
            $this->infer($expr->var, $variableTypeGetter),
            $expr->name->name,
            $this->inferArgsTypes($expr->args, $variableTypeGetter),
        );
    }

    private function createStaticMethodCallReferenceType(PhpParserNode\Expr\StaticCall $expr, Closure $variableTypeGetter): Type
    {
        // Only string method names support.
        if (! $expr->name instanceof PhpParserNode\Identifier) {
            return new UnknownType;
        }

        if (! $expr->class instanceof PhpParserNode\Name) {
            return new StaticMethodCallReferenceType(
                $this->infer($expr->class, $variableTypeGetter),
                $expr->name->name,
                $this->inferArgsTypes($expr->args, $variableTypeGetter),
            );
        }

        return new StaticMethodCallReferenceType(
            $expr->class->toString(),
            $expr->name->name,
            $this->inferArgsTypes($expr->args, $variableTypeGetter),
        );
    }

    private function createPropertyFetchReferenceType(PhpParserNode\Expr\PropertyFetch $expr, Closure $variableTypeGetter): Type
    {
        // Only string prop names support.
        if (! $name = ($expr->name->name ?? null)) {
            return new UnknownType('Cannot infer type of property fetch: not supported yet.');
        }

        return new PropertyFetchReferenceType($this->infer($expr->var, $variableTypeGetter), $name);
    }

    private function createCallableCallReferenceType(PhpParserNode\Expr\FuncCall $expr, Closure $variableTypeGetter): Type
    {
        if ($expr->name instanceof PhpParserNode\Name) {
            return new CallableCallReferenceType(
                new CallableStringType($expr->name->toString()),
                $this->inferArgsTypes($expr->args, $variableTypeGetter),
            );
        }

        return new CallableCallReferenceType(
            $this->infer($expr->name, $variableTypeGetter),
            $this->inferArgsTypes($expr->args, $variableTypeGetter),
        );
    }

    /**
     * When `dim` is empty, it means that the context is setting – handling in AssignHandler.
     *
     * @see AssignHandler
     */
    private function createOffsetAccessType(PhpParserNode\Expr\ArrayDimFetch $expr, Closure $variableTypeGetter): Type
    {
        if (! $expr->dim) {
            return new UnknownType('ArrayDimFetch without dimension is handled in AssignHandler');
        }

        return new OffsetAccessType(
            $this->infer($expr->var, $variableTypeGetter),
            $this->infer($expr->dim, $variableTypeGetter),
        );
    }

    private function createArrayType(PhpParserNode\Expr\Array_ $expr, Closure $variableTypeGetter): Type
    {
        $arrayItems = collect($expr->items)
            ->filter()
            ->map(fn (PhpParserNode\Expr\ArrayItem $arrayItem) => $this->inferArrayItem($arrayItem, $variableTypeGetter))
            ->all();

        return TypeHelper::unpackIfArray(new KeyedArrayType($arrayItems));
    }

    private function inferArrayItem(PhpParserNode\Expr\ArrayItem $arrayItem, Closure $variableTypeGetter): ArrayItemType_
    {
        $keyType = $arrayItem->key ? $this->infer($arrayItem->key, $variableTypeGetter) : null;
        $valueType = $this->infer($arrayItem->value, $variableTypeGetter);

        // Try to evaluate the key to a constant value if possible
        $evaluatedKey = $this->evaluateKey($arrayItem->key);

        return new ArrayItemType_(
            key: $evaluatedKey,
            value: $valueType,
            isOptional: false,
            shouldUnpack: $arrayItem->unpack,
            keyType: $keyType,
        );
    }

    /**
     * Attempts to evaluate the key to a constant value (int|string|null).
     * Returns null if the key cannot be evaluated to a constant.
     *
     * Note: ClassConstFetch with 'self'/'static' cannot be evaluated without Scope context.
     */
    private function evaluateKey(?PhpParserNode\Expr $key): int|string|null
    {
        if (! $key) {
            return null;
        }

        // Handle simple scalar keys directly
        if ($key instanceof PhpParserNode\Scalar\String_) {
            return $key->value;
        }

        if ($key instanceof PhpParserNode\Scalar\LNumber) {
            return $key->value;
        }

        // Try to evaluate more complex constant expressions using ConstExprEvaluator
        // Note: ClassConstFetch with 'self'/'static' will fail without Scope context
        $evaluator = new ConstExprEvaluator(function (PhpParserNode\Expr $node) {
            // We can't handle ClassConstFetch with 'self'/'static' without Scope
            // Other constant expressions will be handled by ConstExprEvaluator
            return null;
        });

        try {
            $result = $evaluator->evaluateSilently($key);

            return is_string($result) || is_int($result) ? $result : null;
        } catch (ConstExprEvaluationException) {
            return null;
        }
    }

    /**
     * @param  array<PhpParserNode\Arg|PhpParserNode\VariadicPlaceholder>  $args
     * @return array<string, Type>
     */
    private function inferArgsTypes(array $args, Closure $variableTypeGetter): array
    {
        return collect($args)
            ->filter(fn ($arg) => $arg instanceof PhpParserNode\Arg)
            ->mapWithKeys(function (PhpParserNode\Arg $arg, $index) use ($variableTypeGetter) {
                $type = $this->infer($arg->value, $variableTypeGetter);
                if ($parsedPhpDoc = $arg->getAttribute('parsedPhpDoc')) {
                    $type->setAttribute('docNode', $parsedPhpDoc);
                }

                if (! $arg->unpack) {
                    return [$arg->name ? $arg->name->name : $index => $type];
                }

                if (! $type instanceof ArrayType && ! $type instanceof KeyedArrayType) {
                    return [$arg->name ? $arg->name->name : $index => $type]; // falling back, but not sure if we should. Maybe some DTO is needed to represent unpacked arg type?
                }

                if ($type instanceof ArrayType) {
                    /*
                     * For example, when passing something that is array, but shape is unknown
                     * $a = foo(...array_keys($bar));
                     */
                    return [$arg->name ? $arg->name->name : $index => $type]; // falling back, but not sure if we should. Maybe some DTO is needed to represent unpacked arg type?
                }

                return collect($type->items)
                    ->mapWithKeys(fn (ArrayItemType_ $item, $i) => [
                        $item->key ?: $index + $i => $item->value,
                    ])
                    ->all();
            })
            ->all();
    }
}
