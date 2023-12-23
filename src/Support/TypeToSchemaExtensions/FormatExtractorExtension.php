<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Support\Str;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\VarTagValueNode;
use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;

class FormatExtractorExtension extends TypeToSchemaExtension
{
    public function shouldHandle(Type $type): bool
    {
        $docNode = $type->getAttribute("docNode");
        if(!$docNode) return false;
        $varNode = $docNode->getTagsByName("@var");
        if(count($varNode) === 0) return false;
        $varNode = reset($varNode);

        if(!($varNode instanceof PhpDocTagNode)) return false;
        if(!($varNode->value instanceof VarTagValueNode)) return false;
        if(!($varNode->value->type instanceof IdentifierTypeNode)) return false;
        if(!Str::contains($varNode->value->type->name, "string")) return false;

        $vars = $docNode->getTagsByName("@format");
        foreach($vars as $var) {
            if(!($var instanceof PhpDocTagNode)) return false;
        }
        return count($vars) > 0;
    }

    public function toSchema(Type $type) : StringType
    {
        $docNode = $type->getAttribute("docNode");
        $vars = $docNode->getTagsByName("@format");
        $format = reset($vars)->value->value;
        if(Str::contains($format, "|")) {
            $format = explode("|", $format);
        }
        if(Str::contains($format, ",")) {
            $format = explode(",", $format)[0];
        }
        return (new StringType())->format($format);
    }
}
