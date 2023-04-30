<?php

namespace Dedoc\Scramble\Infer\Handler;

use Dedoc\Scramble\Infer\Definition\ClassPropertyDefinition;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Support\Str;
use PhpParser\Node;

class PropertyHandler
{
    public function shouldHandle($node)
    {
        return $node instanceof Node\Stmt\Property;
    }

    public function leave(Node\Stmt\Property $node, Scope $scope)
    {
        $classDefinition = $scope->classDefinition();

        foreach ($node->props as $prop) {
            $propertyDefinition = new ClassPropertyDefinition(
                type: $node->type
                    ? TypeHelper::createTypeFromTypeNode($node->type)
                    : new TemplateType($scope->makeConflictFreeTemplateName('T'.Str::studly($prop->name->name))),
                defaultType: $prop->default ? $scope->getType($prop->default) : null,
            );

            $classDefinition->properties[$prop->name->name] = $propertyDefinition;
        }

        $classDefinition->templateTypes = array_merge(
            $classDefinition->templateTypes,
            array_values(array_filter(array_map(
                fn (ClassPropertyDefinition $p) => $p->type instanceof TemplateType ? $p->type : null,
                $classDefinition->properties,
            ))),
        );

//        // deprecated
//        $classType = $scope->class();
//
//        foreach ($node->props as $prop) {
//            $classType->properties = array_merge($classType->properties, [
//                $prop->name->name => $prop->default
//                    ? $scope->getType($prop->default)
//                    : ($node->type
//                        ? TypeHelper::createTypeFromTypeNode($node->type)
//                        : new UnknownType('No property default type')
//                    ),
//            ]);
//        }
    }
}
