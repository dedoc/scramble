<?php

namespace Dedoc\Scramble\Support\Generator;

use Dedoc\Scramble\Exceptions\OpenApiReferenceTargetNotFoundException;
use Dedoc\Scramble\OpenApiVersion;
use Dedoc\Scramble\Support\Generator\Contracts\OpenApiSerializable;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonSerializable;

class Components implements JsonSerializable, OpenApiSerializable
{
    use WithExtensions;

    /**
     * Reference values here represent Schema Objects containing `$ref`, not OpenAPI Reference Objects.
     * The Reference class historically serves both roles depending on context.
     *
     * @var array<string, Schema|Reference>
     */
    public array $schemas = [];

    /** @var array<string, Response|Reference> */
    public array $responses = [];

    /** @var array<string, Parameter|Reference> */
    public array $parameters = [];

    /** @var array<string, Example|Reference> */
    public array $examples = [];

    /** @var array<string, RequestBodyObject|Reference> */
    public array $requestBodies = [];

    /** @var array<string, Header|Reference> */
    public array $headers = [];

    /** @var array<string, SecurityScheme|Reference> */
    public array $securitySchemes = [];

    /** @var array<string, Link|Reference> */
    public array $links = [];

    /** @var array<string, Callback|Reference> */
    public array $callbacks = [];

    /** @var array<string, Path|Reference> */
    public array $pathItems = [];

    /**
     * OAS 3.2.0+
     *
     * @var array<string, MediaType|Reference>
     */
    public array $mediaTypes = [];

    // @todo: figure out how to solve the problem of duplicating resource names better
    public array $tempNames = [];

    public function addSecurityScheme(string $name, SecurityScheme $securityScheme)
    {
        $this->securitySchemes[$name] = $securityScheme;

        return $this;
    }

    public function hasSchema(string $schemaName): bool
    {
        return array_key_exists($schemaName, $this->schemas);
    }

    public function addSchema(string $schemaName, Schema $schema): Reference
    {
        $this->schemas[$schemaName] = $schema;

        return new Reference('schemas', $schemaName, $this);
    }

    public function removeSchema(string $schemaName): void
    {
        unset($this->schemas[$schemaName]);
    }

    public function removeResponse(string $responseName): void
    {
        unset($this->responses[$responseName]);
    }

    public function addParameter(string $name, Parameter|Reference $parameter): Reference
    {
        return $this->add(new Reference('parameters', $name, $this), $parameter);
    }

    public function addExample(string $name, Example|Reference $example): Reference
    {
        return $this->add(new Reference('examples', $name, $this), $example);
    }

    public function addRequestBody(string $name, RequestBodyObject|Reference $requestBody): Reference
    {
        return $this->add(new Reference('requestBodies', $name, $this), $requestBody);
    }

    public function addHeader(string $name, Header|Reference $header): Reference
    {
        return $this->add(new Reference('headers', $name, $this), $header);
    }

    public function addLink(string $name, Link|Reference $link): Reference
    {
        return $this->add(new Reference('links', $name, $this), $link);
    }

    public function addCallback(string $name, Callback|Reference $callback): Reference
    {
        return $this->add(new Reference('callbacks', $name, $this), $callback);
    }

    public function addPathItem(string $name, Path|Reference $pathItem): Reference
    {
        return $this->add(new Reference('pathItems', $name, $this), $pathItem);
    }

    /**
     * @param  array<string, MediaType|Reference>  $mediaTypes
     * @return $this
     */
    public function setMediaTypes(array $mediaTypes): self
    {
        $this->mediaTypes = $mediaTypes;

        return $this;
    }

    public function addMediaType(string $name, MediaType|Reference $mediaType): Reference
    {
        return $this->add(new Reference('mediaTypes', $name, $this), $mediaType);
    }

    public function removeMediaType(string $name): void
    {
        unset($this->mediaTypes[$name]);
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    public function toArray()
    {
        return $this->serializeAs31();
    }

    public function serializeAs31(): mixed
    {
        return $this->serialize(OpenApiVersion::V3_1, fn (OpenApiSerializable $item) => $item->serializeAs31());
    }

    public function serializeAs32(): mixed
    {
        return $this->serialize(OpenApiVersion::V3_2, fn (OpenApiSerializable $item) => $item->serializeAs32());
    }

    /**
     * @param  callable(OpenApiSerializable): mixed  $serializeItem
     */
    private function serialize(OpenApiVersion $version, callable $serializeItem): mixed
    {
        $result = [];

        if (count($this->securitySchemes)) {
            $result['securitySchemes'] = collect($this->securitySchemes)
                ->map($serializeItem)
                ->toArray();
        }

        if (count($this->schemas)) {
            $result['schemas'] = collect($this->schemas)
                ->mapWithKeys(function (Schema|Reference $s, string $fullName) use ($serializeItem) {
                    $name = $this->uniqueSchemaName($fullName);

                    if ($s instanceof Schema) {
                        $s->setTitle($name);
                    }

                    return [
                        $name => $serializeItem($s),
                    ];
                })
                ->sortKeys()
                ->toArray();
        }

        $types = ['responses', 'parameters', 'examples', 'requestBodies', 'headers', 'links', 'callbacks', 'pathItems'];

        if ($version === OpenApiVersion::V3_2) {
            $types[] = 'mediaTypes';
        }

        foreach ($types as $type) {
            if (! count($this->{$type})) {
                continue;
            }

            $result[$type] = collect($this->{$type})
                ->mapWithKeys(function (OpenApiSerializable $item, string $fullName) use ($serializeItem) {
                    return [
                        $this->uniqueSchemaName($fullName) => $serializeItem($item),
                    ];
                })
                ->toArray();
        }

        return array_merge($result, $this->extensionPropertiesToArray());
    }

    /**
     * @deprecated Use context instead
     */
    public function uniqueSchemaName(string $fullName)
    {
        $shortestPossibleName = class_basename($fullName);

        if (
            ($this->tempNames[$shortestPossibleName] ?? null) === null
            || ($this->tempNames[$shortestPossibleName] ?? null) === $fullName
        ) {
            $this->tempNames[$shortestPossibleName] = $fullName;

            return static::slug($shortestPossibleName);
        }

        return static::slug($fullName);
    }

    public function getSchemaReference(string $schemaName)
    {
        return new Reference('schemas', $schemaName, $this);
    }

    public function getSchema(string $schemaName)
    {
        return $this->schemas[$schemaName];
    }

    /**
     * @internal
     *
     * @deprecated
     */
    public static function slug(string $name)
    {
        return Str::replace('\\', '.', $name);
    }

    public function has(Reference $reference): bool
    {
        $this->ensureValidReference($reference);

        return array_key_exists($reference->fullName, $this->{$reference->referenceType});
    }

    public function add(Reference $reference, $object): Reference
    {
        $this->ensureValidReference($reference, $object);

        $this->{$reference->referenceType}[$reference->fullName] = $object;

        return $reference;
    }

    public function get(Reference $reference)
    {
        $this->ensureValidReference($reference);

        $references = $this->{$reference->referenceType};

        if (! array_key_exists($reference->fullName, $references)) {
            throw new OpenApiReferenceTargetNotFoundException(sprintf(
                '[%s] reference target doesn\'t exist in the [%s] list',
                $reference->fullName,
                $reference->referenceType,
            ));
        }

        return $references[$reference->fullName];
    }

    private function ensureValidReference(Reference $reference, $object = null)
    {
        if ($reference->uri !== null) {
            throw new InvalidArgumentException('URI references cannot be used as local component registry keys.');
        }

        $references = [
            'schemas' => Schema::class,
            'responses' => Response::class,
            'parameters' => Parameter::class,
            'examples' => Example::class,
            'requestBodies' => RequestBodyObject::class,
            'headers' => Header::class,
            'securitySchemes' => SecurityScheme::class,
            'links' => Link::class,
            'callbacks' => Callback::class,
            'pathItems' => Path::class,
            'mediaTypes' => MediaType::class,
        ];

        if (! in_array($reference->referenceType, $referenceTypes = array_keys($references))) {
            $validTypes = implode(', ', $referenceTypes);

            throw new InvalidArgumentException("Only $validTypes references are allowed");
        }

        if ($object === null) {
            return;
        }

        $expectedType = $references[$reference->referenceType];

        if (! $object instanceof Reference && ! is_a($object, $expectedType)) {
            $actualType = get_class($object);

            throw new InvalidArgumentException("Object must be $expectedType to be added to $reference->referenceType references, $actualType given");
        }
    }
}
