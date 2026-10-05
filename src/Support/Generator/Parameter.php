<?php

namespace Dedoc\Scramble\Support\Generator;

use Dedoc\Scramble\Support\Generator\Contracts\OpenApiSerializable;
use JsonSerializable;

class Parameter implements JsonSerializable, OpenApiSerializable
{
    use WithAttributes;
    use WithExtensions;

    public string $name;

    /**
     * Possible values are "query", "header", "path", or "cookie".
     *
     * @var "query"|"header"|"path"|"cookie".
     */
    public string $in;

    public bool $required = false;

    public ?bool $explode = null;

    /**
     * Possible values are "simple", "label", "matrix", "form", "spaceDelimited", "pipeDelimited" or "deepObject".
     *
     * @var "simple"|"label"|"matrix"|"form"|"spaceDelimited"|"pipeDelimited"|"deepObject"|null
     */
    public ?string $style = null;

    public string $description = '';

    /** @var array|scalar|null|MissingValue */
    public $example;

    /** @var array<string, Example> */
    public array $examples = [];

    public bool $deprecated = false;

    public bool $allowEmptyValue = false;

    public bool $allowReserved = false;

    public Schema|Reference|null $schema = null;

    /** @var array<string, MediaType> */
    public array $content = [];

    public function __construct(string $name, string $in)
    {
        $this->name = $name;
        $this->in = $in;

        $this->example = new MissingValue;

        if ($this->in === 'path') {
            $this->required = true;
        }
    }

    public static function make(string $name, string $in): static
    {
        return new static($name, $in);
    }

    public function required(bool $required)
    {
        $this->required = $required;

        return $this;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function setSchema(Schema|Reference|null $schema): self
    {
        $this->schema = $schema;

        return $this;
    }

    public function setAllowReserved(bool $allowReserved): self
    {
        $this->allowReserved = $allowReserved;

        return $this;
    }

    /**
     * @param  array<string, MediaType>  $content
     */
    public function setContent(array $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function addContent(string $key, MediaType $mediaType): self
    {
        $this->content[$key] = $mediaType;

        return $this;
    }

    public function description(string $description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @param  array|scalar|null|MissingValue  $example
     */
    public function example($example)
    {
        $this->example = $example;

        return $this;
    }

    /**
     * @param  array<string, Example>  $examples
     * @return $this
     */
    public function examples(array $examples): self
    {
        $this->examples = $examples;

        return $this;
    }

    public function setExplode(bool $explode): self
    {
        $this->explode = $explode;

        return $this;
    }

    public function setStyle(string $style): self
    {
        $this->style = $style;

        return $this;
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    public function toArray(): array
    {
        return $this->serializeAs31();
    }

    public function serializeAs31(): mixed
    {
        return $this->serialize(fn (OpenApiSerializable $item) => $item->serializeAs31());
    }

    public function serializeAs32(): mixed
    {
        return $this->serialize(fn (OpenApiSerializable $item) => $item->serializeAs32());
    }

    /**
     * @param callable(OpenApiSerializable): mixed $serializeItem
     */
    private function serialize(callable $serializeItem): array
    {
        $result = array_filter([
            'name' => $this->name,
            'in' => $this->in,
            'required' => $this->required,
            'description' => $this->description,
            'deprecated' => $this->deprecated,
            'allowEmptyValue' => $this->allowEmptyValue,
            'allowReserved' => $this->allowReserved,
            'style' => $this->style,
        ]);

        if ($this->schema) {
            $result['schema'] = $serializeItem($this->schema);
        }

        $examples = [];
        if ($this->examples) {
            foreach ($this->examples as $key => $example) {
                $serializedExample = $serializeItem($example);
                if ($serializedExample) {
                    $examples[$key] = $serializedExample;
                }
            }
        }

        return array_merge(
            $result,
            $this->example instanceof MissingValue ? [] : ['example' => $this->example],
            ! is_null($this->explode) ? [
                'explode' => $this->explode,
            ] : [],
            $examples ? ['examples' => $examples] : [],
            $this->content ? ['content' => array_map($serializeItem, $this->content)] : [],
            $this->extensionPropertiesToArray(),
        );
    }
}
