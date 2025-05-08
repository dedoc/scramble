<?php

namespace Dedoc\Scramble\Tests\Infer\stubs;

class FooWithDefaultProperties
{
    public array $default = [
        // From comment
        'value',
        /**
         * From PHPDoc
         *
         * @var string
         */
        'foo' => 'bar',
    ];
}
