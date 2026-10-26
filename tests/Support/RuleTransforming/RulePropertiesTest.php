<?php

use Dedoc\Scramble\Support\OperationExtensions\RulesEvaluator\RuleType;
use Dedoc\Scramble\Support\RuleTransforming\RuleProperties;
use Illuminate\Validation\Rule;

it('returns the original inferred property type', function () {
    $type = getVariableTypeAfter('$rule = \\'.Rule::class.'::enum(stdClass::class);', 'rule');
    $properties = new RuleProperties(new RuleType($type));

    expect($properties->type('type'))->toBe($type->getPropertyType('type'))
        ->and($properties->type('type')->toString())->toBe('class-string<stdClass>');
});

it('builds a property type from a runtime value', function () {
    $properties = new RuleProperties(Rule::in(['a', 'b']));

    expect($properties->type('rule')->toString())->toBe('string(in)');
});
