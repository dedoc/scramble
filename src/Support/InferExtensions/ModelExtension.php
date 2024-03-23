<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Extensions\Event\MethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\Event\PropertyFetchEvent;
use Dedoc\Scramble\Infer\Extensions\MethodReturnTypeExtension;
use Dedoc\Scramble\Infer\Extensions\PropertyTypeExtension;
use Dedoc\Scramble\Support\ResponseExtractor\ModelInfo;
use Dedoc\Scramble\Support\Type\AbstractType;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\BooleanType;
use Dedoc\Scramble\Support\Type\FloatType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\NullType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Dedoc\Scramble\Support\Type\Union;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ModelExtension implements MethodReturnTypeExtension, PropertyTypeExtension
{
    private static $cache;

    public function shouldHandle(ObjectType $type): bool
    {
        return $type->isInstanceOf(Model::class);
    }

    public function hasProperty(ObjectType $type, string $name)
    {
        $info = $this->getModelInfo($type);

        return $info->get('attributes')->has($name) || $info->get('relations')->has($name);
    }

    public function getPropertyType(PropertyFetchEvent $event): ?Type
    {
        if (! $this->hasProperty($event->getInstance(), $event->getName())) {
            return null;
        }

        $info = $this->getModelInfo($event->getInstance());

        if ($attribute = $info->get('attributes')->get($event->getName())) {
            $baseType = $this->getAttributeTypeFromEloquentCasts($attribute['cast'] ?? '')
                ?? $this->getAttributeTypeFromDbColumnType($attribute['type'] ?? '');

            if ($attribute['nullable']) {
                return Union::wrap([$baseType, new NullType()]);
            }

            return $baseType;
        }

        if ($relation = $info->get('relations')->get($event->getName())) {
            return $this->getRelationType($relation);
        }

        throw new \LogicException('Should not happen');
    }

    private function getAttributeTypeFromDbColumnType(string $columnType): AbstractType
    {
        $type = Str::before($columnType, ' ');
        $typeName = Str::before($type, '(');

        // @todo Fix to native types
        $attributeType = match ($typeName) {
            'int', 'integer', 'bigint' => new IntegerType(),
            'float', 'double', 'decimal' => new FloatType(),
            'varchar', 'string', 'text', 'datetime' => new StringType(), // string, text - needed?
            'tinyint', 'bool', 'boolean' => new BooleanType(), // bool, boolean - needed?
            'json', 'array' => new ArrayType(),
            default => new UnknownType("unimplemented DB column type [$type]"),
        };

        return $attributeType;
    }

    /**
     * @todo Add support for custom castables.
     */
    private function getAttributeTypeFromEloquentCasts(string $cast): ?AbstractType
    {
        if ($cast && enum_exists($cast)) {
            return new ObjectType($cast);
        }

        $castAsType = Str::before($cast, ':');
        $castAsParameters = str($cast)->after("{$castAsType}:")->explode(',');

        if (Str::startsWith($castAsType, 'encrypted:')) {
            $castAsType = $castAsParameters->first(); // array, collection, json, object
        }

        return match ($castAsType) {
            'array', 'json' => new ArrayType(),
            'real', 'float', 'double' => new FloatType(),
            'int', 'integer', 'timestamp' => new IntegerType(),
            'bool', 'boolean' => new BooleanType(),
            'string', 'decimal' => new StringType(),
            'object' => new ObjectType('\stdClass'),
            'collection' => new ObjectType(Collection::class),
            'Illuminate\Database\Eloquent\Casts\AsEnumCollection' => new Generic(Collection::class, [
                new TemplateType($castAsParameters->first())
            ]),
            'date', 'datetime', 'custom_datetime' => new ObjectType(Carbon::class),
            'immutable_date', 'immutable_datetime', 'immutable_custom_datetime' => new ObjectType(CarbonImmutable::class),
            default => null,
        };
    }

    private function getRelationType(array $relation)
    {
        if ($isManyRelation = Str::contains($relation['type'], 'Many')) {
            return new Generic(
                \Illuminate\Database\Eloquent\Collection::class,
                [new ObjectType($relation['related'])]
            );
        }

        return new ObjectType($relation['related']);
    }

    public function getMethodReturnType(MethodCallEvent $event): ?Type
    {
        if ($event->getName() !== 'toArray') {
            return null;
        }

        /** @var ClassDefinition $definition */
        $definition = $event->getDefinition();

        if (array_key_exists('toArray', $definition?->methods ?: [])) {
            return null;
        }

        $info = $this->getModelInfo($event->getInstance());

        /** @var Model $instance */
        $instance = $info->get('instance');

        $arrayableAttributesTypes = $info->get('attributes', collect())
            ->when($instance->getVisible(), fn ($c, $visible) => $c->only($visible))
            ->when($instance->getHidden(), fn ($c, $visible) => $c->except($visible))
            ->filter(fn ($attr) => $attr['appended'] !== false)
            ->map(function ($_, $name) use ($event) {
                $propertyType = $event->getInstance()->getPropertyType($name);

                (new TypeWalker)->replace($propertyType, function (Type $t) {
                    return $t->isInstanceOf(Carbon::class)
                        ? tap(new StringType, fn ($t) => $t->setAttribute('format', 'date-time'))
                        : null;
                });

                return $propertyType;
            });

        $arrayableRelationsTypes = $info->get('relations', collect())
            ->only($this->getProtectedValue($instance, 'with'))
            ->when($instance->getVisible(), fn ($c, $visible) => $c->only($visible))
            ->when($instance->getHidden(), fn ($c, $visible) => $c->except($visible))
            ->map(function ($_, $name) use ($event) {
                return $event->getInstance()->getPropertyType($name);
            });

        return new KeyedArrayType([
            ...$arrayableAttributesTypes->map(fn ($type, $name) => new ArrayItemType_($name, $type))->values()->all(),
            ...$arrayableRelationsTypes->map(fn ($type, $name) => new ArrayItemType_($name, $type, isOptional: true))->values()->all(),
        ]);
    }

    private function getModelInfo(ObjectType $type)
    {
        return static::$cache[$type->name] ??= (new ModelInfo($type->name))->handle();
    }

    private function getProtectedValue($obj, $name)
    {
        $array = (array) $obj;
        $prefix = chr(0).'*'.chr(0);

        return $array[$prefix.$name];
    }
}
