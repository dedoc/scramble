<?php

namespace Dedoc\Scramble\Infer\Dumpers;

use Dedoc\Scramble\Infer\Definition\ClassDefinition as ClassDefinitionData;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\Union;
use Dedoc\Scramble\Support\Type\IntersectionType;
use Dedoc\Scramble\Support\Type\VoidType;
use Dedoc\Scramble\Support\Type\MixedType;
use Dedoc\Scramble\Support\Type\UnknownType;
use Dedoc\Scramble\Support\Type\SelfType;
use Dedoc\Scramble\Support\Type\EnumCaseType;
use Dedoc\Scramble\Support\Type\CallableStringType;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\FloatType;
use Dedoc\Scramble\Support\Type\BooleanType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Literal;

class StubDumper
{
    /**
     * Dumps the class definition as PHPDoc annotations to the string.
     */
    public function dump(ClassDefinitionData $definition): string
    {
        $lines = [];
        
        // Add class docblock
        $lines[] = '/**';
        
        // Add template annotations
        foreach ($definition->templateTypes as $template) {
            $templateDef = $template->is ? " is {$template->is->toString()}" : '';
            $defaultDef = $template->default ? " = {$template->default->toString()}" : '';
            $lines[] = " * @template {$template->name}{$templateDef}{$defaultDef}";
        }
        
        // Add extends annotation if parent class has template types
        if ($definition->parentClassDefinition && $definition->parentClassDefinition->templateTypes) {
            $parentTemplates = array_map(fn($t) => $t->toString(), $definition->parentClassDefinition->templateTypes);
            $lines[] = " * @extends {$definition->parentFqn}<" . implode(', ', $parentTemplates) . ">";
        }
        
        $lines[] = ' */';
        
        // Add class definition
        $parentClass = $definition->parentFqn ? " extends {$definition->parentFqn}" : '';
        $lines[] = "class {$definition->name}{$parentClass}";
        $lines[] = '{';
        
        // Add properties with their docblocks
        foreach ($definition->properties as $name => $property) {
            $lines[] = '';
            $lines[] = '    /**';
            $lines[] = "     * @var {$property->type->toString()}";
            if ($property->defaultType) {
                $lines[] = "     * @default {$property->defaultType->toString()}";
            }
            $lines[] = '     */';
            $phpType = $this->toPhpType($property->type);
            $lines[] = "    public {$phpType} \${$name};";
        }
        
        // Add methods
        foreach ($definition->methods as $name => $method) {
            $lines[] = '';
            
            $type = $method->getType();
            
            // Add method docblock
            $hasDocBlock = false;
            $docLines = [];
            
            // Add template annotations for methods
            foreach ($type->templates as $template) {
                $templateDef = $template->is ? " is {$template->is->toString()}" : '';
                $defaultDef = $template->default ? " = {$template->default->toString()}" : '';
                $docLines[] = "     * @template {$template->name}{$templateDef}{$defaultDef}";
                $hasDocBlock = true;
            }
            
            // Add @param annotations
            foreach ($type->arguments as $paramName => $paramType) {
                $docLines[] = "     * @param {$paramType->toString()} \${$paramName}";
                $hasDocBlock = true;
            }
            
            // Add @return annotation only if it's not unknown and can't be expressed as PHP type hint
            if (!($type->returnType instanceof UnknownType) && !$this->canBeExpressedAsPhpType($type->returnType)) {
                $docLines[] = "     * @return {$type->returnType->toString()}";
                $hasDocBlock = true;
            }
            
            // Only add docblock if we have annotations
            if ($hasDocBlock) {
                $lines[] = '    /**';
                $lines = array_merge($lines, $docLines);
                $lines[] = '     */';
            }
            
            // Add method signature
            $params = [];
            foreach ($type->arguments as $paramName => $paramType) {
                $phpType = $this->toPhpType($paramType);
                $params[] = "{$phpType} \${$paramName}";
            }
            $paramString = implode(', ', $params);
            
            $returnType = '';
            if (!($type->returnType instanceof UnknownType)) {
                $returnPhpType = $this->toPhpType($type->returnType);
                $returnType = ": {$returnPhpType}";
            }
            $visibility = $method->isStatic ? 'public static' : 'public';
            
            $lines[] = "    {$visibility} function {$name}({$paramString}){$returnType}";
            $lines[] = '    {';
            $lines[] = '    }';
        }
        
        $lines[] = '}';
        
        return implode("\n", $lines);
    }

    /**
     * Converts a type to its PHP type representation.
     */
    private function toPhpType(Type $type): string
    {
        // Handle template types
        if ($type instanceof TemplateType) {
            return $type->is ? $this->toPhpType($type->is) : 'mixed';
        }

        // Handle array types
        if ($type instanceof KeyedArrayType || $type instanceof ArrayType) {
            return 'array';
        }

        // Handle generic types (use their base class name)
        if ($type instanceof Generic) {
            return $type->name;
        }

        // Handle union types
        if ($type instanceof Union) {
            // For PHP 8+ we can use union types
            return implode('|', array_map(fn($t) => $this->toPhpType($t), $type->types));
        }

        // Handle intersection types
        if ($type instanceof IntersectionType) {
            // For PHP 8.1+ we can use intersection types
            return implode('&', array_map(fn($t) => $this->toPhpType($t), $type->types));
        }

        // Handle void type
        if ($type instanceof VoidType) {
            return 'void';
        }

        // Handle mixed type
        if ($type instanceof MixedType) {
            return 'mixed';
        }

        // Handle unknown type
        if ($type instanceof UnknownType) {
            return 'mixed';
        }

        // Handle self type
        if ($type instanceof SelfType) {
            return 'self';
        }

        // Handle enum case type
        if ($type instanceof EnumCaseType) {
            return $type->name; // Use the enum class name
        }

        // Handle callable string type
        if ($type instanceof CallableStringType) {
            return 'callable';
        }

        // Handle basic types
        if ($type instanceof StringType) {
            return 'string';
        }
        if ($type instanceof IntegerType) {
            return 'int';
        }
        if ($type instanceof FloatType) {
            return 'float';
        }
        if ($type instanceof BooleanType) {
            return 'bool';
        }

        // Handle object types
        if ($type instanceof ObjectType) {
            return $type->name;
        }

        // Handle function types
        if ($type instanceof FunctionType) {
            return 'callable';
        }

        // Default fallback
        return 'mixed';
    }

    /**
     * Checks if a type can be fully expressed as a PHP type hint.
     */
    private function canBeExpressedAsPhpType(Type $type): bool
    {
        if ($type instanceof TemplateType || 
            $type instanceof KeyedArrayType || 
            $type instanceof Generic ||
            $type instanceof UnknownType ||
            $type instanceof Literal\LiteralStringType ||
            $type instanceof Literal\LiteralIntegerType ||
            $type instanceof Literal\LiteralBooleanType ||
            $type instanceof Literal\LiteralFloatType) {
            return false;
        }

        if ($type instanceof Union || $type instanceof IntersectionType) {
            return collect($type->types)->every(fn($t) => $this->canBeExpressedAsPhpType($t));
        }

        return true;
    }
}
