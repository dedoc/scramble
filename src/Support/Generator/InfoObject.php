<?php

namespace Dedoc\Scramble\Support\Generator;

class InfoObject
{
    public string $title;

    public string $version;

    public string $description = '';

    public string $logo = '';

    public function __construct(string $title, string $version = '0.0.1')
    {
        $this->title = $title;
        $this->version = $version;
    }

    public static function make(string $title)
    {
        return new self($title);
    }

    public function setVersion(string $version): self
    {
        $this->version = $version;

        return $this;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function setLogo(string $logo): self
    {
        $this->logo = $logo;

        return $this;
    }

    public function toArray()
    {
        return array_filter([
            'title' => $this->title,
            'version' => $this->version,
            'description' => $this->description,
            'x-logo' => [
                'url' => $this->logo
            ],
        ]);
    }
}
