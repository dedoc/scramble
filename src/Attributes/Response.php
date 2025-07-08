<?php

namespace Dedoc\Scramble\Attributes;

use Attribute;
use Dedoc\Scramble\PhpDoc\PhpDocTypeHelper;
use Dedoc\Scramble\Support\Generator\MediaType;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response as OpenApiResponse;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\PhpDoc;
use Illuminate\Support\Str;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;

/**
 * @phpstan-type BaseExample array<mixed>|scalar|null
 */
#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION)]
class Response
{
    public function __construct(
        public readonly int|string $status = 200,
        public readonly ?string $description = null,
        public readonly string $mediaType = 'application/json',
        public readonly ?string $type = null,
        public readonly ?string $format = null,
        /** @var BaseExample|BaseExample[] $examples */
        public readonly mixed $examples = [],
    ) {}

    public static function toOpenApiResponse(Response $responseAttribute, ?OpenApiResponse $originalResponse, TypeTransformer $openApiTransformer): OpenApiResponse
    {
        $response = $originalResponse ?? OpenApiResponse::make($responseAttribute->status);

        $response
            ->setDescription(self::getDescription($responseAttribute, $response))
            ->addContent(
                $responseAttribute->mediaType,
                self::getMediaType($responseAttribute, $response, $openApiTransformer),
            );

        return $response;
    }

    private static function getDescription(Response $responseAttribute, OpenApiResponse $response): string
    {
        if ($responseAttribute->description === null) {
            return $response->description;
        }

        return Str::replace('$0', $response->description, $responseAttribute->description);
    }

    private static function getMediaType(Response $responseAttribute, OpenApiResponse $response, TypeTransformer $openApiTransformer): MediaType
    {
        $mediaType = $response->content[$responseAttribute->mediaType] ?? new MediaType;

        return $mediaType
            ->setSchema(self::getSchema($responseAttribute, $mediaType->schema, $openApiTransformer));
    }

    private static function getSchema(Response $responseAttribute, Schema|Reference|null $schema, TypeTransformer $openApiTransformer): Schema|Reference
    {
        if (
            ! $responseAttribute->type
            && ! $responseAttribute->format
            && ! $responseAttribute->examples
        ) {
            return $schema ?: Schema::fromType(new StringType);
        }

        $schemaType = $schema
            ? clone ($schema instanceof Reference ? $schema->resolve()->type : $schema->type)
            : new StringType;

        if ($responseAttribute->type) {
            $schemaType = $openApiTransformer->transform(
                PhpDocTypeHelper::toType(
                    PhpDoc::parse("/** @return $responseAttribute->type */")->getReturnTagValues()[0]->type ?? new IdentifierTypeNode('mixed')
                )
            )->addProperties($schemaType);
        }

        if ($responseAttribute->format) {
            $schemaType->format($responseAttribute->format);
        }

        if ($responseAttribute->examples) {
            $schemaType->examples($responseAttribute->examples);
        }

        return Schema::fromType($schemaType);
    }
}
