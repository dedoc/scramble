<?php

namespace Dedoc\Scramble\RuleTransformers;

use Dedoc\Scramble\Contracts\RuleTransformer;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Dedoc\Scramble\Support\RuleTransforming\NormalizedRule;
use Dedoc\Scramble\Support\RuleTransforming\RuleSetToSchemaTransformer;
use Dedoc\Scramble\Support\RuleTransforming\RuleTransformerContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\File;

class FileRule implements RuleTransformer
{
    public function __construct(
        private RuleSetToSchemaTransformer $rulesToSchemaTransformer,
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

        return $this->rulesToSchemaTransformer->transform(
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
            /**
             * `buildValidationRules` is protected {@see File::buildValidationRules},
             * so to get the rules the transformer can transform, this "magic" is used.
             */
            (fn () => $this->buildValidationRules())->call($rule),
        ));
    }
}
