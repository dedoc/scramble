<?php

namespace Dedoc\Scramble\Tests\Files;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SamplePostModel extends Model
{
    use SoftDeletes;

    public $timestamps = true;

    protected $guarded = [];

    protected $table = 'posts';

    protected $with = ['parent', 'children', 'user'];

    protected $casts = [
        'read_time' => 'int',
        'status' => Status::class,
        'settings' => 'array',
        'approved_at' => 'datetime',
    ];

    public function getReadTimeAttribute()
    {
        return 123;
    }

    public function parent()
    {
        return $this->belongsTo(SamplePostModel::class);
    }

    public function children()
    {
        return $this->hasMany(SamplePostModel::class);
    }

    public function user()
    {
        return $this->belongsTo(SampleUserModel::class, 'user_id');
    }

    public function scopeApproved($query)
    {
        return $query->where('approved_at', '>', now());
    }

    public function scopeApprovedTypedParam(Builder $query)
    {
        return $query->where('approved_at', '>', now());
    }
}
