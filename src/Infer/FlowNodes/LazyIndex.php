<?php

namespace Dedoc\Scramble\Infer\FlowNodes;

use Dedoc\Scramble\Infer\Contracts\Index;
use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Reflector\FunctionReflector;
use Dedoc\Scramble\Support\Type\CallableStringType;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\MixedType;
use Dedoc\Scramble\Support\Type\Reference\CallableCallReferenceType;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Support\Str;
use ReflectionFunction;
use ReflectionParameter;
use Throwable;

class LazyIndex implements Index
{
    /**
     * @param array<string, FunctionType> $functions
     * @param array<string, ClassDefinition> $classes
     */
    public function __construct(
        private array $functions = [],
        private array $classes = [],
    )
    {}

    public function getFunction(string $name): ?FunctionType
    {
        if (isset($this->functions[$name])) {
            return $this->functions[$name];
        }

        try {
            $reflection = new ReflectionFunction($name);
        } catch (Throwable) {
            return null;
        }

        if (
            ($filePath = $reflection->getFileName())
            && $this->shouldAnalyzeAstByPath($filePath)
        ) {
            $functionReflector = FunctionReflector::makeFromCodeString(
                $name,
                file_get_contents($filePath),
                $this,
            );

            try {
                $functionType = $functionReflector->getIncompleteType();
            } catch (\LogicException $e) {
                // @todo log/dump
                $functionType = null;
            }

            if ($functionType) {
                $this->functions[$name] = $functionType;
            }

            return $functionType;
        };

        $parameters = collect($reflection->getParameters())
            ->mapWithKeys(fn (ReflectionParameter $p) => [
                $p->name => ($paramType = $p->getType())
                    ? TypeHelper::createTypeFromReflectionType($paramType)
                    : new MixedType,
            ])
            ->all();

        $returnType = ($retType = $reflection->getReturnType())
            ? TypeHelper::createTypeFromReflectionType($retType)
            : new UnknownType();

        return $this->functions[$name] = new FunctionType($name, $parameters, $returnType);
    }

    public function getClass(string $name): ?ClassDefinition
    {
        if (isset($this->classes[$name])) {
            return $this->classes[$name];
        }

        // @todo shallowly analyze

        return null;
    }

    private function shouldAnalyzeAstByPath(string $filePath): bool
    {
        return ! Str::contains($filePath, DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR);
    }
}
