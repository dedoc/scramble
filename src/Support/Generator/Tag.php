<?php

namespace Dedoc\Scramble\Support\Generator;

class Tag
{
    use WithAttributes;
    use WithExtensions;

    public ?string $summary = null;

    public ?string $parent = null;

    public ?string $kind = null;

    public ?ExternalDocumentation $externalDocs = null;

    public function __construct(
        public string $name,
        public ?string $description = null,
    ) {}

    public function toArray(): mixed
    {
        $result = array_filter([
            'name' => $this->name,
            'description' => $this->description,
            'summary' => $this->summary,
            'parent' => $this->parent,
            'kind' => $this->kind,
            'externalDocs' => $this->externalDocs?->toArray(),
        ]);

        return array_merge(
            $result,
            $this->extensionPropertiesToArray()
        );
    }
}
