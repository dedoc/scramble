<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Closure;
use Dedoc\Scramble\Infer\Contracts\ArgumentTypeBag;
use Dedoc\Scramble\Infer\Extensions\Event\FunctionCallEvent;
use Dedoc\Scramble\Infer\Extensions\Event\MethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\ExpressionTypeInferExtension;
use Dedoc\Scramble\Infer\Extensions\FunctionReturnTypeExtension;
use Dedoc\Scramble\Infer\Extensions\MethodReturnTypeExtension;
use Dedoc\Scramble\Infer\Reflector\ClosureReflector;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralBooleanType;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\NullType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\ReferenceType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceResponse;
use Illuminate\Support\Facades\Response as ResponseFacade;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;
use ReflectionClass;
use Symfony\Component\HttpFoundation\StreamedJsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResponseFactoryTypeInfer implements ExpressionTypeInferExtension, FunctionReturnTypeExtension, MethodReturnTypeExtension
{
    public function shouldHandle(ObjectType|string $callee): bool
    {
        if (is_string($callee)) {
            return $callee === 'response';
        }

        return $callee->isInstanceOf(ResponseFactory::class);
    }

    public function getFunctionReturnType(FunctionCallEvent $event): ?Type
    {
        if (count($event->arguments)) {
            return new Generic(Response::class, [
                $event->getArg('content', 0, new LiteralStringType('')),
                $event->getArg('status', 1, new LiteralIntegerType(200)),
                $event->getArg('headers', 2, new ArrayType),
            ]);
        }

        return new ObjectType(ResponseFactory::class);
    }

    public function getMethodReturnType(MethodCallEvent $event): ?Type
    {
        $methodName = $event->name;

        // Check for built-in methods first
        $builtInType = match ($methodName) {
            'noContent' => new Generic(Response::class, [
                new LiteralStringType(''),
                $event->getArg('status', 0, new LiteralIntegerType(204)),
                $event->getArg('headers', 1, new ArrayType),
            ]),
            'json' => new Generic(JsonResponse::class, [
                $event->getArg('data', 0, new ArrayType),
                $event->getArg('status', 1, new LiteralIntegerType(200)),
                $event->getArg('headers', 2, new ArrayType),
            ]),
            'make' => new Generic(Response::class, [
                $event->getArg('content', 0, new LiteralStringType('')),
                $event->getArg('status', 1, new LiteralIntegerType(200)),
                $event->getArg('headers', 2, new ArrayType),
            ]),
            'download' => (new BinaryFileResponseTypeFactory(
                file: $event->getArg('file', 0),
                name: $event->getArg('name', 1, new NullType),
                headers: $event->getArg('headers', 2, new ArrayType),
                disposition: $event->getArg('disposition', 3, new LiteralStringType('attachment')),
            ))->build(),
            'file' => (new BinaryFileResponseTypeFactory(
                file: $event->getArg('file', 0),
                headers: $event->getArg('headers', 1, new ArrayType),
            ))->build(),
            'stream' => new Generic(StreamedResponse::class, [
                $event->getArg('callbackOrChunks', 0),
                $event->getArg('status', 1, new LiteralIntegerType(200)),
                $event->getArg('headers', 2, new ArrayType),
            ]),
            'streamJson' => new Generic(StreamedJsonResponse::class, [
                $event->getArg('data', 0),
                $event->getArg('status', 1, new LiteralIntegerType(200)),
                $event->getArg('headers', 2, new ArrayType),
            ]),
            'streamDownload' => new Generic(StreamedResponse::class, [
                $event->getArg('callback', 0),
                new LiteralIntegerType(200),
                $event->getArg('headers', 2, new ArrayType),
            ]),
            'eventStream' => (new Generic(StreamedResponse::class, [
                $event->getArg('callback', 0),
                new LiteralIntegerType(200),
                $event->getArg('headers', 1, new ArrayType),
            ]))->mergeAttributes([
                'mimeType' => 'text/event-stream',
                'endStreamWith' => ($endStreamWithType = $event->getArg('endStreamWith', 2, new LiteralStringType('</stream>'))) instanceof LiteralStringType
                    ? $endStreamWithType->value
                    : null,
            ]),
            default => null,
        };

        if ($builtInType !== null) {
            return $builtInType;
        }

        // Check if it's a registered macro
        if ($macroClosure = $this->getMacroClosure($methodName)) {
            // Try to infer the macro's return type to extract the data structure
            $dataType = $this->inferMacroReturnType($macroClosure, $event->scope, $event->arguments);

            return new Generic(JsonResponse::class, [
                $dataType ?? $event->getArg('data', 0, new ArrayType),
                $event->getArg('status', 1, new LiteralIntegerType(200)),
                $event->getArg('headers', 2, new ArrayType),
            ]);
        }

        return null;
    }

    /**
     * Get the macro closure for the given method name.
     */
    private function getMacroClosure(string $methodName): ?Closure
    {
        // Check if Laravel application is available
        if (! function_exists('app')) {
            return null;
        }

        try {
            $factory = app(ResponseFactory::class);

            // Try to get the macro closure from the factory
            if (method_exists($factory, 'hasMacro') && $factory->hasMacro($methodName)) {
                // Use reflection to get the macro closure
                $reflection = new ReflectionClass($factory);
                if ($reflection->hasProperty('macros')) {
                    $macrosProperty = $reflection->getProperty('macros');
                    $macrosProperty->setAccessible(true);
                    $macros = $macrosProperty->getValue($factory);

                    if (isset($macros[$methodName]) && $macros[$methodName] instanceof Closure) {
                        return $macros[$methodName];
                    }
                }
            }

            // Fallback: Check the facade's static macros property
            $facadeReflection = new ReflectionClass(ResponseFacade::class);
            if ($facadeReflection->hasProperty('macros')) {
                $macrosProperty = $facadeReflection->getProperty('macros');
                $macrosProperty->setAccessible(true);
                $macros = $macrosProperty->getValue();

                if (isset($macros[$methodName]) && $macros[$methodName] instanceof Closure) {
                    return $macros[$methodName];
                }
            }
        } catch (\Throwable $e) {
            // If all checks fail, return null
            return null;
        }

        return null;
    }

    /**
     * Infer the return type of a macro closure to extract the data structure.
     */
    private function inferMacroReturnType(Closure $closure, Scope $scope, ArgumentTypeBag $arguments): ?Type
    {
        try {
            $reflector = ClosureReflector::make($closure);
            $closureDefinition = $reflector->getFunctionLikeDefinition();
            $returnType = $closureDefinition->getReturnType();

            // If the return type is already a Generic JsonResponse, extract the data type
            if ($returnType instanceof Generic && $returnType->isInstanceOf(JsonResponse::class)) {
                if (isset($returnType->templateTypes[0])) {
                    return $returnType->templateTypes[0];
                }
            }

            // Try to analyze the closure's AST to find $response->json($data, ...) calls
            if ($astNode = $reflector->getAstNode()) {
                $dataType = $this->extractJsonDataFromClosure($astNode, $scope, $arguments, $closureDefinition);
                if ($dataType) {
                    return $dataType;
                }
            }

            // If we have a return type but it's not Generic, try to analyze AST
            if ($returnType && ! ($returnType instanceof UnknownType)) {
                if ($returnType instanceof ObjectType && $returnType->isInstanceOf(JsonResponse::class)) {
                    // Try to find json() calls in the closure
                    if ($astNode = $reflector->getAstNode()) {
                        return $this->extractJsonDataFromClosure($astNode, $scope, $arguments, $closureDefinition);
                    }
                }
            }
        } catch (\Throwable $e) {
            // If inference fails, return null to fall back to default
            return null;
        }

        return null;
    }

    /**
     * Extract the data type from $response->json($data, ...) calls in the closure AST.
     */
    private function extractJsonDataFromClosure(
        Node\FunctionLike $astNode,
        Scope $scope,
        ArgumentTypeBag $arguments,
        \Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition $closureDefinition
    ): ?Type {
        try {
            $nodeFinder = new NodeFinder;

            // Find all method calls in the closure
            $methodCalls = $nodeFinder->find($astNode->getStmts() ?? [], function (Node $node) {
                return $node instanceof Node\Expr\MethodCall
                    && $node->name instanceof Node\Identifier
                    && $node->name->name === 'json';
            });

            foreach ($methodCalls as $methodCall) {
                /** @var Node\Expr\MethodCall $methodCall */
                // Get the first argument (the data parameter)
                if (isset($methodCall->args[0])) {
                    $dataArg = $methodCall->args[0];

                    // Try to infer the type of the data argument using the scope
                    try {
                        // Create a child scope for analyzing the closure
                        $closureScope = $scope->createChildScope(clone $scope->context);

                        // Populate closure scope variables with argument types from call site
                        // This allows us to infer types of parameters passed to the macro
                        $paramNames = array_keys($closureDefinition->type->arguments ?? []);
                        foreach ($paramNames as $index => $paramName) {
                            $argType = $arguments->get($paramName, $index);
                            if ($argType && ! ($argType instanceof UnknownType)) {
                                // Set the variable type in the closure scope
                                $closureScope->addVariableType(
                                    $astNode->getStartLine() ?? 0,
                                    $paramName,
                                    $argType
                                );
                            }
                        }

                        // Use the scope's getType method to infer the type
                        $dataType = $closureScope->getType($dataArg->value);

                        if ($dataType && ! ($dataType instanceof UnknownType)) {
                            // Resolve any reference types
                            if ($dataType instanceof ReferenceType) {
                                $referenceResolver = ReferenceTypeResolver::getInstance();
                                $dataType = $referenceResolver->resolve($closureScope, $dataType);
                            }

                            if ($dataType && ! ($dataType instanceof UnknownType)) {
                                // Recursively find and wrap JsonResources at any level
                                $updatedType = $this->wrapJsonResourcesInType($dataType, $scope);
                                
                                // Only return updated type if JsonResources were found and wrapped
                                if ($updatedType !== $dataType) {
                                    return $updatedType;
                                }

                                return $dataType;
                            }
                        }
                    } catch (\Throwable $e) {
                        // Continue to next method call
                        continue;
                    }
                }
            }
        } catch (\Throwable $e) {
            // If extraction fails, return null
            return null;
        }

        return null;
    }

    /**
     * Recursively wrap JsonResources in ResourceResponse at any level of the type structure.
     */
    private function wrapJsonResourcesInType(Type $type, Scope $scope): Type
    {
        // If the type is a JsonResource, wrap it in ResourceResponse
        if ($this->isJsonResourceType($type, $scope)) {
            return new Generic(ResourceResponse::class, [$type]);
        }
        
        // Handle KeyedArrayType - recursively check all items
        if ($type instanceof KeyedArrayType) {
            $updatedItems = [];
            $hasChanges = false;
            
            foreach ($type->items as $item) {
                $originalValue = $item->value;
                $updatedValue = $this->wrapJsonResourcesInType($originalValue, $scope);
                
                if ($updatedValue !== $originalValue) {
                    $hasChanges = true;
                }
                
                $updatedItems[] = new ArrayItemType_(
                    $item->key,
                    $updatedValue,
                    $item->isOptional
                );
            }
            
            if ($hasChanges) {
                return new KeyedArrayType($updatedItems, $type->isList);
            }
        }
        
        // Handle ArrayType - recursively check the value type
        if ($type instanceof ArrayType && $type->value) {
            $updatedValue = $this->wrapJsonResourcesInType($type->value, $scope);
            if ($updatedValue !== $type->value) {
                return new ArrayType($updatedValue);
            }
        }
        
        // For Generic types, recursively check template types
        if ($type instanceof Generic && !empty($type->templateTypes)) {
            $updatedTemplates = [];
            $hasChanges = false;
            
            foreach ($type->templateTypes as $templateType) {
                $updatedTemplate = $this->wrapJsonResourcesInType($templateType, $scope);
                $updatedTemplates[] = $updatedTemplate;
                
                if ($updatedTemplate !== $templateType) {
                    $hasChanges = true;
                }
            }
            
            if ($hasChanges) {
                $updatedGeneric = new Generic($type->name, $updatedTemplates);
                // Preserve attributes if any
                foreach ($type->attributes() as $key => $value) {
                    $updatedGeneric->setAttribute($key, $value);
                }
                return $updatedGeneric;
            }
        }
        
        // No changes needed
        return $type;
    }

    /**
     * Check if a type is a JsonResource.
     */
    private function isJsonResourceType(Type $type, Scope $scope): bool
    {
        if (! ($type instanceof ObjectType || $type instanceof Generic)) {
            return false;
        }
        
        $className = $type->name;
        
        // Ensure the class is analyzed in the index
        $scope->index->getClass($className);
        
        // Try isInstanceOf first (works if class is analyzed)
        if ($type->isInstanceOf(JsonResource::class)) {
            return true;
        }
        
        // Fallback: check class hierarchy directly
        if (class_exists($className) && is_a($className, JsonResource::class, true)) {
            return true;
        }
        
        // Check parent hierarchy in index
        if ($classDefinition = $scope->index->getClass($className)) {
            $parentFqn = $classDefinition->parentFqn;
            while ($parentFqn) {
                if ($parentFqn === JsonResource::class) {
                    return true;
                }
                $parentDef = $scope->index->getClass($parentFqn);
                $parentFqn = $parentDef->parentFqn ?? null;
            }
        }
        
        return false;
    }

    public function getType(Expr $node, Scope $scope): ?Type
    {
        // call Response and JsonResponse constructors
        if (
            $node instanceof Expr\New_
            && (
                $scope->getType($node)->isInstanceOf(JsonResponse::class)
                || $scope->getType($node)->isInstanceOf(Response::class)
            )
        ) {
            /** @var ObjectType $nodeType */
            $nodeType = $scope->getType($node);

            $contentName = $nodeType->isInstanceOf(JsonResponse::class) ? 'data' : 'content';
            $contentDefaultType = $nodeType->isInstanceOf(JsonResponse::class)
                ? new ArrayType
                : new LiteralStringType('');

            return new Generic($nodeType->name, [
                TypeHelper::getArgType($scope, $node->args, [$contentName, 0], $contentDefaultType),
                TypeHelper::getArgType($scope, $node->args, ['status', 1], new LiteralIntegerType(200)),
                TypeHelper::getArgType($scope, $node->args, ['headers', 2], new ArrayType),
            ]);
        }

        return null;
    }
}
