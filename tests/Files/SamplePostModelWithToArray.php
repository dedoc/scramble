<?php

namespace Dedoc\Scramble\Tests\Files;

use Illuminate\Database\Eloquent\Model;

class SamplePostModelWithToArray extends Model
{
    public $timestamps = true;

    protected $guarded = [];

    protected $table = 'posts';

    protected $casts = [
        'status' => Status::class,
    ];

    public function getReadTimeAttribute()
    {
        return 123;
    }

    public function parent()
    {
        return $this->belongsTo(SamplePostModelWithToArray::class);
    }

    public function children()
    {
        return $this->hasMany(SamplePostModelWithToArray::class);
    }

    public function toArray()
    {
        return [
            'id' => $this->id,
            'children' => $this->children,
            'read_time' => $this->read_time,
            'user' => $this->user,
            'created_at' => $this->created_at,
        ];
    }

    public function user()
    {
        return $this->belongsTo(SampleUserModel::class, 'user_id');
    }
}
