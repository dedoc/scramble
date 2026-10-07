<?php

namespace Dedoc\Scramble\Support\Generator\Types;

use Dedoc\Scramble\Support\Generator\Contracts\OpenApiSerializable;

class MixedType extends Type
{
    public function __construct()
    {
        parent::__construct('mixed');
    }

    public function serializeAs31(): mixed
    {
        return $this->serialize(parent::serializeAs31(), fn (OpenApiSerializable $item) => $item->serializeAs31());
    }

    public function serializeAs32(): mixed
    {
        return $this->serialize(parent::serializeAs32(), fn (OpenApiSerializable $item) => $item->serializeAs32());
    }

    /**
     * @param  callable(OpenApiSerializable): mixed  $serializeItem
     */
    private function serialize(array $parentArray, callable $serializeItem): mixed
    {
        $result = $parentArray;

        unset($result['type']);

        // Yes. It is not an array sometimes. I live with it.
        return count($result) ? $result : (object) [];
    }
}
