<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesExtractor;

use Dedoc\Scramble\Support\Generator\MissingValue;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiSchema;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\RuleTransforming\SchemaBag;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;

/**
 * @internal
 */
class SchemaBagToParametersTransformer
{
    public function __construct(
        private TypeTransformer $openApiTransformer,
        private bool $mergeDotNotatedKeys = true,
        /** @var array<string, PhpDocNode> */
        private array $rulesDocs = [],
        private string $in = 'query',
    ) {}

    /** @return Parameter[] */
    public function handle(SchemaBag $schemaBag): array
    {
        return $this->transformSchemaBagToParameters($schemaBag);
    }

    /** @return array<int, Parameter> */
    private function transformSchemaBagToParameters(SchemaBag $schemaBag): array
    {
        return collect($schemaBag->all())
            ->reject(fn ($_, $name) => $this->shouldIgnoreParameter($name))
            ->map(function ($schema, $name) {
                if (! $rulesDocs = $this->rulesDocs[$name] ?? null) {
                    return $schema;
                }

                return (new PhpDocSchemaTransformer($this->openApiTransformer))->transform($schema, $rulesDocs);
            })
            ->map(fn ($schema, $name) => $this->makeParameterFromSchema($schema, $name))
            ->values()
            ->pipe(fn ($c) => $this->mergeDotNotatedKeys ? collect((new DeepParametersMerger($c))->handle()) : $c)
            ->all();
    }

    protected function shouldIgnoreParameter(string $name): bool
    {
        $rulesDocs = $this->rulesDocs[$name] ?? null;

        return (bool) ($rulesDocs?->getTagsByName('@ignoreParam') ?? [])
            || (bool) ($rulesDocs?->getTagsByName('@hidden') ?? []);
    }

    /**
     * When incoming JSON schema has one example, we move it from the schema to the resulting parameter. If
     * incoming JSON schema has many parameters – we keep it on JSON schema instead. This is due to examples on
     * parameter are named (array<string, Example>) and examples on JSON schema are just values (value[]).
     */
    protected function makeParameterFromSchema(OpenApiSchema $schema, string $name): Parameter
    {
        $description = $schema->description;

        $schemaExamples = $schema->examples;

        $schema->setDescription('');
        if (count($schemaExamples) < 2) {
            $schema->examples([]);
        }

        return Parameter::make($name, $schema->getAttribute('isInQuery') ? 'query' : $this->in)
            ->setSchema(Schema::fromType($schema))
            ->example(count($schemaExamples) === 1 ? $schemaExamples[0] : new MissingValue)
            ->required((bool) $schema->getAttribute('required', false))
            ->description($description);
    }
}
