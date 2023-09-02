<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Carbon\Carbon;
use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Extensions\Event\MethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\Event\PropertyFetchEvent;
use Dedoc\Scramble\Infer\Extensions\MethodReturnTypeExtension;
use Dedoc\Scramble\Infer\Extensions\PropertyTypeExtension;
use Dedoc\Scramble\Support\ResponseExtractor\ModelInfo;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\BooleanType;
use Dedoc\Scramble\Support\Type\FloatType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\NullType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Dedoc\Scramble\Support\Type\Union;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Database\Eloquent\Model;
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
            $baseType = $this->getBaseAttributeType($info->get('instance'), $event->getName(), $attribute);

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

    private function getBaseAttributeType(Model $model, string $key, array $value)
    {
        $type = explode(' ', $value['type']);
        $typeName = explode('(', $type[0])[0];

        if (in_array($key, $model->getDates())) {
            return new ObjectType(Carbon::class);
        }

        $attributeType = match ($typeName) {
            'int', 'integer', 'bigint' => new IntegerType(),
            'float', 'double', 'decimal' => new FloatType(),
            'string', 'text', 'datetime' => new StringType(),
            'bool', 'boolean' => new BooleanType(),
            'json', 'array' => new ArrayType(),
            default => new UnknownType("unimplemented DB column type [$type[0]]"),
        };

        if ($value['cast'] && function_exists('enum_exists') && enum_exists($value['cast'])) {
            if (! isset($value['cast']::cases()[0]->value)) {
                return $attributeType;
            }

            return new ObjectType($value['cast']);
        }

        return $attributeType;
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

        return new ArrayType([
            ...$arrayableAttributesTypes->map(fn ($type, $name) => new ArrayItemType_($name, $type))->values()->all(),
            ...$arrayableRelationsTypes->map(fn ($type, $name) => new ArrayItemType_($name, $type, $isOptional = true))->values()->all(),
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
