<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\UseResource;
use Illuminate\Database\Eloquent\Model;

class ReverseLookupTest_User extends Model {}

#[UseResource(\Vendor\Package\Http\Resources\ReverseLookupTest_AttributedResource::class)]
class ReverseLookupTest_Attributed extends Model {}
