<?php

namespace Dedoc\Scramble\Support\Generator;

class Server
{
    public string $url;

    public string $description = '';

    /**
     * @var array<string, ServerVariable>
     */
    public array $variables = [];

    public function __construct(string $url)
    {
        $this->url = $url;
    }

    public static function make(string $url)
    {
        return new self($url);
    }

    public function setDescription(string $description): Server
    {
        $this->description = $description;

        return $this;
    }

    public function toArray()
    {
        return array_filter([
            'url' => $this->url,
            'description' => $this->description,
            'variables' => count($this->variables)
                ? array_map(fn (ServerVariable $v) => $v->toArray(), $this->variables)
                : null,
        ]);
    }

    /**
     * @param  array<string, ServerVariable>  $variables
     */
    public function variables(array $variables)
    {
        $this->variables = $variables;

        return $this;
    }
}
