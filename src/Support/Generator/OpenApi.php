<?php

namespace Dedoc\Scramble\Support\Generator;

use Dedoc\Scramble\OpenApiVersion;
use Dedoc\Scramble\Support\Generator\Contracts\OpenApiSerializable;
use JsonSerializable;

class OpenApi implements JsonSerializable, OpenApiSerializable
{
    use WithExtensions;

    /** OAS 3.2.0+ */
    public ?string $self = null;

    /** @deprecated Version is picked by serializer */
    public string $version;

    public InfoObject $info;

    public Components $components;

    public ?string $jsonSchemaDialect = null;

    /** @var Server[] */
    public array $servers = [];

    /** @var Path[] */
    public array $paths = [];

    /** @var array<string, Path|Reference> */
    public array $webhooks = [];

    /** @var SecurityRequirement[]|null */
    public ?array $security = [];

    /** @var Tag[] */
    public array $tags = [];

    public ?ExternalDocumentation $externalDocs = null;

    public function __construct(string $version = '')
    {
        $this->version = '';
        $this->components = new Components;
    }

    public static function make(string $version = '')
    {
        return new self($version);
    }

    public function setComponents(Components $components)
    {
        $this->components = $components;

        return $this;
    }

    public function secure(SecurityScheme $securityScheme)
    {
        $this->components->addSecurityScheme($securityScheme->schemeName, $securityScheme);

        $this->security ??= [];
        $this->security[] = new SecurityRequirement([$securityScheme->schemeName => []]);

        return $this;
    }

    public function setInfo(InfoObject $info)
    {
        $this->info = $info;

        return $this;
    }

    /**
     * @param  Path[]  $paths
     */
    public function paths(array $paths)
    {
        $this->paths = $paths;

        return $this;
    }

    public function addPath(Path $path)
    {
        $this->paths[] = $path;

        return $this;
    }

    public function addServer(Server $server)
    {
        $this->servers[] = $server;

        return $this;
    }

    public function setSelf(?string $self): self
    {
        $this->self = $self;

        return $this;
    }

    public function setJsonSchemaDialect(?string $jsonSchemaDialect): self
    {
        $this->jsonSchemaDialect = $jsonSchemaDialect;

        return $this;
    }

    /**
     * @param  array<string, Path|Reference>  $webhooks
     */
    public function setWebhooks(array $webhooks): self
    {
        $this->webhooks = $webhooks;

        return $this;
    }

    public function addWebhook(string $name, Path|Reference $webhook): self
    {
        $this->webhooks[$name] = $webhook;

        return $this;
    }

    public function setExternalDocs(?ExternalDocumentation $externalDocs): self
    {
        $this->externalDocs = $externalDocs;

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

    public function serializeAs31(): array
    {
        return $this->serialize(OpenApiVersion::V3_1, '3.1.2', fn (OpenApiSerializable $item) => $item->serializeAs31());
    }

    public function serializeAs32(): array
    {
        return $this->serialize(OpenApiVersion::V3_2, '3.2.1', fn (OpenApiSerializable $item) => $item->serializeAs32());
    }

    /**
     * @param  callable(OpenApiSerializable): mixed  $serializeItem
     */
    private function serialize(OpenApiVersion $version, string $openApiVersion, callable $serializeItem): array
    {
        $result = [
            'openapi' => $openApiVersion,
            'info' => $serializeItem($this->info),
        ];

        if ($version >= OpenApiVersion::V3_2 && $this->self) {
            $result['$self'] = $this->self;
        }

        if ($this->jsonSchemaDialect !== null) {
            $result['jsonSchemaDialect'] = $this->jsonSchemaDialect;
        }

        if (count($this->servers)) {
            $result['servers'] = array_map(
                $serializeItem,
                $this->servers,
            );
        }

        if (count($this->tags)) {
            $result['tags'] = array_map(
                $serializeItem,
                $this->tags,
            );

            if ($version === OpenApiVersion::V3_1 && $tagGroups = $this->serializeTagGroups()) {
                $result['x-tagGroups'] = $tagGroups;
            }
        }

        if ($this->security) {
            $result['security'] = array_map(
                $serializeItem,
                $this->security,
            );
        }

        if (count($this->paths)) {
            $paths = [];

            foreach ($this->paths as $pathBuilder) {
                $path = '/'.$pathBuilder->path;
                $existingPath = (array) ($paths[$path] ?? []);
                $serializedPath = (array) $serializeItem($pathBuilder);

                if (isset($existingPath['additionalOperations'], $serializedPath['additionalOperations'])) {
                    $serializedPath['additionalOperations'] = array_replace(
                        $existingPath['additionalOperations'],
                        $serializedPath['additionalOperations'],
                    );
                }

                $paths[$path] = array_merge($existingPath, $serializedPath) ?: (object) [];
            }

            $result['paths'] = $paths;
        }

        if (count($this->webhooks)) {
            $result['webhooks'] = array_map($serializeItem, $this->webhooks);
        }

        if (count($serializedComponents = $serializeItem($this->components))) {
            $result['components'] = $serializedComponents;
        }

        if ($this->externalDocs !== null) {
            $result['externalDocs'] = $serializeItem($this->externalDocs);
        }

        return array_merge($result, $this->extensionPropertiesToArray());
    }

    /**
     * Each group lists the immediate children of its parent tag.
     * Standalone tags get their own group to remain visible in renderers like Redoc.
     *
     * @return list<array{name: string, tags: list<string>}>
     */
    private function serializeTagGroups(): array
    {
        $parents = [];

        foreach ($this->tags as $tag) {
            if ($tag->parent !== null) {
                $parents[$tag->parent] = true;
            }
        }

        if (! $parents) {
            return [];
        }

        $groups = [];
        $tagNames = [];

        foreach ($this->tags as $tag) {
            $tagNames[$tag->name] = true;

            if ($tag->parent === null && isset($parents[$tag->name])) {
                continue;
            }

            $name = $tag->parent ?? $tag->name;
            $groups[$name] ??= ['name' => $name, 'tags' => []];
            $groups[$name]['tags'][] = $tag->name;
        }

        foreach ($this->paths as $path) {
            foreach ($path->operations as $operation) {
                foreach ($operation->tags as $name) {
                    if (isset($tagNames[$name])) {
                        continue;
                    }

                    $groups[$name] ??= ['name' => $name, 'tags' => []];
                    $groups[$name]['tags'][] = $name;
                    $tagNames[$name] = true;
                }
            }
        }

        return array_values($groups);
    }
}
