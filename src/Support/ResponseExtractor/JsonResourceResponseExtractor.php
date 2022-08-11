<?php

namespace Dedoc\Documentor\Support\ResponseExtractor;

use Dedoc\Documentor\Support\Generator\Parameter;
use Dedoc\Documentor\Support\Generator\Response;
use Dedoc\Documentor\Support\Generator\Schema;
use Dedoc\Documentor\Support\Generator\Types\ObjectType;
use Dedoc\Documentor\Support\Generator\Types\StringType;
use Illuminate\Support\Str;
use Illuminate\Http\Resources\Json\JsonResource;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\FirstFindingVisitor;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use ReflectionClass;

class JsonResourceResponseExtractor
{
    private string $class;

    public function __construct(string $class)
    {
        $this->class = $class;
    }

    public function extract(): ?Response
    {
        $reflectionClass = new ReflectionClass($this->class);
        $classSourceCode = file_get_contents($reflectionClass->getFileName());
        $classAst = (new ParserFactory)->create(ParserFactory::PREFER_PHP7)->parse($classSourceCode);

        /** @var Node\Stmt\ClassMethod|null $methodNode */
        [$methodNode, $aliases] = $this->findFirstNode(
            $classAst,
            fn (Node $node) => $node instanceof Node\Stmt\ClassMethod && $node->name->name === 'toArray'
        );

        if (! $methodNode) {
            return null;
        }

        $modelClass = $this->getModelName($reflectionClass, $aliases);

        /*
         * To describe the response Documentor uses 2 strategies:
         *
         * 1. Trying to execute the resource's `toArray` method.
         * 2. If 1 fails, simple static analysis is used to get the values of the array.
         * 2.1. Simple static analysis is really simple. It works only when array is returned from `toArray` and
         * can understand only few things regarding typing:
         * - key type when string
         * - value type from property fetch on resource ($this->id)
         * - value type from property fetch on resource prop ($this->resource->id)
         * - value type from resource construction (new SomeResource(xxx)), when xxx is `when` call, key is optional
         * - optionally merged arrays:
         * -- mergeWhen +
         * -- merge +
         * -- when
         */

        /** @var Node\Stmt\Return_|null $returnNode */
        $returnNode = (new NodeFinder())->findFirst(
            $methodNode,
            fn (Node $node) => $node instanceof Node\Stmt\Return_
        );

        if (! $returnNode) {
//            dump('no return node', $this->class);
            return null;
        }

        if ($returnNode->expr instanceof Node\Expr\Array_) {
            [$sampleResponse, $requiredFields] = (new ArrayNodeSampleInferer($returnNode->expr))();
        }

        if (! isset($sampleResponse)) {
//            dump('no $sampleResponse node', $this->class);
            return null;
        }

//        return null;
//
//        dd('fuck');
//
//        [$sampleResponse, $requiredFields] = $this->getSampleResponse($modelClass);

        $schema = Schema::fromType($type = new ObjectType);

        collect($sampleResponse)->each(function ($v, $key) use ($requiredFields, $type) {
            if (! is_string($key)) {
                return;
            }

            $type->addProperty($key, new StringType);
        })->filter();

        $type->setRequired($requiredFields);

        return Response::make(200)
            ->setContent('application/json', $schema);
    }

    private function getSampleResponse(string $modelClass)
    {
        $resource = new $this->class(app($modelClass));

        return [
            $result = $resource->toArray(request()),
            array_keys($result),
        ];
    }

    private function findFirstNode(?array $classAst, \Closure $param)
    {
        $visitor = new FirstFindingVisitor($param);

        $nameResolver = new NameResolver();
        $traverser = new NodeTraverser;
        $traverser->addVisitor($nameResolver);
        $traverser->addVisitor($visitor);
        $traverser->traverse($classAst);

        /** @var Node\Stmt\ClassMethod $methodNode */
        $methodNode = $visitor->getFoundNode();

        // @todo Fix dirty way of getting the map of aliases
        $context = $nameResolver->getNameContext();
        $reflection = new ReflectionClass($context);
        $property = $reflection->getProperty('origAliases');
        $property->setAccessible(true);
        $value = $property->getValue($context);
        $aliases = array_map(fn (Node\Name $n) => $n->toCodeString(), $value[1]);

        return [$methodNode, $aliases];
    }

    private function getModelName(ReflectionClass $reflectionClass, array $classAliasesMap)
    {
        $phpDoc = $reflectionClass->getDocComment() ?: '';

        $mixinOrPropertyLine = Str::of($phpDoc)
            ->explode("\n")
            ->first(fn ($str) => Str::is(['*@property*$resource', '*@mixin*'], $str));

        if ($mixinOrPropertyLine) {
            $modelName = Str::replace(['@property', '$resource', '@mixin', ' ', '*'], '', $mixinOrPropertyLine);

            $modelClass = $classAliasesMap[$modelName] ?? $modelName;

            if (class_exists($modelClass)) {
                return $modelClass;
            }
        }

        $modelName = (string) Str::of(Str::of($this->class)->explode('\\')->last())->replace('Resource', '')->singular();

        $modelClass = 'App\\Models\\'.$modelName;
        if (! class_exists($modelClass)) {
            return null;
        }

        return null;
    }
}
