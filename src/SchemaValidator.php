<?php

namespace Dedoc\Scramble;

use Dedoc\Scramble\Exceptions\InvalidSchema;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use Illuminate\Support\Str;

class SchemaValidator
{
    /**
     * @param  array<int, array{callable(OpenApiType): bool, string}>  $rules
     */
    public function __construct(
        private array $rules,
    ) {}

    public function hasRules(): bool
    {
        return (bool) count($this->rules);
    }

    /**
     * @return InvalidSchema[]
     *
     * @throws InvalidSchema
     */
    public function validate(OpenApiType $type, string $path): array
    {
        $exceptions = [];

        foreach ($this->rules as [$ruleCb, $errorMessageGetter, $ignorePaths, $throw]) {
            if (Str::is($ignorePaths, $path)) {
                continue;
            }

            if ($ruleCb($type, $path)) {
                continue;
            }

            throw_if(
                $throw,
                $exception = InvalidSchema::createForSchema(value($errorMessageGetter, $type, $path), $path, $type),
            );

            $exceptions[] = $exception;
        }

        return $exceptions;
    }
}
