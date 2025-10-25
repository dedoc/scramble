<?php

namespace Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor;

use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\OperationExtensions\RequestBodyExtension;
use Dedoc\Scramble\Support\OperationExtensions\RulesEvaluator\NodeRulesEvaluator;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\GeneratesParametersFromRules;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\ParametersExtractionResult;
use Dedoc\Scramble\Support\RouteInfo;
use Dedoc\Scramble\Support\SchemaClassDocReflector;
use Illuminate\Http\Request;
use PhpParser\Node;
use PhpParser\Node\FunctionLike;
use PhpParser\NodeFinder;
use PhpParser\PrettyPrinter;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;

class ValidateCallParametersExtractor implements ParameterExtractor
{
    use GeneratesParametersFromRules;

    public function __construct(
        private PrettyPrinter $printer,
        private TypeTransformer $openApiTransformer,
    ) {}

    public function handle(RouteInfo $routeInfo, array $parameterExtractionResults): array
    {
        if (! $astNode = $routeInfo->actionNode()) {
            return $parameterExtractionResults;
        }

        [$callToValidate, $validationRules] = $this->getCallToValidateAndValidationRulesNodes($astNode);

        if (! $validationRules) {
            return $parameterExtractionResults;
        }

        $validationRulesNode = $validationRules instanceof Node\Arg ? $validationRules->value : $validationRules;

        $phpDocReflector = new SchemaClassDocReflector($callToValidate->getAttribute('parsedPhpDoc', new PhpDocNode([])));

        $parameterExtractionResults[] = new ParametersExtractionResult(
            parameters: $this->makeParameters(
                rules: (new NodeRulesEvaluator($this->printer, $astNode, $validationRulesNode, $routeInfo->route, $routeInfo->className()))->handle(),
                typeTransformer: $this->openApiTransformer,
                rulesDocsRetriever: new TypeBasedRulesDocumentationRetriever(
                    $routeInfo->getScope(),
                    $routeInfo->getScope()->getType($validationRulesNode),
                ),
                in: in_array(mb_strtolower($routeInfo->route->methods()[0]), RequestBodyExtension::HTTP_METHODS_WITHOUT_REQUEST_BODY)
                    ? 'query'
                    : 'body',
            ),
            schemaName: $phpDocReflector->getSchemaName(),
            description: $phpDocReflector->getDescription(),
        );

        return $parameterExtractionResults;
    }

    private function getCallToValidateAndValidationRulesNodes(FunctionLike $methodNode)
    {
        // $request->validate, when $request is a Request instance
        /** @var Node\Expr\MethodCall $callToValidate */
        $callToValidate = (new NodeFinder)->findFirst(
            $methodNode,
            fn (Node $node) => $node instanceof Node\Expr\MethodCall
                && $node->var instanceof Node\Expr\Variable
                && is_a($this->getPossibleParamType($methodNode, $node->var), Request::class, true)
                && $node->name instanceof Node\Identifier
                && $node->name->name === 'validate'
        );
        $validationRules = $callToValidate->args[0] ?? null;

        if (! $validationRules) {
            // $this->validate($request, $rules), rules are second param. First should be $request, but no way to check type. So relying on convention.
            $callToValidate = (new NodeFinder)->findFirst(
                $methodNode,
                fn (Node $node) => $node instanceof Node\Expr\MethodCall
                    && count($node->args) >= 2
                    && $node->var instanceof Node\Expr\Variable && $node->var->name === 'this'
                    && $node->name instanceof Node\Identifier && $node->name->name === 'validate'
                    && $node->args[0]->value instanceof Node\Expr\Variable
                    && is_a($this->getPossibleParamType($methodNode, $node->args[0]->value), Request::class, true)
                    && $node->name->name === 'validate'
            );
            $validationRules = $callToValidate->args[1] ?? null;
        }

        if (! $validationRules) {
            // Validator::make($request->...(), $rules), rules are second param. First should be $request, but no way to check type. So relying on convention.
            $callToValidate = (new NodeFinder)->findFirst(
                $methodNode,
                fn (Node $node) => $node instanceof Node\Expr\StaticCall
                    && count($node->args) >= 2
                    && $node->class instanceof Node\Name && is_a($node->class->toString(), \Illuminate\Support\Facades\Validator::class, true)
                    && $node->name instanceof Node\Identifier && $node->name->name === 'make'
                    && $node->args[0]->value instanceof Node\Expr\MethodCall && is_a($this->getPossibleParamType($methodNode, $node->args[0]->value->var), Request::class, true)
            );
            $validationRules = $callToValidate->args[1] ?? null;
        }

        return [$callToValidate, $validationRules];
    }

    private function getPossibleParamType(FunctionLike $methodNode, Node\Expr\Variable $node): ?string
    {
        $paramsMap = collect($methodNode->getParams())
            ->mapWithKeys(function (Node\Param $param) {
                if (! isset($param->type->name)) {
                    return [];
                }

                try {
                    return [
                        $param->var->name => $param->type->name,
                    ];
                } catch (\Throwable $exception) {
                    throw $exception;
                }
            })
            ->toArray();

        return $paramsMap[$node->name] ?? null;
    }
}
