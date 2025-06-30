<?php

namespace Dedoc\Scramble\Attributes;

use Dedoc\Scramble\Support\Generator\Example as OpenApiExample;
use Dedoc\Scramble\Support\Generator\MissingValue as OpenApiMissingValue;

class Example
{
    public function __construct(
        public mixed $value = new MissingValue,
        public ?string $summary = null,
        public ?string $description = null,
        public ?string $externalValue = null,
    ) {}

    public static function toOpenApiExample(Example $example): OpenApiExample
    {
        return new OpenApiExample(
            value: $example->value instanceof MissingValue ? new OpenApiMissingValue : $example->value,
            summary: $example->summary,
            description: $example->description,
            externalValue: $example->externalValue,
        );
    }
}
