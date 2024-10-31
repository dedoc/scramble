<?php

namespace Dedoc\Scramble\Support\Generator;

class Parameter
{
    use WithAttributes;
    use WithExtensions;

    public string $name;

    /**
     * Possible values are "query", "header", "path" or "cookie".
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

    /** @var array|scalar|null|MissingExample */
    public $example;

    public bool $deprecated = false;

    public bool $allowEmptyValue = false;

    public ?Schema $schema = null;

    public function __construct(string $name, string $in)
    {
        $this->name = $name;
        $this->in = $in;

        $this->example = new MissingExample;

        if ($this->in === 'path') {
            $this->required = true;
        }
    }

    public static function make(string $name, string $in)
    {
        return new static($name, $in);
    }

    public function toArray(): array
    {
        $result = array_filter([
            'name' => $this->name,
            'in' => $this->in,
            'required' => $this->required,
            'description' => $this->description,
            'deprecated' => $this->deprecated,
            'allowEmptyValue' => $this->allowEmptyValue,
            'style' => $this->style,
        ]);

        if ($this->schema) {
            $result['schema'] = $this->schema->toArray();
        }

        return array_merge(
            $result,
            $this->example instanceof MissingExample ? [] : ['example' => $this->example],
            ! is_null($this->explode) ? [
                'explode' => $this->explode,
            ] : [],
            $this->extensionPropertiesToArray(),
        );
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

    public function setSchema(?Schema $schema): self
    {
        $this->schema = $schema;

        return $this;
    }

    public function description(string $description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @param  array|scalar|null|MissingExample  $example
     */
    public function example($example)
    {
        $this->example = $example;

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
}
