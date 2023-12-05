<?php

namespace Dedoc\Scramble\Support\Generator;

use Illuminate\Support\Facades\Log;

class OpenApi
{
    public string $version;

    public InfoObject $info;

    public Components $components;

    /** @var Server[] */
    public array $servers = [];

    /** @var Path[] */
    public array $paths = [];

    /** @var string[] */
    public array $tags = [];

    private ?Security $defaultSecurity = null;

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
        $securityScheme->default();

        $this->components->addSecurityScheme($securityScheme->schemeName, $securityScheme);
        if ($securityScheme->default) {
            $this->defaultSecurity(new Security($securityScheme->schemeName));
        }

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

    /**
     * @param  string[]  $tags
     */
    public function tags(array $tags)
    {
        $this->tags = $tags;

        return $this;
    }

    public function addTag(string $tag)
    {
        $this->tags[] = $tag;

        return $this;
    }

    public function addServer(Server $server)
    {
        $this->servers[] = $server;

        return $this;
    }

    public function defaultSecurity(Security $security)
    {
        $this->defaultSecurity = $security;

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

        if ($this->defaultSecurity) {
            $result['security'] = [$this->defaultSecurity->toArray()];
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

        $tags = [];

        if (count($this->tags)) {
            $tags = $this->tags;
        }

        $result['tags'] = array_merge(
            $tags,
            collect($result['paths'])->pluck('*.tags')->flatten()->unique()->toArray(),
        );

        Log::debug($result['paths']);

        if (count($serializedComponents = $this->components->toArray())) {
            $result['components'] = $serializedComponents;
        }

        return $result;
    }
}
