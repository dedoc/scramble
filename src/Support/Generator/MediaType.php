<?php

namespace Dedoc\Scramble\Support\Generator;

use Dedoc\Scramble\OpenApiVersion;
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
        /** OAS 3.2.0+ */
        public Schema|Reference|null $itemSchema = null,
        /**
         * OAS 3.2.0+
         *
         * @var list<Encoding>
         */
        public array $prefixEncoding = [],
        /** OAS 3.2.0+ */
        public ?Encoding $itemEncoding = null,
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
    public function setItemSchema(Schema|Reference|null $itemSchema): self
    {
        $this->itemSchema = $itemSchema;

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
     * @param  list<Encoding>  $prefixEncoding
     * @return $this
     */
    public function setPrefixEncoding(array $prefixEncoding): self
    {
        $this->prefixEncoding = array_values($prefixEncoding);

        return $this;
    }

    /**
     * @return $this
     */
    public function setItemEncoding(?Encoding $itemEncoding): self
    {
        $this->itemEncoding = $itemEncoding;

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
    public function addPrefixEncoding(Encoding $encoding): self
    {
        $this->prefixEncoding[] = $encoding;

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

    /**
     * @return $this
     */
    public function removePrefixEncoding(int $index): self
    {
        unset($this->prefixEncoding[$index]);
        $this->prefixEncoding = array_values($this->prefixEncoding);

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

        if ($this->schema) {
            $result['schema'] = $serializeItem($this->schema);
        }

        if ($version === OpenApiVersion::V3_2 && $this->itemSchema !== null) {
            $result['itemSchema'] = $serializeItem($this->itemSchema);
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
        );

        if ($version === OpenApiVersion::V3_2) {
            if ($this->prefixEncoding) {
                $result['prefixEncoding'] = array_values(array_map($serializeItem, $this->prefixEncoding));
            }

            if ($this->itemEncoding !== null) {
                $result['itemEncoding'] = $serializeItem($this->itemEncoding);
            }
        }

        $result = array_merge(
            $result,
            $this->extensionPropertiesToArray(),
        );

        return $result ?: (object) [];
    }
}
