<?php

namespace Dedoc\Scramble\Infer\DefinitionBuilders;

use Dedoc\Scramble\Infer\Services\FileNameResolver;
use Dedoc\Scramble\PhpDoc\PhpDocTypeHelper;
use Dedoc\Scramble\Support\PhpDoc;
use Dedoc\Scramble\Support\Type\Type;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\VarTagValueNode;
use ReflectionClass;
use ReflectionProperty;

class ReflectionPropertyPhpDocTypeExtractor
{
    private PhpDocNode $classPhpDoc;

    private PhpDocNode $constructorPhpDoc;

    /** @var array<string, Type> */
    private array $classDefinedPropertyTypes;

    /**
     * @param  ReflectionClass<covariant object>  $classReflection
     */
    public function __construct(private ReflectionClass $classReflection)
    {
        $this->classPhpDoc = $this->getClassPhpDoc($classReflection);
        $this->constructorPhpDoc = $this->getConstructorPhpDoc($classReflection);
        $this->classDefinedPropertyTypes = $this->extractClassDefinedPropertyTypes();
    }

    public function getType(string $propertyName): ?Type
    {
        $classPropertyType = $this->classDefinedPropertyTypes[$propertyName] ?? null;

        if ($classPropertyType) {
            return $classPropertyType;
        }

        if (! $this->classReflection->hasProperty($propertyName)) {
            return null;
        }

        $reflectionProperty = $this->classReflection->getProperty($propertyName);
        $varType = $this->getVarType($this->getPropertyPhpDoc($reflectionProperty));

        if ($varType) {
            return $varType;
        }

        if (! $reflectionProperty->isPromoted()) {
            return null;
        }

        return $this->getPromotedParameterType($propertyName);
    }

    /**
     * @return array<string, Type>
     */
    public function getClassDefinedPropertyTypes(): array
    {
        return $this->classDefinedPropertyTypes;
    }

    /**
     * @return array<string, Type>
     */
    private function extractClassDefinedPropertyTypes(): array
    {
        $properties = [];

        foreach ([
            ...$this->classPhpDoc->getPropertyTagValues(),
            ...$this->classPhpDoc->getPropertyReadTagValues(),
        ] as $propertyTagValue) {
            $properties[ltrim($propertyTagValue->propertyName, '$')] = PhpDocTypeHelper::toType($propertyTagValue->type);
        }

        return $properties;
    }

    private function getVarType(PhpDocNode $phpDoc): ?Type
    {
        /** @var VarTagValueNode|null $varTag */
        $varTag = array_values($phpDoc->getVarTagValues())[0] ?? null;

        return $varTag ? PhpDocTypeHelper::toType($varTag->type) : null;
    }

    private function getPromotedParameterType(string $propertyName): ?Type
    {
        $type = null;

        foreach ($this->constructorPhpDoc->getParamTagValues() as $paramTag) {
            if (ltrim($paramTag->parameterName, '$') !== $propertyName) {
                continue;
            }

            $type = PhpDocTypeHelper::toType($paramTag->type);
        }

        return $type;
    }

    /**
     * @param  ReflectionClass<covariant object>  $classReflection
     */
    private function getClassPhpDoc(ReflectionClass $classReflection): PhpDocNode
    {
        return (($comment = $classReflection->getDocComment()) && ($path = $classReflection->getFileName()))
            ? PhpDoc::parse($comment, FileNameResolver::createForFile($path))
            : new PhpDocNode([]);
    }

    /**
     * @param  ReflectionClass<covariant object>  $classReflection
     */
    private function getConstructorPhpDoc(ReflectionClass $classReflection): PhpDocNode
    {
        $constructor = $classReflection->getConstructor();

        return ($constructor && ($comment = $constructor->getDocComment()) && ($path = $constructor->getFileName()))
            ? PhpDoc::parse($comment, FileNameResolver::createForFile($path))
            : new PhpDocNode([]);
    }

    private function getPropertyPhpDoc(ReflectionProperty $propertyReflection): PhpDocNode
    {
        $classReflection = $propertyReflection->getDeclaringClass();

        return (($comment = $propertyReflection->getDocComment()) && ($path = $classReflection->getFileName()))
            ? PhpDoc::parse($comment, FileNameResolver::createForFile($path))
            : new PhpDocNode([]);
    }
}
