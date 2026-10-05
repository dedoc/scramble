<?php

namespace Dedoc\Scramble\Support\Generator\Contracts;

interface OpenApiSerializable
{
    public function serializeAs31(): mixed;

    public function serializeAs32(): mixed;
}
