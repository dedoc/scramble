<?php

namespace Dedoc\Scramble\Support\Generator;

class OpenApi
{
    public string $version;

    public InfoObject $info;

    public Components $components;

    /** @var Server[] */
    public array $servers = [];

    /** @var Path[] */
    public array $paths = [];

    /** @var SecurityRequirement[]|null */
    public ?array $security = [];

    /** @var Tag[] */
    public array $tags = [];

    public function __construct(string $version)
    {
        $this->version = $version;
        $this->components = new Components;
    }

    public static function make(string $version)
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

    public function toArray()
    {
        $result = [
            'openapi' => $this->version,
            'info' => $this->info->toArray(),
        ];

        if (count($this->servers)) {
            $result['servers'] = array_map(
                fn (Server $s) => $s->toArray(),
                $this->servers,
            );
        }

        if (count($this->tags)) {
            $result['tags'] = array_map(
                fn (Tag $s) => $s->toArray(),
                $this->tags,
            );
        }

        if ($this->security) {
            $result['security'] = array_map(
                fn (SecurityRequirement $sr) => $sr->toArray(),
                $this->security,
            );
        }

        if (count($this->paths)) {
            $paths = [];

            foreach ($this->paths as $pathBuilder) {
                $paths['/'.$pathBuilder->path] = array_merge(
                    $paths['/'.$pathBuilder->path] ?? [],
                    $pathBuilder->toArray(),
                );
            }

            $result['paths'] = $paths;
        }

        if (count($serializedComponents = $this->components->toArray())) {
            $result['components'] = $serializedComponents;
        }

        return $result;
    }
}
