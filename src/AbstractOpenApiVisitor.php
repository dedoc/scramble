<?php

namespace Dedoc\Scramble;

class AbstractOpenApiVisitor implements OpenApiVisitor
{
    public function enter(mixed $object, array $path = []) {}

    public function leave(mixed $object, array $path = []) {}
}
