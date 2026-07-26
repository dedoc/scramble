<?php

namespace Dedoc\Scramble\Support\JsonResource;

use Dedoc\Scramble\Attributes\WithRelations;
use Dedoc\Scramble\Infer\Contracts\Index;
use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Http\Resources\Json\JsonResource;

class AppliesWithRelationsAttributes
{
    use ResolvesModelFromJsonResourceInstance;

    public function __construct(private Index $index) {}

    /**
     * @param  list<WithRelations>  $attributes
     */
    public function apply(Type $type, array $attributes): Type
    {
        if (! $attributes) {
            return $type;
        }

        return (new TypeWalker)->map($type, function (Type $t) use ($attributes) {
            if (! $t instanceof ObjectType || ! $t->isInstanceOf(JsonResource::class)) {
                return $t;
            }

            foreach ($attributes as $attribute) {
                if ($t->name !== $attribute->class) {
                    continue;
                }

                if (! $t instanceof Generic) {
                    $t = new Generic($t->name, [new UnknownType]);
                }

                $t = $this->withRelations($t, $attribute->relations);
            }

            return $t;
        });
    }

    /**
     * @param  list<string>  $relations
     */
    private function withRelations(Generic $resourceType, array $relations): Generic
    {
        $modelType = $this->resolveModelFromJsonResourceInstance($resourceType, $this->index);

        if (! $modelType) {
            return $resourceType;
        }

        $resourceType->templateTypes[0] = ReferenceTypeResolver::getInstance()->resolve(
            new GlobalScope,
            new MethodCallReferenceType(
                $modelType,
                'load',
                [
                    new KeyedArrayType(
                        array_map(
                            fn (string $relation) => new ArrayItemType_(null, new LiteralStringType($relation)),
                            $relations,
                        ),
                        isList: true,
                    ),
                ],
            ),
        );

        return $resourceType;
    }
}
