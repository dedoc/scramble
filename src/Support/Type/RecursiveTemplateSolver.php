<?php

namespace Dedoc\Scramble\Support\Type;

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
        (new TypeWalker)->walk($parameter, function ($t) use (&$solutions, $parameter, $argument, $template): void {
            $path = TypePath::findFirst(
                $parameter,
                fn ($pt) => $pt === $t,
            );

            if (! $path) {
                return;
            }

            $argumentT = $path->getFrom($argument);

            if (! $argumentT instanceof Type) {
                return;
            }

            if ($parameter === $t && $argumentT === $argument) {
                return;
            }

            $solutions[] = $this->solve($t, $argumentT, $template);
        });

        return $this->makeUnion($solutions);
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
