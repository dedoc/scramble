<?php

namespace Dedoc\Scramble\Tests\Files;

use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Model;

class SampleCircleModel extends Model
{
    protected $guarded = [];

    protected $table = 'circles';
}
