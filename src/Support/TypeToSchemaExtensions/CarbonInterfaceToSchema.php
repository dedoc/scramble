<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Carbon\CarbonInterface;
use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Type\Type;

class CarbonInterfaceToSchema extends TypeToSchemaExtension
{
    public function shouldHandle(Type $type): bool
    {
        return $type->isInstanceOf(CarbonInterface::class);
    }

    public function toSchema(Type $type)
    {
        return (new StringType)->format('date-time');
    }
}
