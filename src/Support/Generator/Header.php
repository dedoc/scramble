<?php

namespace Dedoc\Scramble\Support\Generator;

class Header
{
    use WithAttributes;
    use WithExtensions;

    public function __construct(
        public ?string $description = null,
        public ?bool $required = null,
        public ?bool $deprecated = null,
        public ?bool $explode = null,
        public Schema|Reference|null $schema = null,
        public mixed $example = new MissingValue,
        /** @var array<string, Example|Reference> */
        public array $examples = [],
        /** @var array<string, MediaType> */
        public array $content = [],
    ) {}

    /**
     * @return $this
     */
    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return $this
     */
    public function setRequired(?bool $required): self
    {
        $this->required = $required;

        return $this;
    }

    /**
     * @return $this
     */
    public function setDeprecated(?bool $deprecated): self
    {
        $this->deprecated = $deprecated;

        return $this;
    }

    /**
     * @return $this
     */
    public function setExplode(?bool $explode): self
    {
        $this->explode = $explode;

        return $this;
    }

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
     * @param  array<string, MediaType>  $content
     * @return $this
     */
    public function setContent(array $content): self
    {
        $this->content = $content;

        return $this;
    }

    /**
     * @return $this
     */
    public function addContent(string $key, MediaType $mediaType): self
    {
        $this->content[$key] = $mediaType;

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
    public function removeContent(string $key): self
    {
        unset($this->content[$key]);

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
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = array_filter([
            'description' => $this->description,
            'required' => $this->required,
            'deprecated' => $this->deprecated,
            'explode' => $this->explode,
        ], fn ($value) => $value !== null);

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

        $content = array_map(fn ($mt) => $mt->toArray(), $this->content);

        return array_merge(
            $result,
            $this->example instanceof MissingValue ? [] : ['example' => $this->example],
            $examples ? ['examples' => $examples] : [],
            $content ? ['content' => $content] : [],
            $this->extensionPropertiesToArray(),
        );
    }
}
