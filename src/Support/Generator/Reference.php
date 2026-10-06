<?php

namespace Dedoc\Scramble\Support\Generator;

use Dedoc\Scramble\OpenApiVersion;
use Dedoc\Scramble\Support\Generator\Contracts\OpenApiSerializable;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonSerializable;
use LogicException;

class Reference extends Type implements JsonSerializable, OpenApiSerializable
{
    public ?string $referenceType;

    public ?string $shortName;

    /**
     * This must be a unique name across all the references with the same type!
     */
    public ?string $fullName;

    public ?string $summary = null;

    public ?string $uri;

    private ?Components $components;

    public function __construct(
        ?string $referenceType = null,
        ?string $fullName = null,
        ?Components $components = null,
        ?string $shortName = null,
        ?string $uri = null,
    ) {
        parent::__construct('$ref');

        if ($uri === null && ($referenceType === null || $fullName === null || $components === null)) {
            throw new InvalidArgumentException('A reference requires either a URI or a component type, name, and registry.');
        }

        if ($uri !== null && ($referenceType !== null || $fullName !== null || $components !== null || $shortName !== null)) {
            throw new InvalidArgumentException('A URI reference cannot also specify local component metadata.');
        }

        $this->referenceType = $referenceType;
        $this->fullName = $fullName;
        $this->components = $components;
        $this->shortName = $shortName;
        $this->uri = $uri;
    }

    public static function fromUri(string $uri): self
    {
        return new self(uri: $uri);
    }

    public function setSummary(?string $summary): self
    {
        $this->summary = $summary;

        return $this;
    }

    public function getUri(): string
    {
        return $this->uri ?? "#/components/{$this->referenceType}/{$this->getUniqueName()}";
    }

    public function resolve()
    {
        if ($this->uri !== null || $this->components === null) {
            throw new LogicException('URI references cannot be resolved through the local component registry.');
        }

        return $this->components->get($this);
    }

    public function getUniqueName()
    {
        if ($this->uri !== null || $this->components === null || $this->fullName === null) {
            throw new LogicException('URI references do not have a local component name.');
        }

        return $this->components->uniqueSchemaName($this->shortName ?: $this->fullName);
    }

    public function setDescription(string $description): Type
    {
        $casesDescription = $this->getEnumReferenceCasesDescription();

        if ($description && $casesDescription) {
            $description = Str::replaceLast($casesDescription, '', $description)."\n".$casesDescription;
        }

        return parent::setDescription($description);
    }

    /**
     * This is a workaround for Stoplight Elements. When `enum_cases_description_strategy` is set to `description` and
     * enum used as array item value and user adds some description, we want to keep the description in the UI.
     */
    private function getEnumReferenceCasesDescription(): ?string
    {
        if ($this->uri !== null) {
            return null;
        }

        $schema = $this->resolve();

        if (! $schema instanceof Schema) {
            return null;
        }

        if (! is_string($casesDescription = $schema->type->getAttribute('casesDescription'))) {
            return null;
        }

        return $casesDescription;
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
        return $this->serialize(OpenApiVersion::V31, parent::serializeAs31(), fn (OpenApiSerializable $item) => $item->serializeAs31());
    }

    public function serializeAs32(): mixed
    {
        return $this->serialize(OpenApiVersion::V32, parent::serializeAs32(), fn (OpenApiSerializable $item) => $item->serializeAs32());
    }

    /**
     * @param  callable(OpenApiSerializable): mixed  $serializeItem
     */
    private function serialize(OpenApiVersion $version, array $parentArray, callable $serializeItem): mixed
    {
        // OpenAPI 3.1 requires inline Media Type Objects because reusable media types were introduced in 3.2.
        if ($version === OpenApiVersion::V31 && $this->referenceType === 'mediaTypes') {
            return $serializeItem($this->resolve());
        }

        if ($this->nullable) {
            return [
                'anyOf' => [$serializeItem((clone $this)->nullable(false)), ['type' => 'null']],
            ];
        }

        unset($parentArray['type']);

        return [
            ...array_filter($parentArray),
            '$ref' => $this->getUri(),
            ...($this->summary !== null ? ['summary' => $this->summary] : []),
        ];
    }
}
