<?php

namespace Dedoc\Scramble\Support\OperationExtensions;

use Dedoc\Scramble\Attributes\Response as ResponseAttribute;
use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Combined\AnyOf;
use Dedoc\Scramble\Support\Generator\MediaType;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types as OpenApiTypes;
use Dedoc\Scramble\Support\RouteInfo;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\Union;
use Illuminate\Support\Collection;
use ReflectionAttribute;
use function DeepCopy\deep_copy;

class ResponseExtension extends OperationExtension
{
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $inferredResponses = $this->collectInferredResponses($routeInfo);

        $responses = $this->applyResponsesAttributes($inferredResponses, $routeInfo);

        foreach ($responses as $response) {
            $operation->addResponse($response);
        }
    }

    /**
     * @return Collection<int, Response|Reference>
     */
    private function collectInferredResponses(RouteInfo $routeInfo): Collection
    {
        $returnType = $routeInfo->getReturnType();

        if (! $returnType) {
            return collect();
        }

        $returnTypes = $returnType instanceof Union
            ? $returnType->types
            : [$returnType];

        $responses = collect($returnTypes)
            ->merge($routeInfo->getMethodType()->exceptions ?? [])
            ->map(function (Type $type) use ($routeInfo) {
                /*
                 * Any inline comments on the entire response type that are not originating in the controller,
                 * should not leak to the resulting documentation.
                 */
                $docSource = $type->getAttribute('docNode')?->getAttribute('sourceClass');

                if ($docSource && ($docSource !== $routeInfo->className())) {
                    $type->setAttribute('docNode', null);
                }

                return $type;
            })
            ->map($this->openApiTransformer->toResponse(...))
            ->filter()
            ->unique(fn ($response) => ($response instanceof Response ? $response->code : 'ref').':'.json_encode($response->toArray()))
            ->values();

        [$responses, $references] = $responses->partition(fn ($r) => $r instanceof Response)->all();
        /** @var Collection<int, Response> $responses */
        /** @var Collection<int, Reference> $references */

        return $responses
            ->groupBy('code')
            ->map(function (Collection $responses, $code) {
                if (count($responses) === 1) {
                    return $responses->first();
                }

                // @todo: Responses with similar code and type should result in a different example schemas.

                $responsesTypes = $responses->map(function (Response $r) {
                    $schema = ($r->content['application/json'] ?? null)->schema ?? null;
                    if (! $schema) {
                        return null;
                    }
                    if ($schema instanceof Reference) {
                        return $schema->resolve()->type;
                    }

                    return $schema->type;
                })
                    /*
                     * Empty response body can happen, and in case it is going to be grouped
                     * by status, it should become an empty string.
                     */
                    ->map(fn ($type) => $type ?: new OpenApiTypes\StringType)
                    ->unique(fn ($type) => json_encode($type->toArray()))
                    ->values()
                    ->all();

                return Response::make((int) $code)
                    ->setDescription($responses->first()->description) // @phpstan-ignore property.nonObject
                    ->addContent(
                        'application/json',
                        new MediaType(
                            schema: Schema::fromType(count($responsesTypes) > 1 ? (new AnyOf)->setItems($responsesTypes) : $responsesTypes[0]),
                        ),
                    );
            })
            ->values()
            ->merge($references)
            ->values();
    }

    /**
     * @param  Collection<int, Response|Reference>  $inferredResponses
     * @return Collection<int, Response|Reference>
     */
    private function applyResponsesAttributes(Collection $inferredResponses, RouteInfo $routeInfo): Collection
    {
        $responseAttributes = $routeInfo->reflectionMethod()?->getAttributes(ResponseAttribute::class, ReflectionAttribute::IS_INSTANCEOF) ?: [];

        if (! count($responseAttributes)) {
            return $inferredResponses;
        }

        foreach ($responseAttributes as $responseAttribute) {
            $responseAttributeInstance = $responseAttribute->newInstance();

            $originalResponse = $inferredResponses
                ->map(fn (Response|Reference $r): Response => $r instanceof Reference ? $r->resolve() : $r)
                ->first(fn (Response $r) => $r->code === $responseAttributeInstance->status);

            $newResponse = ResponseAttribute::toOpenApiResponse($responseAttributeInstance, $originalResponse, $this->openApiTransformer);

            $responseHasChanged = ! $originalResponse
                || json_encode($newResponse->toArray(), JSON_THROW_ON_ERROR) !== json_encode($originalResponse->toArray(), JSON_THROW_ON_ERROR);

            if (! $responseHasChanged) {
                continue;
            }

            $inferredResponses = $inferredResponses
                ->map(function (Response|Reference $r) use ($responseAttributeInstance, $newResponse) {
                    $response = $r instanceof Reference ? $r->resolve() : $r;

                    /** @var Response $response */

                    return $response->code === $responseAttributeInstance->status ? $newResponse : $r;
                });

            if (! $originalResponse) {
                $inferredResponses->push($newResponse);
            }
        }

        return $inferredResponses;
    }
}
