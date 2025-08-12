<?php

namespace Dedoc\Scramble\Support\Generator;

use Dedoc\Scramble\Support\Generator\Combined\AnyOf;
use Dedoc\Scramble\Support\Generator\Types\NullType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Illuminate\Support\Str;

class Reference extends Type
{
    public string $referenceType;

    public ?string $shortName;

    /**
     * This must be a unique name across all the references with the same type!
     */
    public string $fullName;

    private Components $components;

    public function __construct(
        string $referenceType,
        string $fullName,
        Components $components,
        ?string $shortName = null,
    ) {
        $this->type = '$ref';
        $this->referenceType = $referenceType;
        $this->fullName = $fullName;
        $this->components = $components;
        $this->shortName = $shortName;
    }

    public function resolve()
    {
        return $this->components->get($this);
    }

    public function getUniqueName()
    {
        return $this->components->uniqueSchemaName($this->shortName ?: $this->fullName);
    }

    public function setDescription(string $description): Type
    {
        $casesDescription = $this->getEnumReferenceCasesDescription();

        if ($description && $casesDescription) {
            $description = Str::replaceLast($casesDescription, '', $description)."\n".$casesDescription;
        }

        return parent::setDescription($description);
    }

    /**
     * This is a workaround for Stoplight Elements. When `enum_cases_description_strategy` is set to `description` and
     * enum used as array item value and user adds some description, we want to keep the description in the UI.
     */
    private function getEnumReferenceCasesDescription(): ?string
    {
        $schema = $this->resolve();

        if (! $schema instanceof Schema) {
            return null;
        }

        if (! is_string($casesDescription = $schema->type->getAttribute('casesDescription'))) {
            return null;
        }

        return $casesDescription;
    }

    public function toArray()
    {
        if ($this->nullable) {
            return (new AnyOf)->setItems([(clone $this)->nullable(false), new NullType])->toArray();
        }

        $parentArray = parent::toArray();
        unset($parentArray['type']);

        return array_filter([
            ...$parentArray,
            '$ref' => "#/components/{$this->referenceType}/{$this->getUniqueName()}",
        ]);
    }
}
