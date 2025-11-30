<?php

namespace Dedoc\Scramble\Tests\Infer\Analyzer;

use Dedoc\Scramble\Infer\Analyzer\PropertyAnalyzer;
use Dedoc\Scramble\Support\Type\NullType;
use ReflectionProperty;

test('handles property without type hint', function () {
    $analyzer = PropertyAnalyzer::from($ref = new ReflectionProperty(PropertyWithoutTypeHint_PropertyAnalyzerTest::class, 'foo'));

    // This is probably an issue, as the native reflection returns `hasDefaultType` `true` when a property has attribute in constructor property promotion.
    expect($analyzer->getDefaultType())->toBeInstanceOf(NullType::class);
});
class PropertyWithoutTypeHint_PropertyAnalyzerTest
{
    public function __construct(
        #[SomeAttr(['a' => 42]['a'])]
        public $foo,
    )
    {
    }
}
