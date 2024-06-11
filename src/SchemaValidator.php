<?php

namespace Dedoc\Scramble;

use Dedoc\Scramble\Exceptions\InvalidSchema;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;

class SchemaValidator
{
    /**
     * @param  array<int, array{callable(OpenApiType): bool, string}>  $rules
     */
    public function __construct(
        private array $rules,
    ) {
    }

    public function hasRules(): bool
    {
        return (bool) count($this->rules);
    }

    /**
     * @throws InvalidSchema
     */
    public function validate(OpenApiType $type, string $path): void
    {
        foreach ($this->rules as [$ruleCb, $errorMessageGetter]) {
            if (! $ruleCb($type, $path)) {
                $errorMessage = value($errorMessageGetter, $type, $path);

                $file = $type->getAttribute('file');
                $line = $type->getAttribute('line');

                if ($file) {
                    $errorMessage = rtrim($errorMessage, '.').'. Got when analyzing an expression in file ['.$file.'] on line '.$line;
                }

                throw InvalidSchema::create($errorMessage, $path);
            }
        }
    }
}
