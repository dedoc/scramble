<?php

use Dedoc\Scramble\Support\OperationExtensions\RulesEvaluator\RuleType;
use Dedoc\Scramble\Support\RuleTransforming\RuleProperties;
use Dedoc\Scramble\Support\Type\Generic;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

enum BackedEnum_InRuleInferenceTest: int
{
    case One = 1;
}

enum UnitEnum_InRuleInferenceTest
{
    case Foo;
}

it('infers in rule properties', function (string $expression, array $values) {
    $type = getVariableTypeAfter('$rule = '.$expression.';', 'rule');
    $properties = new RuleProperties(new RuleType($type));

    expect($type)->toBeInstanceOf(Generic::class)
        ->and($type->name)->toBe(In::class)
        ->and($properties->string('rule'))->toBe('in')
        ->and($properties->array('values'))->toBe($values);
})->with([
    'factory array' => ['\\'.Rule::class.'::in(["a", "b"])', ['a', 'b']],
    'factory named array' => ['\\'.Rule::class.'::in(values: ["a", "b"])', ['a', 'b']],
    'factory variadic' => ['\\'.Rule::class.'::in("a", "b")', ['a', 'b']],
    'factory scalar' => ['\\'.Rule::class.'::in(values: "a")', ['a']],
    'factory empty array' => ['\\'.Rule::class.'::in([])', []],
    'constructor array' => ['new \\'.In::class.'(["a", "b"])', ['a', 'b']],
    'constructor named array' => ['new \\'.In::class.'(values: ["a", "b"])', ['a', 'b']],
    'constructor variadic' => ['new \\'.In::class.'("a", "b")', ['a', 'b']],
    'constructor scalar' => ['new \\'.In::class.'(values: "a")', ['a']],
    'factory arrayable' => ['\\'.Rule::class.'::in(new Arrayable_InRuleInferenceTest)', ['a', 'b']],
    'constructor arrayable' => ['new \\'.In::class.'(new Arrayable_InRuleInferenceTest)', ['a', 'b']],
    'enum array' => ['\\'.Rule::class.'::in([BackedEnum_InRuleInferenceTest::One, UnitEnum_InRuleInferenceTest::Foo])', [BackedEnum_InRuleInferenceTest::One, UnitEnum_InRuleInferenceTest::Foo]],
    'enum single case' => ['new \\'.In::class.'(BackedEnum_InRuleInferenceTest::One)', [BackedEnum_InRuleInferenceTest::One]],
]);

it('keeps unknown in rule values unresolved', function () {
    $type = getVariableTypeAfter('$rule = \\'.Rule::class.'::in($unknown);', 'rule');

    expect((new RuleProperties(new RuleType($type)))->array('values'))->toBeNull();
});

class Arrayable_InRuleInferenceTest implements \Illuminate\Contracts\Support\Arrayable
{
    public function toArray(): array
    {
        return ['a', 'b'];
    }
}
