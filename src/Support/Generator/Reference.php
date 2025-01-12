<?php

namespace Dedoc\Scramble\Support\Generator;

use Dedoc\Scramble\Support\Generator\Combined\AnyOf;
use Dedoc\Scramble\Support\Generator\Types\NullType;
use Dedoc\Scramble\Support\Generator\Types\Type;

class Reference extends Type
{
    public string $referenceType;

    public ?string $shortName;

    /**
     * This must be a unique name across all the references with the same type!
     */
    public string $fullName;

    private Components $components;

    public function __construct(
        string $referenceType,
        string $fullName,
        Components $components,
        ?string $shortName = null,
    ) {
        $this->referenceType = $referenceType;
        $this->fullName = $fullName;
        $this->components = $components;
        $this->shortName = $shortName;
    }

    public function resolve()
    {
        return $this->components->get($this);
    }

    public function getUniqueName()
    {
        return $this->components->uniqueSchemaName($this->shortName ?: $this->fullName);
    }

    public function toArray()
    {
        if ($this->nullable) {
            return (new AnyOf)->setItems([(clone $this)->nullable(false), new NullType])->toArray();
        }

        return array_filter([
            'description' => $this->description,
            '$ref' => "#/components/{$this->referenceType}/{$this->getUniqueName()}",
        ]);
    }
}
