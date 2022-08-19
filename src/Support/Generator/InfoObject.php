<?php

namespace Dedoc\ApiDocs\Support\Generator;

class InfoObject
{
    public string $title;

    public string $version;

    public function __construct(string $title)
    {
        $this->title = $title;
    }

    public static function make(string $title)
    {
        return new self($title);
    }

    public function setVersion(string $version): InfoObject
    {
        $this->version = $version;

        return $this;
    }

    public function toArray()
    {
        return [
            'title' => $this->title,
            'version' => $this->version,
        ];
    }
}
