<?php

namespace Dedoc\Scramble\RuleTransformers;

use Dedoc\Scramble\Contracts\RuleTransformer;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Dedoc\Scramble\Support\RuleTransforming\NormalizedRule;
use Dedoc\Scramble\Support\RuleTransforming\RuleTransformerContext;

class RegexRule implements RuleTransformer
{
    public function shouldHandle(NormalizedRule $rule): bool
    {
        return $rule->is('regex');
    }

    public function toSchema(Type $previous, NormalizedRule $rule, RuleTransformerContext $context): Type
    {
        if (! is_string($regex = $rule->getParameters()[0])) {
            return $previous;
        }

        if (! $this->isRegexConvertableToEcma262($regex)) {
            // @todo collect the error ErrorCollector
            return $previous;
        }

        if (! $normalizedRegex = $this->normalize($regex)) {
            // @todo collect the error ErrorCollector
            return $previous;
        }

        return $previous->pattern($normalizedRegex);
    }

    private function normalize(string $pattern): ?string
    {
        // remove regex delimiters (assumes leading / and optional trailing /flags)
        $pattern = preg_replace('#^/(.*?)/[a-zA-Z]*$#', '$1', $pattern);

        if (! $pattern) {
            return null;
        }

        return strtr($pattern, [
            '\z' => '$',
            '\A' => '^',
        ]);
    }

    protected function isRegexConvertableToEcma262(string $pattern): bool
    {
        $unsupported = [
            '(?<=', '(?<!', // lookbehind
            '(?(', // conditional
            '(*', // backtracking verbs
        ];

        foreach ($unsupported as $token) {
            if (str_contains($pattern, $token)) {
                // regex contains unsupported PCRE construct ($token)
                return false;
            }
        }

        if (preg_match('/\(\?[imsxU]+\)/', $pattern)) {
            // inline PCRE flags are not supported in ECMA-262 patterns
            return false;
        }

        return true;
    }
}
