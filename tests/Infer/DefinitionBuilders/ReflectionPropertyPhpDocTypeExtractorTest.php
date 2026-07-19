<?php

use Dedoc\Scramble\Infer\DefinitionBuilders\ReflectionPropertyPhpDocTypeExtractor;
use Dedoc\Scramble\Tests\Files\SampleUserModel as ImportedModel;

it('extracts reflection property types from phpdoc', function (string $property, string $expectedType) {
    $type = (new ReflectionPropertyPhpDocTypeExtractor(
        new ReflectionClass(PhpDocPropertyTypes_ReflectionPropertyPhpDocTypeExtractorTest::class),
    ))->getType($property);

    expect($type?->toString())->toBe($expectedType);
})->with([
    'class property' => ['classProperty', 'string'],
    'class property read' => ['classPropertyRead', 'boolean'],
    'property var' => ['propertyVar', 'float'],
    'promoted parameter' => ['promotedParameter', 'array<string>'],
    'imported type' => ['importedType', ImportedModel::class],
    'imported class property type' => ['classImportedType', ImportedModel::class],
    'magic class property' => ['magicProperty', 'string'],
]);

it('prioritizes class property PHPDoc over property and promoted parameter PHPDoc', function () {
    $extractor = new ReflectionPropertyPhpDocTypeExtractor(
        new ReflectionClass(PhpDocPropertyTypes_ReflectionPropertyPhpDocTypeExtractorTest::class),
    );

    expect($extractor->getType('classPrecedence')?->toString())->toBe('int')
        ->and($extractor->getType('varPrecedence')?->toString())->toBe('float');
});

/**
 * @property string $classProperty
 * @property-read bool $classPropertyRead
 * @property int $classPrecedence
 * @property ImportedModel $classImportedType
 * @property string $magicProperty
 */
class PhpDocPropertyTypes_ReflectionPropertyPhpDocTypeExtractorTest
{
    public mixed $classProperty;

    public mixed $classPropertyRead;

    /** @var float */
    public $propertyVar;

    /** @var float */
    public $classPrecedence;

    public mixed $classImportedType;

    /** @var ImportedModel */
    public $importedType;

    /**
     * @param  array<int, string>  $promotedParameter
     * @param  float  $varPrecedence
     */
    public function __construct(
        public mixed $promotedParameter,
        /** @var float */
        public mixed $varPrecedence,
    ) {}
}
