<?php

namespace Dedoc\Scramble\Support\Generator;

class Link
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

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = array_filter([
            'operationRef' => $this->operationRef,
            'operationId' => $this->operationId,
            'description' => $this->description,
        ], fn ($value) => $value !== null);

        if ($this->server) {
            $result['server'] = $this->server->toArray();
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
