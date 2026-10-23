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
    'interface property' => ['interfaceProperty', 'string'],
    'parent interface property' => ['parentInterfaceProperty', 'boolean'],
    'trait property' => ['traitProperty', 'int'],
    'nested trait property' => ['nestedTraitProperty', 'float'],
]);

it('prioritizes class property PHPDoc over the one defined in interfaces and traits', function (string $property, string $expectedType) {
    $extractor = new ReflectionPropertyPhpDocTypeExtractor(
        new ReflectionClass(PhpDocPropertyTypes_ReflectionPropertyPhpDocTypeExtractorTest::class),
    );

    expect($extractor->getType($property)?->toString())->toBe($expectedType);
})->with([
    'class over trait and interface' => ['inheritedPrecedence', 'string'],
    'trait over interface' => ['traitOverInterfacePrecedence', 'string'],
    'trait over nested trait' => ['traitInheritancePrecedence', 'string'],
    'interface over parent interface' => ['interfaceInheritancePrecedence', 'string'],
]);

it('prioritizes class property PHPDoc over property and promoted parameter PHPDoc', function () {
    $extractor = new ReflectionPropertyPhpDocTypeExtractor(
        new ReflectionClass(PhpDocPropertyTypes_ReflectionPropertyPhpDocTypeExtractorTest::class),
    );

    expect($extractor->getType('classPrecedence')?->toString())->toBe('int')
        ->and($extractor->getType('varPrecedence')?->toString())->toBe('float');
});

/**
 * @property-read bool $parentInterfaceProperty
 * @property-read int $interfaceInheritancePrecedence
 */
interface PhpDocPropertyTypesParentInterface_ReflectionPropertyPhpDocTypeExtractorTest {}

/**
 * @property-read string $interfaceProperty
 * @property-read int $inheritedPrecedence
 * @property-read int $traitOverInterfacePrecedence
 * @property-read string $interfaceInheritancePrecedence
 */
interface PhpDocPropertyTypesInterface_ReflectionPropertyPhpDocTypeExtractorTest extends PhpDocPropertyTypesParentInterface_ReflectionPropertyPhpDocTypeExtractorTest {}

/**
 * @property-read float $nestedTraitProperty
 * @property-read int $traitInheritancePrecedence
 */
trait PhpDocPropertyTypesNestedTrait_ReflectionPropertyPhpDocTypeExtractorTest {}

/**
 * @property-read int $traitProperty
 * @property-read bool $inheritedPrecedence
 * @property-read string $traitOverInterfacePrecedence
 * @property-read string $traitInheritancePrecedence
 */
trait PhpDocPropertyTypesTrait_ReflectionPropertyPhpDocTypeExtractorTest
{
    use PhpDocPropertyTypesNestedTrait_ReflectionPropertyPhpDocTypeExtractorTest;
}

/**
 * @property string $classProperty
 * @property-read bool $classPropertyRead
 * @property int $classPrecedence
 * @property ImportedModel $classImportedType
 * @property string $magicProperty
 * @property-read string $inheritedPrecedence
 */
class PhpDocPropertyTypes_ReflectionPropertyPhpDocTypeExtractorTest implements PhpDocPropertyTypesInterface_ReflectionPropertyPhpDocTypeExtractorTest
{
    use PhpDocPropertyTypesTrait_ReflectionPropertyPhpDocTypeExtractorTest;

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
