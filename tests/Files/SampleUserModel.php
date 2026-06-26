<?php

namespace Dedoc\Scramble\Tests\Files;

use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class SampleUserModel extends Model
{
    use Searchable;

    public $timestamps = true;

    protected $guarded = [];

    protected $table = 'users';

    protected function casts(): array
    {
        return [
            'roles' => AsEnumCollection::of(Role::class),
        ];
    }

    public function circles()
    {
        return $this->hasMany(SampleCircleModel::class);
    }
}
