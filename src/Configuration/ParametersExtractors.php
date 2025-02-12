<?php

namespace Dedoc\Scramble\Configuration;

use Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor\AttributesParametersExtractor;
use Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor\FormRequestParametersExtractor;
use Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor\MethodCallsParametersExtractor;
use Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor\PathParametersExtractor;
use Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor\ValidateCallParametersExtractor;
use Illuminate\Support\Arr;

class ParametersExtractors
{
    protected array $extractors = [];

    protected array $appends = [];

    protected array $prepends = [];

    public function append(array|string $extractor)
    {
        $this->appends = array_merge(
            $this->appends,
            Arr::wrap($extractor)
        );

        return $this;
    }

    public function prepend(array|string $extractor)
    {
        $this->prepends = array_merge(
            $this->prepends,
            Arr::wrap($extractor)
        );

        return $this;
    }

    public function use(array $extractors)
    {
        $this->extractors = $extractors;

        return $this;
    }

    public function all(): array
    {
        $base = $this->extractors ?: [
            PathParametersExtractor::class,
            FormRequestParametersExtractor::class,
            ValidateCallParametersExtractor::class,
        ];

        $defaultAppends = [
            MethodCallsParametersExtractor::class,
            AttributesParametersExtractor::class,
        ];

        return array_values(array_unique([
            ...$this->prepends,
            ...$base,
            ...$this->appends,
            ...$defaultAppends,
        ]));
    }
}
