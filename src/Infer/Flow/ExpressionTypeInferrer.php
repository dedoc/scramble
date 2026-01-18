<?php

namespace Dedoc\Scramble\Infer\Flow;

use Closure;
use Dedoc\Scramble\Infer\Scope\NodeTypesResolver;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Infer\SimpleTypeGetters\BooleanNotTypeGetter;
use Dedoc\Scramble\Infer\SimpleTypeGetters\CastTypeGetter;
use Dedoc\Scramble\Infer\SimpleTypeGetters\ClassConstFetchTypeGetter;
use Dedoc\Scramble\Infer\SimpleTypeGetters\ConstFetchTypeGetter;
use Dedoc\Scramble\Infer\SimpleTypeGetters\ScalarTypeGetter;
use Dedoc\Scramble\Support\OperationExtensions\RulesEvaluator\ConstFetchEvaluator;
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
use Dedoc\Scramble\Support\Type\SelfType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\Union;
use Dedoc\Scramble\Support\Type\UnknownType;
use Dedoc\Scramble\Support\Type\VoidType;
use PhpParser\ConstExprEvaluationException;
use PhpParser\ConstExprEvaluator;
use PhpParser\Node as PhpParserNode;
use PhpParser\Node\Expr;

/**
 * @internal
 */
class ExpressionTypeInferrer
{
    public function __construct(
        private Scope $scope,
        private NodeTypesResolver $nodeTypesResolver,
    ) {}

    /**
     * Ideally, `infer` should accept not Node but just expressions. @todo
     */
    public function infer(PhpParserNode $expr, Closure $variableTypeGetter): Type
    {
        $type = $this->doInfer($expr, $variableTypeGetter);

        if ($type instanceof UnknownType) {
            return $type;
        }

        if (! $expr instanceof Expr\Variable) {
            $this->nodeTypesResolver->setType($expr, $type);
        }

        return $type;
    }

    public function doInfer(PhpParserNode $expr, Closure $variableTypeGetter): Type
    {
        if ($expr instanceof Expr\Variable && $expr->name === 'this') {
            return new SelfType($this->scope->classDefinition()?->name ?: 'unknown');
        }

        if ($expr instanceof Expr\Variable) {
            return $variableTypeGetter($expr);
        }

        $type = $this->nodeTypesResolver->getType($expr);

        if (! $type instanceof UnknownType) {
            return $type;
        }

        if ($this->nodeTypesResolver->hasType($expr)) { // For case when the unknown type was in node type resolver.
            return $type;
        }

        if ($expr instanceof PhpParserNode\Scalar) {
            return (new ScalarTypeGetter)($expr);
        }

        if ($expr instanceof Expr\Cast) {
            return (new CastTypeGetter)($expr);
        }

        if ($expr instanceof Expr\ConstFetch) {
            return (new ConstFetchTypeGetter)($expr);
        }

        if ($expr instanceof Expr\Throw_) {
            return new VoidType;
        }

        if ($expr instanceof Expr\Ternary) {
            return Union::wrap([
                $this->infer($expr->if ?? $expr->cond, $variableTypeGetter),
                $this->infer($expr->else, $variableTypeGetter),
            ]);
        }

        if ($expr instanceof Expr\BinaryOp\Coalesce) {
            return Union::wrap([
                $this->infer($expr->left, $variableTypeGetter),
                $this->infer($expr->right, $variableTypeGetter),
            ]);
        }

        if ($expr instanceof Expr\Match_) {
            return Union::wrap(array_map(
                fn (PhpParserNode\MatchArm $arm) => $this->infer($arm->body, $variableTypeGetter),
                $expr->arms,
            ));
        }

        if ($expr instanceof Expr\ClassConstFetch) {
            return (new ClassConstFetchTypeGetter)($expr, $this->scope);
        }

        if (
            $expr instanceof Expr\BinaryOp\Equal
            || $expr instanceof Expr\BinaryOp\Identical
            || $expr instanceof Expr\BinaryOp\NotEqual
            || $expr instanceof Expr\BinaryOp\NotIdentical
            || $expr instanceof Expr\BinaryOp\Greater
            || $expr instanceof Expr\BinaryOp\GreaterOrEqual
            || $expr instanceof Expr\BinaryOp\Smaller
            || $expr instanceof Expr\BinaryOp\SmallerOrEqual
        ) {
            return new BooleanType;
        }

        if ($expr instanceof Expr\BooleanNot) {
            return (new BooleanNotTypeGetter)($expr);
        }

        if ($expr instanceof Expr\New_) {
            if (! $expr->class instanceof PhpParserNode\Name) {
                return new NewCallReferenceType($this->infer($expr->class, $variableTypeGetter), $this->inferArgsTypes($expr->args, $variableTypeGetter));
            }

            return new NewCallReferenceType($expr->class->toString(), $this->inferArgsTypes($expr->args, $variableTypeGetter));
        }

        if ($expr instanceof Expr\MethodCall) {
            // Only string method names support.
            if (! $expr->name instanceof PhpParserNode\Identifier) {
                return new UnknownType;
            }

            $calleeType = $this->infer($expr->var, $variableTypeGetter);

            return new MethodCallReferenceType($calleeType, $expr->name->name, $this->inferArgsTypes($expr->args, $variableTypeGetter));
        }

        if ($expr instanceof Expr\StaticCall) {
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

        if ($expr instanceof Expr\PropertyFetch) {
            // Only string prop names support.
            if (! $name = ($expr->name->name ?? null)) {
                return new UnknownType('Cannot infer type of property fetch: not supported yet.');
            }

            return new PropertyFetchReferenceType($this->infer($expr->var, $variableTypeGetter), $name);
        }

        if ($expr instanceof Expr\FuncCall) {
            if ($expr->name instanceof PhpParserNode\Name) {
                return new CallableCallReferenceType(new CallableStringType($expr->name->toString()), $this->inferArgsTypes($expr->args, $variableTypeGetter));
            }

            return new CallableCallReferenceType($this->infer($expr->name, $variableTypeGetter), $this->inferArgsTypes($expr->args, $variableTypeGetter));
        }

        /**
         * When `dim` is empty, it means that the context is setting – handling in AssignHandler.
         *
         * @see AssignHandler
         */
        if ($expr instanceof Expr\ArrayDimFetch && $expr->dim) {
            return new OffsetAccessType(
                $this->infer($expr->var, $variableTypeGetter),
                $this->infer($expr->dim, $variableTypeGetter),
            );
        }

        if ($expr instanceof Expr\Array_) {
            //            return $this->buildArrayType($expr, $variableTypeGetter);
        }

        return $type;
    }

    private function buildArrayType(Expr\Array_ $expr, Closure $variableTypeGetter): Type
    {
        $arrayItems = collect($expr->items)
            ->filter()
            ->map(fn (PhpParserNode\ArrayItem $arrayItem) => $this->buildArrayItemType($arrayItem, $variableTypeGetter))
            ->all();

        return TypeHelper::unpackIfArray(new KeyedArrayType($arrayItems));
    }

    private function buildArrayItemType(PhpParserNode\ArrayItem $node, Closure $variableTypeGetter): ArrayItemType_
    {
        $keyType = $node->key ? $this->infer($node->key, $variableTypeGetter) : null;

        return new ArrayItemType_(
            $this->evaluateKeyNode($node->key, $this->scope), // @todo handle cases when key is something dynamic
            $this->infer($node->value, $variableTypeGetter),
            isOptional: false,
            shouldUnpack: $node->unpack,
            keyType: $keyType,
        );
    }

    private function evaluateKeyNode(?Expr $key, Scope $scope): int|string|null
    {
        if (! $key) {
            return null;
        }

        $evaluator = new ConstExprEvaluator(function (Expr $node) use ($scope) {
            if ($node instanceof Expr\ClassConstFetch) {
                return (new ConstFetchEvaluator([
                    'self' => $scope->classDefinition()?->name,
                    'static' => $scope->classDefinition()?->name,
                ]))->evaluate($node);
            }

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
