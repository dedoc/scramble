<?php

namespace Dedoc\Scramble\Support\Type;

use Closure;

class RecursiveTemplateSolver
{
    public function solve(Type $parameter, Type $argument, TemplateType $template): ?Type
    {
        if ($template === $parameter) {
            return $argument;
        }

        if ($parameter instanceof Union) {
            return $this->makeUnion(array_map(
                fn (Type $pt) => $this->solve($pt, $argument, $template),
                $this->getStructurallyMatchingTypes($parameter, $argument),
            ));
        }

        if ($argument instanceof Union) {
            return $this->makeUnion(array_map(
                fn (Type $at) => $this->solve($parameter, $at, $template),
                $argument->types,
            ));
        }

        if ($parameter instanceof Generic && $parameter->name === 'iterable') {
            $argument = $this->normalizeIterable($argument);
        }

        $solutions = [];

        $this->walk($parameter, function ($t) use (&$solutions, $parameter, $argument, $template): void {
            // Current path of type $t in $parameter
            $path = TypePath::findFirst(
                $parameter,
                fn ($pt) => $pt === $t,
            );

            // There will be some path, but just in case
            if (! $path) {
                return;
            }

            // Given our current location in $parameter type, get the corresponding
            // type in the same location in $argument
            $argumentT = $path->getFrom($argument);

            // In case there is nothing in the same location in $argument
            if (! $argumentT instanceof Type) {
                return;
            }

            // In case we are at the root of both types, we don't need to solve anything
            // because it would be an invisible recursive call below.
            if ($parameter === $t && $argumentT === $argument) {
                return;
            }

            $solutions[] = $this->solve($t, $argumentT, $template);
        });

        return $this->makeUnion($solutions);
    }

    private function walk(Type $type, Closure $cb): void
    {
        $typeTraverser = new TypeTraverser([
            new class($cb) extends AbstractTypeVisitor
            {
                public function __construct(private Closure $cb) {}

                public function enter(Type $type): ?Type
                {
                    ($this->cb)($type);

                    return $type;
                }
            },
        ]);

        $typeTraverser->traverse($type);
    }

    private function normalizeIterable(Type $argument): Type
    {
        if ($argument instanceof ArrayType) {
            return new Generic('iterable', [$argument->key, $argument->value]);
        }

        if ($argument instanceof KeyedArrayType) {
            return new Generic('iterable', [
                $argument->getKeyType(),
                $argument->items
                    ? new Union(array_map(fn (ArrayItemType_ $t) => $t->value, $argument->items))
                    : new UnknownType,
            ]);
        }

        return $argument;

    }

    /**
     * @return Type[]
     */
    private function getStructurallyMatchingTypes(Union $parameter, Type $argument): array
    {
        $matching = [];

        foreach ($parameter->types as $pt) {
            if (
                $pt instanceof Generic
                && $argument instanceof Generic
                && $pt->isInstanceOf($argument->name)
            ) {
                $matching[] = $pt;

                continue;
            }

            if ($pt::class === $argument::class) {
                $matching[] = $pt;

                continue;
            }

            if ($pt->accepts($argument)) {
                $matching[] = $pt;
            }
        }

        if ($matching) {
            return $matching;
        }

        return $parameter->types;
    }

    /**
     * @param  (Type|null)[]  $types
     */
    private function makeUnion(array $types): ?Type
    {
        $types = array_values(array_filter($types));

        if (! $types) {
            return null;
        }

        return Union::wrap($types);
    }
}
