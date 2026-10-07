<?php

namespace Dedoc\Scramble\Support\Generator;

use Dedoc\Scramble\Support\Generator\Contracts\OpenApiSerializable;
use JsonSerializable;

class Operation implements JsonSerializable, OpenApiSerializable
{
    use WithAttributes;
    use WithExtensions;

    public string $method;

    public string $path = '';

    public ?string $operationId = null;

    public string $description = '';

    public string $summary = '';

    public ?ExternalDocumentation $externalDocs = null;

    public bool $deprecated = false;

    /** @var array<SecurityRequirement>|null */
    public ?array $security = null;

    public array $tags = [];

    /** @var (Parameter|Reference)[] */
    public array $parameters = [];

    public RequestBodyObject|Reference|null $requestBodyObject = null;

    /** @var (Response|Reference)[]|null */
    public ?array $responses = [];

    /** @var array<string, Callback|Reference> */
    public array $callbacks = [];

    /** @var Server[] */
    public array $servers = [];

    public function __construct(string $method)
    {
        $this->method = $method;
    }

    /** @return self */
    public static function make(string $method)
    {
        return new self($method);
    }

    /** @return $this */
    public function addRequestBodyObject(RequestBodyObject|Reference $requestBodyObject)
    {
        $this->requestBodyObject = $requestBodyObject;

        return $this;
    }

    /**
     * @param  Server[]  $servers
     * @return $this
     */
    public function servers(array $servers)
    {
        $this->servers = $servers;

        return $this;
    }

    /**
     * @param  Response|Reference  $response
     * @return $this
     */
    public function addResponse($response)
    {
        $this->responses[] = $response;

        return $this;
    }

    public function addCallback(string $name, Callback|Reference $callback): self
    {
        $this->callbacks[$name] = $callback;

        return $this;
    }

    /** @return $this */
    public function addSecurity($security)
    {
        if ($security === []) {
            $security = new SecurityRequirement([]);
        }

        $this->security ??= [];
        $this->security[] = $security;

        return $this;
    }

    /** @return $this */
    public function setOperationId(?string $operationId)
    {
        $this->operationId = $operationId;

        return $this;
    }

    /** @return $this */
    public function setMethod(string $method)
    {
        $this->method = $method;

        return $this;
    }

    /** @return $this */
    public function setPath(string $path)
    {
        $this->path = $path;

        return $this;
    }

    /** @return $this */
    public function summary(string $summary)
    {
        $this->summary = $summary;

        return $this;
    }

    /** @return $this */
    public function description(string $description)
    {
        $this->description = $description;

        return $this;
    }

    public function setExternalDocs(?ExternalDocumentation $externalDocs): self
    {
        $this->externalDocs = $externalDocs;

        return $this;
    }

    /** @return $this */
    public function deprecated(bool $deprecated)
    {
        $this->deprecated = $deprecated;

        return $this;
    }

    /** @return $this */
    public function setTags(array $tags)
    {
        $this->tags = array_map(fn ($t) => (string) $t, $tags);

        return $this;
    }

    /** @return $this */
    public function addParameters(array $parameters)
    {
        $this->parameters = array_merge($this->parameters, $parameters);

        return $this;
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray()
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
        $result = array_filter([
            'operationId' => $this->operationId,
            'description' => $this->description,
            'summary' => $this->summary,
            'externalDocs' => $this->externalDocs
                ? $serializeItem($this->externalDocs)
                : null,
            'deprecated' => $this->deprecated,
            'tags' => $this->tags,
            'parameters' => array_map($serializeItem, $this->parameters),
            'requestBody' => $this->requestBodyObject
                ? $serializeItem($this->requestBodyObject)
                : null,
            'responses' => $this->responses !== null && count($this->responses)
                ? $serializeItem(new Responses($this->responses))
                : null,
            'callbacks' => array_map($serializeItem, $this->callbacks),
            'security' => $this->security !== null
                ? array_map($serializeItem, $this->security)
                : null,
            'servers' => array_map($serializeItem, array_values($this->servers)),
        ], fn ($value, $key) => $key === 'security'
            ? $value !== null
            : (bool) $value,
            ARRAY_FILTER_USE_BOTH,
        );

        return array_merge(
            $result,
            $this->extensionPropertiesToArray(),
        );
    }
}
