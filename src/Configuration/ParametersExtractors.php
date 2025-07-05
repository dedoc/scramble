<?php

namespace Dedoc\Scramble\Configuration;

use Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor\AttributesParametersExtractor;
use Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor\FormRequestParametersExtractor;
use Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor\MethodCallsParametersExtractor;
use Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor\ParameterExtractor;
use Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor\PathParametersExtractor;
use Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor\ValidateCallParametersExtractor;
use Illuminate\Support\Arr;

class ParametersExtractors
{
    /** @var class-string<ParameterExtractor>[] */
    protected array $extractors = [];

    /** @var class-string<ParameterExtractor>[] */
    protected array $appends = [];

    /** @var class-string<ParameterExtractor>[] */
    protected array $prepends = [];

    /**
     * @param  class-string<ParameterExtractor>[]|class-string<ParameterExtractor>  $extractor
     * @return $this
     */
    public function append(array|string $extractor): self
    {
        $this->appends = array_merge(
            $this->appends,
            Arr::wrap($extractor)
        );

        return $this;
    }

    /**
     * @param  class-string<ParameterExtractor>[]|class-string<ParameterExtractor>  $extractor
     * @return $this
     */
    public function prepend(array|string $extractor): self
    {
        $this->prepends = array_merge(
            $this->prepends,
            Arr::wrap($extractor)
        );

        return $this;
    }

    /**
     * @param  class-string<ParameterExtractor>[]  $extractors
     * @return $this
     */
    public function use(array $extractors): self
    {
        $this->extractors = $extractors;

        return $this;
    }

    /**
     * @return class-string<ParameterExtractor>[]
     */
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
