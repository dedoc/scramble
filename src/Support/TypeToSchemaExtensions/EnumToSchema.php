<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Support\Generator\ClassBasedReference;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Types as OpenApi;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\UnknownType;
use Dedoc\Scramble\Support\PhpDoc;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ReflectionEnum;
use ReflectionEnumBackedCase;

class EnumToSchema extends TypeToSchemaExtension
{
    public function shouldHandle(Type $type): bool
    {
        return function_exists('enum_exists')
            && $type instanceof ObjectType
            && enum_exists($type->name);
    }

    /**
     * @param  ObjectType  $type
     */
    public function toSchema(Type $type): OpenApi\Type
    {
        $name = $type->name;

        if (! isset($name::cases()[0]->value)) { // only backed enums support
            return new UnknownType;
        }

        $values = array_map(fn ($s) => $s->value, $name::cases());

        $schemaType = is_string($values[0]) ? new StringType : new IntegerType;
        $schemaType->enum($values);

        $this->addEnumCasesDescriptions($type, $schemaType);

        $this->addEnumDescription($type, $schemaType);

        return $schemaType;
    }

    public function reference(ObjectType $type): Reference
    {
        return ClassBasedReference::create('schemas', $type->name, $this->components);
    }

    protected function addEnumCasesDescriptions(ObjectType $type, OpenApi\Type $schemaType): void
    {
        $enumReflection = new ReflectionEnum($type->name); // @phpstan-ignore argument.type

        $cases = collect(array_filter(
            $enumReflection->getCases(),
            fn ($case) => $case instanceof ReflectionEnumBackedCase,
        ))
            ->keyBy(fn (ReflectionEnumBackedCase $case): int|string => $case->getBackingValue())
            ->map(function (ReflectionEnumBackedCase $case) {
                $doc = PhpDoc::parse($case->getDocComment() ?: '/** */');

                return trim(Str::replace("\n", ' ', $doc->getAttribute('summary').' '.$doc->getAttribute('description'))); // @phpstan-ignore binaryOp.invalid, binaryOp.invalid
            });

        if (! $cases->some(fn ($description) => (bool) $description)) {
            return;
        }

        $enumDescriptionStrategy = config('scramble.enum_cases_description_strategy');

        if ($enumDescriptionStrategy === 'description') {
            $this->handleDescriptionEnumStrategy($schemaType, $cases);

            return;
        }

        if ($enumDescriptionStrategy === 'extension') {
            $this->handleExtensionEnumStrategy($schemaType, $cases);

            return;
        }
    }

    protected function addEnumDescription(ObjectType $type, OpenApi\Type $schemaType): void
    {
        $enumReflection = new ReflectionEnum($type->name); // @phpstan-ignore argument.type

        $doc = PhpDoc::parse($enumReflection->getDocComment() ?: '/** */');
        $description = trim(Str::replace("\n", ' ', $doc->getAttribute('summary').' '.$doc->getAttribute('description'))); // @phpstan-ignore binaryOp.invalid, binaryOp.invalid

        if (! $description) {
            return;
        }

        $schemaType->setDescription($description."\n".$schemaType->description);

        /*
         * Cases description are stored in attribute due to if enum is used as a property in some object,
         * users may override enum class description and some way is needed
         */
        $schemaType->setAttribute('description', $description);
    }

    /**
     * @param  Collection<array-key, string>  $cases
     */
    protected function handleDescriptionEnumStrategy(OpenApi\Type $schema, Collection $cases): void
    {
        $description = $cases
            ->map(fn ($description, $value) => "| `{$value}` <br/> {$description} |")
            ->prepend('|---|')
            ->prepend('| |')
            ->join("\n");

        $schema->setDescription($description);

        /*
         * Cases description are stored in attribute due to if enum is used as a property in some object,
         * users may override enum class description and some way is needed
         */
        $schema->setAttribute('casesDescription', $description);
    }

    /**
     * @param  Collection<array-key, string>  $cases
     */
    protected function handleExtensionEnumStrategy(OpenApi\Type $schema, Collection $cases): void
    {
        $schema->setExtensionProperty(
            'enumDescriptions',
            $cases->all()
        );
    }
}
