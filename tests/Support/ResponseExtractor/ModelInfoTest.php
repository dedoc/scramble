<?php

use Dedoc\Scramble\Support\ResponseExtractor\ModelInfo;
use Illuminate\Database\Eloquent\Model;

it('handles model without updated_at column', function () {
    $modelInfo = new ModelInfo(UserModelWithoutUpdatedAt::class);

    $modelInfo->handle();
})->expectNotToPerformAssertions();

class UserModelWithoutUpdatedAt extends Model
{
    protected $table = 'users';

    const UPDATED_AT = null;
}
