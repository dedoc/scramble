<?php

namespace Dedoc\ApiDocs\Support\ComplexTypeHandler;

use Dedoc\ApiDocs\Support\ClassAstHelper;
use Dedoc\ApiDocs\Support\ResponseExtractor\ModelInfo;
use Dedoc\ApiDocs\Support\Type\Identifier;
use Dedoc\ApiDocs\Support\Type\Type;
use Dedoc\ApiDocs\Support\TypeHandlers\TypeHandlers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Str;
use PhpParser\Node\Expr\Array_;

class JsonResourceHandler
{
    private Identifier $type;

    public function __construct(Identifier $type)
    {
        $this->type = $type;
    }

    public static function shouldHandle(Type $type)
    {
        return $type instanceof Identifier
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

        TypeHandlers::registerIdentifierHandler($this->type->name, function (string $name) use ($classAstHelper) {
            $fqName = $classAstHelper->resolveFqName($name);

            return ComplexTypeHandlers::handle(new Identifier($fqName));
        });

        $modelClass = $this->getModelName($classAstHelper->classReflection, $classAstHelper->namesResolver);
        $modelInfo = null;
        if ($modelClass && is_a($modelClass, Model::class, true)) {
            $modelInfo = (new ModelInfo($modelClass))->handle();
        }

        $type = (new JsonResourceToArrayNodeTypeGetter($returnNode->expr, $classAstHelper->namesResolver, $modelInfo))();

        TypeHandlers::unregisterIdentifierHandler($this->type->name);

        if ($type && isset(ComplexTypeHandlers::$components)) {
            $type->setDescription('`'.ComplexTypeHandlers::$components->uniqueSchemaName($this->type->name).'`');
        }

        return $type;
    }

    private function getModelName(\ReflectionClass $reflectionClass, callable $getFqName)
    {
        $phpDoc = $reflectionClass->getDocComment() ?: '';

        $mixinOrPropertyLine = Str::of($phpDoc)
            ->explode("\n")
            ->first(fn ($str) => Str::is(['*@property*$resource', '*@mixin*'], $str));

        if ($mixinOrPropertyLine) {
            $modelName = Str::replace(['@property', '$resource', '@mixin', ' ', '*'], '', $mixinOrPropertyLine);

            $modelClass = $getFqName($modelName);

            if (class_exists($modelClass)) {
                return $modelClass;
            }
        }

        $modelName = (string) Str::of(Str::of($this->type->name)->explode('\\')->last())->replace('Resource', '')->singular();

        $modelClass = 'App\\Models\\'.$modelName;
        if (! class_exists($modelClass)) {
            return null;
        }

        return $modelClass;
    }
}
