<?php

namespace Dedoc\Documentor\Support\ResponseExtractor;

use Dedoc\Documentor\Support\Generator\OpenApi;
use Dedoc\Documentor\Support\Generator\Response;
use Dedoc\Documentor\Support\Generator\Schema;
use Dedoc\Documentor\Support\Generator\Types\ArrayType;
use Dedoc\Documentor\Support\Generator\Types\ObjectType;

class AnonymousResourceCollectionResponseExtractor
{
    private string $class;

    private OpenApi $openApi;

    public function __construct(OpenApi $openApi, string $class)
    {
        $this->class = $class;
        $this->openApi = $openApi;
    }

    public function extract(): ?Response
    {
        $responseWrapKey = $this->class::$wrap;

        $response = (new JsonResourceResponseExtractor($this->openApi, $this->class))
            ->extract();

        if (! $response) {
            return null;
        }

        $type = $response->getContent('application/json');

        $responseType = $responseWrapKey
            ? (new ObjectType)->addProperty($responseWrapKey, $type)->setRequired([$responseWrapKey])
            : (new ArrayType())->setItems($type);

        return Response::make(200)
            ->description('Array of `'.$this->openApi->components->uniqueSchemaName($this->class).'`')
            ->setContent(
                'application/json',
                Schema::fromType($responseType),
            );
    }
}
