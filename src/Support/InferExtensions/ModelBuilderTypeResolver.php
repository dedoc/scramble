<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class ModelBuilderTypeResolver
{
    public static function resolveClass(string $modelClass): string
    {
        try {
            /** @var Model $model */
            $model = app($modelClass);
            $builder = $model->newModelQuery();

            return $builder instanceof Builder ? $builder::class : Builder::class;
        } catch (Throwable) {
            return Builder::class;
        }
    }
}
