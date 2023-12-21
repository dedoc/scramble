<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Support\Str;
use PHPStan\PhpDocParser\Ast\PhpDoc\VarTagValueNode;
use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Support\Generator\Types\StringType;

class FormatExtractorExtension extends TypeToSchemaExtension
{
    public function getDescription(Type $type)
    {
        $docNode = $type->getAttribute("docNode");
        if(!$docNode) return false;

        $vars = $docNode->getTagsByName("@var");
        foreach($vars as $var) {
            $value = $var->value;
            if(!($value instanceof VarTagValueNode)) return false;

            return $value->description;
        }
    }

    public function shouldHandle(Type $type): bool
    {
        $description = $this->getDescription($type);
        if(!$description) return false;
        return Str::of($description)->contains('@format');
    }

    public function toSchema(Type $type): StringType
    {
        $description = $this->getDescription($type);
        $parts = explode('@format', $description);
        $format = trim($parts[1]);
        $description = trim($parts[0]);
        return (new StringType)->format($format)->setDescription($description);
    }
}
