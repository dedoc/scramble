<?php

namespace Dedoc\ApiDocs\Support\Generator;

class Server
{
    private string $url;

    private string $description = '';

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
        ]);
    }
}
