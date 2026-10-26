<?php

use Dedoc\Scramble\Support\OperationExtensions\RulesEvaluator\RuleType;
use Dedoc\Scramble\Support\RuleTransforming\RuleProperties;
use Dedoc\Scramble\Support\Type\ObjectType;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

enum Enum_RuleStaticMethodReturnTypeExtensionTest: string
{
    case Foo = 'foo';
    case Bar = 'bar';
}

it('infers the enum rule and its enum class', function (string $argument) {
    $type = getVariableTypeAfter('$rule = \\'.Rule::class.'::enum('.$argument.');', 'rule');

    expect($type)->toBeInstanceOf(ObjectType::class)
        ->and($type->name)->toBe(Enum::class)
        ->and($type->getPropertyType('type')->toString())->toBe('class-string<'.Enum_RuleStaticMethodReturnTypeExtensionTest::class.'>');
})->with([
    'positional argument' => [Enum_RuleStaticMethodReturnTypeExtensionTest::class.'::class'],
    'named argument' => ['type: '.Enum_RuleStaticMethodReturnTypeExtensionTest::class.'::class'],
]);

it('infers enum rule properties through setters', function (string $expression, array $only, array $except) {
    $type = getVariableTypeAfter('$rule = '.$expression.';', 'rule');
    $properties = new RuleProperties(new RuleType($type));

    expect($properties->string('type'))->toBe(Enum_RuleStaticMethodReturnTypeExtensionTest::class)
        ->and($properties->array('only'))->toBe($only)
        ->and($properties->array('except'))->toBe($except);
})->with([
    'constructor defaults' => ['new \\'.Enum::class.'(Enum_RuleStaticMethodReturnTypeExtensionTest::class)', [], []],
    'factory defaults' => ['\\'.Rule::class.'::enum(Enum_RuleStaticMethodReturnTypeExtensionTest::class)', [], []],
    'only array' => ['\\'.Rule::class.'::enum(Enum_RuleStaticMethodReturnTypeExtensionTest::class)->only([Enum_RuleStaticMethodReturnTypeExtensionTest::Foo])', [Enum_RuleStaticMethodReturnTypeExtensionTest::Foo], []],
    'except single case' => ['\\'.Rule::class.'::enum(Enum_RuleStaticMethodReturnTypeExtensionTest::class)->except(Enum_RuleStaticMethodReturnTypeExtensionTest::Bar)', [], [Enum_RuleStaticMethodReturnTypeExtensionTest::Bar]],
    'both setters' => ['\\'.Rule::class.'::enum(Enum_RuleStaticMethodReturnTypeExtensionTest::class)->only(values: Enum_RuleStaticMethodReturnTypeExtensionTest::Foo)->except(values: [Enum_RuleStaticMethodReturnTypeExtensionTest::Bar])', [Enum_RuleStaticMethodReturnTypeExtensionTest::Foo], [Enum_RuleStaticMethodReturnTypeExtensionTest::Bar]],
    'setter replaces values' => ['\\'.Rule::class.'::enum(Enum_RuleStaticMethodReturnTypeExtensionTest::class)->only([Enum_RuleStaticMethodReturnTypeExtensionTest::Foo])->only([Enum_RuleStaticMethodReturnTypeExtensionTest::Bar])', [Enum_RuleStaticMethodReturnTypeExtensionTest::Bar], []],
]);

it('preserves enum rule properties when setting the validator in a separate call', function () {
    $type = getVariableTypeAfter('
        $rule = \\'.Rule::class.'::enum(Enum_RuleStaticMethodReturnTypeExtensionTest::class);
        $rule->only(Enum_RuleStaticMethodReturnTypeExtensionTest::Foo);
        $rule->except([Enum_RuleStaticMethodReturnTypeExtensionTest::Bar]);
        $rule->setValidator(validator: new \\stdClass);
    ', 'rule');
    $properties = new RuleProperties(new RuleType($type));

    expect($properties->string('type'))->toBe(Enum_RuleStaticMethodReturnTypeExtensionTest::class)
        ->and($properties->array('only'))->toBe([Enum_RuleStaticMethodReturnTypeExtensionTest::Foo])
        ->and($properties->array('except'))->toBe([Enum_RuleStaticMethodReturnTypeExtensionTest::Bar])
        ->and($type->getPropertyType('validator')->toString())->toBe(stdClass::class);
});
