<?php

namespace Dedoc\Scramble\Tests\Files;

use Illuminate\Database\Eloquent\Model;

class SampleUserModel extends Model
{
    public $timestamps = true;

    protected $guarded = [];

    protected $table = 'users';
}
