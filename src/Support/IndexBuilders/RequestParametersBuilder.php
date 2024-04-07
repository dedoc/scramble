<?php

namespace Dedoc\Scramble\Support\IndexBuilders;

use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Generator\MissingExample;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Helpers\ExamplesExtractor;
use Dedoc\Scramble\Support\Type\BooleanType;
use Dedoc\Scramble\Support\Type\FloatType;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\Literal\LiteralBooleanType;
use Dedoc\Scramble\Support\Type\Literal\LiteralFloatType;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\MixedType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PhpParser\Comment;
use PhpParser\Node;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;

class RequestParametersBuilder
{
    public function __construct(public Bag $bag)
    {
    }

    public function afterAnalyzedNode(Scope $scope, Node $node)
    {
        if (! $node instanceof Node\Stmt\Expression) {
            return;
        }

        if (! $node->expr instanceof Node\Expr\MethodCall) {
            return;
        }

        $methodCallNode = $node->expr;

        $varType = $scope->getType($methodCallNode->var);

        if (! $varType->isInstanceOf(Request::class)) {
            return;
        }

        if (! $name = $this->getNameNodeValue($scope, $methodCallNode->name)) {
            return;
        }

        if (! ($parameterName = TypeHelper::getArgType($scope, $methodCallNode->args, ['key', 0])->value ?? null)) {
            return;
        }

        if ($this->shouldIgnoreParameter($node)) {
            return;
        }

        $parameter = Parameter::make($parameterName, 'query'/* @todo: this is just a temp solution */);

        [$parameterType, $parameterDefault] = match ($name) {
            'integer' => $this->makeIntegerParameter($scope, $methodCallNode),
            'float' => $this->makeFloatParameter($scope, $methodCallNode),
            'boolean' => $this->makeBooleanParameter($scope, $methodCallNode),
            'string', 'str' => $this->makeStringParameter($scope, $methodCallNode),
            'enum' => $this->makeEnumParameter($scope, $methodCallNode),
            'query' => $this->makeQueryParameter($scope, $methodCallNode, $parameter),
            default => [null, null],
        };

        if (! $parameterType) {
            return;
        }

        if ($parameterDefaultFromDoc = $this->getParameterDefaultFromPhpDoc($node)) {
            $parameterDefault = $parameterDefaultFromDoc;
        }

        $this->checkExplicitParameterPlacementInQuery($node, $parameter);

        $parameter
            ->description($this->makeDescriptionFromComments($node))
            ->setSchema(Schema::fromType(
                app(TypeTransformer::class)->transform($parameterType)
            ))
            ->default($parameterDefault ?? new MissingExample);

        // @todo: query
        // @todo: get/input/post/?

        $this->bag->set($parameterName, $parameter);
    }

    private function getNameNodeValue(Scope $scope, Node $nameNode)
    {
        if ($nameNode instanceof Node\Identifier) {
            return $nameNode->name;
        }

        $type = $scope->getType($nameNode);
        if (! $type instanceof LiteralStringType) {
            return null;
        }

        return $type->value;
    }

    private function makeIntegerParameter(Scope $scope, Node $node)
    {
        return [
            new IntegerType,
            TypeHelper::getArgType($scope, $node->args, ['default', 1], new LiteralIntegerType(0))->value ?? null,
        ];
    }

    private function makeFloatParameter(Scope $scope, Node $node)
    {
        return [
            new FloatType,
            TypeHelper::getArgType($scope, $node->args, ['default', 1], new LiteralFloatType(0))->value ?? null,
        ];
    }

    private function makeBooleanParameter(Scope $scope, Node $node)
    {
        return [
            new BooleanType,
            TypeHelper::getArgType($scope, $node->args, ['default', 1], new LiteralBooleanType(false))->value ?? null,
        ];
    }

    private function makeStringParameter(Scope $scope, Node $node)
    {
        return [
            new StringType,
            TypeHelper::getArgType($scope, $node->args, ['default', 1])->value ?? null,
        ];
    }

    private function makeEnumParameter(Scope $scope, Node $node)
    {
        if (! $className = TypeHelper::getArgType($scope, $node->args, ['default', 1])->value ?? null) {
            return [null, null];
        }

        return [
            new ObjectType($className),
            null,
        ];
    }

    private function makeQueryParameter(Scope $scope, Node $node, Parameter $parameter)
    {
        $parameter->setAttribute('isInQuery', true);

        return [
            new MixedType,
            TypeHelper::getArgType($scope, $node->args, ['default', 1])->value ?? null,
        ];
    }

    private function makeDescriptionFromComments(Node\Stmt\Expression $node)
    {
        /*
         * @todo: consider adding only @param annotation support,
         * so when description is taken only if comment is marked with @param
         */
        if ($phpDoc = $node->getAttribute('parsedPhpDoc')) {
            return trim($phpDoc->getAttribute('summary').' '.$phpDoc->getAttribute('description'));
        }

        if ($node->getComments()) {
            $docText = collect($node->getComments())
                ->map(fn (Comment $c) => $c->getReformattedText())
                ->join("\n");

            return (string) Str::of($docText)->replace(['//', ' * ', '/**', '/*', '*/'], '')->trim();
        }

        return '';
    }

    private function shouldIgnoreParameter(Node\Stmt\Expression $node)
    {
        /** @var PhpDocNode|null $phpDoc */
        $phpDoc = $node->getAttribute('parsedPhpDoc');

        return (bool) $phpDoc?->getTagsByName('@ignoreParam');
    }

    private function getParameterDefaultFromPhpDoc(Node\Stmt\Expression $node)
    {
        /** @var PhpDocNode|null $phpDoc */
        $phpDoc = $node->getAttribute('parsedPhpDoc');

        return ExamplesExtractor::make($phpDoc, '@default')->extract()[0] ?? null;
    }

    private function checkExplicitParameterPlacementInQuery(Node\Stmt\Expression $node, Parameter $parameter)
    {
        /** @var PhpDocNode|null $phpDoc */
        $phpDoc = $node->getAttribute('parsedPhpDoc');

        if ((bool) $phpDoc?->getTagsByName('@query')) {
            $parameter->setAttribute('isInQuery', true);
        }
    }
}
