<?php

namespace Dedoc\Scramble\Support\Generator;

class MediaType
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

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [];

        if ($this->schema) {
            $result['schema'] = $this->schema->toArray();
        }

        $examples = [];
        foreach ($this->examples as $key => $example) {
            $serializedExample = $example->toArray();
            if ($serializedExample) {
                $examples[$key] = $serializedExample;
            }
        }

        $encoding = array_map(fn ($e) => $e->toArray(), $this->encoding);

        return array_merge(
            $result,
            $this->example instanceof MissingValue ? [] : ['example' => $this->example],
            $examples ? ['examples' => $examples] : [],
            $encoding ? ['encoding' => $encoding] : [],
            $this->extensionPropertiesToArray(),
        );
    }
}
