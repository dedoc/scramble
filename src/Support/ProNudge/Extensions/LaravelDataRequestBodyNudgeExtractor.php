<?php

namespace Dedoc\Scramble\Support\ProNudge\Extensions;

use Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor\ParameterExtractor;
use Dedoc\Scramble\Support\ProNudge\ProNudgeCollector;
use Dedoc\Scramble\Support\ProNudge\ProNudgeSignal;
use Dedoc\Scramble\Support\RouteInfo;
use ReflectionNamedType;
use ReflectionParameter;

/** @internal */
class LaravelDataRequestBodyNudgeExtractor implements ParameterExtractor
{
    private const LARAVEL_DATA_CLASS = 'Spatie\LaravelData\Data';

    public function __construct(
        private ProNudgeCollector $collector,
    ) {}

    public function handle(RouteInfo $routeInfo, array $parameterExtractionResults): array
    {
        if (! $reflectionAction = $routeInfo->reflectionAction()) {
            return $parameterExtractionResults;
        }

        $hasLaravelDataParameter = collect($reflectionAction->getParameters())
            ->contains(fn (ReflectionParameter $parameter) => $this->isLaravelDataParameter($parameter));

        if ($hasLaravelDataParameter) {
            $this->collector->record(ProNudgeSignal::LaravelDataRequest, $routeInfo);
        }

        return $parameterExtractionResults;
    }

    private function isLaravelDataParameter(ReflectionParameter $parameter): bool
    {
        $type = $parameter->getType();

        if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return false;
        }

        return class_exists(self::LARAVEL_DATA_CLASS)
            && is_a($type->getName(), self::LARAVEL_DATA_CLASS, true);
    }
}
