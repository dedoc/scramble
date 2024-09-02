<?php

namespace Dedoc\Scramble\Support\Helpers;

use Dedoc\Scramble\Support\InferExtensions\JsonResourceTypeInfer;
use Dedoc\Scramble\Support\Type\ObjectType;

class JsonResourceAnalyzer
{
    public static function getAssociatedModelClass(ObjectType $jsonResourceInstance): ?string
    {
        $className = $jsonResourceInstance->name;

        JsonResourceTypeInfer::modelType($jsonResourceInstance->name);
    }
}
