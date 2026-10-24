<?php

namespace Dedoc\Scramble\Tests\Infer;

use Dedoc\Scramble\Support\Type\ConditionalType;

it('preserves enum match conditionals in arm order', function (string $method, string $expected) {
    $definition = analyzeClass(EnumMatchStatus::class)->getClassDefinition(EnumMatchStatus::class)->getMethod($method);

    expect($definition->getReturnType()->toString())->toBe(str_replace('Status::', EnumMatchStatus::class.'::', $expected));
})->with([
    'self cases with declared return' => ['label', '(self is Status::Draft ? string(Draft article) : (self is Status::Published ? string(Published article) : (self is Status::Archived ? string(Archived article) : never)))'],
    'explicit cases without declared return' => ['explicitLabel', '(self is Status::Draft ? string(Draft article) : (self is Status::Published ? string(Published article) : (self is Status::Archived ? string(Archived article) : never)))'],
    'multiple conditions and duplicate case identities' => ['visibility', '(self is Status::Draft ? string(Not public) : (self is Status::Archived ? string(Not public) : (self is Status::Draft ? string(Later draft) : (self is Status::Published ? string(Public) : never))))'],
    'default before cases' => ['withDefault', '(self is Status::Published ? string(Public) : string(Not public))'],
    'assigned match' => ['assigned', '(self is Status::Draft ? string(Draft) : string(Other))'],
    'nested expression' => ['nested', 'array{label: (self is Status::Draft ? string(Draft) : string(Other))}'],
]);

it('resolves enum match returns for the actual receiver case', function (string $case, string $method, string $expected) {
    $analysis = analyzeClass(EnumMatchStatus::class);

    expect($analysis->getExpressionType('\\'.EnumMatchStatus::class."::$case->$method()")->toString())->toBe($expected);
})->with([
    ['Draft', 'label', 'string(Draft article)'],
    ['Published', 'label', 'string(Published article)'],
    ['Archived', 'label', 'string(Archived article)'],
    ['Draft', 'visibility', 'string(Not public)'],
    ['Archived', 'visibility', 'string(Not public)'],
    ['Published', 'visibility', 'string(Public)'],
    ['Draft', 'withDefault', 'string(Not public)'],
    ['Published', 'withDefault', 'string(Public)'],
]);

it('falls back for the entire match when any condition is unsupported', function (string $method) {
    $definition = analyzeClass(EnumMatchStatus::class)->getClassDefinition(EnumMatchStatus::class)->getMethod($method);
    $returnType = $definition->getReturnType();

    expect($returnType)->not->toBeInstanceOf(ConditionalType::class)
        ->and(explode('|', $returnType->toString()))->toEqualCanonicalizing(['string(First)', 'string(Second)', 'string(Other)']);
})->with(['dynamicCondition', 'scalarCondition', 'nonThisSubject']);

it('unions enum match returns for a receiver with an unknown case', function () {
    $analysis = analyzeFile('<?php
        function enumLabel(\\'.EnumMatchStatus::class.' $status) {
            return $status->label();
        }
    ');

    expect($analysis->getFunctionDefinition('enumLabel')->type->getReturnType()->toString())
        ->toBe('string(Draft article)|string(Published article)|string(Archived article)');
});

it('infers matches on unit enums', function () {
    $definition = analyzeClass(EnumMatchOther::class)->getClassDefinition(EnumMatchOther::class)->getMethod('label');

    expect($definition->getReturnType()->toString())->toBe('(self is '.EnumMatchOther::class.'::Draft ? string(Draft) : never)');
});

enum EnumMatchStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    public const NOT_A_CASE = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft article',
            self::Published => 'Published article',
            self::Archived => 'Archived article',
        };
    }

    public function explicitLabel()
    {
        return match ($this) {
            EnumMatchStatus::Draft => 'Draft article',
            EnumMatchStatus::Published => 'Published article',
            EnumMatchStatus::Archived => 'Archived article',
        };
    }

    public function visibility(): string
    {
        return match ($this) {
            self::Draft, EnumMatchStatus::Archived => 'Not public',
            EnumMatchStatus::Draft => 'Later draft',
            self::Published => 'Public',
        };
    }

    public function withDefault(): string
    {
        return match ($this) {
            default => 'Not public',
            self::Published => 'Public',
        };
    }

    public function assigned(): string
    {
        $label = match ($this) {
            self::Draft => 'Draft',
            default => 'Other',
        };

        return $label;
    }

    public function nested()
    {
        return ['label' => match ($this) {
            self::Draft => 'Draft',
            default => 'Other',
        }];
    }

    public function dynamicCondition($case)
    {
        return match ($this) {
            self::Draft => 'First',
            self::Published, $case => 'Second',
            default => 'Other',
        };
    }

    public function scalarCondition()
    {
        return match ($this) {
            self::Draft => 'First',
            'published' => 'Second',
            default => 'Other',
        };
    }

    public function nonThisSubject(self $status)
    {
        return match ($status) {
            self::Draft => 'First',
            self::Published => 'Second',
            default => 'Other',
        };
    }
}

enum EnumMatchOther
{
    case Draft;

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
        };
    }
}

class EnumMatchNonEnum
{
    public function label()
    {
        return match ($this) {
            EnumMatchStatus::Draft => 'First',
            default => 'Other',
        };
    }
}

it('handles method call on this in class', function () {
    $result = analyzeClass(Foo_EnumMatchTest::class);

    expect($result->getClassDefinition(Foo_EnumMatchTest::class)->getMethod('enumMatchOnMethodCall')->getReturnType()->toString())
        ->toBe('string(Draft)');
});
class Foo_EnumMatchTest
{
    public function enumMatchOnMethodCall()
    {
        return match ($this->methodCall()) {
            EnumMatchStatus::Draft => 'Draft',
            EnumMatchStatus::Published => 'Published',
            default => null,
        };
    }

    public function methodCall()
    {
        return EnumMatchStatus::Draft;
    }
}

it('handles method call on this redefined', function () {
    $result = analyzeClass(Bar_EnumMatchTest::class)
        ->getClassDefinition(Bar_EnumMatchTest::class)
        ->getMethod('enumMatchOnMethodCall');

    expect($result->getReturnType()->toString())
        ->toBe('string(Published)');
});
class Bar_EnumMatchTest extends Foo_EnumMatchTest
{
    public function methodCall()
    {
        return EnumMatchStatus::Published;
    }
}
