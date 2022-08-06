<?php

namespace Dedoc\Documentor\Support\Generator;

class InfoObject
{
    private string $title;

    public function __construct(string $title)
    {
        $this->title = $title;
    }

    public function toArray()
    {
        return [
            'title' => $this->title,
        ];
    }
}
