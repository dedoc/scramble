<?php

namespace Dedoc\Scramble\Attributes;

use Attribute;
use Dedoc\Scramble\Infer\Services\FileNameResolver;
use Dedoc\Scramble\PhpDoc\PhpDocTypeHelper;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response as OpenApiResponse;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\PhpDoc;
use Illuminate\Support\Str;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;

use function DeepCopy\deep_copy;

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

    public static function toOpenApiResponse(Response $responseAttribute, ?OpenApiResponse $originalResponse, TypeTransformer $openApiTransformer, ?FileNameResolver $nameResolver): OpenApiResponse
    {
        $response = $originalResponse ? deep_copy($originalResponse) : OpenApiResponse::make($responseAttribute->status);

        $response = self::applyResponseMediaType($responseAttribute, $response, $openApiTransformer, $nameResolver);

        $response->setDescription(self::getDescription($responseAttribute, $response));

        return $response;
    }

    private static function applyResponseMediaType(Response $responseAttribute, OpenApiResponse $response, TypeTransformer $openApiTransformer, ?FileNameResolver $nameResolver): OpenApiResponse
    {
        if (! $responseAttribute->type) {
            return $response
                ->setContent(
                    $responseAttribute->mediaType,
                    self::getMediaType($responseAttribute, $response),
                );
        }

        $responseFromType = $openApiTransformer->toResponse(
            PhpDocTypeHelper::toType(
                PhpDoc::parse("/** @return $responseAttribute->type */", $nameResolver)->getReturnTagValues()[0]->type ?? new IdentifierTypeNode('mixed')
            )
        );

        if ($responseFromType instanceof Reference) {
            $responseFromType = deep_copy($responseFromType->resolve());
        }

        /** @var OpenApiResponse|null $responseFromType */
        if (! $responseFromType) {
            return $response->setContent(
                $responseAttribute->mediaType,
                self::getMediaType($responseAttribute, $response),
            );
        }

        return $response
            ->setDescription($responseFromType->description ?: self::getDescription($responseAttribute, $response))
            ->setContent(
                $responseAttribute->mediaType,
                self::getMediaType($responseAttribute, $responseFromType),
            );
    }

    private static function getDescription(Response $responseAttribute, OpenApiResponse $response): string
    {
        if ($responseAttribute->description === null) {
            return $response->description;
        }

        return Str::replace('$0', $response->description, $responseAttribute->description);
    }

    private static function getMediaType(Response $responseAttribute, OpenApiResponse $response): Schema|Reference
    {
        return self::getSchema($responseAttribute, $response->content[$responseAttribute->mediaType] ?? null);
    }

    private static function getSchema(Response $responseAttribute, Schema|Reference|null $schema): Schema|Reference
    {
        if (! $responseAttribute->format && ! $responseAttribute->examples) {
            return $schema ?: Schema::fromType(new StringType);
        }

        $schemaType = $schema
            ? clone ($schema instanceof Reference ? $schema->resolve()->type : $schema->type)
            : new StringType;

        if ($responseAttribute->format) {
            $schemaType->format($responseAttribute->format);
        }

        if ($responseAttribute->examples) {
            $schemaType->examples($responseAttribute->examples);
        }

        return Schema::fromType($schemaType);
    }
}
