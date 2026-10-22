<?php

namespace Dedoc\Scramble\Tests\Infer\Analyzer;

use Dedoc\Scramble\Infer\Analyzer\PropertyAnalyzer;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\NullType;
use Dedoc\Scramble\Tests\Infer\Analyzer\Files\HookedPropertyClosingClass;
use Dedoc\Scramble\Tests\Infer\Analyzer\Files\HookedPropertyFollowedByMethod;
use ReflectionProperty;

test('handles property without type hint', function () {
    $analyzer = PropertyAnalyzer::from($ref = new ReflectionProperty(PropertyWithoutTypeHint_PropertyAnalyzerTest::class, 'foo'));

    // This is probably an issue, as the native reflection returns `hasDefaultType` `true` when a property has attribute in constructor property promotion.
    expect($analyzer->getDefaultType())->toBeInstanceOf(NullType::class);
});

test('handles default value of hooked property', function (string $class, string $expectedType) {
    require_once __DIR__.'/Files/HookedProperties.php';

    $analyzer = PropertyAnalyzer::from(new ReflectionProperty($class, 'foo'));

    expect($analyzer->getDefaultType())->toBeInstanceOf($expectedType);
})->with([
    [HookedPropertyFollowedByMethod::class, NullType::class],
    [HookedPropertyClosingClass::class, LiteralStringType::class],
])->skip(PHP_VERSION_ID < 80400, 'Property hooks require PHP 8.4');

class PropertyWithoutTypeHint_PropertyAnalyzerTest
{
    public function __construct(
        #[SomeAttr(['a' => 42]['a'])]
        public $foo,
    ) {}
}
