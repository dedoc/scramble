<?php

namespace Dedoc\Scramble\Support\OperationExtensions;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Combined\AnyOf;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types as OpenApiTypes;
use Dedoc\Scramble\Support\RouteInfo;
use Dedoc\Scramble\Support\Type\Union;
use Illuminate\Support\Collection;

class ResponseExtension extends OperationExtension
{
    public function handle(Operation $operation, RouteInfo $routeInfo)
    {
        $returnType = $routeInfo->getReturnType();

        if (! $returnType) {
            return [];
        }

        $returnTypes = $returnType instanceof Union
            ? $returnType->types
            : [$returnType];

        $responses = collect($returnTypes)
            ->merge(optional($routeInfo->getMethodType())->exceptions ?: [])
            ->map(fn ($returnType) => $this->openApiTransformer->toResponse($returnType))
            ->filter();

        [$responses, $references] = $responses->partition(fn ($r) => $r instanceof Response);

        $responses = $responses
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
            ->values()
            ->merge($references)
            ->all();

        foreach ($responses as $response) {
            $operation->addResponse($response);
        }
    }
}
