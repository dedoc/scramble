<?php

namespace Dedoc\Scramble\Infer\DefinitionBuilders;

use Dedoc\Scramble\Infer\Context;
use Dedoc\Scramble\Infer\Contracts\ClassDefinitionBuilder;
use Dedoc\Scramble\Infer\Contracts\Index as IndexContract;
use Dedoc\Scramble\Infer\Definition\ClassDefinition as ClassDefinitionData;
use Dedoc\Scramble\Infer\Definition\ClassPropertyDefinition;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\Definition\LazyShallowClassDefinition;
use Dedoc\Scramble\Infer\Extensions\Event\ClassDefinitionCreatedEvent;
use Dedoc\Scramble\Infer\Reflector\ClassReflector;
use Dedoc\Scramble\Infer\Services\FileNameResolver;
use Dedoc\Scramble\PhpDoc\PhpDocTypeHelper;
use Dedoc\Scramble\Support\PhpDoc;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\MixedType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use League\Uri\UriTemplate\Template;
use PHPStan\PhpDocParser\Ast\PhpDoc\MixinTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\TemplateTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\UsesTagValueNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use ReflectionClass;
use ReflectionProperty;

class LazyClassReflectionDefinitionBuilder implements ClassDefinitionBuilder
{
    /**
     * @param  string[]  $ignoreClasses  The names of classes that should not be analyzed when analyzing mixins recursively.
     */
    public function __construct(
        public IndexContract $index,
        public ReflectionClass $reflection,
        public array $ignoreClasses = [],
    ) {}

    public function build(): LazyShallowClassDefinition
    {
        $parentDefinition = ($parentName = ($this->reflection->getParentClass() ?: null)?->name)
            ? (! in_array($parentName, $this->ignoreClasses) ? ((
                (new self($this->index, $this->reflection->getParentClass(), [...$this->ignoreClasses, $this->reflection->name]))->build() // @phpstan-ignore argument.type
            )->getData()) : new ClassDefinitionData(name: ''))
            : new ClassDefinitionData(name: '');

        if (! $this->reflection->getFileName()) {
            throw new \Exception('Cannot build the definition due to the missing file name.');
        }

        $classPhpDoc = ($comment = $this->reflection->getDocComment())
            ? PhpDoc::parse($comment, FileNameResolver::createForFile($this->reflection->getFileName()))
            : new PhpDocNode([]);

        $classTemplates = collect($classPhpDoc->getTemplateTagValues())
            ->merge($classPhpDoc->getTemplateTagValues('@template-covariant'))
            ->values()
            ->map(fn (TemplateTagValueNode $n) => new TemplateType(
                name: $n->name,
                is: $n->bound ? PhpDocTypeHelper::toType($n->bound) : null,
            ))
            ->keyBy('name');

        $classDefinitionData = new ClassDefinitionData(
            name: $this->reflection->name,
            templateTypes: $classTemplates->values()->all(),
            properties: array_map(fn ($pd) => clone $pd, $parentDefinition->properties ?: []),
            methods: $parentDefinition->methods ?: [],
            parentFqn: $parentName ?? null,
        );

        $mixinsDefinedTemplates = $this->applyMixins($classPhpDoc, $classDefinitionData);

        /*
         * Traits get analyzed by embracing default behavior of PHP reflection: reflection properties and
         * reflection methods get copied into the class that uses the trait.
         */

        foreach ($this->reflection->getProperties() as $reflectionProperty) {
            if ($reflectionProperty->class !== $this->reflection->name) {
                continue;
            }

            $classDefinitionData->properties[$reflectionProperty->name] = $this->buildPropertyDefinition($reflectionProperty, $classTemplates);
        }

        $classSource = ClassReflector::make($this->reflection->name)->getSource();

        foreach ($this->reflection->getMethods() as $reflectionMethod) {
            if ($reflectionMethod->class !== $this->reflection->name) {
                continue;
            }

            if (array_key_exists($reflectionMethod->name, $classDefinitionData->methods)) {
                $isDefinedInClass = Str::contains($classSource, [
                    "function $reflectionMethod->name(",
                    "function $reflectionMethod->name ",
                ]);

                if (! $isDefinedInClass) {
                    continue;
                }
            }

            $classDefinitionData->methods[$reflectionMethod->name] = new FunctionLikeDefinition(
                new FunctionType(
                    $reflectionMethod->name,
                    arguments: [],
                    returnType: new UnknownType,
                ),
                definingClassName: $this->reflection->name,
                isStatic: $reflectionMethod->isStatic(),
            );
        }

        $classDefinition = new LazyShallowClassDefinition(
            $classDefinitionData,
            parentDefinedTemplates: $this->getParentDefinedTemplates($parentDefinition, $classPhpDoc, $classDefinitionData->templateTypes),
            mixinsDefinedTemplates: $mixinsDefinedTemplates,
            interfacesDefinedTemplates: [],
        );

        //        Context::getInstance()->extensionsBroker->afterClassDefinitionCreated(new ClassDefinitionCreatedEvent($this->reflection->name, $classDefinition));

        return $classDefinition;
    }

    /**
     * @param  Collection<string, TemplateType>  $classTemplates
     */
    private function buildPropertyDefinition(ReflectionProperty $reflectionProperty, Collection $classTemplates): ClassPropertyDefinition
    {
        $propertyPhpDoc = PhpDoc::parse($reflectionProperty->getDocComment() ?: '/** */');

        if ($reflectionProperty->isStatic()) {
            return new ClassPropertyDefinition(
                type: $reflectionProperty->hasDefaultValue()
                    ? (TypeHelper::createTypeFromValue($reflectionProperty->getDefaultValue()) ?: new UnknownType)
                    : new UnknownType,
            );
        }

        $propertyPhpDocType = $this->toInferType(collect($propertyPhpDoc->getVarTagValues())->first()?->type, $classTemplates);
        $typeHintType = $reflectionProperty->hasType() ? TypeHelper::createTypeFromReflectionType($reflectionProperty->getType()) : null;

        return new ClassPropertyDefinition(
            type: $propertyPhpDocType ?? $typeHintType ?? new MixedType,
            defaultType: $reflectionProperty->hasDefaultValue()
                ? TypeHelper::createTypeFromValue($reflectionProperty->getDefaultValue())
                : null,
        );
    }

    /**
     * @param  Collection<string, TemplateType>  $classTemplates
     */
    private function toInferType(?TypeNode $type, Collection $classTemplates): ?Type
    {
        if (! $type) {
            return null;
        }

        $inferType = PhpDocTypeHelper::toType($type);

        return (new TypeWalker)
            ->map(
                $inferType,
                fn (Type $t) => $t instanceof ObjectType && $classTemplates->has($t->name) ? $classTemplates->get($t->name) : $t, // @phpstan-ignore argument.type
            );
    }

    /**
     * @param  TemplateType[]  $definitionTemplates
     * @return array<string, Type>
     */
    private function getParentDefinedTemplates(?ClassDefinitionData $parentDefinition, PhpDocNode $doc, array $definitionTemplates): array
    {
        if (! $extendsNodes = $doc->getExtendsTagValues()) {
            return [];
        }

        $extendsNode = array_values($extendsNodes)[0];

        $extendedType = $this->toInferType(
            $extendsNode->type,
            collect($definitionTemplates)->keyBy('name'),
        );

        if (! $extendedType instanceof Generic) {
            return [];
        }

        return collect($parentDefinition?->templateTypes ?: [])
            ->mapWithKeys(function ($parentTemplateType, int $i) use ($extendedType) {
                return [
                    $parentTemplateType->name => $extendedType->templateTypes[$i] ?? new UnknownType,
                ];
            })
            ->all();
    }

    /**
     * @return array<string, array<string, Type>>
     */
    private function applyMixins(PhpDocNode $classPhpDoc, ClassDefinitionData $classDefinitionData): array
    {
        $mixinsDefinedTemplates = [];

        $mixins = $this->getMixinsTypes($classPhpDoc, $classDefinitionData);

        foreach ($mixins as $type) {
            $mixinsDefinedTemplates = array_merge($mixinsDefinedTemplates, $this->applyConcreteMixin($classDefinitionData, $type));
        }

        return $mixinsDefinedTemplates;
    }

    /**
     * Get the map of applied mixins/traits (including `@mixin` and `@use` tags) recursively.
     *
     * @return ObjectType[]
     */
    private function getMixinsTypes(PhpDocNode $classPhpDoc, ClassDefinitionData $classDefinitionData): array
    {
        $annotatedTypes = [
            ...$classPhpDoc->getMixinTagValues(),
            ...$this->getUsesTagValues($classDefinitionData->name),
        ];

        return array_values(array_filter(
            array_map(
                fn (MixinTagValueNode|UsesTagValueNode $node) => $this->toInferType($node->type, collect($classDefinitionData->templateTypes)->keyBy('name')),
                $annotatedTypes,
            ),
            fn ($t) => $t instanceof ObjectType,
        ));
    }

    /**
     * @return UsesTagValueNode[]
     */
    private function getUsesTagValues(string $className): array
    {
        $reflector = ClassReflector::make($className);

        $usesTags = Str::matchAll(
            '/@use\s+[^\r\n*]+/',
            $reflector->getSource(),
        );

        $comment = "/**\n".$usesTags->join("\n").'*/';

        return PhpDoc::parse($comment, new FileNameResolver($reflector->getNameContext()))->getUsesTagValues();
    }

    /**
     * @return array<string, array<string, Type>>
     */
    private function applyConcreteMixin(ClassDefinitionData $classDefinitionData, ObjectType $type): array
    {
        $shouldApplyMixin = ! in_array($type->name, $this->ignoreClasses);

        if (! $shouldApplyMixin) {
            return [];
        }

        if (! $mixinReflection = rescue(fn () => new ReflectionClass($type->name))) { // @phpstan-ignore argument.type
            return [];
        }

        $mixinDefinition = (new self(
            $this->index,
            $mixinReflection,
            ignoreClasses: [...$this->ignoreClasses, $type->name],
        ))->build();

        $classDefinitionData->methods = array_merge(
            $classDefinitionData->methods,
            $mixinDefinition->getData()->methods,
        );

        $classDefinitionData->properties = array_merge(
            $classDefinitionData->properties,
            $mixinDefinition->getData()->properties,
        );

        $definedTemplates = $this->getDefinedTemplates($mixinDefinition->getData(), $type);
        $mixinDefinedTemplates = collect($mixinDefinition->mixinsDefinedTemplates)
            ->map(function (array $templatesMap) use ($definedTemplates) {
                return collect($templatesMap)
                    ->map(function ($templateType) use ($definedTemplates) {
                        if ($templateType instanceof TemplateType && array_key_exists($templateType->name, $definedTemplates)) {
                            return $definedTemplates[$templateType->name];
                        }

                        return $templateType;
                    })
                    ->all();
            })
            ->all();

        // @todo template types!?

        return [
            ...$mixinDefinedTemplates,
            $mixinReflection->name => $definedTemplates,
        ];
    }

    /**
     * @return array<string, Type>
     */
    private function getDefinedTemplates(ClassDefinitionData $classDefinitionData, ObjectType $type): array
    {
        return collect($classDefinitionData->templateTypes)
            ->mapWithKeys(function ($templateType, int $i) use ($type) {
                $concreteType = $type instanceof Generic
                    ? ($type->templateTypes[$i] ?? new UnknownType('no expected generic type'))
                    : new UnknownType('expected generic got object');

                return [
                    $templateType->name => $concreteType,
                ];
            })
            ->all();
    }
}
