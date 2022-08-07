<?php

namespace Dedoc\Documentor\Support\Generator;

class Operation
{
    public string $method;

    public ?string $operationId = null;

    public string $description = '';

    public array $tags = [];

    /** @var Parameter[] */
    public array $parameters = [];

    private ?RequestBodyObject $requestBodyObject = null;

    /** @var Response[]|null */
    private ?array $responses = [];

    public function __construct(string $method)
    {
        $this->method = $method;
    }

    public static function make(string $method)
    {
        return new self($method);
    }

    public function addRequestBodyObject(RequestBodyObject $requestBodyObject)
    {
        $this->requestBodyObject = $requestBodyObject;

        return $this;
    }

    public function addResponse(Response $response)
    {
        $this->responses[] = $response;

        return $this;
    }

    public function setOperationId(?string $operationId)
    {
        $this->operationId = $operationId;

        return $this;
    }

    public function description(string $description)
    {
        $this->description = $description;

        return $this;
    }

    public function setTags(array $tags)
    {
        $this->tags = $tags;

        return $this;
    }

    public function addParameters(array $parameters)
    {
        $this->parameters = array_merge($this->parameters, $parameters);

        return $this;
    }

    public function toArray()
    {
        $result = [
            //            'tags' => ['Shit I know', 'Wow'], 'summary' => 'List Todo Item', 'description' => 'Wow, this is very interesting'
        ];

        if ($this->operationId) {
            $result['operationId'] = $this->operationId;
        }

        if ($this->description) {
            $result['description'] = $this->description;
        }

        if (count($this->tags)) {
            $result['tags'] = $this->tags;
        }

        if (count($this->parameters)) {
            $result['parameters'] = array_map(fn (Parameter $p) => $p->toArray(), $this->parameters);
        }

        if ($this->requestBodyObject) {
            $result['requestBody'] = $this->requestBodyObject->toArray();
        }

        if (count($this->responses)) {
            $responses = [];
            foreach ($this->responses as $response) {
                $responses[$response->code ?: 'default'] = $response->toArray();
            }
            $result['responses'] = $responses;
        }

        return $result;
    }
}
