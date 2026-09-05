<?php

namespace Vendor\Package\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReverseLookupTest_UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'roles' => $this->someComputedRolesList(),
        ];
    }

    /** @return list<string> */
    private function someComputedRolesList(): array
    {
        return ['admin'];
    }
}
