<?php

namespace Dedoc\Scramble\Support\InferExtensions\PropertyTypesFromPhpDoc;

use Dedoc\Scramble\Infer\Extensions\Event\PropertyFetchEvent;
use Dedoc\Scramble\Infer\Extensions\PropertyTypeExtension;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Support\Collection;
use ReflectionClass;
use ReflectionException;

final class PropertyTypesFromPhpdocExtension implements PropertyTypeExtension
{
    /**
     * @var array<string, \Illuminate\Support\Collection<string, \Dedoc\Scramble\Support\Type\Type>>
     */
    public static array $cache = [];

    /**
     * Determine whether the extension should handle the given type.
     */
    public function shouldHandle(ObjectType $type): bool
    {
        return true;
    }

    /**
     * Get the property type from the given event.
     */
    public function getPropertyType(PropertyFetchEvent $event): ?Type
    {
        $type = $event->getInstance();

        $properties = $this->getProperties($type);

        dump($properties);

        return $properties->get($event->name);
    }

    /**
     * @return \Illuminate\Support\Collection<string, \Dedoc\Scramble\Support\Type\Type>
     */
    private function getProperties(ObjectType $type): Collection
    {
        if (isset(self::$cache[$type->name])) {
            return self::$cache[$type->name];
        }

        $name = $type->name;

        try {
            $reflection = new ReflectionClass($name);
        } catch (ReflectionException $e) {
            return new Collection;
        }

        $extractors = [
            new PromotedParamExtractor,
            new VarPropertyExtractor,
            new ClassPropertyExtractor,
        ];

        /** @var \Illuminate\Support\Collection<string, \Dedoc\Scramble\Support\Type\Type> $properties */
        $properties = new Collection;

        foreach ($extractors as $extractor) {
            $properties = $properties->merge($extractor($reflection));
        }

        return self::$cache[$type->name] = $properties;
    }
}
