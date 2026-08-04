<?php

namespace Dedoc\Scramble\Support\ProNudge\Extensions;

use Dedoc\Scramble\Contracts\OperationTransformer;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\ProNudge\ProNudgeCollector;
use Dedoc\Scramble\Support\ProNudge\ProNudgeSignal;
use Dedoc\Scramble\Support\RouteInfo;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\TypeWalker;

/** @internal */
class LaravelDataReturnTypeNudgeExtension implements OperationTransformer
{
    private const LARAVEL_DATA_CLASS = 'Spatie\LaravelData\Data';

    public function __construct(
        private ProNudgeCollector $collector,
    ) {}

    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $returnType = $routeInfo->getActionType()?->getReturnType();

        if (! $returnType) {
            return;
        }

        $dataType = (new TypeWalker)->first(
            $returnType,
            fn ($type) => $type instanceof ObjectType
                && class_exists(self::LARAVEL_DATA_CLASS)
                && is_a($type->name, self::LARAVEL_DATA_CLASS, true),
        );

        if (! $dataType) {
            return;
        }

        $this->collector->record(ProNudgeSignal::LaravelDataReturn, $routeInfo);
    }
}
