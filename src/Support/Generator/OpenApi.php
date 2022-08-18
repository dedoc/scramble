<?php

namespace Dedoc\ApiDocs\Support\Generator;

class OpenApi
{
    private string $version;

    private InfoObject $info;

    public Components $components;

    /** @var Path[] */
    private array $paths = [];

    public function __construct(string $version)
    {
        $this->version = $version;
        $this->components = new Components;
    }

    public static function make(string $version)
    {
        return new self($version);
    }

    public function addInfo(InfoObject $info)
    {
        $this->info = $info;

        return $this;
    }

    public function addPath(Path $buildPath)
    {
        $this->paths[] = $buildPath;

        return $this;
    }

    public function toArray()
    {
        $result = [
            'openapi' => $this->version,
            'info' => $this->info->toArray(),
        ];

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
