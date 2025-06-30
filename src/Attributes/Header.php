<?php

namespace Dedoc\Scramble\Attributes;

use Attribute;
use Dedoc\Scramble\Support\Generator\Header as OpenApiHeader;
use Dedoc\Scramble\Support\Generator\MissingValue as OpenApiMissingValue;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_ALL)]
class Header
{
    /**
     * @param  scalar|array<mixed>|object|MissingValue  $example
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $description = null,
        public readonly ?bool $deprecated = null,
        public readonly ?bool $required = null,
        public readonly ?bool $explode = null,
        public readonly ?string $type = null,
        public readonly ?string $format = null,
        public readonly mixed $example = new MissingValue,
        /** @var array<string, Example> $examples */
        public readonly array $examples = [],
        public readonly int|string|null $statusCode = null,
    ) {}

    public static function toOpenApiHeader(Header $header): OpenApiHeader
    {
        $examples = array_map(fn ($e) => Example::toOpenApiExample($e), $header->examples);

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
