<?php

namespace Dedoc\Scramble\Tests\Infer;

class Bar
{
    public function __construct(
        public int $id,
    ) {}

    public function foo()
    {
        return $this->id;
    }
}

it('infers a return type of call on an object', function () {
    $type = analyzeFile(<<<'EOD'
<?php
class Foo {
    public function bar(\Dedoc\Scramble\Tests\Infer\Bar $object)
    {
        return $object->id;
    }
}
EOD)->getExpressionType('(new Foo)->bar()');

    expect($type->toString())->toBe('int');
});
