<?php

namespace Dedoc\Scramble\Support\Generator;

use Dedoc\Scramble\Support\Generator\Combined\AnyOf;
use Dedoc\Scramble\Support\Generator\Types\NullType;
use Dedoc\Scramble\Support\Generator\Types\Type;

class Reference extends Type
{
    public string $referenceType;

    public string $fullName;

    public function __construct(string $referenceType, string $fullName)
    {
        $this->referenceType = $referenceType;
        $this->fullName = $fullName;
    }

    public function toArray(OpenApi $openApi)
    {
        if ($this->nullable) {
            return (new AnyOf)->setItems([(clone $this)->nullable(false), new NullType])->toArray($openApi);
        }

        return array_filter([
            'description' => $this->description,
            '$ref' => "#/components/{$this->referenceType}/{$openApi->components->uniqueSchemaName($this->fullName)}",
        ]);
    }
}
