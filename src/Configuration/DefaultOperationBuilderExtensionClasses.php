<?php

namespace Dedoc\Scramble\Configuration;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\OperationExtensions\ErrorResponsesExtension;
use Dedoc\Scramble\Support\OperationExtensions\RequestBodyExtension;
use Dedoc\Scramble\Support\OperationExtensions\RequestEssentialsExtension;
use Dedoc\Scramble\Support\OperationExtensions\ResponseExtension;

class DefaultOperationBuilderExtensionClasses
{
    /**
     * @return array<class-string<OperationExtension>>
     */
    public function get(): array {
        return [
            RequestEssentialsExtension::class,
            RequestBodyExtension::class,
            ErrorResponsesExtension::class,
            ResponseExtension::class,
        ];
    }
}
