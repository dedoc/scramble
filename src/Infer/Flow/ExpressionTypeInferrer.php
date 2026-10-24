<?php

namespace Dedoc\Scramble\Infer\Flow;

use Closure;
use Dedoc\Scramble\Infer\Scope\NodeTypesResolver;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Infer\SimpleTypeGetters\BooleanNotTypeGetter;
use Dedoc\Scramble\Infer\SimpleTypeGetters\CastTypeGetter;
use Dedoc\Scramble\Infer\SimpleTypeGetters\ClassConstFetchTypeGetter;
use Dedoc\Scramble\Infer\SimpleTypeGetters\ConstFetchTypeGetter;
use Dedoc\Scramble\Infer\SimpleTypeGetters\ScalarTypeGetter;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\BinaryOpType;
use Dedoc\Scramble\Support\Type\BooleanType;
use Dedoc\Scramble\Support\Type\CallableStringType;
use Dedoc\Scramble\Support\Type\CoalesceType;
use Dedoc\Scramble\Support\Type\ConcatenatedStringType;
use Dedoc\Scramble\Support\Type\ConditionalType;
use Dedoc\Scramble\Support\Type\EnumCaseType;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralBooleanType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\NeverType;
use Dedoc\Scramble\Support\Type\OffsetAccessType;
use Dedoc\Scramble\Support\Type\Reference\CallableCallReferenceType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\Reference\NewCallReferenceType;
use Dedoc\Scramble\Support\Type\Reference\PropertyFetchReferenceType;
use Dedoc\Scramble\Support\Type\Reference\StaticMethodCallReferenceType;
use Dedoc\Scramble\Support\Type\SelfType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\Union;
use Dedoc\Scramble\Support\Type\UnknownType;
use PhpParser\Node as PhpParserNode;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;

/**
 * @internal
 */
class ExpressionTypeInferrer
{
    public function __construct(
        private Scope $scope,
        private NodeTypesResolver $nodeTypesResolver,
    ) {}

    public function resetCache(): self
    {
        return new self($this->scope, new NodeTypesResolver);
    }

    /**
     * Ideally, `infer` should accept not Node but just expressions. @todo
     */
    public function infer(PhpParserNode $expr, Closure $variableTypeGetter): Type
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

        $nonCacheableType = match (true) {
            $expr instanceof PhpParserNode\Scalar\InterpolatedString => ConcatenatedStringType::fromParts(array_map(
                fn (PhpParserNode\InterpolatedStringPart|Expr $part) => $part instanceof PhpParserNode\InterpolatedStringPart
                    ? new LiteralStringType($part->value)
                    : $this->infer($part, $variableTypeGetter),
                $expr->parts,
            )),
            $expr instanceof PhpParserNode\Scalar => (new ScalarTypeGetter)($expr),
            $expr instanceof Expr\Cast => (new CastTypeGetter)($expr),
            $expr instanceof Expr\ConstFetch => (new ConstFetchTypeGetter)($expr),
            $expr instanceof Expr\Throw_ => new NeverType,
            $expr instanceof Expr\Ternary => $this->inferTernary($expr, $variableTypeGetter),
            $expr instanceof Expr\BinaryOp\Coalesce => new CoalesceType(
                $this->infer($expr->left, $variableTypeGetter),
                $this->infer($expr->right, $variableTypeGetter),
            ),
            $expr instanceof Expr\Match_ => $this->inferMatch($expr, $variableTypeGetter),
            $expr instanceof Expr\ClassConstFetch => (new ClassConstFetchTypeGetter)($expr, $this->scope),
            $expr instanceof Expr\BinaryOp\Equal
            || $expr instanceof Expr\BinaryOp\Identical
            || $expr instanceof Expr\BinaryOp\NotEqual
            || $expr instanceof Expr\BinaryOp\NotIdentical
            || $expr instanceof Expr\BinaryOp\Greater
            || $expr instanceof Expr\BinaryOp\GreaterOrEqual
            || $expr instanceof Expr\BinaryOp\Smaller
            || $expr instanceof Expr\BinaryOp\SmallerOrEqual => new BooleanType,
            $expr instanceof Expr\BinaryOp\Plus
            || $expr instanceof Expr\BinaryOp\Minus
            || $expr instanceof Expr\BinaryOp\Mul
            || $expr instanceof Expr\BinaryOp\Div
            || $expr instanceof Expr\BinaryOp\Mod
            || $expr instanceof Expr\BinaryOp\Pow
            || $expr instanceof Expr\BinaryOp\BooleanAnd
            || $expr instanceof Expr\BinaryOp\BooleanOr
            || $expr instanceof Expr\BinaryOp\LogicalAnd
            || $expr instanceof Expr\BinaryOp\LogicalOr
            || $expr instanceof Expr\BinaryOp\LogicalXor
            || $expr instanceof Expr\BinaryOp\Concat => new BinaryOpType(
                $this->infer($expr->left, $variableTypeGetter),
                $this->infer($expr->right, $variableTypeGetter),
                $expr->getOperatorSigil(),
            ),
            $expr instanceof Expr\Clone_ => $this->infer($expr->expr, $variableTypeGetter),
            $expr instanceof Expr\BooleanNot => (new BooleanNotTypeGetter)($expr),
            default => null,
        };

        if ($nonCacheableType) {
            return $nonCacheableType;
        }

        $type = match (true) {
            $expr instanceof Expr\New_ => $this->inferNewCall($expr, $variableTypeGetter),
            $expr instanceof Expr\MethodCall || $expr instanceof NullsafeMethodCall => $this->inferMethodCall($expr, $variableTypeGetter),
            $expr instanceof Expr\StaticCall => $this->inferStaticCall($expr, $variableTypeGetter),
            $expr instanceof Expr\FuncCall => $this->inferFuncCall($expr, $variableTypeGetter),
            $expr instanceof Expr\PropertyFetch || $expr instanceof NullsafePropertyFetch => $this->inferPropertyFetch($expr, $variableTypeGetter),
            /**
             * When `dim` is empty, it means that the context is setting – handling in AssignHandler.
             *
             * @see AssignHandler
             */
            $expr instanceof Expr\ArrayDimFetch && $expr->dim => new OffsetAccessType(
                $this->infer($expr->var, $variableTypeGetter),
                $this->infer($expr->dim, $variableTypeGetter),
            ),
            default => $type,
        };

        if (! $type instanceof UnknownType) {
            $this->nodeTypesResolver->setType($expr, $type);
        }

        return $type;
    }

    /**
     * @return array<int, Type[]>|null
     */
    private function getEnumLikeMatchConditions(Expr\Match_ $expr, Closure $variableTypeGetter): ?array
    {
        // $this
        // $this->foo()
        // $this->foo
        $cond = $expr->cond;

        $isThis = $cond instanceof Expr\Variable && $cond->name === 'this';
        $isThisMethodCall = ($cond instanceof Expr\MethodCall || $cond instanceof Expr\NullsafeMethodCall)
            && $cond->var instanceof Expr\Variable && $cond->var->name === 'this';
        $isThisPropertyFetch = ($cond instanceof Expr\PropertyFetch || $cond instanceof Expr\NullsafePropertyFetch)
            && $cond->var instanceof Expr\Variable && $cond->var->name === 'this';

        if (! $isThis && ! $isThisMethodCall && ! $isThisPropertyFetch) {
            return null;
        }

        $conditions = [];

        foreach ($expr->arms as $i => $arm) {
            $conditions[$i] = [];
            foreach ($arm->conds ?? [] as $cond) {
                // Expr that looks like enum case
                if (! $cond instanceof Expr\ClassConstFetch
                    || ! $cond->class instanceof PhpParserNode\Name
                    || ! $cond->name instanceof PhpParserNode\Identifier) {
                    return null;
                }

                $conditions[$i][] = $this->infer($cond, $variableTypeGetter);
            }
        }

        return $conditions;
    }

    private function inferMatch(Expr\Match_ $expr, Closure $variableTypeGetter): Type
    {
        if (($conditions = $this->getEnumLikeMatchConditions($expr, $variableTypeGetter)) === null) {
            return Union::wrap(array_map(
                fn (PhpParserNode\MatchArm $arm) => $this->infer($arm->body, $variableTypeGetter),
                $expr->arms,
            ));
        }

        // Default is the fallback regardless of its position among the arms.
        $result = new NeverType;
        foreach ($expr->arms as $arm) {
            if ($arm->conds === null) {
                $result = $this->infer($arm->body, $variableTypeGetter);
            }
        }

        // Build from the end so earlier arms remain the outermost conditions.
        foreach (array_reverse($expr->arms, preserve_keys: true) as $i => $arm) {
            if ($arm->conds === null) {
                continue;
            }

            $body = $this->infer($arm->body, $variableTypeGetter);
            foreach (array_reverse($conditions[$i]) as $condition) {
                $result = new ConditionalType($this->infer($expr->cond, $variableTypeGetter), $condition, $body, $result);
            }
        }

        return $result;
    }

    private function inferTernary(Expr\Ternary $expr, Closure $variableTypeGetter): Type
    {
        $ifTrue = $this->infer($expr->if ?? $expr->cond, $variableTypeGetter);
        $ifFalse = $this->infer($expr->else, $variableTypeGetter);

        if ($expr->cond instanceof Expr\Variable && is_string($expr->cond->name)) {
            $condition = $this->infer($expr->cond, $variableTypeGetter);
            $argument = $this->scope->functionDefinition()?->type->arguments[$expr->cond->name] ?? null;

            if ($argument !== null && $condition === $argument) {
                return new ConditionalType(
                    // Convert PHP truthiness to a boolean while preserving the argument template.
                    new BinaryOpType($condition, new LiteralBooleanType(true), '&&'),
                    new LiteralBooleanType(true),
                    $ifTrue,
                    $ifFalse,
                );
            }
        }

        return Union::wrap([$ifTrue, $ifFalse]);
    }

    private function inferNewCall(Expr\New_ $expr, Closure $variableTypeGetter): Type
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

    private function inferMethodCall(Expr\MethodCall|NullsafeMethodCall $expr, Closure $variableTypeGetter): Type
    {
        // Only string method names support.
        if (! $expr->name instanceof PhpParserNode\Identifier) {
            return new UnknownType;
        }

        $calleeType = $this->infer($expr->var, $variableTypeGetter);

        return new MethodCallReferenceType(
            $calleeType,
            $expr->name->name,
            $this->inferArgsTypes($expr->args, $variableTypeGetter),
            isNullsafe: $expr instanceof NullsafeMethodCall,
        );
    }

    private function inferStaticCall(Expr\StaticCall $expr, Closure $variableTypeGetter): Type
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

    private function inferFuncCall(Expr\FuncCall $expr, Closure $variableTypeGetter): Type
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

    private function inferPropertyFetch(Expr\PropertyFetch|NullsafePropertyFetch $expr, Closure $variableTypeGetter): Type
    {
        // Only string prop names support.
        if (! $name = ($expr->name->name ?? null)) {
            return new UnknownType('Cannot infer type of property fetch: not supported yet.');
        }

        $object = $this->infer($expr->var, $variableTypeGetter);

        return new PropertyFetchReferenceType(
            $object,
            $name,
            isNullsafe: $expr instanceof NullsafePropertyFetch,
            resolveLate: $expr->var instanceof Expr\Variable
                && is_string($expr->var->name)
                && $object instanceof TemplateType
                && ($this->scope->context->functionDefinition?->type->arguments[$expr->var->name] ?? null) === $object,
        );
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
