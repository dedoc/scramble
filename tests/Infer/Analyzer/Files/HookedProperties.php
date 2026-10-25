<?php

namespace Dedoc\Scramble\Tests\Infer\Analyzer\Files;

class HookedPropertyFollowedByMethod
{
    public ?\stdClass $foo = null {
        get {
            return $this->foo ??= new \stdClass;
        }
    }

    public function bar(): void {}
}

class HookedPropertyClosingClass
{
    public string $foo = 'a' {
        get => strtoupper($this->foo);
    }
}
