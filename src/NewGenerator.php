<?php

namespace Dedoc\Scramble;

use Dedoc\Scramble\Support\OperationBuilder;

class NewGenerator
{
    public function __construct(
        private Infer $infer,
        private RoutesProvider $routesProvider,
        private OperationBuilder $operationBuilder,
    ) {
    }

    public function generate(GeneratorConfig $config): void
    {
        /*...*/
    }
}
