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

    private ?Security $defaultSecurity = null;

    public function __construct(string $version)
    {
        $this->version = $version;
        $this->components = new Components;
    }

    public static function make(string $version): self
    {
        return new self($version);
    }

    public function setComponents(Components $components): self
    {
        $this->components = $components;

        return $this;
    }

    public function secure(SecurityScheme $securityScheme): self
    {
        $securityScheme->default();

        $this->components->addSecurityScheme($securityScheme->schemeName, $securityScheme);
        if ($securityScheme->default) {
            $this->defaultSecurity(new Security($securityScheme->schemeName));
        }

        return $this;
    }

    public function setInfo(InfoObject $info): self
    {
        $this->info = $info;

        return $this;
    }

    /**
     * @param  Path[]  $paths
     */
    public function paths(array $paths): self
    {
        $this->paths = $paths;

        return $this;
    }

    public function addPath(Path $path): self
    {
        $this->paths[] = $path;

        return $this;
    }

    public function addServer(Server $server): self
    {
        $this->servers[] = $server;

        return $this;
    }

    public function defaultSecurity(Security $security): self
    {
        $this->defaultSecurity = $security;

        return $this;
    }

    /**
     * @return array<mixed>
     */
    public function toArray(): array
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

        if (count($serializedComponents = $this->components->toArray())) {
            $result['components'] = $serializedComponents;
        }

        return $result;
    }
}
