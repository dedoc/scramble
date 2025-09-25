<?php

namespace Dedoc\Scramble\Support\OperationExtensions;

use Dedoc\Scramble\Attributes\Header as HeaderAttribute;
use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\RouteInfo;
use ReflectionAttribute;

use function DeepCopy\deep_copy;

class ResponseHeadersExtension extends OperationExtension
{
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        if (! $reflectionMethod = $routeInfo->reflectionAction()) {
            return;
        }

        $headerAttributesInstances = array_map(
            fn (ReflectionAttribute $attribute) => $attribute->newInstance(),
            $reflectionMethod->getAttributes(HeaderAttribute::class, ReflectionAttribute::IS_INSTANCEOF),
        );

        if (! $headerAttributesInstances || ! $operation->responses) {
            return;
        }

        foreach ($operation->responses as $i => $response) {
            if (! $responseStatusCode = $this->getResponseStatusCode($response)) {
                continue;
            }

            $applicableHeaders = array_filter(
                $headerAttributesInstances,
                fn (HeaderAttribute $attribute) => $attribute->status === '*' || $attribute->status == $responseStatusCode
            );

            if (! count($applicableHeaders)) {
                continue;
            }

            if ($response instanceof Reference) {
                $response = deep_copy($response->resolve());
            }

            if (! $response instanceof Response) {
                continue;
            }

            foreach ($applicableHeaders as $header) {
                $response->addHeader(
                    $header->name,
                    HeaderAttribute::toOpenApiHeader($header, $this->openApiTransformer),
                );
            }

            $operation->responses[$i] = $response;
        }
    }

    private function getResponseStatusCode(Response|Reference $response): ?string
    {
        if ($response instanceof Response) {
            return (string) $response->code;
        }

        $unreferencedResponse = $response->resolve();
        if ($unreferencedResponse instanceof Response) {
            return (string) $unreferencedResponse->code;
        }

        return null;
    }
}
