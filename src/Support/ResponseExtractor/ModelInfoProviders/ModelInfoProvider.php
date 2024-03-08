<?php

namespace Dedoc\Scramble\Support\ResponseExtractor\ModelInfoProviders;

use Illuminate\Database\Eloquent\Model;

interface ModelInfoProvider
{
    public function getAttributes(Model $model): array;
}
