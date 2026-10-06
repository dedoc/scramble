<?php

namespace Dedoc\Scramble\Support\Generator;

use Dedoc\Scramble\Support\Generator\Contracts\OpenApiSerializable;
use JsonSerializable;

class MediaType implements JsonSerializable, OpenApiSerializable
{
    use WithAttributes;
    use WithExtensions;

    public function __construct(
        public Schema|Reference|null $schema = null,
        public mixed $example = new MissingValue,
        /** @var array<string, Example|Reference> */
        public array $examples = [],
        /** @var array<string, Encoding> */
        public array $encoding = [],
    ) {}

    /**
     * @return $this
     */
    public function setSchema(Schema|Reference|null $schema): self
    {
        $this->schema = $schema;

        return $this;
    }

    /**
     * @return $this
     */
    public function setExample(mixed $example): self
    {
        $this->example = $example;

        return $this;
    }

    /**
     * @param  array<string, Example|Reference>  $examples
     * @return $this
     */
    public function setExamples(array $examples): self
    {
        $this->examples = $examples;

        return $this;
    }

    /**
     * @param  array<string, Encoding>  $encoding
     * @return $this
     */
    public function setEncoding(array $encoding): self
    {
        $this->encoding = $encoding;

        return $this;
    }

    /**
     * @return $this
     */
    public function addExample(string $key, Example|Reference $example): self
    {
        $this->examples[$key] = $example;

        return $this;
    }

    /**
     * @return $this
     */
    public function addEncoding(string $key, Encoding $encoding): self
    {
        $this->encoding[$key] = $encoding;

        return $this;
    }

    /**
     * @return $this
     */
    public function removeExample(string $key): self
    {
        unset($this->examples[$key]);

        return $this;
    }

    /**
     * @return $this
     */
    public function removeEncoding(string $key): self
    {
        unset($this->encoding[$key]);

        return $this;
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    public function toArray(): mixed
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
     * @param  callable(OpenApiSerializable): mixed  $serializeItem
     */
    private function serialize(callable $serializeItem): mixed
    {
        $result = [];

        if ($this->schema) {
            $result['schema'] = $serializeItem($this->schema);
        }

        $examples = [];
        foreach ($this->examples as $key => $example) {
            $serializedExample = $serializeItem($example);
            if ($serializedExample) {
                $examples[$key] = $serializedExample;
            }
        }

        $encoding = array_map($serializeItem, $this->encoding);

        $result = array_merge(
            $result,
            $this->example instanceof MissingValue ? [] : ['example' => $this->example],
            $examples ? ['examples' => $examples] : [],
            $encoding ? ['encoding' => $encoding] : [],
            $this->extensionPropertiesToArray(),
        );

        return $result ?: (object) [];
    }
}
