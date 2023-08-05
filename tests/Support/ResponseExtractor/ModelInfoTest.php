<?php

use Dedoc\Scramble\Support\ResponseExtractor\ModelInfo;

it('handles model without updated_at column', function () {
    $modelInfo = new ModelInfo(UserModelWithoutUpdatedAt::class);

    $modelInfo->handle();
})->expectNotToPerformAssertions();

class UserModelWithoutUpdatedAt extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'users';

    const UPDATED_AT = null;
}
