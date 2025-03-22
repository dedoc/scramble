<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Support\Generator\ClassBasedReference;
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
use ReflectionEnumUnitCase;

class EnumToSchema extends TypeToSchemaExtension
{
    public function shouldHandle(Type $type)
    {
        return function_exists('enum_exists')
            && $type instanceof ObjectType
            && enum_exists($type->name);
    }

    /**
     * @param  ObjectType  $type
     */
    public function toSchema(Type $type)
    {
        $name = $type->name;

        if (! isset($name::cases()[0]->value)) {
            return new UnknownType("$type->name enum doesnt have values");
        }

        $values = array_map(fn ($s) => $s->value, $name::cases());

        $schemaType = is_string($values[0]) ? new StringType : new IntegerType;
        $schemaType->enum($values);

        $this->addEnumDescriptions($type, $schemaType);

        return $schemaType;
    }

    public function reference(ObjectType $type)
    {
        return ClassBasedReference::create('schemas', $type->name, $this->components);
    }

    protected function addEnumDescriptions(ObjectType $type, OpenApi\Type $schemaType): void
    {
        $enumReflection = new ReflectionEnum($type->name);

        $cases = collect($enumReflection->getCases())
            ->keyBy(fn (ReflectionEnumUnitCase|ReflectionEnumBackedCase $case) => $case->getBackingValue())
            ->map(function (ReflectionEnumUnitCase|ReflectionEnumBackedCase $case) {
                $doc = PhpDoc::parse($case->getDocComment() ?: '/** */');

                return trim(Str::replace("\n", ' ', $doc->getAttribute('summary').' '.$doc->getAttribute('description')));
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

    protected function handleDescriptionEnumStrategy(OpenApi\Type $schema, Collection $cases): void
    {
        $description = $cases
            ->map(fn ($description, $value) => "| `{$value}` <br/> {$description} |")
            ->prepend('|---|')
            ->prepend('| |')
            ->join("\n");

        $schema->setDescription($description);
    }

    protected function handleExtensionEnumStrategy(OpenApi\Type $schema, Collection $cases): void
    {
        $schema->setExtensionProperty(
            'enumDescriptions',
            $cases->all()
        );
    }
}
