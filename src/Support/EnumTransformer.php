<?php

namespace Dedoc\Scramble\Support;

use BackedEnum;
use Dedoc\Scramble\Support\Generator\Types as OpenApi;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\UnknownType;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ReflectionEnum;
use ReflectionEnumBackedCase;

class EnumTransformer
{
    /** @var BackedEnum[] */
    private array $except = [];

    /** @var BackedEnum[] */
    private array $only = [];

    /**
     * @param  class-string  $enumClassName
     * @param  array{nameStrategy?: 'name'|'varnames'|null|false, descriptionStrategy?: 'description'|'extension'|null|false}  $config
     */
    public function __construct(
        private string $enumClassName,
        private array $config = [],
    ) {}

    /**
     * @param  class-string  $enumClassName
     */
    public static function make(string $enumClassName): self
    {
        return new self($enumClassName, [
            'nameStrategy' => config('scramble.enum_cases_names_strategy'),
            'descriptionStrategy' => config('scramble.enum_cases_description_strategy'),
        ]);
    }

    /**
     * @param  BackedEnum[]  $except
     */
    public function except(array $except): self
    {
        $this->except = $except;

        return $this;
    }

    /**
     * @param  BackedEnum[]  $only
     */
    public function only(array $only): self
    {
        $this->only = $only;

        return $this;
    }

    public function transform(): OpenApi\Type
    {
        $name = $this->enumClassName;

        if (! isset($name::cases()[0]->value)) { // only backed enums support
            return new UnknownType;
        }

        $cases = $this->cases();

        if ($cases->isEmpty()) {
            return new UnknownType;
        }

        $values = $cases->map(fn (BackedEnum $case) => $case->value)->values()->all();

        $schemaType = is_string($values[0]) ? new StringType : new IntegerType;

        if (count($values) === 1 && ($this->only || $this->except)) {
            $schemaType->const($values[0]);
        } else {
            $schemaType->enum($values);
        }

        $this->addEnumCasesDescriptions($schemaType, $cases);

        $this->addEnumDescription($schemaType);

        $this->addEnumNames($schemaType, $cases);

        return $schemaType;
    }

    /**
     * @return Collection<int, BackedEnum>
     */
    private function cases(): Collection
    {
        return collect($this->enumClassName::cases())
            ->reject(fn ($case) => in_array($case, $this->except))
            ->filter(fn ($case) => ! $this->only || in_array($case, $this->only))
            ->values();
    }

    /**
     * @param  Collection<int, BackedEnum>  $cases
     */
    private function addEnumCasesDescriptions(OpenApi\Type $schemaType, Collection $cases): void
    {
        $descriptions = $cases
            ->mapWithKeys(function (BackedEnum $case) {
                $reflection = new ReflectionEnumBackedCase($case::class, $case->name);
                $doc = PhpDoc::parse($reflection->getDocComment() ?: '/** */');

                return [
                    $case->value => trim(Str::replace("\n", ' ', $doc->getAttribute('summary').' '.$doc->getAttribute('description'))), // @phpstan-ignore binaryOp.invalid, binaryOp.invalid
                ];
            });

        if (! $descriptions->some(fn ($description) => (bool) $description)) {
            return;
        }

        $enumDescriptionStrategy = $this->config['descriptionStrategy'] ?? null;

        if ($enumDescriptionStrategy === 'description') {
            $description = $descriptions
                ->map(fn ($description, $value) => "| `{$value}` <br/> {$description} |")
                ->prepend('|---|')
                ->prepend('| |')
                ->join("\n");

            $schemaType->setDescription($description);

            /*
             * Cases description are stored in attribute due to if enum is used as a property in some object,
             * users may override enum class description and some way is needed
             */
            $schemaType->setAttribute('casesDescription', $description);

            return;
        }

        if ($enumDescriptionStrategy === 'extension') {
            $schemaType->setExtensionProperty('enumDescriptions', $descriptions->all());
        }
    }

    private function addEnumDescription(OpenApi\Type $schemaType): void
    {
        $enumReflection = new ReflectionEnum($this->enumClassName); // @phpstan-ignore argument.type

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
     * @param  Collection<int, BackedEnum>  $cases
     */
    private function addEnumNames(OpenApi\Type $schemaType, Collection $cases): void
    {
        /** @var 'name'|'varnames'|null $enumNameStrategy */
        $enumNameStrategy = $this->config['nameStrategy'] ?? null;

        if (! $enumNameStrategy) {
            return;
        }

        $extensionKey = $enumNameStrategy === 'varnames' ? 'enum-varnames' : 'enumNames';

        $schemaType->setExtensionProperty(
            $extensionKey,
            $cases->map(fn (BackedEnum $case) => $case->name)->values()->all(),
        );
    }
}
