<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesExtractor;

use Dedoc\Scramble\Contracts\AllRulesSchemasTransformer;
use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor\RulesDocumentationRetriever;
use Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor\RulesNodes;
use Dedoc\Scramble\Support\RuleTransforming\NormalizedRule;
use Dedoc\Scramble\Support\RuleTransforming\RuleSetToSchemaTransformer;
use Dedoc\Scramble\Support\RuleTransforming\RuleTransformerContext;
use Dedoc\Scramble\Support\RuleTransforming\SchemaBag;
use Illuminate\Support\Arr;
use PhpParser\Node;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;

/**
 * @internal
 */
class RulesToParameters
{
    private bool $mergeDotNotatedKeys = true;

    /** @var array<string, PhpDocNode> */
    private array $rulesDocs;

    /**
     * @param  array<string, RuleSet>  $rules
     * @param  Node[]|RulesDocumentationRetriever  $validationNodesResults
     */
    public function __construct(
        private array $rules,
        array|RulesDocumentationRetriever $validationNodesResults,
        private TypeTransformer $openApiTransformer,
        private string $in = 'query',
    ) {
        // This is for backward compatibility
        $this->rulesDocs = is_array($validationNodesResults)
            ? RulesNodes::makeFromStatements($validationNodesResults)->getDocNodes()
            : $validationNodesResults->getDocNodes();
    }

    public function mergeDotNotatedKeys(bool $mergeDotNotatedKeys = true): self
    {
        $this->mergeDotNotatedKeys = $mergeDotNotatedKeys;

        return $this;
    }

    /** @return Parameter[] */
    public function handle(): array
    {
        return $this->transformSchemaBagToParameters($this->toSchemaBag());
    }

    public function toSchemaBag(): SchemaBag
    {
        $schemaBag = $this->createSchemaBag();

        $this->applySchemaBagTransformingExtensions($schemaBag);

        return $schemaBag;
    }

    private function createSchemaBag(): SchemaBag
    {
        $bag = new SchemaBag;

        foreach ($this->rules as $name => $ruleSet) {
            $schema = $this->makeRuleSetToSchemaTransformer()->transform(
                $ruleSet,
                context: $this->makeRuleTransformerContext($name, $ruleSet),
            );

            $bag->set($name, $schema);
        }

        return $bag;
    }

    private function applySchemaBagTransformingExtensions(SchemaBag $schemaBag): void
    {
        $extensions = $this->getGeneratorConfig()->ruleTransformers
            ->instances(AllRulesSchemasTransformer::class, [
                TypeTransformer::class => $this->openApiTransformer,
                RuleSetToSchemaTransformer::class => $this->makeRuleSetToSchemaTransformer(),
            ]);

        foreach ($this->rules as $name => $ruleSet) {
            $rules = RuleSetToSchemaTransformer::normalizeAndPrioritizeRules($ruleSet);

            foreach ($rules as $rule) {
                $normalizedRule = NormalizedRule::fromValue($rule);

                $extensions
                    ->filter(fn (AllRulesSchemasTransformer $bagTransformer) => $bagTransformer->shouldHandle($normalizedRule))
                    ->each(fn (AllRulesSchemasTransformer $bagTransformer) => $bagTransformer->transformAll(
                        $schemaBag,
                        $normalizedRule,
                        $this->makeRuleTransformerContext($name, $ruleSet),
                    ));
            }
        }
    }

    private function getGeneratorConfig(): GeneratorConfig
    {
        return $this->openApiTransformer->context->config;
    }

    /** @return array<int, Parameter> */
    private function transformSchemaBagToParameters(SchemaBag $schemaBag): array
    {
        return (new SchemaBagToParametersTransformer(
            $this->openApiTransformer,
            $this->mergeDotNotatedKeys,
            $this->rulesDocs,
            $this->in,
        ))->handle($schemaBag);
    }

    private function makeRuleTransformerContext(string $name, mixed $rules): RuleTransformerContext
    {
        return RuleTransformerContext::makeFromOpenApiContext($this->openApiTransformer->context, [
            'field' => $name,
            'fieldRules' => collect(Arr::wrap($rules)),
        ]);
    }

    public function makeRuleSetToSchemaTransformer(): RuleSetToSchemaTransformer
    {
        return new RuleSetToSchemaTransformer(
            $this->openApiTransformer,
            $this->openApiTransformer->context->config->ruleTransformers,
        );
    }
}
