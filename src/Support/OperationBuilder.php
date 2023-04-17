<?php

namespace Dedoc\Scramble\Support;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\RouteDocumentation;

class OperationBuilder
{
    /** @var class-string<OperationExtension> */
    private array $extensionsClasses;

    public function __construct(array $extensionsClasses = [])
    {
        $this->extensionsClasses = $extensionsClasses;
    }

    public function build(RouteInfo $routeInfo, OpenApi $openApi): RouteDocumentation
    {
        $documentation = new RouteDocumentation(
            'get',
            '',
            new Operation(),
            new Components(),
        );

        foreach ($this->extensionsClasses as $extensionClass) {
            $extension = app()->make($extensionClass, [
                'openApi' => $openApi,
            ]);

            $extension->handle($documentation, $routeInfo);
        }

        return $documentation;
    }
}
