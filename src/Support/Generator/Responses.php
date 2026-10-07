<?php

namespace Dedoc\Scramble\Support\Generator;

use Dedoc\Scramble\Exceptions\OpenApiReferenceTargetNotFoundException;
use Dedoc\Scramble\Support\Generator\Contracts\OpenApiSerializable;
use JsonSerializable;

class Responses implements JsonSerializable, OpenApiSerializable
{
    use WithExtensions;

    public function __construct(
        /** @var (Response|Reference)[] */
        public array $responses = []
    ) {}

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    public function toArray(): mixed
    {
        return $this->serializeAs31();
    }

    public function serializeAs31(): mixed
    {
        return $this->serialize(fn (OpenApiSerializable $item) => $item->serializeAs31());
    }

    public function serializeAs32(): mixed
    {
        return $this->serialize(fn (OpenApiSerializable $item) => $item->serializeAs32());
    }

    /**
     * @param  callable(OpenApiSerializable): mixed  $serializeItem
     */
    private function serialize(callable $serializeItem): mixed
    {
        $responses = [];

        foreach ($this->responses as $response) {
            if ($response instanceof Response) {
                $responses[$response->code ?: 'default'] = $serializeItem($response);
            } elseif ($response instanceof Reference) {
                try {
                    $referencedResponse = $response->resolve();
                } catch (OpenApiReferenceTargetNotFoundException) {
                    // This catch is needed in case a reference target is removed from the document (when a
                    // reference is not used in the document for example). But all of this should not really be
                    // needed when `code` is not stored in the response due to the resolution
                    // will not be needed at all to resolve the code.
                    continue;
                }

                $responses[$referencedResponse->code ?: 'default'] = $serializeItem($response);
            }
        }

        return array_replace($responses, $this->extensionPropertiesToArray());
    }
}
