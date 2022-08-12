<?php

namespace Dedoc\Documentor\Support\Generator;

class Reference
{
    private string $referenceType;
    private string $fullName;
    private Components $components;

    public function __construct(string $referenceType, string $fullName, Components $components)
    {
        $this->referenceType = $referenceType;
        $this->fullName = $fullName;
        $this->components = $components;
    }

    public function toArray()
    {
        return [
            '$ref' => "#/components/{$this->referenceType}/{$this->components->uniqueSchemaName($this->fullName)}",
        ];
    }
}
