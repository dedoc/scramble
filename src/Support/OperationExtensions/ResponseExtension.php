<?php

namespace Dedoc\Scramble\Support\OperationExtensions;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Combined\AnyOf;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types as OpenApiTypes;
use Dedoc\Scramble\Support\RouteInfo;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\Union;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ResponseExtension extends OperationExtension
{
    public function handle(Operation $operation, RouteInfo $routeInfo)
    {
        $returnTypes = $routeInfo->getReturnTypes();

        if (! $returnTypes = $returnTypes[0] ?? null) {
            return [];
        }

        $returnTypes = $returnTypes instanceof Union
            ? $returnTypes->types
            : [$returnTypes];

        $responses = collect($returnTypes)
            ->map(fn ($returnType) => $this->openApiTransformer->toResponse($returnType))
            ->merge($this->getErrorResponses($routeInfo->getMethodType()->exceptions ?? []))
            ->filter()
            ->groupBy('code')
            ->map(function (Collection $responses, $code) {
                if (count($responses) === 1) {
                    return $responses->first();
                }

                return Response::make((int) $code)
                    ->setContent(
                        'application/json',
                        Schema::fromType((new AnyOf)->setItems(
                            $responses->pluck('content.application/json.type')
                                /*
                                 * Empty response body can happen, and in case it is going to be grouped
                                 * by status, it should become an empty string.
                                 */
                                ->map(fn ($type) => $type ?: new OpenApiTypes\StringType)
                                ->all()
                        ))
                    );
            })
            ->all();

        foreach ($responses as $response) {
            $operation->addResponse($response);
        }
    }

    /**
     * @param array<ObjectType|Generic> $exceptions
     */
    private function getErrorResponses(array $exceptions)
    {
        return collect($exceptions)
            ->map(function (Type $exception) {
                if ($exception->isInstanceOf(ValidationException::class)) {
                    $validationResponseBodyType = (new OpenApiTypes\ObjectType())
                        ->addProperty(
                            'message',
                            (new OpenApiTypes\StringType())
                                ->setDescription('Errors overview.')
                        )
                        ->addProperty(
                            'errors',
                            (new OpenApiTypes\ObjectType())
                                ->setDescription('A detailed description of each field that failed validation.')
                                ->additionalProperties((new OpenApiTypes\ArrayType)->setItems(new OpenApiTypes\StringType()))
                        )
                        ->setRequired(['message', 'errors']);

                    return Response::make(422)
                        ->description('Validation error')
                        ->setContent(
                            'application/json',
                            Schema::fromType($validationResponseBodyType)
                        );
                }

                return null;
            })
            ->filter()
            ->values();
        dd($exceptions);
    }
}
