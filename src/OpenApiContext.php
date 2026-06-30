<?php

namespace Dedoc\Scramble;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Diagnostics\DiagnosticsCollector;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\ResponseExtractor\ModelInfo;
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
        public DiagnosticsCollector $diagnostics = new DiagnosticsCollector,
    ) {
        /** @todo remove this part when code analyzer/type inference is more modular and created per open api generation */
        ModelInfo::$diagnostics = $this->diagnostics;
    }
}
