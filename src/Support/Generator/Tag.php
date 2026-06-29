<?php

namespace Dedoc\Scramble\Support\Generator;

class Tag
{
    use WithAttributes;
    use WithExtensions;

    public function __construct(
        public string $name,
        public ?string $description = null,
        public ?string $parent = null,
    ) {}

    public function toArray(): mixed
    {
        $result = array_filter([
            'name' => $this->name,
            'description' => $this->description,
            'parent' => $this->parent,
        ]);

        return array_merge(
            $result,
            $this->extensionPropertiesToArray()
        );
    }
}
