<?php

namespace Dedoc\Scramble\Support\Generator;

class Example
{
    public function __construct(
        public mixed $value = new MissingValue,
        public ?string $summary = null,
        public ?string $description = null,
        public ?string $externalValue = null,
        public ?string $type = null,
    ) {
        if ($this->type === 'int') {
            $this->type = 'integer';
        }
        if ($this->type === 'bool') {
            $this->type = 'boolean';
        }
    }

    public function toArray()
    {
        return array_filter(
            ['value' => $this->value],
            fn ($v) => ! $v instanceof MissingValue,
        ) + array_filter([
            'summary' => $this->summary,
            'description' => $this->description,
            'externalValue' => $this->externalValue,
        ]);
    }
}
