<?php

namespace Dedoc\Scramble\Support\InferExtensions\PropertyTypesFromPhpDoc;

use Illuminate\Support\Collection;
use ReflectionClass;

interface PropertyExtractor
{
    /**
     * Retrieve the properties.
     *
     * @param  \ReflectionClass<object>  $reflection
     * @return \Illuminate\Support\Collection<string, \Dedoc\Scramble\Support\Type\Type>
     */
    public function __invoke(ReflectionClass $reflection): Collection;
}
