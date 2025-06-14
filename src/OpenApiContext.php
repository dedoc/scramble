<?php

namespace Dedoc\Scramble;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Illuminate\Support\Collection;
use ReflectionAttribute;

class OpenApiContext
{
    public function __construct(
        public readonly OpenApi $openApi,
        public readonly GeneratorConfig $config,
        public ContextReferences $references = new ContextReferences,
        /**
         * @var Collection<int, ReflectionAttribute<Group>>
         */
        public Collection $groups = new Collection,
    ) {}
}
