<?php

namespace Dedoc\Scramble\Support\ResponseExtractor;

use Dedoc\Scramble\Support\ComplexTypeHandler\ComplexTypeHandlers;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\RouteInfo;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\TypeHandlers\TypeHandlers;
use Illuminate\Support\Arr;

class ResponsesExtractor
{
    private RouteInfo $routeInfo;

    public function __construct(RouteInfo $routeInfo)
    {
        $this->routeInfo = $routeInfo;
    }

    public function __invoke()
    {
        return collect(Arr::wrap([$this->routeInfo->getHandledReturnType()]))
            ->filter(fn ($t) => !! $t[1])
            ->map(function ($type) {
                $type = $type[1];

                $hint = $type instanceof Reference
                    ? ComplexTypeHandlers::$components->getSchema($type->fullName)->type->hint
                    : $type->hint;

                $type->setDescription('');

                return Response::make(200)
                    ->description($hint)
                    ->setContent('application/json', Schema::fromType($type));
            })
            ->values()
            ->all();
    }
}
