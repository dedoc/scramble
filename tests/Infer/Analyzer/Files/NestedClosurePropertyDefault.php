<?php

namespace Dedoc\Scramble\Tests\Infer\Analyzer\Files;

class NestedClosurePropertyDefault
{
    public mixed $foo = static function ($callback = static function () {}) {};
}
