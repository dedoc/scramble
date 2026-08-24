<?php

namespace Dedoc\Scramble\Exceptions;

use Dedoc\Scramble\Contracts\Diagnostics\Diagnostic;
use Dedoc\Scramble\Diagnostics\ValidationRules\Vr003AllEvaluatorsFailedDiagnostic;
use Exception;
use Illuminate\Support\Arr;
use Throwable;

class RulesEvaluationException extends Exception implements BuildsDiagnostics, RouteAware
{
    use RouteAwareTrait;

    /** @var array<string, Throwable> */
    public array $exceptions = [];

    public ?string $class = null;

    /**
     * @param  array<string, Throwable>  $exceptions
     */
    public static function fromExceptions(array $exceptions): self
    {
        $exceptions = collect(Arr::wrap($exceptions))
            ->reduce(function (array $acc, Throwable $e, string $key) {
                if ($e instanceof self) {
                    return array_merge($acc, $e->exceptions);
                }

                return [...$acc, $key => $e];
            }, []);

        $previous = reset($exceptions) ?: null;

        $exception = new self(self::buildMessage($exceptions), previous: $previous);
        $exception->exceptions = $exceptions;

        return $exception;
    }

    public function forClass(?string $class): self
    {
        $this->class = $class;

        return $this;
    }

    /**
     * @param  array<string, Throwable>  $exceptions
     */
    private static function buildMessage(array $exceptions): string
    {
        $lines = [
            'Cannot evaluate validation rules ('.count($exceptions).' evaluators failed):',
        ];

        foreach ($exceptions as $key => $e) {
            $name = class_basename($key);

            $context = self::extractFileLocation($e);

            $lines[] = "  [$name] {$e->getMessage()}".($context ? " {$context}" : '');
        }

        return implode("\n", $lines);
    }

    private static function extractFileLocation(Throwable $e): ?string
    {
        if ($e->getFile() && $e->getLine()) {
            return "(at {$e->getFile()}:{$e->getLine()})";
        }

        return null;
    }

    public function toDiagnostic(): Diagnostic
    {
        return Vr003AllEvaluatorsFailedDiagnostic::fromRulesEvaluationException($this);
    }
}
