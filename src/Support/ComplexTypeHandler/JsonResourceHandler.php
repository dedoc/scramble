<?php

namespace Dedoc\Scramble\Support\ComplexTypeHandler;

use Dedoc\Scramble\Support\ClassAstHelper;
use Dedoc\Scramble\Support\Generator\Types\OpenApiTypeHelper;
use Dedoc\Scramble\Support\Infer\Handler\ReturnTypeGettingExtensions;
use Dedoc\Scramble\Support\ResponseExtractor\ModelInfo;
use Dedoc\Scramble\Support\Type\Identifier;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\TypeHandlers\TypeHandlers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Str;
use PhpParser\Node\Expr\Array_;
use Symfony\Component\Yaml\Yaml;

class JsonResourceHandler
{
    private Identifier $type;

    public function __construct($type)
    {
        $this->type = $type instanceof ObjectType
            ? new Identifier($type->name)
            : $type;
    }

    public static function shouldHandle(Type $type)
    {
        return ($type instanceof Identifier || $type instanceof ObjectType)
            && is_a($type->name, JsonResource::class, true)
            && ! is_a($type->name, ResourceCollection::class, true);
    }

    public function handle()
    {
        $classAstHelper = new ClassAstHelper($this->type->name);
        $returnNode = $classAstHelper->getReturnNodeOfMethod('toArray');

        if (! $returnNode) {
            return null;
        }

        if (! ($returnNode->expr instanceof Array_)) {
            return null;
        }

        if (! $type = $returnNode->expr->getAttribute('type')) {
            return null;
        }

        $openApiType = OpenApiTypeHelper::fromType($type);

        if (isset(ComplexTypeHandlers::$components)) {
            $openApiType->setHint('`'.ComplexTypeHandlers::$components->uniqueSchemaName($this->type->name).'`');
        }

        return $openApiType;
    }
}
