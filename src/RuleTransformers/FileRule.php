<?php

namespace Dedoc\Scramble\RuleTransformers;

use Dedoc\Scramble\Contracts\RuleTransformer;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\RulesMapper;
use Dedoc\Scramble\Support\RuleTransforming\NormalizedRule;
use Dedoc\Scramble\Support\RuleTransforming\RuleTransformerContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\File;

class FileRule implements RuleTransformer
{
    public function __construct(
        protected RulesMapper $rulesMapper,
    ) {
    }

    public function shouldHandle(NormalizedRule $rule): bool
    {
        return $rule->is(File::class);
    }

    /**
     * @param  NormalizedRule<File>  $rule
     */
    public function toSchema(Type $previous, NormalizedRule $rule, RuleTransformerContext $context): Type
    {
        $fileRule = $rule->getRule();

        $rulesToSchemaTransformer = (fn () => $this->rulesToSchemaTransformer)->call($this->rulesMapper);

        return $rulesToSchemaTransformer->transform(
            $this->buildFileValidationRules($fileRule),
            $previous,
            $context,
        );
    }

    /**
     * @return list<string|ValidationRule>
     */
    private function buildFileValidationRules(File $rule): array
    {
        return array_values(array_map(
            fn ($rule) => $rule instanceof ValidationRule ? $rule : (string) $rule,
            (fn () => $this->buildValidationRules())->call($rule),
        ));
    }
}
