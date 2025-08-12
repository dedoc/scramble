<?php

namespace Dedoc\Scramble\Support\Generator;

use Dedoc\Scramble\Exceptions\OpenApiReferenceTargetNotFoundException;

class Operation
{
    use WithAttributes;
    use WithExtensions;

    public string $method;

    public string $path = '';

    public ?string $operationId = null;

    public string $description = '';

    public string $summary = '';

    public bool $deprecated = false;

    /** @var array<SecurityRequirement>|null */
    public ?array $security = null;

    public array $tags = [];

    /** @var (Parameter|Reference)[] */
    public array $parameters = [];

    public ?RequestBodyObject $requestBodyObject = null;

    /** @var (Response|Reference)[]|null */
    public ?array $responses = [];

    /** @var Server[] */
    public array $servers = [];

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

    /**
     * @param  Server[]  $servers
     */
    public function servers(array $servers)
    {
        $this->servers = $servers;

        return $this;
    }

    /**
     * @param  Response|Reference  $response
     */
    public function addResponse($response)
    {
        $this->responses[] = $response;

        return $this;
    }

    public function addSecurity($security)
    {
        if ($security === []) {
            $security = new SecurityRequirement([]);
        }

        $this->security ??= [];
        $this->security[] = $security;

        return $this;
    }

    public function setOperationId(?string $operationId)
    {
        $this->operationId = $operationId;

        return $this;
    }

    public function setMethod(string $method)
    {
        $this->method = $method;

        return $this;
    }

    public function setPath(string $path)
    {
        $this->path = $path;

        return $this;
    }

    public function summary(string $summary)
    {
        $this->summary = $summary;

        return $this;
    }

    public function description(string $description)
    {
        $this->description = $description;

        return $this;
    }

    public function deprecated(bool $deprecated)
    {
        $this->deprecated = $deprecated;

        return $this;
    }

    public function setTags(array $tags)
    {
        $this->tags = array_map(fn ($t) => (string) $t, $tags);

        return $this;
    }

    public function addParameters(array $parameters)
    {
        $this->parameters = array_merge($this->parameters, $parameters);

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray()
    {
        $result = [];

        if ($this->operationId) {
            $result['operationId'] = $this->operationId;
        }

        if ($this->description) {
            $result['description'] = $this->description;
        }

        if ($this->summary) {
            $result['summary'] = $this->summary;
        }

        if ($this->deprecated) {
            $result['deprecated'] = $this->deprecated;
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

        if ($this->responses !== null && count($this->responses)) {
            $responses = [];
            foreach ($this->responses as $response) {
                if ($response instanceof Response) {
                    $responses[$response->code ?: 'default'] = $response->toArray();
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

                    $responses[$referencedResponse->code ?: 'default'] = $response->toArray();
                }
            }
            $result['responses'] = $responses;
        }

        if ($this->security !== null) {
            $result['security'] = array_map(
                fn ($s) => $s->toArray(),
                $this->security,
            );
        }

        if (count($this->servers)) {
            $servers = [];
            foreach ($this->servers as $server) {
                $servers[] = $server->toArray();
            }
            $result['servers'] = $servers;
        }

        return array_merge(
            $result,
            $this->extensionPropertiesToArray(),
        );
    }
}
