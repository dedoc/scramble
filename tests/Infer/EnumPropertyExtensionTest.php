<?php

/*
 * Tests for EnumPropertyExtension - resolves enum ->value and ->name properties
 *
 * The extension is registered globally in ScrambleServiceProvider.
 */

it('resolves enum value property for specific case', function () {
    $type = analyzeFile(<<<'EOD'
<?php
use Dedoc\Scramble\Tests\Files\Enums\StringEnum;

class Test {
    public function test() {
        return StringEnum::Foo->value;
    }
}
EOD)->getClassDefinition('Test')
        ->getMethodDefinition('test')
        ->type->getReturnType();

    expect($type->toString())->toBe('string(foo)');
});

it('resolves enum name property for specific case', function () {
    $type = analyzeFile(<<<'EOD'
<?php
use Dedoc\Scramble\Tests\Files\Enums\StringEnum;

class Test {
    public function test() {
        return StringEnum::Foo->name;
    }
}
EOD)->getClassDefinition('Test')
        ->getMethodDefinition('test')
        ->type->getReturnType();

    expect($type->toString())->toBe('string(Foo)');
});

it('resolves integer backed enum value property for specific case', function () {
    $type = analyzeFile(<<<'EOD'
<?php
use Dedoc\Scramble\Tests\Files\Enums\IntegerEnum;

class Test {
    public function test() {
        return IntegerEnum::Two->value;
    }
}
EOD)->getClassDefinition('Test')
        ->getMethodDefinition('test')
        ->type->getReturnType();

    expect($type->toString())->toBe('int(2)');
});

it('resolves enum value property as reference for generic enum type', function () {
    $type = analyzeFile(<<<'EOD'
<?php
use Dedoc\Scramble\Tests\Files\Enums\StringEnum;

class Test {
    public function test(StringEnum $status) {
        return $status->value;
    }
}
EOD)->getClassDefinition('Test')
        ->getMethodDefinition('test')
        ->type->getReturnType();

    // For generic enum type, returns reference to the enum class
    expect($type->toString())->toBe('Dedoc\Scramble\Tests\Files\Enums\StringEnum');
});

it('resolves enum name property as reference for generic enum type', function () {
    $type = analyzeFile(<<<'EOD'
<?php
use Dedoc\Scramble\Tests\Files\Enums\StringEnum;

class Test {
    public function test(StringEnum $status) {
        return $status->name;
    }
}
EOD)->getClassDefinition('Test')
        ->getMethodDefinition('test')
        ->type->getReturnType();

    // For generic enum type, returns reference to the enum class
    expect($type->toString())->toBe('Dedoc\Scramble\Tests\Files\Enums\StringEnum');
});

it('returns unknown for value property on non-backed enum', function () {
    $type = analyzeFile(<<<'EOD'
<?php
use Dedoc\Scramble\Tests\Files\Enums\PureEnum;

class Test {
    public function test() {
        return PureEnum::First->value;
    }
}
EOD)->getClassDefinition('Test')
        ->getMethodDefinition('test')
        ->type->getReturnType();

    // Pure enums don't have a value property, returns unknown
    expect($type->toString())->toContain('unknown');
});
