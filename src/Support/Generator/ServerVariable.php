<?php

namespace Dedoc\Scramble\Support\Generator;

class ServerVariable
{
    use WithExtensions;

    /**
     * @param  non-empty-array<string>|null  $enum
     */
    public function __construct(
        public string $default,
        public ?array $enum = null,
        public ?string $description = null
    ) {}

    /**
     * @param  non-empty-array<string>|null  $enum
     */
    public static function make(
        string $default,
        ?array $enum = null,
        ?string $description = null
    ) {
        return new self($default, $enum, $description);
    }

    public function toArray()
    {
        $result = array_filter([
            'default' => $this->default,
            'enum' => $this->enum && count($this->enum) ? $this->enum : null,
            'description' => $this->description,
        ]);

        return array_merge(
            $result,
            $this->extensionPropertiesToArray()
        );
    }
}
