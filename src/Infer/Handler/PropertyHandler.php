<?php

namespace Dedoc\Scramble\Infer\Handler;

use Dedoc\Scramble\Infer\Definition\ClassPropertyDefinition;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\TypeHelper;
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
        if (! $classDefinition = $scope->classDefinition()) {
            // Anonymous class - not implemented yet.
            return;
        }

        $ownProperties = [];
        foreach ($node->props as $prop) {
            $annotatedType = isset($node->type)
                ? TypeHelper::createTypeFromTypeNode($node->type)
                : null;

            $propertyDefinition = new ClassPropertyDefinition(
                type: new TemplateType($scope->makeConflictFreeTemplateName('T'.Str::studly($prop->name->name)), $annotatedType),
                defaultType: $prop->default ? $scope->getType($prop->default) : null,
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
