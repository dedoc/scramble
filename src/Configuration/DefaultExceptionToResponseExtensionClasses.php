<?php

declare(strict_types=1);

namespace Dedoc\Scramble\Configuration;

use Dedoc\Scramble\Extensions\ExceptionToResponseExtension;
use Dedoc\Scramble\Support\ExceptionToResponseExtensions\AuthorizationExceptionToResponseExtension;
use Dedoc\Scramble\Support\ExceptionToResponseExtensions\HttpExceptionToResponseExtension;
use Dedoc\Scramble\Support\ExceptionToResponseExtensions\NotFoundExceptionToResponseExtension;
use Dedoc\Scramble\Support\ExceptionToResponseExtensions\ValidationExceptionToResponseExtension;

class DefaultExceptionToResponseExtensionClasses
{
    /**
     * @return array<class-string<ExceptionToResponseExtension>>
     */
    public function get(): array
    {
        return [
            ValidationExceptionToResponseExtension::class,
            AuthorizationExceptionToResponseExtension::class,
            NotFoundExceptionToResponseExtension::class,
            HttpExceptionToResponseExtension::class,
        ];
    }
}
