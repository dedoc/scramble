<?php

namespace Dedoc\Scramble\Support\OperationExtensions;

use Dedoc\Scramble\Attributes\Response as ResponseAttribute;
use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Infer\Services\FileNameResolver;
use Dedoc\Scramble\Support\Generator\Combined\AnyOf;
use Dedoc\Scramble\Support\Generator\Header;
use Dedoc\Scramble\Support\Generator\Link;
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
            ->merge($routeInfo->getActionType()->exceptions ?? [])
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
            ->map($this->mergeResponses(...))
            ->values()
            ->concat($references)
            ->values();
    }

    /**
     * @param  Collection<int, Response|Reference>  $inferredResponses
     * @return Collection<int, Response|Reference>
     */
    private function applyResponsesAttributes(Collection $inferredResponses, RouteInfo $routeInfo): Collection
    {
        $responseAttributes = $routeInfo->reflectionAction()?->getAttributes(ResponseAttribute::class, ReflectionAttribute::IS_INSTANCEOF) ?: [];

        if (! count($responseAttributes)) {
            return $inferredResponses;
        }

        foreach ($responseAttributes as $responseAttribute) {
            $responseAttributeInstance = $responseAttribute->newInstance();

            $originalResponse = $inferredResponses
                ->map(fn (Response|Reference $r): Response => $r instanceof Reference ? $r->resolve() : $r)
                ->first(fn (Response $r) => $r->code === $responseAttributeInstance->status);

            $newResponse = ResponseAttribute::toOpenApiResponse(
                $responseAttributeInstance,
                $originalResponse,
                $this->openApiTransformer,
                ($fileName = $routeInfo->reflectionAction()?->getFileName())
                    ? FileNameResolver::createForFile($fileName)
                    : null,
            );

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

    /**
     * @param  Collection<int, Response>  $responses
     */
    private function mergeResponses(Collection $responses): Response
    {
        if (count($responses) === 1) {
            /** @var Response $response */
            $response = $responses->first();

            return $response;
        }

        return tap(
            Response::make((int) $responses->first()?->code)
                ->setDescription(trim($responses->map->description->join("\n\n")))
                ->setLinks($this->mergeLinks($responses))
                ->setHeaders($this->mergeHeaders($responses)),
            fn (Response $r) => $this->addContentToResponse($r, $responses),
        );
    }

    /**
     * @param  Collection<int, Response>  $responses
     * @return array<string, Header|Reference>
     */
    private function mergeHeaders(Collection $responses): array
    {
        return array_merge(...$responses->map->headers);
    }

    /**
     * @param  Collection<int, Response>  $responses
     * @return array<string, Link|Reference>
     */
    private function mergeLinks(Collection $responses): array
    {
        return array_merge(...$responses->map->links);
    }

    /**
     * @param  Collection<int, Response>  $responses
     * @return array<string, Schema|Reference>
     */
    private function mergeContent(Collection $responses): array
    {
        /** @var Collection<string, Collection<int, Reference|Schema>> $contentCollections */
        $contentCollections = collect();

        foreach ($responses as $r) {
            foreach ($r->content as $typeName => $content) {
                if (! $contentCollections->has($typeName)) {
                    $contentCollections->offsetSet($typeName, collect());
                }
                $contentCollections->get($typeName)?->push($content);
            }
        }

        return $contentCollections
            ->map(function (Collection $schemas) {
                $types = $schemas
                    ->map(function (Schema|Reference $s) {
                        return $s instanceof Reference ? $s->resolve()->type : $s->type;
                    })
                    /*
                     * Empty response body can happen, and in case it is going to be grouped
                     * by status, it should become an empty string.
                     */
                    ->map(fn ($type) => $type ?: new OpenApiTypes\StringType)
                    ->unique(fn ($type) => json_encode($type->toArray()))
                    ->values()
                    ->all();

                return Schema::fromType(count($types) > 1 ? (new AnyOf)->setItems($types) : $types[0]);
            })
            ->all();
    }

    /**
     * @param  Collection<int, Response>  $responses
     */
    private function addContentToResponse(Response $r, Collection $responses): void
    {
        $mergedContentTypes = $this->mergeContent($responses);

        foreach ($mergedContentTypes as $type => $mergedContent) {
            $r->setContent($type, $mergedContent);
        }
    }
}
