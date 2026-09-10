<?php

namespace Dedoc\Scramble\Support\Generator;

class ExternalDocumentation
{
    public function __construct(
        public string $url,
        public ?string $description = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'description' => $this->description,
            'url' => $this->url,
        ]);
    }
}
