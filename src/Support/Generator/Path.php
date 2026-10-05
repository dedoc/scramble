<?php

namespace Dedoc\Scramble\Support\Generator;

use Dedoc\Scramble\Support\Generator\Contracts\OpenApiSerializable;
use JsonSerializable;

class Path implements JsonSerializable, OpenApiSerializable
{
    use WithExtensions;

    public string $path;

    /**
     * @deprecated Path Item `$ref` has special sibling semantics that may change in future OpenAPI versions.
     */
    public ?string $ref = null;

    public ?string $summary = null;

    public ?string $description = null;

    /** @var array<string, Operation> */
    public array $operations = [];

    /** @var Server[] */
    public array $servers = [];

    /** @var (Parameter|Reference)[] */
    public array $parameters = [];

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    public static function make(string $path)
    {
        return new self($path);
    }

    /**
     * @param  Server[]  $servers
     */
    public function servers(array $servers)
    {
        $this->servers = $servers;

        return $this;
    }

    public function addOperation(Operation $operationBuilder)
    {
        $this->operations[$operationBuilder->method] = $operationBuilder;

        return $this;
    }

    /**
     * @deprecated Path Item `$ref` has special sibling semantics that may change in future OpenAPI versions.
     */
    public function setRef(?string $ref): self
    {
        $this->ref = $ref;

        return $this;
    }

    public function setSummary(?string $summary): self
    {
        $this->summary = $summary;

        return $this;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @param  (Parameter|Reference)[]  $parameters
     */
    public function setParameters(array $parameters): self
    {
        $this->parameters = $parameters;

        return $this;
    }

    /**
     * @param  (Parameter|Reference)[]  $parameters
     */
    public function addParameters(array $parameters): self
    {
        $this->parameters = array_merge($this->parameters, $parameters);

        return $this;
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

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
     * @param callable(OpenApiSerializable): mixed $serializeItem
     */
    private function serialize(callable $serializeItem): mixed
    {
        $result = array_filter([
            '$ref' => $this->ref,
            'summary' => $this->summary,
            'description' => $this->description,
        ], fn ($value) => $value !== null);

        foreach ($this->operations as $method => $operation) {
            $result[$method] = $serializeItem($operation);
        }

        if (count($this->servers)) {
            $result['servers'] = array_map($serializeItem, array_values($this->servers));
        }

        if (count($this->parameters)) {
            $result['parameters'] = array_map($serializeItem, $this->parameters);
        }

        return array_merge($result, $this->extensionPropertiesToArray());
    }
}
