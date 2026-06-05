<?php

namespace Dedoc\Scramble\Infer\Handler;

use Dedoc\Scramble\Infer\Definition\AttributeDefinition;
use Dedoc\Scramble\Infer\Definition\ClassPropertyDefinition;
use Dedoc\Scramble\Infer\Definition\PropertyVisibility;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Illuminate\Support\Str;
use PhpParser\Node;
use ReflectionProperty;

class PropertyHandler
{
    public function shouldHandle($node)
    {
        return $node instanceof Node\Stmt\Property;
    }

    public function leave(Node\Stmt\Property $node, Scope $scope)
    {
        if (! $classDefinition = $scope->classDefinition()) {
            // Anonymous class - not implemented yet.
            return;
        }

        $ownProperties = [];
        foreach ($node->props as $prop) {
            $annotatedType = isset($node->type)
                ? TypeHelper::createTypeFromTypeNode($node->type)
                : null;

            $attributes = [];
            $docComment = $node->getDocComment()?->getText();
            $declaringFileName = null;

            try {
                $reflectionProperty = new ReflectionProperty($classDefinition->name, $prop->name->name);
                $attributes = AttributeDefinition::fromReflectionAttributesArray($reflectionProperty->getAttributes());

                if (! $docComment) {
                    $docComment = $reflectionProperty->getDocComment() ?: null;
                }

                $declaringFileName = $reflectionProperty->getDeclaringClass()->getFileName() ?: null;
            } catch (\ReflectionException) {
            }

            $propertyDefinition = new ClassPropertyDefinition(
                type: new TemplateType($scope->makeConflictFreeTemplateName('T'.Str::studly($prop->name->name)), $annotatedType),
                defaultType: $prop->default ? $scope->getType($prop->default) : null,
                isStatic: $node->isStatic(),
                visibility: match (true) {
                    $node->isPrivate() => PropertyVisibility::Private,
                    $node->isProtected() => PropertyVisibility::Protected,
                    default => PropertyVisibility::Public,
                },
                attributes: $attributes,
                docComment: $docComment,
                declaringFileName: $declaringFileName,
            );
            $ownProperties[$prop->name->name] = $propertyDefinition;
        }

        $classDefinition->properties = array_merge($classDefinition->properties, $ownProperties);

        $classDefinition->templateTypes = array_merge(
            $classDefinition->templateTypes,
            array_values(array_filter(array_map(
                fn (ClassPropertyDefinition $p) => $p->type instanceof TemplateType ? $p->type : null,
                $ownProperties,
            ))),
        );
    }
}
