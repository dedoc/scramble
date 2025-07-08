<?php

namespace Dedoc\Scramble\Attributes;

use Attribute;
use Dedoc\Scramble\PhpDoc\PhpDocTypeHelper;
use Dedoc\Scramble\Support\Generator\Header as OpenApiHeader;
use Dedoc\Scramble\Support\Generator\MissingValue as OpenApiMissingValue;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\MixedType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\PhpDoc;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION)]
class Header
{
    /**
     * @param  scalar|array<mixed>|object|MissingValue  $example
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $description = null,
        public readonly ?string $type = null,
        public readonly ?string $format = null,
        public readonly ?bool $required = null,
        /** @var array<mixed>|scalar|null|MissingValue */
        public readonly mixed $default = new MissingValue,
        public readonly mixed $example = new MissingValue,
        /** @var array<string, Example> $examples */
        public readonly array $examples = [],
        public readonly ?bool $deprecated = null,
        public readonly ?bool $explode = null,
        public readonly int|string $status = 200,
    ) {}

    public static function toOpenApiHeader(Header $header, TypeTransformer $openApiTransformer): OpenApiHeader
    {
        $type = self::getType($header, $openApiTransformer);
        $default = $header->default instanceof MissingValue ? new OpenApiMissingValue : $header->default;
        $format = $header->format ?: '';

        if ($type instanceof MixedType && ($format || ! $default instanceof OpenApiMissingValue)) {
            $type = new StringType;
        }

        return new OpenApiHeader(
            description: $header->description,
            required: $header->required,
            deprecated: $header->deprecated,
            explode: $header->explode,
            schema: Schema::fromType(
                $type->default($default)->format($format)
            ),
            example: $header->example instanceof MissingValue ? new OpenApiMissingValue : $header->example,
            examples: array_map(fn ($e) => Example::toOpenApiExample($e), $header->examples),
        );
    }

    public static function getType(Header $header, TypeTransformer $openApiTransformer): OpenApiType
    {
        return $header->type ? $openApiTransformer->transform(
            PhpDocTypeHelper::toType(
                PhpDoc::parse("/** @return $header->type */")->getReturnTagValues()[0]->type ?? new IdentifierTypeNode('mixed')
            )
        ) : new MixedType;
    }
}
