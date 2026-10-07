<?php

namespace Dedoc\Scramble\Support\Generator;

use Dedoc\Scramble\Support\Generator\Contracts\OpenApiSerializable;
use JsonSerializable;

class Link implements JsonSerializable, OpenApiSerializable
{
    use WithAttributes;
    use WithExtensions;

    public function __construct(
        public ?string $operationRef = null,
        public ?string $operationId = null,
        /** @var array<string, mixed> */
        public array $parameters = [],
        public mixed $requestBody = new MissingValue,
        public ?string $description = null,
        public ?Server $server = null,
    ) {}

    /**
     * @return $this
     */
    public function setOperationRef(?string $operationRef): self
    {
        $this->operationRef = $operationRef;

        return $this;
    }

    /**
     * @return $this
     */
    public function setOperationId(?string $operationId): self
    {
        $this->operationId = $operationId;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return $this
     */
    public function setParameters(array $parameters): self
    {
        $this->parameters = $parameters;

        return $this;
    }

    /**
     * @return $this
     */
    public function addParameter(string $name, mixed $value): self
    {
        $this->parameters[$name] = $value;

        return $this;
    }

    /**
     * @return $this
     */
    public function setRequestBody(mixed $requestBody): self
    {
        $this->requestBody = $requestBody;

        return $this;
    }

    /**
     * @return $this
     */
    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return $this
     */
    public function setServer(?Server $server): self
    {
        $this->server = $server;

        return $this;
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
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
            'operationRef' => $this->operationRef,
            'operationId' => $this->operationId,
            'description' => $this->description,
        ], fn ($value) => $value !== null);

        if ($this->server) {
            $result['server'] = $serializeItem($this->server);
        }

        if ($this->parameters) {
            $result['parameters'] = $this->parameters;
        }

        return array_merge(
            $result,
            $this->requestBody instanceof MissingValue ? [] : ['requestBody' => $this->requestBody],
            $this->extensionPropertiesToArray(),
        );
    }
}
