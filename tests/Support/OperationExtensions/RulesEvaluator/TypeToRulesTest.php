<?php

use Dedoc\Scramble\Support\OperationExtensions\RulesEvaluator\RuleType;
use Dedoc\Scramble\Support\OperationExtensions\RulesEvaluator\TypeToRules;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\BooleanType;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralBooleanType;
use Dedoc\Scramble\Support\Type\Literal\LiteralFloatType;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\NullType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\Union;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\ValidationRule;

it('evaluates literal values', function (Type $type, mixed $expected) {
    expect((new TypeToRules)->evaluate($type))->toBe($expected);
})->with([
    'string' => [new LiteralStringType('required'), 'required'],
    'integer' => [new LiteralIntegerType(0), 0],
    'float' => [new LiteralFloatType(1.5), 1.5],
    'boolean' => [new LiteralBooleanType(false), false],
    'null' => [new NullType, null],
    'empty array' => [new KeyedArrayType, []],
    'union' => [new Union([new StringType, new LiteralStringType('email'), new LiteralStringType('url')]), ['email', 'url']],
    'nullable union' => [new Union([new StringType, new NullType, new LiteralStringType('email')]), ['email']],
]);

it('evaluates nested arrays and unions in array values', function () {
    $type = new Union([
        new StringType,
        new KeyedArrayType([
            new ArrayItemType_('name', new KeyedArrayType([
                new ArrayItemType_(null, new LiteralStringType('required')),
                new ArrayItemType_(null, new Union([new StringType, new LiteralStringType('string')])),
            ])),
            new ArrayItemType_('email', new Union([
                new StringType,
                new KeyedArrayType([
                    new ArrayItemType_(null, new LiteralStringType('email')),
                ]),
            ])),
        ]),
    ]);

    expect((new TypeToRules)->evaluate($type))->toBe([
        'name' => ['required', 'string'],
        'email' => ['email'],
    ]);
});

it('collects known rules from lists regardless of numeric keys', function () {
    $type = new KeyedArrayType([
        new ArrayItemType_(0, new LiteralStringType('string')),
        new ArrayItemType_(1, new LiteralStringType('required')),
        new ArrayItemType_(5, new LiteralStringType('email')),
        new ArrayItemType_(6, new NullType),
    ]);

    expect((new TypeToRules)->evaluate($type))->toBe([
        'string',
        'required',
        'email',
    ]);
});

it('omits unsupported rules from a union', function () {
    expect((new TypeToRules)->evaluate(new Union([new StringType])))->toBe([]);
});

it('preserves object rules recovered from a method return type', function () {
    $rule = new ObjectType(LegacyRule_TypeToRulesTest::class);
    $type = new KeyedArrayType([
        new ArrayItemType_(null, new LiteralStringType('required')),
        new ArrayItemType_(null, $rule),
    ]);

    $rules = (new TypeToRules)->evaluate($type);

    expect($rules)->toHaveCount(2)
        ->and($rules[0])->toBe('required')
        ->and($rules[1])->toBeInstanceOf(RuleType::class)
        ->and($rules[1]->type)->toBe($rule);
});

it('deduplicates object rules across alternative rule sets', function () {
    $type = new KeyedArrayType([
        new ArrayItemType_('expiry_date', new Union([
            new KeyedArrayType([
                new ArrayItemType_(null, new LiteralStringType('required')),
                new ArrayItemType_(null, new ObjectType(LegacyRule_TypeToRulesTest::class)),
            ]),
            new KeyedArrayType([
                new ArrayItemType_(null, new ObjectType(LegacyRule_TypeToRulesTest::class)),
                new ArrayItemType_(null, new ObjectType(ModernRule_TypeToRulesTest::class)),
            ]),
        ])),
    ]);

    $rules = (new TypeToRules)->evaluate($type)['expiry_date'];

    expect($rules)->toHaveCount(2)
        ->and($rules[0])->toBeInstanceOf(RuleType::class)
        ->and($rules[0]->type->toString())->toBe(LegacyRule_TypeToRulesTest::class)
        ->and($rules[1])->toBeInstanceOf(RuleType::class)
        ->and($rules[1]->type->toString())->toBe(ModernRule_TypeToRulesTest::class);
});

it('wraps only Laravel validation rule objects', function (string $class) {
    $type = new ObjectType($class);
    $rule = (new TypeToRules)->evaluate($type);

    expect($rule)->toBeInstanceOf(RuleType::class)
        ->and($rule->type)->toBe($type);
})->with([LegacyRule_TypeToRulesTest::class, ModernRule_TypeToRulesTest::class]);

it('does not wrap unsupported types directly or inside rule lists', function (Type $type) {
    $converter = new TypeToRules;

    expect($converter->evaluate($type))->toBeNull()
        ->and($converter->evaluate(new KeyedArrayType([
            new ArrayItemType_(null, new LiteralStringType('string')),
            new ArrayItemType_(null, $type),
        ])))->toBe(['string']);
})->with([
    'unknown boolean' => [new BooleanType],
    'unknown string' => [new StringType],
    'unrelated object' => [new ObjectType(stdClass::class)],
    'unavailable class' => [new ObjectType('MissingRule_TypeToRulesTest')],
]);

it('shows recovery of conditional expiry date rules containing an object or closure', function () {
    $ruleSet = new Union([
        new KeyedArrayType([
            new ArrayItemType_(0, new FunctionType('closure')),
        ]),
        new KeyedArrayType([
            new ArrayItemType_(0, new LiteralStringType('required')),
            new ArrayItemType_(1, new LiteralStringType('email')),
        ]),
    ]);

    expect((new TypeToRules)->evaluate($ruleSet))->toBe(['email']);
});

it('keeps required only when every alternative requires the field', function (Type $alternative, array $expected) {
    $type = new Union([
        new KeyedArrayType([
            new ArrayItemType_(null, new LiteralStringType('required')),
            new ArrayItemType_(null, new LiteralStringType('email')),
        ]),
        $alternative,
    ]);

    expect((new TypeToRules)->evaluate($type))->toBe($expected);
})->with([
    'required in both' => [new LiteralStringType('required|email'), ['required', 'email']],
    'absent in another branch' => [new KeyedArrayType, ['email']],
    'sometimes in another branch' => [new LiteralStringType('sometimes|email'), ['email', 'sometimes']],
]);

it('combines a union inside a rule list', function () {
    $type = new KeyedArrayType([
        new ArrayItemType_(null, new LiteralStringType('email')),
        new ArrayItemType_(null, new Union([
            new LiteralStringType('required'),
            new LiteralStringType('sometimes'),
        ])),
    ]);

    expect((new TypeToRules)->evaluate($type))->toBe(['email', 'sometimes']);
});

it('merges alternative field maps by name and accounts for missing fields', function () {
    $type = new Union([
        new KeyedArrayType([
            new ArrayItemType_('email', new LiteralStringType('required|email')),
            new ArrayItemType_('name', new LiteralStringType('required|string')),
        ]),
        new KeyedArrayType([
            new ArrayItemType_('email', new LiteralStringType('email')),
        ]),
    ]);

    expect((new TypeToRules)->evaluate($type))->toBe([
        'email' => ['email'],
        'name' => ['string'],
    ]);
});

it('recovers conditional rules inside a field map regardless of branch order', function (bool $reverse) {
    $alternatives = [
        new KeyedArrayType([
            new ArrayItemType_(null, new FunctionType('closure')),
        ]),
        new KeyedArrayType([
            new ArrayItemType_(null, new LiteralStringType('required')),
            new ArrayItemType_(null, new LiteralStringType('email')),
        ]),
    ];

    $type = new KeyedArrayType([
        new ArrayItemType_('expiry_date', new Union($reverse ? array_reverse($alternatives) : $alternatives)),
    ]);

    expect((new TypeToRules)->evaluate($type))->toBe(['expiry_date' => ['email']]);
})->with([false, true]);

class LegacyRule_TypeToRulesTest implements Rule
{
    public function passes($attribute, $value): bool
    {
        return true;
    }

    public function message(): string
    {
        return 'Invalid value.';
    }
}

class ModernRule_TypeToRulesTest implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void {}
}
