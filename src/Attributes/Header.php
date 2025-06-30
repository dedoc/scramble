<?php

namespace Dedoc\Scramble\Attributes;

use Attribute;
use Dedoc\Scramble\Support\Generator\Example as OpenApiExample;
use Dedoc\Scramble\Support\Generator\MissingValue as OpenApiMissingValue;
use Dedoc\Scramble\Support\Generator\Header as OpenApiHeader;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_ALL)]
class Header
{
    public readonly bool $required;

    /**
     * @param  scalar|array<mixed>|object|MissingValue  $example
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $description = null,
        ?bool $required = null,
        public readonly bool $deprecated = false,
        public readonly ?bool $explode = null,
        public readonly ?string $type = null,
        public readonly ?string $format = null,
        public readonly mixed $example = new MissingValue,
        /** @var array<string, Example> $examples */
        public readonly array $examples = [],
        public readonly int|string|null $statusCode = null,
    ) {
        $this->required = $required ?? false;
    }

    public static function toOpenApiHeader(Header $header): OpenApiHeader
    {
        $examples = [];
        foreach ($header->examples as $key => $example) {
            $examples[$key] = Example::toOpenApiExample($example);
        }

        return new OpenApiHeader(
            description: $header->description,
            required: $header->required,
            deprecated: $header->deprecated,
            explode: $header->explode,
            example: $header->example instanceof MissingValue ? new OpenApiMissingValue : $header->example,
            examples: $examples,
        );
    }
} 